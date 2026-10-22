<?php
declare(strict_types=1);

function dats_intake_string(array $input, string $key, int $maxLength, bool $required = false): ?string
{
    $value = trim((string) ($input[$key] ?? ''));
    if ($required && $value === '') {
        throw new InvalidArgumentException(ucwords(str_replace('_', ' ', $key)) . ' is required.');
    }
    if (strlen($value) > $maxLength) {
        throw new InvalidArgumentException(ucwords(str_replace('_', ' ', $key)) . " must be {$maxLength} characters or fewer.");
    }
    return $value === '' ? null : $value;
}

function dats_normalize_intake_year(mixed $value): string
{
    $text = trim((string) $value);
    if (!preg_match('/^\d{4}$/', $text)) {
        throw new InvalidArgumentException('A valid four-digit academic year is required.');
    }
    $year = (int) $text;
    if ($year < 1901 || $year > 2155) {
        throw new InvalidArgumentException('Academic year must be within the supported range of 1901 to 2155.');
    }
    return (string) $year;
}

function dats_intake_year_levels(): array
{
    return ['Undisclosed', '1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year', 'Senior High School', 'Graduate', 'High School', 'Elementary', 'Pre-Baccalaureate'];
}

function dats_validate_intake_enum(array $input, string $key, array $allowed, string $default): string
{
    $value = trim((string) ($input[$key] ?? '')) ?: $default;
    if (!in_array($value, $allowed, true)) {
        throw new InvalidArgumentException(ucwords(str_replace('_', ' ', $key)) . ' is invalid.');
    }
    return $value;
}

function dats_validate_new_student_payload(array $input): array
{
    $birthdate = trim((string) ($input['birthdate'] ?? ''));
    if ($birthdate !== '') {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $birthdate);
        if (!$date || $date->format('Y-m-d') !== $birthdate) {
            throw new InvalidArgumentException('Birthdate must be a valid date.');
        }
    }

    return [
        'student_number' => dats_intake_string($input, 'student_number', 30, true),
        'first_name' => dats_intake_string($input, 'first_name', 80, true),
        'middle_initial' => dats_intake_string($input, 'middle_initial', 10),
        'last_name' => dats_intake_string($input, 'last_name', 80, true),
        'gender' => dats_validate_intake_enum($input, 'gender', ['Female', 'Male', 'Other', 'Undisclosed'], 'Undisclosed'),
        'birthdate' => $birthdate === '' ? null : $birthdate,
        'address' => dats_intake_string($input, 'address', 255),
        'nationality' => dats_intake_string($input, 'nationality', 80),
        'civil_status' => dats_validate_intake_enum($input, 'civil_status', ['Single', 'Married', 'Widowed', 'Separated', 'Undisclosed'], 'Undisclosed'),
        'contact_number' => dats_intake_string($input, 'contact_number', 30),
        'college_id' => (int) ($input['college_id'] ?? 0) ?: null,
        'course_id' => (int) ($input['course_id'] ?? 0) ?: null,
        'major_id' => (int) ($input['major_id'] ?? 0) ?: null,
        'college_name' => dats_intake_string($input, 'college_name', 150),
        'course_name' => dats_intake_string($input, 'course_name', 180),
        'major_name' => dats_intake_string($input, 'major_name', 150),
        'academic_year' => dats_normalize_intake_year($input['academic_year'] ?? null),
        'year_level' => dats_validate_intake_enum($input, 'year_level', dats_intake_year_levels(), 'Undisclosed'),
    ];
}

function dats_find_or_create_intake_academic_record(PDO $pdo, string $table, string $name, ?int $parentId = null): int
{
    $where = $table === 'colleges' ? 'name = :name' : 'name = :name AND college_id = :parent_id';
    $query = $pdo->prepare("SELECT id FROM {$table} WHERE {$where} AND status = 'Active' LIMIT 1");
    $parameters = [':name' => $name];
    if ($table !== 'colleges') $parameters[':parent_id'] = $parentId;
    $query->execute($parameters);
    $existingId = (int) ($query->fetchColumn() ?: 0);
    if ($existingId > 0) return $existingId;

    if ($table === 'colleges') {
        $insert = $pdo->prepare('INSERT INTO colleges (name) VALUES (:name)');
        $insert->execute([':name' => $name]);
    } else {
        $insert = $pdo->prepare('INSERT INTO courses (college_id, name) VALUES (:parent_id, :name)');
        $insert->execute([':parent_id' => $parentId, ':name' => $name]);
    }
    return (int) $pdo->lastInsertId();
}

