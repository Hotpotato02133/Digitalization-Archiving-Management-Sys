<?php
declare(strict_types=1);

require_once __DIR__ . '/scan-intake.php';

/**
 * Bulk student import from a CSV (Excel: File > Save As > CSV). Two phases over the same file:
 * a dry-run plan that validates every row and changes nothing, and a commit that inserts the rows
 * the plan marked ready. Row rules are the same ones the Add Student form enforces
 * (dats_validate_new_student_payload / dats_insert_intake_student), so an import can never create
 * a student the form would have refused.
 */

const DATS_STUDENT_IMPORT_MAX_ROWS = 5000;
const DATS_STUDENT_IMPORT_MAX_BYTES = 5 * 1024 * 1024;

/** Header spellings (letters and digits only, lowercased) seen in registrar exports -> field. */
function dats_student_import_header_aliases(): array
{
    return [
        'student_number' => ['studentnumber', 'studentno', 'studentnum', 'studentid', 'studid', 'idnumber', 'idno', 'id', 'srcode', 'number'],
        'last_name' => ['lastname', 'surname', 'familyname', 'lname'],
        'first_name' => ['firstname', 'givenname', 'givennames', 'fname'],
        'middle_initial' => ['middleinitial', 'mi', 'middlename', 'middle', 'mname'],
        'full_name' => ['name', 'studentname', 'fullname'],
        'gender' => ['gender', 'sex'],
        'birthdate' => ['birthdate', 'dateofbirth', 'birthday', 'dob', 'bday'],
        'address' => ['address', 'homeaddress', 'completeaddress'],
        'nationality' => ['nationality', 'citizenship'],
        'civil_status' => ['civilstatus', 'maritalstatus'],
        'contact_number' => ['contactnumber', 'contactno', 'contact', 'mobile', 'mobilenumber', 'mobileno', 'phone', 'phonenumber', 'cellphone'],
        'college_name' => ['college', 'collegename', 'department'],
        'course_name' => ['course', 'coursename', 'program', 'degree', 'degreeprogram'],
        'major_name' => ['major', 'majorname', 'specialization'],
        'academic_year' => ['academicyear', 'schoolyear', 'sy', 'ay', 'enrollmentyear', 'yearenrolled'],
        'year_level' => ['yearlevel', 'year', 'level', 'yrlevel'],
    ];
}

function dats_student_import_field_for_header(string $header): ?string
{
    $key = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $header) ?? '');
    if ($key === '') return null;
    foreach (dats_student_import_header_aliases() as $field => $aliases) {
        if (in_array($key, $aliases, true)) return $field;
    }
    return null;
}

/** The ready-to-fill CSV a registrar can start from. */
function dats_student_import_template(): string
{
    return "Student Number,Last Name,First Name,Middle Initial,Gender,Birthdate,Address,Nationality,Civil Status,Contact Number,College,Course,Major,Academic Year,Year Level\r\n"
        . "2026-000123,Dela Cruz,Juan,M.,Male,2005-03-14,\"123 Rizal St., Zamboanga City\",Filipino,Single,09171234567,College of Education,Bachelor of Elementary Education,,2026,1st Year\r\n";
}

/**
 * Reads the CSV into rows keyed by field name. Handles a UTF-8 BOM, Windows-1252 exports from
 * Excel, comma/semicolon/tab delimiters, quoted multi-line cells, title rows above the headers,
 * and skips blank lines. Returns ['rows' => [['line' => n, 'cells' => [...]]], 'unmapped' => [...]].
 */