function dats_find_or_create_intake_major(PDO $pdo, string $name, int $courseId): int
{
    $query = $pdo->prepare("SELECT id FROM course_majors WHERE name = :name AND course_id = :course_id AND status = 'Active' LIMIT 1");
    $query->execute([':name' => $name, ':course_id' => $courseId]);
    $existingId = (int) ($query->fetchColumn() ?: 0);
    if ($existingId > 0) return $existingId;

    $insert = $pdo->prepare('INSERT INTO course_majors (course_id, name) VALUES (:course_id, :name)');
    $insert->execute([':course_id' => $courseId, ':name' => $name]);
    return (int) $pdo->lastInsertId();
}

function dats_resolve_intake_academic_hierarchy(PDO $pdo, array $student): array
{
    $collegeName = trim((string) ($student['college_name'] ?? ''));
    $courseName = trim((string) ($student['course_name'] ?? ''));
    $majorName = trim((string) ($student['major_name'] ?? ''));
    $collegeId = (int) ($student['college_id'] ?? 0);
    $courseId = (int) ($student['course_id'] ?? 0);
    $majorId = (int) ($student['major_id'] ?? 0);

    if ($collegeName !== '' && $collegeId < 1) $collegeId = dats_find_or_create_intake_academic_record($pdo, 'colleges', $collegeName);
    if ($courseName !== '' && $courseId < 1) {
        if ($collegeId < 1) throw new InvalidArgumentException('Select or enter a college before the course.');
        $courseId = dats_find_or_create_intake_academic_record($pdo, 'courses', $courseName, $collegeId);
    }
    if ($majorName !== '' && $majorId < 1) {
        if ($courseId < 1) throw new InvalidArgumentException('Select or enter a course before the major.');
        $majorId = dats_find_or_create_intake_major($pdo, $majorName, $courseId);
    }

    $student['college_id'] = $collegeId ?: null;
    $student['course_id'] = $courseId ?: null;
    $student['major_id'] = $majorId ?: null;
    return $student;
}

function dats_validate_student_hierarchy(PDO $pdo, array $student): array
{
    $collegeId = (int) ($student['college_id'] ?? 0);
    $courseId = (int) ($student['course_id'] ?? 0);
    $majorId = (int) ($student['major_id'] ?? 0);

    if ($collegeId > 0) {
        $query = $pdo->prepare("SELECT id FROM colleges WHERE id = :id AND status = 'Active'");
        $query->execute([':id' => $collegeId]);
        if (!$query->fetchColumn()) throw new InvalidArgumentException('The selected college is unavailable.');
    }
    if ($courseId > 0) {
        if ($collegeId < 1) throw new InvalidArgumentException('Select a college before selecting a course.');
        $query = $pdo->prepare("SELECT id FROM courses WHERE id = :id AND college_id = :college_id AND status = 'Active'");
        $query->execute([':id' => $courseId, ':college_id' => $collegeId]);
        if (!$query->fetchColumn()) throw new InvalidArgumentException('The selected course does not belong to that college.');
    }
    if ($majorId > 0) {
        if ($courseId < 1) throw new InvalidArgumentException('Select a course before selecting a major.');
        $query = $pdo->prepare("SELECT id FROM course_majors WHERE id = :id AND course_id = :course_id AND status = 'Active'");
        $query->execute([':id' => $majorId, ':course_id' => $courseId]);
        if (!$query->fetchColumn()) throw new InvalidArgumentException('The selected major does not belong to that course.');
    }
    return $student;
}

function dats_find_existing_student_for_intake(PDO $pdo, int $studentId): array
{
    $query = $pdo->prepare("SELECT id, academic_year FROM students WHERE id = :id AND status <> 'Inactive' AND deleted_at IS NULL LIMIT 1");
    $query->execute([':id' => $studentId]);
    $student = $query->fetch(PDO::FETCH_ASSOC);
    if (!$student) throw new InvalidArgumentException('The selected student was not found or is inactive.');
    $student['academic_year'] = dats_normalize_intake_year($student['academic_year'] ?? null);
    return $student;
}

function dats_insert_intake_student(PDO $pdo, array $student): int
{
    $duplicate = $pdo->prepare('SELECT id FROM students WHERE student_number = :student_number LIMIT 1');
    $duplicate->execute([':student_number' => $student['student_number']]);
    if ($duplicate->fetchColumn()) throw new InvalidArgumentException('A student with that student number already exists.');

    $statement = $pdo->prepare('INSERT INTO students (student_number, first_name, middle_initial, last_name, gender, birthdate, address, nationality, civil_status, contact_number, college_id, course_id, major_id, academic_year, year_level) VALUES (:student_number, :first_name, :middle_initial, :last_name, :gender, :birthdate, :address, :nationality, :civil_status, :contact_number, :college_id, :course_id, :major_id, :academic_year, :year_level)');
    $parameters = [];
    foreach (['student_number', 'first_name', 'middle_initial', 'last_name', 'gender', 'birthdate', 'address', 'nationality', 'civil_status', 'contact_number', 'college_id', 'course_id', 'major_id', 'academic_year', 'year_level'] as $field) {
        $parameters[":" . $field] = $student[$field] ?? null;
    }
    $statement->execute($parameters);
    $id = (int) $pdo->lastInsertId();
    if ($id < 1) throw new RuntimeException('The student record could not be created.');
    return $id;
}

function dats_run_transaction(PDO $pdo, callable $operation): mixed
{
    $pdo->beginTransaction();
    try {
        $result = $operation($pdo);
        $pdo->commit();
        return $result;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function dats_remove_new_upload(?string $absolutePath): void
{
    if ($absolutePath && is_file($absolutePath)) @unlink($absolutePath);
}

function dats_resolve_intake_student(PDO $pdo, array $input): array
{
    $mode = strtolower(trim((string) ($input['student_mode'] ?? '')));
    if ($mode === 'existing') {
        $student = dats_find_existing_student_for_intake($pdo, (int) ($input['student_id'] ?? 0));
        return ['student_id' => (int) $student['id'], 'academic_year' => $student['academic_year'], 'created' => false];
    }
    if ($mode === 'new') {
        $student = dats_validate_new_student_payload($input);
        $student = dats_resolve_intake_academic_hierarchy($pdo, $student);
        $student = dats_validate_student_hierarchy($pdo, $student);
        return [
            'student_id' => dats_insert_intake_student($pdo, $student),
            'academic_year' => $student['academic_year'],
            'created' => true,
        ];
    }
    if ($mode !== '') throw new InvalidArgumentException('Student mode must be existing or new.');

    $studentId = (int) ($input['student_id'] ?? 0);
    if ($studentId < 1 && trim((string) ($input['student_number'] ?? '')) !== '') {
        $query = $pdo->prepare('SELECT id FROM students WHERE student_number = :student_number LIMIT 1');
        $query->execute([':student_number' => trim((string) $input['student_number'])]);
        $studentId = (int) ($query->fetchColumn() ?: 0);
    }
    if ($studentId < 1 && trim((string) ($input['student_name'] ?? '')) !== '') {
        $query = $pdo->prepare("SELECT id FROM students WHERE CONCAT(first_name, ' ', last_name) = :student_name LIMIT 1");
        $query->execute([':student_name' => trim((string) $input['student_name'])]);
        $studentId = (int) ($query->fetchColumn() ?: 0);
    }
    if ($studentId < 1) throw new InvalidArgumentException('Student is required.');
    return [
        'student_id' => $studentId,
        'academic_year' => dats_normalize_intake_year($input['academic_year'] ?? null),
        'created' => false,
    ];
}

function dats_require_document_upload(array $input, ?array $file): void
{
    // A real $_FILES upload is required unconditionally: create_document() must never archive
    // a server-side path supplied by the client (that previously let `file_path` alone, with no
    // uploaded file, be used to pull arbitrary files already on disk into the archive).
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('Upload a document before adding it to the archive.');
    }
}