function dats_student_import_read(string $path): array
{
    $size = @filesize($path);
    if ($size === false || $size === 0) throw new InvalidArgumentException('The file is empty.');
    if ($size > DATS_STUDENT_IMPORT_MAX_BYTES) throw new InvalidArgumentException('The file is larger than 5 MB. Split it into smaller files.');
    $text = (string) file_get_contents($path);
    if (str_starts_with($text, "\xEF\xBB\xBF")) $text = substr($text, 3);
    if (!mb_check_encoding($text, 'UTF-8')) $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
    if (str_contains(substr($text, 0, 2000), "\0")) throw new InvalidArgumentException('That does not look like a CSV file. In Excel use File > Save As > CSV (UTF-8).');

    // The delimiter is whichever of , ; tab is most common across the first lines, so a title row
    // above the headings (which usually has none) cannot mislead the guess.
    $sample = implode("\n", array_slice(preg_split('/\r\n|\r|\n/', substr($text, 0, 8000)) ?: [], 0, 12));
    $delimiter = ',';
    $best = substr_count($sample, ',');
    foreach ([';', "\t"] as $candidate) {
        if (substr_count($sample, $candidate) > $best) { $delimiter = $candidate; $best = substr_count($sample, $candidate); }
    }

    $stream = fopen('php://memory', 'r+');
    fwrite($stream, $text);
    rewind($stream);
    $headers = null;
    $rows = [];
    $unmapped = [];
    $line = 0;
    while (($cells = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
        $line++;
        if ($cells === [null] || !array_filter($cells, static fn ($cell): bool => trim((string) $cell) !== '')) continue;
        if ($headers === null) {
            // The header row is the first row (within the first 10) that names a student number and a name.
            $mapped = array_map(static fn ($cell): ?string => dats_student_import_field_for_header((string) $cell), $cells);
            $hasNumber = in_array('student_number', $mapped, true);
            $hasName = in_array('full_name', $mapped, true) || (in_array('last_name', $mapped, true) && in_array('first_name', $mapped, true));
            if ($hasNumber && $hasName) {
                $headers = $mapped;
                foreach ($cells as $index => $cell) {
                    if ($mapped[$index] === null && trim((string) $cell) !== '') $unmapped[] = trim((string) $cell);
                }
            } elseif ($line >= 10) {
                break;
            }
            continue;
        }
        if (count($rows) >= DATS_STUDENT_IMPORT_MAX_ROWS) throw new InvalidArgumentException('A file can hold at most ' . DATS_STUDENT_IMPORT_MAX_ROWS . ' students. Split it into smaller files.');
        $assoc = [];
        foreach ($headers as $index => $field) {
            if ($field !== null && !isset($assoc[$field])) $assoc[$field] = trim((string) ($cells[$index] ?? ''));
        }
        $rows[] = ['line' => $line, 'cells' => $assoc];
    }
    fclose($stream);
    if ($headers === null) {
        throw new InvalidArgumentException('Could not find the column headings. The first row needs a Student Number column and either Last Name and First Name columns or a single Name column. Download the template to see the layout.');
    }
    return ['rows' => $rows, 'unmapped' => array_values(array_unique($unmapped))];
}

/** "DELA CRUZ" / "juan" -> "Dela Cruz" / "Juan"; names already in mixed case are left exactly as typed. */
function dats_student_import_smart_case(string $value): string
{
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    if ($value === '' || (preg_match('/\p{Ll}/u', $value) && preg_match('/\p{Lu}/u', $value))) return $value;
    return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
}

function dats_student_import_split_full_name(string $name): ?array
{
    if (!str_contains($name, ',')) return null;
    [$last, $given] = array_map('trim', explode(',', $name, 2));
    if ($last === '' || $given === '') return null;
    $middle = '';
    if (preg_match('/^(.*\S)\s+([A-Za-zÑñ]\.?)$/u', $given, $match)) {
        $given = $match[1];
        $middle = $match[2];
    }
    return [$last, $given, $middle];
}

function dats_student_import_gender(string $value): ?string
{
    return match (strtolower(trim($value, " .\t"))) {
        '' => 'Undisclosed',
        'm', 'male', 'man', 'boy' => 'Male',
        'f', 'female', 'woman', 'girl' => 'Female',
        'other', 'o' => 'Other',
        'undisclosed', 'n/a', 'na', '-' => 'Undisclosed',
        default => null,
    };
}

function dats_student_import_civil_status(string $value): ?string
{
    return match (strtolower(trim($value, " .\t"))) {
        '', 'undisclosed', 'n/a', 'na', '-' => 'Undisclosed',
        's', 'single' => 'Single',
        'm', 'married' => 'Married',
        'w', 'widowed', 'widow', 'widower' => 'Widowed',
        'separated', 'sep' => 'Separated',
        default => null,
    };
}

function dats_student_import_year_level(string $value): ?string
{
    $key = strtolower(trim(preg_replace('/[^A-Za-z0-9 ]/', '', $value) ?? ''));
    if ($key === '' || $key === 'undisclosed' || $key === 'na') return 'Undisclosed';
    $ordinals = ['1' => '1st Year', 'i' => '1st Year', 'first' => '1st Year', '1st' => '1st Year', '2' => '2nd Year', 'ii' => '2nd Year', 'second' => '2nd Year', '2nd' => '2nd Year',
        '3' => '3rd Year', 'iii' => '3rd Year', 'third' => '3rd Year', '3rd' => '3rd Year', '4' => '4th Year', 'iv' => '4th Year', 'fourth' => '4th Year', '4th' => '4th Year',
        '5' => '5th Year', 'v' => '5th Year', 'fifth' => '5th Year', '5th' => '5th Year'];
    $stripped = trim(preg_replace('/\b(year|yr|level)\b/', '', $key) ?? $key);
    if (isset($ordinals[$stripped])) return $ordinals[$stripped];
    foreach (dats_intake_year_levels() as $level) {
        if (strtolower($level) === $key) return $level;
    }
    return match ($key) {
        'shs', 'senior high', 'grade 11', 'grade 12' => 'Senior High School',
        'graduated', 'alumni' => 'Graduate',
        default => null,
    };
}

/** Y-m-d, m/d/Y (slash = month first, the usual registrar convention) and "January 5, 2001". Null when unreadable. */
function dats_student_import_birthdate(string $value): ?string
{
    $value = trim($value);
    foreach (['!Y-m-d', '!m/d/Y', '!n/j/Y', '!m-d-Y', '!d-M-Y', '!F j, Y', '!M j, Y', '!j F Y', '!Y/m/d'] as $format) {
        $date = DateTimeImmutable::createFromFormat($format, $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date && (!$errors || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
            $year = (int) $date->format('Y');
            if ($year >= 1900 && $year <= (int) date('Y')) return $date->format('Y-m-d');
        }
    }
    return null;
}

/** First plausible 4-digit year in "2026", "2026-2027", "AY 2026-27". */
function dats_student_import_academic_year(string $value): ?string
{
    return preg_match('/\b(20\d{2}|19\d{2}|21[0-4]\d)\b/', $value, $match) ? $match[1] : null;
}

/** Turns one raw row into a payload for dats_validate_new_student_payload(), plus errors and warnings. */
function dats_student_import_normalize_row(array $cells, string $defaultYear): array
{
    $errors = [];
    $warnings = [];
    $number = trim(preg_replace('/\s+/u', ' ', (string) ($cells['student_number'] ?? '')) ?? '');

    $last = (string) ($cells['last_name'] ?? '');
    $first = (string) ($cells['first_name'] ?? '');
    $middle = (string) ($cells['middle_initial'] ?? '');
    if (($last === '' || $first === '') && ($cells['full_name'] ?? '') !== '') {
        $split = dats_student_import_split_full_name((string) $cells['full_name']);
        if ($split === null) {
            $errors[] = 'Name must be written "Last, First M." or given in separate Last Name and First Name columns.';
        } else {
            [$last, $first, $splitMiddle] = $split;
            if ($middle === '') $middle = $splitMiddle;
        }
    }
    $last = dats_student_import_smart_case($last);
    $first = dats_student_import_smart_case($first);
    // A middle NAME becomes its initial; an initial keeps its letter(s).
    $middle = trim($middle, " .");
    if ($middle !== '') {
        $middle = mb_strlen($middle) > 3 ? mb_strtoupper(mb_substr($middle, 0, 1)) . '.' : mb_strtoupper($middle) . '.';
    }

    if ($number === '') $errors[] = 'Student number is required.';

    $gender = dats_student_import_gender((string) ($cells['gender'] ?? ''));
    if ($gender === null) { $warnings[] = 'Gender "' . $cells['gender'] . '" not recognized; set to Undisclosed.'; $gender = 'Undisclosed'; }
    $civil = dats_student_import_civil_status((string) ($cells['civil_status'] ?? ''));
    if ($civil === null) { $warnings[] = 'Civil status "' . $cells['civil_status'] . '" not recognized; set to Undisclosed.'; $civil = 'Undisclosed'; }
    $level = dats_student_import_year_level((string) ($cells['year_level'] ?? ''));
    if ($level === null) { $warnings[] = 'Year level "' . $cells['year_level'] . '" not recognized; set to Undisclosed.'; $level = 'Undisclosed'; }

    $birthdate = '';
    if (($cells['birthdate'] ?? '') !== '') {
        $parsed = dats_student_import_birthdate((string) $cells['birthdate']);
        if ($parsed === null) $warnings[] = 'Birthdate "' . $cells['birthdate'] . '" not readable; left blank.';
        $birthdate = $parsed ?? '';
    }

    $year = ($cells['academic_year'] ?? '') !== '' ? dats_student_import_academic_year((string) $cells['academic_year']) : null;
    if ($year === null) {
        if (($cells['academic_year'] ?? '') !== '') $warnings[] = 'Academic year "' . $cells['academic_year'] . '" not readable; used ' . $defaultYear . '.';
        $year = $defaultYear;
    }

    return [
        'student' => [
            'student_number' => $number, 'last_name' => $last, 'first_name' => $first, 'middle_initial' => $middle,
            'gender' => $gender, 'birthdate' => $birthdate, 'address' => (string) ($cells['address'] ?? ''),
            'nationality' => (string) ($cells['nationality'] ?? ''), 'civil_status' => $civil,
            'contact_number' => (string) ($cells['contact_number'] ?? ''),
            'college_name' => trim((string) ($cells['college_name'] ?? '')), 'course_name' => trim((string) ($cells['course_name'] ?? '')),
            'major_name' => trim((string) ($cells['major_name'] ?? '')), 'academic_year' => $year, 'year_level' => $level,
        ],
        'errors' => $errors,
        'warnings' => $warnings,
    ];
}

/** Letters-only identity used to spot the same person under a different student number. */
function dats_student_import_person_key(string $last, string $first): string
{
    return strtoupper(preg_replace('/[^A-Za-z]/', '', str_replace(['Ñ', 'ñ'], 'N', $last . $first)) ?? '');
}

/**
 * Validates the whole file without changing anything. Each row is `ready`, `duplicate` (the student
 * number already exists, or repeats earlier in the file) or `error`. Colleges and courses the file
 * names that do not exist yet are listed, since committing creates them. A college or course may be
 * written as its full name OR its short name/code ("BSIT").
 */
function dats_student_import_plan(PDO $pdo, string $path, string $defaultYear): array
{
    $parsed = dats_student_import_read($path);

    $existing = [];
    foreach ($pdo->query('SELECT student_number, last_name, first_name, deleted_at FROM students')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existing[strtolower(trim($row['student_number']))] = $row;
    }
    $people = [];
    foreach ($existing as $number => $row) {
        if ($row['deleted_at'] === null) $people[dats_student_import_person_key($row['last_name'], $row['first_name'])][] = $row['student_number'];
    }
    $colleges = [];
    foreach ($pdo->query("SELECT id, name, short_name FROM colleges WHERE status = 'Active'")->fetchAll(PDO::FETCH_ASSOC) as $row) $colleges[] = $row;
    $courses = [];
    foreach ($pdo->query("SELECT id, college_id, name, short_name FROM courses WHERE status = 'Active'")->fetchAll(PDO::FETCH_ASSOC) as $row) $courses[] = $row;

    $seen = [];
    $rows = [];
    $newColleges = [];
    $newCourses = [];
    $summary = ['total' => 0, 'ready' => 0, 'duplicate' => 0, 'error' => 0, 'warnings' => 0];
    foreach ($parsed['rows'] as $item) {
        $summary['total']++;
        $normalized = dats_student_import_normalize_row($item['cells'], $defaultYear);
        $student = $normalized['student'];
        $errors = $normalized['errors'];
        $warnings = $normalized['warnings'];
        $status = 'ready';

        if (!$errors) {
            try {
                $student = array_merge($student, array_intersect_key(dats_validate_new_student_payload($student), array_flip(['student_number', 'first_name', 'last_name', 'middle_initial', 'address', 'nationality', 'contact_number', 'academic_year'])));
            } catch (InvalidArgumentException $error) {
                $errors[] = $error->getMessage();
            }
        }

        if (!$errors) {
            // Resolve college/course by full or short name, so "BSIT" matches "BS Information Technology".
            $collegeId = 0;
            if ($student['college_name'] !== '') {
                foreach ($colleges as $college) {
                    if (strcasecmp($college['name'], $student['college_name']) === 0 || ($college['short_name'] !== null && strcasecmp($college['short_name'], $student['college_name']) === 0)) {
                        $collegeId = (int) $college['id'];
                        $student['college_name'] = $college['name'];
                        break;
                    }
                }
                if ($collegeId === 0) $newColleges[$student['college_name']] = true;
            }
            if ($student['course_name'] !== '') {
                if ($student['college_name'] === '') {
                    $errors[] = 'A course needs a college. Add the College column or clear the course.';
                } else {
                    $courseId = 0;
                    foreach ($courses as $course) {
                        if ((int) $course['college_id'] === $collegeId && $collegeId > 0
                            && (strcasecmp($course['name'], $student['course_name']) === 0 || ($course['short_name'] !== null && strcasecmp($course['short_name'], $student['course_name']) === 0))) {
                            $courseId = (int) $course['id'];
                            $student['course_name'] = $course['name'];
                            break;
                        }
                    }
                    if ($courseId === 0) $newCourses[$student['college_name'] . ' > ' . $student['course_name']] = true;
                }
            }
            if ($student['major_name'] !== '' && $student['course_name'] === '') $errors[] = 'A major needs a course.';
        }

        if (!$errors) {
            $key = strtolower($student['student_number']);
            if (isset($existing[$key])) {
                $status = 'duplicate';
                $errors[] = $existing[$key]['deleted_at'] !== null
                    ? 'This student number belongs to a deleted student. Restore that student instead.'
                    : 'A student with this number already exists; skipped.';
            } elseif (isset($seen[$key])) {
                $status = 'duplicate';
                $errors[] = 'Repeats the student number on line ' . $seen[$key] . '; skipped.';
            } else {
                $seen[$key] = $item['line'];
                $same = $people[dats_student_import_person_key($student['last_name'], $student['first_name'])] ?? [];
                if ($same) $warnings[] = 'Same name as existing student ' . $same[0] . ' with a different number.';
                $people[dats_student_import_person_key($student['last_name'], $student['first_name'])][] = $student['student_number'];
            }
        }
        if ($errors && $status === 'ready') $status = 'error';

        $summary[$status]++;
        if ($warnings) $summary['warnings']++;
        $rows[] = ['line' => $item['line'], 'status' => $status, 'student' => $student, 'errors' => $errors, 'warnings' => $warnings];
    }

    return [
        'summary' => $summary,
        'rows' => $rows,
        'new_colleges' => array_keys($newColleges),
        'new_courses' => array_keys($newCourses),
        'unmapped_columns' => $parsed['unmapped'],
    ];
}

/**
 * Inserts every `ready` row from a plan inside one transaction. A row that still fails at insert
 * time (a race on the student number, a hierarchy conflict) is reported and skipped; it does not
 * undo the other rows. Returns the counts and the final per-row outcomes for anything not created.
 */
function dats_student_import_commit(PDO $pdo, array $plan, ?int $actorId = null): array
{
    $created = 0;
    $skipped = [];
    $pdo->beginTransaction();
    try {
        $year = $pdo->prepare('INSERT IGNORE INTO enrollment_years (year) VALUES (:year)');
        foreach ($plan['rows'] as $row) {
            if ($row['status'] !== 'ready') {
                $skipped[] = ['line' => $row['line'], 'student_number' => $row['student']['student_number'] ?? '', 'reason' => implode(' ', $row['errors'])];
                continue;
            }
            try {
                $pdo->exec('SAVEPOINT import_row');
                $student = dats_resolve_intake_academic_hierarchy($pdo, $row['student']);
                $student = dats_validate_student_hierarchy($pdo, $student);
                dats_insert_intake_student($pdo, $student);
                $year->execute([':year' => $student['academic_year']]);
                $pdo->exec('RELEASE SAVEPOINT import_row');
                $created++;
            } catch (InvalidArgumentException | PDOException $error) {
                $pdo->exec('ROLLBACK TO SAVEPOINT import_row');
                $skipped[] = ['line' => $row['line'], 'student_number' => $row['student']['student_number'] ?? '', 'reason' => $error instanceof PDOException ? 'Could not be saved.' : $error->getMessage()];
            }
        }
        if ($created > 0) {
            $log = $pdo->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description) VALUES (:user_id, 'IMPORTED', 'student', NULL, :description)");
            $log->execute([':user_id' => $actorId ?: null, ':description' => "Imported {$created} students from CSV"]);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    return ['created' => $created, 'skipped' => $skipped];
}

/** The slice of a plan the import dialog shows: counts, every problem row (capped), and a sample of good rows. */
function dats_student_import_preview_payload(array $plan): array
{
    $display = static fn (array $row): array => [
        'line' => $row['line'], 'status' => $row['status'],
        'student_number' => $row['student']['student_number'] ?? '',
        'name' => trim(($row['student']['last_name'] ?? '') . ', ' . ($row['student']['first_name'] ?? '') . ' ' . ($row['student']['middle_initial'] ?? ''), ' ,'),
        'college' => $row['student']['college_name'] ?? '', 'course' => $row['student']['course_name'] ?? '',
        'messages' => array_merge($row['errors'], $row['warnings']),
    ];
    $problems = array_values(array_filter($plan['rows'], static fn (array $row): bool => $row['status'] !== 'ready' || $row['warnings']));
    $ready = array_values(array_filter($plan['rows'], static fn (array $row): bool => $row['status'] === 'ready' && !$row['warnings']));
    return [
        'summary' => $plan['summary'],
        'new_colleges' => $plan['new_colleges'],
        'new_courses' => $plan['new_courses'],
        'unmapped_columns' => $plan['unmapped_columns'],
        'problem_rows' => array_map($display, array_slice($problems, 0, 300)),
        'problem_rows_truncated' => count($problems) > 300,
        'sample_rows' => array_map($display, array_slice($ready, 0, 8)),
    ];
}
