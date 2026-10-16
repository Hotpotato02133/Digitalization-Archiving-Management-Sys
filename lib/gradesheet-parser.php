<?php
declare(strict_types=1);

require_once __DIR__ . '/gradesheet-names.php';

const DATS_GRADESHEET_SEMESTERS = ['1st Semester', '2nd Semester', 'Summer', 'Midyear'];
const DATS_GRADESHEET_GRADE_TOKENS = ['INC', 'DRP', 'DROPPED', 'W', 'WITHDRAWN', 'P', 'PASSED', 'F', 'FAILED', 'S', 'FA', 'NC', 'NG', 'IP', 'AUDIT'];
const DATS_GRADESHEET_MAX_TEXT_LENGTH = 400000;

function dats_gradesheet_profile_name_key(?string $name): string
{
    $upper = function_exists('mb_strtoupper') ? mb_strtoupper((string) $name, 'UTF-8') : strtoupper((string) $name);
    return preg_replace('/[^A-ZÑ]/u', '', $upper) ?? '';
}

/** Matches the common printed-name orders against a Students-directory profile. */
function dats_gradesheet_name_matches_student(string $name, array $student): bool
{
    $printed = dats_gradesheet_profile_name_key($name);
    $first = dats_gradesheet_profile_name_key($student['first_name'] ?? '');
    $middle = dats_gradesheet_profile_name_key($student['middle_initial'] ?? '');
    $last = dats_gradesheet_profile_name_key($student['last_name'] ?? '');
    if ($printed === '' || $first === '' || $last === '') return false;

    $given = $first . $middle;
    foreach ([$last . $given, $given . $last, $last . $first, $first . $last] as $candidate) {
        if ($printed === $candidate) return true;
    }
    return false;
}

function dats_gradesheet_pages_matching_student(string $ocrText, array $student): array
{
    $first = dats_gradesheet_profile_name_key($student['first_name'] ?? '');
    $middle = dats_gradesheet_profile_name_key($student['middle_initial'] ?? '');
    $last = dats_gradesheet_profile_name_key($student['last_name'] ?? '');
    $given = $first . $middle;
    $candidates = array_values(array_unique(array_filter([
        $last . $given, $given . $last, $last . $first, $first . $last,
    ], static fn (string $candidate): bool => strlen($candidate) >= 6)));
    if (!$candidates) return [];

    $matches = [];
    foreach (explode("\f", $ocrText) as $index => $pageText) {
        $page = dats_gradesheet_profile_name_key($pageText);
        foreach ($candidates as $candidate) {
            if (str_contains($page, $candidate)) {
                $matches[] = $index + 1;
                break;
            }
        }
    }
    return $matches;
}

function dats_gradesheet_directory_name_parts(string $name): array
{
    $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
    if (str_contains($name, ',')) {
        [$lastName, $givenNames] = array_map('trim', explode(',', $name, 2));
        $givenParts = preg_split('/\s+/u', trim($givenNames), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return [
            'first_name' => $givenParts[0] ?? '',
            'middle_initial' => implode(' ', array_slice($givenParts, 1)),
            'last_name' => $lastName,
        ];
    }
    $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    return [
        'first_name' => $parts[0] ?? '',
        'middle_initial' => implode(' ', array_slice($parts, 1, -1)),
        'last_name' => count($parts) > 1 ? (string) end($parts) : '',
    ];
}

function dats_gradesheet_record_matches_criteria(array $record, array $criteria): bool
{
    $student = dats_gradesheet_directory_name_parts((string) ($criteria['student_name'] ?? ''));
    if (!dats_gradesheet_name_matches_student((string) ($record['student_name'] ?? ''), $student)) return false;
    $recordKey = static function (mixed $value): string {
        $upper = function_exists('mb_strtoupper') ? mb_strtoupper((string) $value, 'UTF-8') : strtoupper((string) $value);
        return preg_replace('/[^A-Z0-9]/u', '', $upper) ?? '';
    };
    $sameText = static fn (mixed $left, mixed $right): bool => $recordKey($left) === $recordKey($right);

    $yearCourse = trim((string) ($criteria['year_course'] ?? ''));
    if ($yearCourse === '') $yearCourse = dats_normalize_year_course((string) ($criteria['student_year'] ?? ''), (string) ($criteria['student_course'] ?? ''));
    if ($yearCourse !== '' && !$sameText($record['year_course'] ?? '', $yearCourse)) return false;
    if (trim((string) ($criteria['semester'] ?? '')) !== '' && !$sameText($record['semester'] ?? '', $criteria['semester'])) return false;
    $yearKey = static function (mixed $value): ?string {
        if (!preg_match('/\b(20\d{2})\b/', (string) $value, $match)) return null;
        $year = (int) $match[1];
        return $year <= 2100 ? (string) $year : null;
    };
    $academicYear = $yearKey($criteria['academic_year'] ?? '');
    if ($academicYear !== null && $yearKey($record['academic_year'] ?? '') !== $academicYear) return false;
    if (trim((string) ($criteria['subject_code'] ?? '')) !== '' && !$sameText($record['subject_code'] ?? '', $criteria['subject_code'])) return false;
    if (trim((string) ($criteria['subject_name'] ?? '')) !== '' && !$sameText($record['subject_name'] ?? '', $criteria['subject_name'])) return false;
    if (trim((string) ($criteria['units'] ?? '')) !== '') {
        if (!is_numeric($criteria['units']) || !is_numeric($record['units'] ?? null)
            || abs((float) $criteria['units'] - (float) $record['units']) > 0.001) return false;
    }
    if (trim((string) ($criteria['grade'] ?? '')) !== '' && strtoupper(trim((string) ($record['grade'] ?? ''))) !== strtoupper(trim((string) $criteria['grade']))) return false;
    if (trim((string) ($criteria['remarks'] ?? '')) !== '' && !$sameText($record['remarks'] ?? '', $criteria['remarks'])) return false;
    return true;
}

function dats_gradesheet_page_matches_criteria(string $pageText, array $criteria): bool
{
    $upper = function_exists('mb_strtoupper') ? mb_strtoupper($pageText, 'UTF-8') : strtoupper($pageText);
    $pageKey = preg_replace('/[^A-Z0-9]/u', '', $upper) ?? '';
    $contains = static function (string $value) use ($pageKey): bool {
        $upper = function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);
        $key = preg_replace('/[^A-Z0-9]/u', '', $upper) ?? '';
        return $key !== '' && str_contains($pageKey, $key);
    };

    foreach (['subject_code', 'subject_name'] as $field) {
        $value = trim((string) ($criteria[$field] ?? ''));
        if ($value !== '' && !$contains($value)) return false;
    }
    $year = trim((string) ($criteria['academic_year'] ?? ''));
    if ($year !== '' && !$contains($year)) return false;
    $semester = trim((string) ($criteria['semester'] ?? ''));
    if ($semester !== '') {
        foreach (preg_split('/\s+/u', $semester, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
            if (!$contains($token)) return false;
        }
    }
    $yearCourse = trim((string) ($criteria['year_course'] ?? ''));
    foreach (preg_split('/\s+/u', $yearCourse, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
        if (!$contains($token)) return false;
    }
    foreach (['units', 'grade', 'remarks'] as $field) {
        $value = trim((string) ($criteria[$field] ?? ''));
        if ($value === '') continue;
        if ($field === 'units' && is_numeric($value)) {
            $number = (float) $value;
            $variants = array_unique([
                $value,
                (string) $number,
                rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.'),
            ]);
            if (!array_filter($variants, $contains)) return false;
        } elseif (!$contains($value)) {
            return false;
        }
    }
    return true;
}
const DATS_GRADESHEET_MAX_LINES = 8000;
// Undergraduate/graduate scale printed on the ZPPSU Report of Rating.
const DATS_GRADESHEET_VALID_GRADES = ['1.0', '1.25', '1.5', '1.75', '2.0', '2.25', '2.5', '2.75', '3.0', '4.0', '5.0'];
const DATS_GRADESHEET_NAME_STOPWORDS = ['NAME', 'NAMES', 'NO', 'SURNAME', 'NOTE', 'COURSE', 'GRADE', 'FINAL', 'REMARKS', 'YEAR', 'STUDENTS', 'NOTHING', 'FOLLOWS', 'FOLLOW', 'REPORT', 'RATING', 'TIME', 'UNITS', 'SUMMER', 'DESCRIPTION', 'SEMESTER', 'APPROVED', 'DEAN', 'VICE', 'PRESIDENT', 'CERTIFIED', 'CORRECT', 'INC', 'DRP', 'PASSED', 'FAILED', 'GRADING', 'SYSTEM', 'DATE', 'SUBMITTED', 'INSTRUCTOR', 'TEL'];

function dats_gradesheet_normalize_semester(string $text): ?string
{
    $normalized = strtolower(trim($text));
    if ($normalized === '') return null;
    if (preg_match('/\b(1st|first)\b/', $normalized)) return '1st Semester';
    if (preg_match('/\b(2nd|second)\b/', $normalized)) return '2nd Semester';
    if (str_contains($normalized, 'summer')) return 'Summer';
    if (str_contains($normalized, 'midyear') || str_contains($normalized, 'mid-year') || str_contains($normalized, 'mid year')) return 'Midyear';
    return null;
}

/**
 * A line only sets the "current semester" context when an ordinal (1st/2nd/etc.) appears
 * together with some form of the word "semester" — otherwise unrelated text like a subject
 * titled "1st Year Seminar" would be misread as a semester header.
 */
function dats_gradesheet_is_semester_line(string $line): bool
{
    $normalized = strtolower($line);
    $hasOrdinal = (bool) preg_match('/\b(1st|first|2nd|second)\b/', $normalized);
    if ($hasOrdinal && str_contains($normalized, 'sem')) return true;
    if (str_contains($normalized, 'summer')) return true;
    if (str_contains($normalized, 'midyear') || str_contains($normalized, 'mid-year') || str_contains($normalized, 'mid year')) return true;
    return false;
}

/** Strips table-border/OCR noise characters, leading list numbering, and collapses whitespace. */
function dats_gradesheet_clean_line(string $line): string
{
    $line = str_replace(['|', "\xe2\x94\x82", '¦', "\t"], ' ', $line);
    $line = preg_replace('/[-_]{3,}/', ' ', $line) ?? $line;
    $line = preg_replace('/\s+/', ' ', $line) ?? $line;
    $line = trim($line);
    // Strip a leading list marker ("1.", "12)", "•", "-") that some scanned tables number rows with.
    $line = preg_replace('/^(?:\d{1,3}[.\)]|[•\-*])\s+/', '', $line) ?? $line;
    return trim($line);
}

function dats_gradesheet_grade_alternation(): string
{
    static $alternation = null;
    if ($alternation === null) {
        $alternation = implode('|', array_map(
            static fn (string $token): string => preg_quote($token, '/'),
            DATS_GRADESHEET_GRADE_TOKENS
        ));
    }
    return $alternation;
}

/**
 * Tries to read one "subject code / title / units / grade" row out of a single cleaned OCR line.
 * Two passes: a strict pattern first, then a looser whitespace-tokenized fallback that tolerates
 * the column misalignment Tesseract commonly produces on scanned tables.
 */
function dats_gradesheet_match_row(string $line): ?array
{
    $gradeAlternation = dats_gradesheet_grade_alternation();

    $strict = '/^(?<code>[A-Z]{2,10}[\s-]?\d{1,4}[A-Z]?)\s+(?<title>.+?)\s+(?<units>\d{1,2}(?:\.\d{1,2})?)\s+(?<grade>[1-5]\.\d{2}|' . $gradeAlternation . ')\b\s*(?<remarks>[A-Za-z]+)?\s*$/i';
    if (preg_match($strict, $line, $match) && trim($match['title']) !== '') {
        return [
            'subject_code' => strtoupper(preg_replace('/\s+/', '', $match['code']) ?? $match['code']),
            'subject_name' => trim($match['title']),
            'units' => (float) $match['units'],
            'grade' => strtoupper(trim($match['grade'])),
            'remarks' => trim((string) ($match['remarks'] ?? '')) ?: null,
        ];
    }

    $tokens = preg_split('/\s+/', $line) ?: [];
    $count = count($tokens);
    if ($count < 4) return null;
    $last = $tokens[$count - 1];
    $secondLast = $tokens[$count - 2];
    if (!preg_match('/^(?:[1-5]\.\d{2}|' . $gradeAlternation . ')$/i', $last)) return null;
    if (!preg_match('/^\d{1,2}(?:\.\d{1,2})?$/', $secondLast)) return null;
    if (!preg_match('/^[A-Z]{2,10}[\s-]?\d{1,4}[A-Z]?$/i', $tokens[0])) return null;
    $title = trim(implode(' ', array_slice($tokens, 1, $count - 3)));
    if ($title === '') return null;

    return [
        'subject_code' => strtoupper($tokens[0]),
        'subject_name' => $title,
        'units' => (float) $secondLast,
        'grade' => strtoupper($last),
        'remarks' => null,
    ];
}

/**
 * Detects a line that identifies WHICH student the rows following it belong to — either a
 * labeled "Name:"/"Student No:" line, or a bare "LAST, FIRST M." heading, which is how many
 * printed gradesheet forms present the name with no explicit label. Returns null for anything
 * else so ordinary subject/semester lines are never mistaken for a student header.
 */
function dats_gradesheet_extract_student_header(string $line): ?array
{
    if (preg_match('/^(?:name|student\s*name)\s*[:\-]\s*(.+)$/i', $line, $match)) {
        $name = trim($match[1]);
        return $name === '' ? null : ['raw_name' => $name, 'raw_student_number' => null];
    }
    if (preg_match('/^student\s*(?:no\.?|number|id)\s*[:\-]\s*([A-Za-z0-9\-]+)$/i', $line, $match)) {
        return ['raw_name' => null, 'raw_student_number' => trim($match[1])];
    }
    // A bare "Dela Cruz, Juan M." style heading: comma-separated name, no digits, short enough
    // that it can't accidentally be a sentence or a subject title.
    if (
        strlen($line) <= 60
        && !preg_match('/\d/', $line)
        && preg_match('/^[A-Z][A-Za-z.\'\-]*(?:\s+[A-Z][A-Za-z.\'\-]*)*,\s*[A-Z][A-Za-z.\'\-]*(?:\s+[A-Z][A-Za-z.\'\-]*)*$/', $line)
    ) {
        return ['raw_name' => $line, 'raw_student_number' => null];
    }
    return null;
}

/**
 * Splits OCR'd text from a (potentially multi-student) gradesheet/"Report of Rating" document
 * into one block per detected student, each carrying its own semester/subject/grade rows.
 *
 * If no student header is ever found, the whole document naturally becomes a single unnamed
 * block — this is what makes the function work equally well for a plain single-student
 * gradesheet with no name line at all, so there's no separate single-document code path.
 *
 * Only "one student's own rows, then the next student's header, then their rows, ..." layouts
 * are supported. A dense matrix layout (students as rows, subjects as columns in one grid) is
 * out of scope — that needs real table/column detection, not line-based text heuristics — and is
 * expected to simply produce zero or garbled blocks rather than silently misassigning rows.
 */
function parse_batch_gradesheet_text(string $ocrText): array
{
    if (strlen($ocrText) > DATS_GRADESHEET_MAX_TEXT_LENGTH) {
        $ocrText = substr($ocrText, 0, DATS_GRADESHEET_MAX_TEXT_LENGTH);
    }
    $blocks = [];
    $current = ['raw_name' => null, 'raw_student_number' => null, 'rows' => []];
    $currentSemester = null;
    $currentAcademicYear = null;

    $flush = function () use (&$blocks, &$current): void {
        if ($current['raw_name'] !== null || $current['raw_student_number'] !== null || $current['rows']) {
            $blocks[] = $current;
        }
    };

    $lineBudget = DATS_GRADESHEET_MAX_LINES;
    foreach (explode("\f", $ocrText) as $pageIndex => $pageText) {
        foreach (preg_split('/\r\n|\r|\n/', $pageText) ?: [] as $rawLine) {
            if (--$lineBudget < 0) break 2;
            $line = dats_gradesheet_clean_line((string) $rawLine);
            if ($line === '') continue;

            if (preg_match('/\b(\d{4})\s*[-\x{2013}\x{2014}]\s*(\d{4})\b/u', $line, $yearMatch)) {
                $currentAcademicYear = $yearMatch[1];
            } elseif (preg_match('/(?:A\.?\s*Y\.?|S\.?\s*Y\.?|academic year|school year)\D{0,6}(\d{4})/i', $line, $yearMatch)) {
                $currentAcademicYear = $yearMatch[1];
            }

            if (dats_gradesheet_is_semester_line($line)) {
                $semester = dats_gradesheet_normalize_semester($line);
                if ($semester !== null) {
                    $currentSemester = $semester;
                    continue;
                }
            }

            $header = dats_gradesheet_extract_student_header($line);
            if ($header !== null) {
                $sameName = $header['raw_name'] !== null && $current['raw_name'] !== null
                    && strcasecmp($header['raw_name'], $current['raw_name']) === 0;
                $sameNumber = $header['raw_student_number'] !== null && $current['raw_student_number'] !== null
                    && strcasecmp($header['raw_student_number'], $current['raw_student_number']) === 0;
                if (!$sameName && !$sameNumber) {
                    if ($current['rows']) {
                        $flush();
                        $current = ['raw_name' => null, 'raw_student_number' => null, 'rows' => []];
                    }
                    if ($header['raw_name'] !== null) $current['raw_name'] = $header['raw_name'];
                    if ($header['raw_student_number'] !== null) $current['raw_student_number'] = $header['raw_student_number'];
                }
                continue;
            }

            $match = dats_gradesheet_match_row($line);
            if ($match !== null) {
                $current['rows'][] = array_merge(['academic_year' => $currentAcademicYear, 'semester' => $currentSemester, 'source_pages' => [$pageIndex + 1]], $match);
            }
        }
    }
    $flush();

    return $blocks;
}

// ---------------------------------------------------------------------------
// "Report of Rating" layout: one subject per page, many students per page,
// often printed as two side-by-side tables (rows 1-25 | 26-50), so a single
// OCR line can hold two students.
// ---------------------------------------------------------------------------

/** Maps an OCR'd grade cell to a value on the printed grading scale, or null when unreadable. */
function dats_gradesheet_normalize_grade(string $token): ?string
{
    $t = strtoupper(trim($token, " \t.,;:'\"`^°¢”“_-|()[]{}~"));
    if ($t === '') return null;
    if (in_array($t, ['INC', 'DRP', 'NG', 'W'], true)) return $t;
    if (preg_match('/^([1-5])[.,:·]\s?(\d{1,2})$/u', $t, $m) || preg_match('/^([1-5])(\d{1,2})$/', $t, $m)) {
        // A bare single digit is deliberately NOT accepted: a handwritten "1.5" often OCRs as just
        // "5", and silently recording that as a 5.0 (failing) grade would be a harmful misread.
        $value = (float) ($m[1] . '.' . $m[2]);
        foreach (DATS_GRADESHEET_VALID_GRADES as $valid) {
            if (abs((float) $valid - $value) < 0.001) return $valid;
        }
    }
    return null;
}

function dats_gradesheet_normalize_remarks(array $tokens): ?string
{
    foreach ($tokens as $token) {
        $t = preg_replace('/[^A-Z]/', '', strtoupper((string) $token)) ?? '';
        if (strlen($t) < 3) continue;
        if ($t === 'INC') return 'INCOMPLETE';
        if ($t === 'DRP') return 'DROPPED';
        foreach (['PASSED', 'FAILED', 'INCOMPLETE', 'DROPPED', 'CONDITIONAL'] as $word) {
            if (strlen($t) >= 4 && levenshtein($t, $word) <= 2) return $word;
        }
    }
    return null;
}

/** Remarks implied by a grade, per the grading system printed on the form itself. */
function dats_gradesheet_remarks_from_grade(?string $grade): ?string
{
    return match (true) {
        $grade === null => null,
        $grade === 'INC' => 'INCOMPLETE',
        $grade === 'DRP', $grade === 'W' => 'DROPPED',
        $grade === 'NG' => null,
        (float) $grade <= 3.0 => 'PASSED',
        (float) $grade === 4.0 => 'CONDITIONAL',
        default => 'FAILED',
    };
}

function dats_gradesheet_tidy_name(string $surname, string $given): string
{
    $surname = trim(preg_replace('/\s+/u', ' ', $surname) ?? $surname);
    $given = trim(preg_replace('/\s+/u', ' ', $given) ?? $given);
    $given = rtrim($given, " -_,");
    // OCR often glues the trailing middle initial onto the first name: "SharmaineM." -> "Sharmaine M."
    $given = preg_replace('/([a-zñ])([A-ZÑ]{1,2})(\.?)$/u', '$1 $2$3', $given) ?? $given;
    return $surname . ', ' . $given;
}

/** Surname particles that must stay attached to the word after them ("Dela" + "Cruz" = "Dela Cruz"). */
const DATS_GRADESHEET_SURNAME_PARTICLES = ['DE', 'DEL', 'DELA', 'DELAS', 'DELOS', 'LA', 'LAS', 'LOS', 'SAN', 'STA', 'STO', 'MC'];

/**
 * Splits a single OCR token that lost BOTH the comma and the space between the surname and given
 * name — e.g. a printed "DELACRUZJUAN" or a cramped handwritten "SantosMaria" — into [surname,
 * given]. Only mixed-case (CamelCase) tokens have a usable boundary to split on; an all-caps blob
 * gives no signal at all, so this returns null rather than guessing at a position. Every caller
 * must flag the record uncertain when this succeeds — the split is still a heuristic guess, and
 * is exactly the kind of case the printed form would otherwise read as one garbled name.
 */
function dats_gradesheet_split_glued_name(string $token): ?array
{
    $token = trim($token);
    if (strlen($token) < 6 || str_contains($token, ' ') || str_contains($token, ',')) return null;
    // Each part needs at least one lowercase letter after its capital — an all-caps token (no
    // case boundary at all) must never match, or every letter would "match" as its own one-letter
    // part and pass the coverage check below despite having no real split point.
    preg_match_all('/[A-ZÑ][a-zñ\'\-]+/u', $token, $matches);
    $parts = $matches[0] ?? [];
    // The matched parts must cover the whole token with nothing left over (stray digits/symbols,
    // or an all-caps run with no lowercase to bound it, fail this and correctly refuse to split).
    if (count($parts) < 2 || implode('', $parts) !== $token) return null;
    $splitAt = (in_array(strtoupper($parts[0]), DATS_GRADESHEET_SURNAME_PARTICLES, true) && count($parts) > 2) ? 2 : 1;
    $given = implode(' ', array_slice($parts, $splitAt));
    if ($given === '') return null;
    return [implode(' ', array_slice($parts, 0, $splitAt)), $given];
}

/**
 * Program codes in the Year & Course column (BEED, BSED, BTTE, ...) and their common OCR
 * misreads (BITEN, BIEN, HEED, PTTEELECT). Used both to find that column and to make sure a
 * course code is never mistaken for part of a student's name.
 */
function dats_normalize_year_course(?string $year = null, ?string $course = null): string
{
    $yearValue = trim((string) ($year ?? ''));
    $courseValue = trim((string) ($course ?? ''));
    if ($yearValue === '' && $courseValue === '') {
        return '';
    }
    if ($yearValue !== '' && $courseValue === '') {
        $combined = preg_replace('/\s+/u', ' ', $yearValue);
        if (preg_match('/^(.*?)(?:\s*[-–|\/]\s*|\s+)(\d{1,2}(?:st|nd|rd|th)?\s*(?:Year|Yr)|Undisclosed|Senior High School|Graduate|High School|Elementary|Pre-Baccalaureate)$/iu', (string) $combined, $matches)) {
            return trim($matches[1]) !== '' ? trim($matches[1]) . ' ' . trim($matches[2]) : trim($matches[2]);
        }
        return $combined;
    }
    if ($yearValue === '' && $courseValue !== '') {
        return $courseValue;
    }
    return $courseValue . ' ' . $yearValue;
}

function dats_gradesheet_is_course_token(string $token): bool
{
    // "PASSED" has the same P..SED shape as a program code, so remark words are excluded.
    return preg_match('/^[BPH][A-Z]{0,2}(?:EED|SED|TTE|ITE|IEN|TEN|ED$|ED-)/', strtoupper($token)) === 1
        && preg_match('/^[A-Z]{2}/', $token) === 1
        && dats_gradesheet_normalize_remarks([$token]) === null;
}

function dats_gradesheet_name_pattern(): string
{
    $surnameWord = "[A-ZÑ][A-ZÑ'\\-]+";
    $particle = '(?:(?:DE|DEL|DELA|DELAS|DELOS|LA|LAS|LOS|SAN|STA|STO|MC)\.?\s+)';
    $givenFirst = "[A-ZÑ][A-Za-zñÑ'\\-]*\\.?";
    return '/(?<![A-Za-zñÑ])(?<surname>' . $particle . '?' . $surnameWord . '(?:\s+' . $surnameWord . ')?)(?<sep>\s*,\s*|\s+)(?<given>' . $givenFirst . ')/u';
}

/**
 * Extends the first given-name token with the rest of the given names and at most one trailing
 * initial ("Mary Chrystallyte A.", "JUVIE ANN M.", "Angelique DL."), stopping at anything that
 * isn't name-shaped — a course code, a grade, a table border.
 */
function dats_gradesheet_extend_given(string $line, int $offset, string $firstGiven): array
{
    $allCaps = preg_match('/[a-zñ]/u', $firstGiven) !== 1;
    $given = $firstGiven;
    $end = $offset;
    // The first given token is always a name word, even when it looks like an initial ("MA." = Maria).
    $sawInitial = false;
    while (!$sawInitial && preg_match('/\G(\s+)([^\s|,]+)/u', $line, $match, 0, $end)) {
        $token = $match[2];
        $isInitial = preg_match('/^[A-ZÑ]\.?$|^[A-ZÑ]{2}\.$/u', $token) === 1;
        $isWord = preg_match('/^[A-ZÑ][a-zñ][A-Za-zñÑ\'\-]*\.?$/u', $token) === 1;
        $isCapsWord = $allCaps && preg_match('/^[A-ZÑ][A-ZÑ\'\-]+\.?$/u', $token) === 1 && !dats_gradesheet_is_course_token($token);
        if (!$isInitial && !$isWord && !$isCapsWord) break;
        if (in_array(strtoupper(rtrim($token, '.')), DATS_GRADESHEET_NAME_STOPWORDS, true)) break;
        $given .= ' ' . $token;
        $end += strlen($match[0]);
        $sawInitial = $isInitial;
    }
    return [$given, $end];
}

/** Checks that don't need the rest of the row: stopwords, course codes, obvious noise. */
function dats_gradesheet_is_plausible_name(string $surname, string $separator, string $given): bool
{
    $words = preg_split('/\s+/', strtoupper($surname)) ?: [];
    foreach ($words as $word) {
        if (in_array(rtrim($word, '.'), DATS_GRADESHEET_NAME_STOPWORDS, true)) return false;
    }
    if (dats_gradesheet_is_course_token(end($words) ?: '') || dats_gradesheet_is_course_token($words[0] ?? '')) return false;
    $firstGiven = strtok($given, ' ') ?: $given;
    if (in_array(strtoupper(rtrim($firstGiven, '.')), DATS_GRADESHEET_NAME_STOPWORDS, true)) return false;
    if (dats_gradesheet_is_course_token($firstGiven)) return false;
    if (!str_contains($separator, ',')) {
        // Without a comma, only accept "SURNAME Given" (mixed case) — and never a course code
        // followed by its major ("BSED Mapeh", "BSED Math"), which has exactly that shape.
        if (preg_match('/[a-zñ]/u', $firstGiven) !== 1) return false;
        if (preg_match('/^B[SETI][A-Z]{0,6}$/', $surname) && preg_match('/^M[a-z]{1,4}h?$/', $firstGiven)) return false;
    }
    return strlen(preg_replace('/[^A-Za-zñÑ]/u', '', $given) ?? '') >= 2;
}

function dats_gradesheet_has_row_number_before(string $line, int $start): bool
{
    return preg_match('/(?:^|[\s|])\d{1,2}\s*[|.:)\]~\'`]*\s*$/', substr($line, max(0, $start - 8), min(8, $start))) === 1;
}

/** Splits the text after a student's name into Year & Course / Final Grade / Remarks. */
function dats_gradesheet_parse_row_rest(string $rest): array
{
    // The next row's number ("... | Passed | | 27 |"). Must be preceded by a space or border so the
    // "25" inside a grade like "2.25" is never mistaken for it.
    $rest = preg_replace('/(?:^|[\s|])\d{1,2}[\s|]*$/', '', $rest) ?? $rest;
    $cells = array_values(array_filter(
        array_map('trim', explode('|', $rest)),
        static fn (string $cell): bool => $cell !== '' && preg_match('/[A-Za-z0-9]/', $cell) === 1
    ));
    // Drop leading 1-2 character fragments (a stray initial, "C,", OCR specks) before the course cell.
    while ($cells && strlen(preg_replace('/[^A-Za-z0-9]/', '', $cells[0]) ?? '') <= 2 && dats_gradesheet_normalize_grade($cells[0]) === null) {
        array_shift($cells);
    }

    $courseTokens = [];
    $afterTokens = [];
    foreach ($cells as $cellIndex => $cell) {
        $tokens = preg_split('/\s+/', $cell) ?: [];
        if ($cellIndex > 0 || $courseTokens !== [] || $afterTokens !== []) {
            array_push($afterTokens, ...$tokens);
            continue;
        }
        foreach ($tokens as $i => $token) {
            $isCourseStart = $courseTokens === [] && (
                dats_gradesheet_is_course_token($token)
                || (preg_match('/^[A-Z]{2,}[A-Za-z0-9\-]*$/', $token) === 1 && dats_gradesheet_normalize_remarks([$token]) === null)
            );
            $isCourseSuffix = $courseTokens !== [] && $i <= 3
                && preg_match('/^(?:[IVX]{1,4}|[IVX1l]{1,3}-?[A-Z]|[A-Z]{1,5}|[A-Z]{2,6}[Il1]{1,3}|[A-Z]-[A-Z]|M[a-z]{1,4}h?|Math)$/', $token);
            if ($isCourseStart || $isCourseSuffix) {
                $courseTokens[] = $token;
                continue;
            }
            $afterTokens = array_merge($afterTokens, array_slice($tokens, $i));
            break;
        }
    }

    $grade = null;
    foreach (array_slice($afterTokens, 0, 2) as $token) {
        $grade = dats_gradesheet_normalize_grade($token);
        if ($grade !== null) break;
    }
    $course = $courseTokens ? implode(' ', $courseTokens) : null;
    return [$course, $grade, dats_gradesheet_normalize_remarks($afterTokens)];
}

/** Finds every student row on one OCR line (two, when the sheet has side-by-side tables). */
function dats_gradesheet_rows_in_line(string $line): array
{
    $normalized = str_replace(['[', ']', '{', '}', '(', ')', '¥', '“', '”', '"', '«', '»', '#', '£', '€', '©', '®', '’', '‘'], '|', $line);
    $normalized = trim(preg_replace('/\s+/u', ' ', str_replace('_', ' ', $normalized)) ?? $normalized);

    preg_match_all(dats_gradesheet_name_pattern(), $normalized, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    $names = [];
    foreach ($matches as $match) {
        $start = $match[0][1];
        if ($names && $start < end($names)['end']) continue; // inside the previous name's extension
        if (!dats_gradesheet_is_plausible_name($match['surname'][0], $match['sep'][0], $match['given'][0])) continue;
        [$given, $end] = dats_gradesheet_extend_given($normalized, $match[0][1] + strlen($match[0][0]), $match['given'][0]);
        $names[] = [
            'start' => $start,
            'end' => $end,
            'name' => dats_gradesheet_tidy_name($match['surname'][0], $given),
            'row_number' => dats_gradesheet_has_row_number_before($normalized, $start),
            'short_surname' => strlen(preg_replace('/[^A-ZÑ]/u', '', strtok($match['surname'][0], ' ') ?: '') ?? '') <= 2,
        ];
    }

    // A real student row always has a Year & Course cell or a row number beside the name; OCR
    // noise from blank table rows ("EE, Cee St I") has neither. Dropping a candidate hands its
    // text back to the previous row, so re-split until the set is stable.
    do {
        $rows = [];
        $kept = [];
        foreach ($names as $index => $name) {
            $restEnd = $names[$index + 1]['start'] ?? strlen($normalized);
            [$course, $grade, $remarks] = dats_gradesheet_parse_row_rest(substr($normalized, $name['end'], $restEnd - $name['end']));
            $anchored = $course !== null || $name['row_number'];
            if (!$anchored || ($name['short_surname'] && $course === null)) continue;
            $name['rest_end'] = $restEnd;
            $kept[] = $name;
            $rows[] = ['student_name' => $name['name'], 'year_course' => $course, 'grade' => $grade, 'remarks' => $remarks, 'uncertain_name' => false];
        }
        $changed = count($kept) !== count($names);
        $names = $kept;
    } while ($changed);

    return array_merge($rows, dats_gradesheet_orphan_course_rows($normalized, $names));
}

/**
 * Recall fallback. The Year & Course code is typed and OCRs far more reliably than names, so a
 * course cell that no recognized name claimed still marks a student row: record it with the
 * nearest name text, flagged as uncertain so an admin corrects the spelling rather than the
 * student silently going missing.
 */
function dats_gradesheet_orphan_course_rows(string $line, array $keptNames): array
{
    preg_match_all('/(?<=^|[\s|])[^\s|]+/', $line, $tokenMatches, PREG_OFFSET_CAPTURE);
    $claimed = [];
    foreach ($keptNames as $name) {
        foreach ($tokenMatches[0] as [$token, $position]) {
            if ($position >= $name['end'] && $position < $name['rest_end'] && dats_gradesheet_is_course_token($token)) {
                $claimed[$position] = true;
                break;
            }
        }
    }

    $rows = [];
    foreach ($tokenMatches[0] as [$token, $position]) {
        if (isset($claimed[$position]) || !dats_gradesheet_is_course_token($token)) continue;
        $cellStart = strrpos(substr($line, 0, $position), '|');
        if ($cellStart === false) continue;
        $previousBorder = strrpos(substr($line, 0, $cellStart), '|');
        $nameStart = $previousBorder === false ? 0 : $previousBorder + 1;
        $overlapsKnownName = false;
        foreach ($keptNames as $name) {
            if ($nameStart < $name['end'] && $cellStart > $name['start']) $overlapsKnownName = true;
        }
        if ($overlapsKnownName) continue;

        $blob = substr($line, $nameStart, $cellStart - $nameStart);
        $blob = trim(preg_replace('/^[\W_]*\d{0,2}[\W_]*/u', '', $blob) ?? '', " \t.,-_~");
        if (strlen(preg_replace('/[^A-Za-zñÑ]/u', '', $blob) ?? '') < 4) continue;
        $firstWord = strtoupper(strtok($blob, ' ,') ?: '');
        if (in_array(rtrim($firstWord, '.'), DATS_GRADESHEET_NAME_STOPWORDS, true)) continue;

        $nextKnown = strlen($line);
        foreach ($keptNames as $name) {
            if ($name['start'] > $position) { $nextKnown = min($nextKnown, $name['start']); }
        }
        [$course, $grade, $remarks] = dats_gradesheet_parse_row_rest(substr($line, $position, $nextKnown - $position));
        $rows[] = ['student_name' => $blob, 'year_course' => $course ?? $token, 'grade' => $grade, 'remarks' => $remarks, 'uncertain_name' => true];
    }
    return $rows;
}

function dats_gradesheet_rating_semester(string $line): ?string
{
    if (!preg_match('/(\S*)\s*semest/i', $line, $match)) {
        return preg_match('/summer/i', $line) && preg_match('/\bs\.?\s*y\b/i', $line) ? 'Summer' : null;
    }
    $word = strtolower($match[1]);
    if (preg_match('/^(?:2|z|second)/', $word)) return '2nd Semester';
    if (preg_match('/^(?:1|i|l|first)/', $word)) return '1st Semester';
    return null;
}

function dats_gradesheet_new_page_context(): array
{
    return ['academic_year' => null, 'semester' => null, 'subject_code' => null, 'subject_name' => null, 'units' => null];
}

/**
 * Parses OCR'd "Report of Rating" pages into one record per student per subject. Pages are
 * separated by form feeds ("\f", which Tesseract emits per page and the browser OCR joins
 * with), and each page's header (semester, SY, course no., description, units) is applied to
 * every student row on that page.
 */
function parse_report_of_rating_text(string $ocrText): array
{
    if (strlen($ocrText) > DATS_GRADESHEET_MAX_TEXT_LENGTH) {
        $ocrText = substr($ocrText, 0, DATS_GRADESHEET_MAX_TEXT_LENGTH);
    }
    $records = [];
    $seen = [];
    $lineBudget = DATS_GRADESHEET_MAX_LINES;

    foreach (explode("\f", $ocrText) as $pageIndex => $pageText) {
        $context = dats_gradesheet_new_page_context();
        $inTable = false;
        foreach (preg_split('/\r\n|\r|\n/', $pageText) ?: [] as $rawLine) {
            if (--$lineBudget < 0) break 2;
            $line = trim(preg_replace('/\s+/u', ' ', (string) $rawLine) ?? '');
            if ($line === '') continue;

            if (preg_match('/report\s+of\s+rating/i', $line)) {
                // A second form on the same OCR "page" (e.g. no form feed between pages).
                if ($records && $context['subject_code'] !== null) $context = dats_gradesheet_new_page_context();
                $inTable = false;
            }
            if (preg_match('/\b(20\d{2})\s*[-–—~]+\s*(20\d{2})\b/u', $line, $yearMatch) && $context['academic_year'] === null) {
                $context['academic_year'] = $yearMatch[1];
            }
            $semester = dats_gradesheet_rating_semester($line);
            if ($semester !== null && !preg_match('/course\s*no/i', $line)) {
                $context['semester'] = $semester;
                continue;
            }
            if (preg_match('/course\s*no/i', $line)) {
                if (preg_match('/course\s*no\W*(.+?)(?:\s+unit|$)/i', $line, $codeMatch)) {
                    $code = trim(preg_replace('/^[\W_]+|[\W_]+$/u', '', $codeMatch[1]) ?? '');
                    // Drop short OCR specks around the code ("oo Computer 121", "Computer 121 oo") — never
                    // a digit group — then normalize "MATH112" and "MATH 112" to the same code. Only
                    // digit-free 1-2 letter specks ("oo", "a", "BN") and leading symbols are OCR noise;
                    // "IS 101", "PE 1", "MATH 1A" are real codes.
                    while (preg_match('/\s+(?![^\s]*\d)[^\s]{1,2}$/u', $code)) {
                        $code = trim(preg_replace('/\s+(?![^\s]*\d)[^\s]{1,2}$/u', '', $code) ?? $code);
                    }
                    while (preg_match('/^(?:[^A-Za-z0-9\s]+|[a-z]{1,2})\s+/u', $code)) {
                        $code = trim(preg_replace('/^(?:[^A-Za-z0-9\s]+|[a-z]{1,2})\s+/u', '', $code) ?? $code);
                    }
                    $code = trim(preg_replace('/^[\W_]+/u', '', $code) ?? $code);
                    $code = preg_replace('/([A-Za-z])(\d)/', '$1 $2', $code) ?? $code;
                    $code = preg_replace('/\s+/', ' ', $code) ?? $code;
                    $context['subject_code'] = $code !== '' ? substr($code, 0, 20) : null;
                }
                if (preg_match('/units?\s*[:.]?\s*[_~]*\s*(\d+(?:\.\d+)?)/i', $line, $unitMatch)) {
                    $context['units'] = (float) $unitMatch[1];
                }
                $inTable = true;
                continue;
            }
            if (preg_match('/description\W*(.+?)(?:\s+time\b|$)/i', $line, $descriptionMatch)) {
                $name = trim(preg_replace('/^[\W_]+/u', '', $descriptionMatch[1]) ?? '');
                // Strip short OCR artifacts left by blank form lines before "Time:" ("Co", "To", "TT").
                while (preg_match('/\s+(?:co|to|tt|t|[\W_]+)$/iu', $name)) {
                    $name = trim(preg_replace('/\s+(?:co|to|tt|t|[\W_]+)$/iu', '', $name) ?? $name);
                }
                $context['subject_name'] = $name !== '' ? substr($name, 0, 255) : null;
                $inTable = true;
                continue;
            }
            // A row rebuilt from the table's cells in the browser: fields are positional, so no
            // guessing where one column ends and the next begins.
            if (str_starts_with($line, '@ROW|')) {
                $row = dats_gradesheet_structured_row($line);
                if ($row === null) continue;
                $key = strtolower($row['student_name'] . '|' . $context['subject_code'] . '|' . $context['subject_name'] . '|' . $context['semester'] . '|' . $context['academic_year']);
                if (isset($seen[$key])) {
                    $records[$seen[$key]]['source_pages'][] = $pageIndex + 1;
                    continue;
                }
                $seen[$key] = count($records);
                $records[] = array_merge($context, $row, ['student_number' => null, 'source_pages' => [$pageIndex + 1]]);
                continue;
            }
            if (preg_match('/^\W*(?:note\s*!?:|grading\s+system|undergraduate|graduate\s+studies|date\s+submitted|certified\s+correct|approved)/i', $line)) {
                $inTable = false;
                continue;
            }
            if (!$inTable) continue;

            foreach (dats_gradesheet_rows_in_line($line) as $row) {
                $key = strtolower($row['student_name'] . '|' . $context['subject_code'] . '|' . $context['subject_name'] . '|' . $context['semester'] . '|' . $context['academic_year']);
                if (isset($seen[$key])) {
                    $records[$seen[$key]]['source_pages'][] = $pageIndex + 1;
                    continue;
                }
                $seen[$key] = count($records);
                $records[] = array_merge($context, $row, ['student_number' => null, 'source_pages' => [$pageIndex + 1]]);
            }
        }
    }
    return array_map('dats_gradesheet_finalize_record', $records);
}

/**
 * Parses "@ROW|no|name|course|grade|remarks" (a row rebuilt from table cells in the browser).
 * A grade ending in "?" was read but not confidently enough to skip a human check.
 */
function dats_gradesheet_structured_row(string $line): ?array
{
    $fields = array_map('trim', explode('|', $line));
    // A trailing "?" (added client-side when a low-confidence, letterless word — typically a pen
    // checkmark or stray mark bleeding into the Name cell — was stripped out before it could
    // corrupt the name) marks the name uncertain even if what remains still looks plausible.
    $rawName = trim(preg_replace('/\s+/u', ' ', $fields[2] ?? '') ?? '');
    $uncertainName = str_contains($rawName, '?');
    $name = trim(preg_replace('/^[^A-Za-zÑñ]+|[^A-Za-zÑñ.]+$/u', '', $rawName) ?? '');
    if (strlen(preg_replace('/[^A-Za-zÑñ]/u', '', $name) ?? '') < 3) return null;
    // Undo OCR damage that has one obvious reading (stray symbols, a glued prefix, a period where the
    // comma belongs, run-together given names). A repair is still a guess, so it keeps the record flagged.
    [$name, $repaired] = dats_gradesheet_repair_name($name);
    if ($repaired) $uncertainName = true;
    if (strlen(preg_replace('/[^A-Za-zÑñ]/u', '', $name) ?? '') < 3) return null;

    if (str_contains($name, ',')) {
        [$surname, $given] = array_map('trim', explode(',', $name, 2));
        $name = $given === '' ? $surname : dats_gradesheet_tidy_name($surname, $given);
    } elseif (preg_match('/^((?:[A-ZÑ][A-ZÑ\'\-]+\s+){0,2}[A-ZÑ][A-ZÑ\'\-]+)\s+([A-ZÑ][a-zñ].*)$/u', $name, $parts)) {
        // "SURNAME Given" with the comma lost by OCR.
        $name = dats_gradesheet_tidy_name($parts[1], $parts[2]);
    } elseif (($glued = dats_gradesheet_split_glued_name($name)) !== null) {
        // Both the comma AND the space were lost — "DelaCruzJuan" style — so the FAMILY NAME,
        // FIRST NAME split below is a guess, however clean-looking the result.
        $name = dats_gradesheet_tidy_name($glued[0], $glued[1]);
        $uncertainName = true;
    } else {
        $uncertainName = true;
    }
    if (preg_match('/\d|[^A-Za-zÑñ.,\'\-\s]/u', $name)) $uncertainName = true;

    $gradeToken = (string) ($fields[4] ?? '');
    $uncertainGrade = str_contains($gradeToken, '?');
    $grade = dats_gradesheet_normalize_grade(str_replace('?', '', $gradeToken));
    if ($grade === null && trim(str_replace('?', '', $gradeToken)) !== '') $uncertainGrade = true;
    $course = trim(preg_replace('/\s+/', ' ', $fields[3] ?? '') ?? '');

    return [
        'student_name' => $name,
        'year_course' => $course !== '' ? $course : null,
        'grade' => $grade,
        'remarks' => dats_gradesheet_normalize_remarks(preg_split('/\s+/', (string) ($fields[5] ?? '')) ?: []),
        'uncertain_name' => $uncertainName,
        'uncertain_grade' => $uncertainGrade,
    ];
}

/**
 * Fills derived remarks and decides whether a human needs to check this record. The reasons are
 * kept (review_reasons) so a reviewer sees WHY a row is flagged, and so fixing the name later
 * clears only the name concern. Name trust is judged by the name's shape, not by OCR confidence.
 */
function dats_gradesheet_finalize_record(array $record): array
{
    $grade = $record['grade'] ?? null;
    $remarks = $record['remarks'] ?? null;
    $derived = dats_gradesheet_remarks_from_grade($grade);
    $conflict = $remarks !== null && $derived !== null && $remarks !== $derived;
    if ($remarks === null) $remarks = $derived;

    $nonCreditWithRemarks = $grade === null && $remarks !== null && isset($record['units']) && (float) $record['units'] === 0.0;
    $record['grade'] = $grade;
    $record['remarks'] = $remarks;

    $reasons = [];
    if (($grade === null && !$nonCreditWithRemarks) || !empty($record['uncertain_grade'])) $reasons[] = 'grade';
    if (!empty($record['uncertain_name']) || dats_gradesheet_name_issues((string) ($record['student_name'] ?? '')) !== []) $reasons[] = 'name';
    if ($remarks === null || $conflict) $reasons[] = 'remarks';
    if (empty($record['subject_code']) || empty($record['academic_year']) || empty($record['semester'])) $reasons[] = 'details';
    return dats_gn_set_reasons($record, $reasons);
}

/**
 * Normalizes a name read from a "Name:" labeled line, or the comma'd bare-heading form, into the
 * required FAMILY NAME, FIRST NAME MIDDLE INITIAL order used everywhere else. Returns
 * [name, uncertain]; uncertain is true whenever getting there required a guess (no comma to
 * anchor the surname/given split on), not just whenever the text looks odd.
 */
function dats_gradesheet_normalize_block_name(string $raw): array
{
    $name = trim(preg_replace('/\s+/u', ' ', $raw) ?? $raw);
    if ($name === '') return ['', false];
    if (str_contains($name, ',')) {
        [$surname, $given] = array_map('trim', explode(',', $name, 2));
        return $given === '' ? [$surname, false] : [dats_gradesheet_tidy_name($surname, $given), false];
    }
    if (!str_contains($name, ' ')) {
        $glued = dats_gradesheet_split_glued_name($name);
        return $glued === null ? [$name, true] : [dats_gradesheet_tidy_name($glued[0], $glued[1]), true];
    }
    // No comma, but space-separated: the printed order can't be told apart from the text alone, so
    // this is always a guess. Report-of-Rating style forms print "First [Middle] Last", so the
    // last word is taken as the surname unless it is a particle that must stay with the word
    // before it ("... DE LA CRUZ" keeps "DE LA CRUZ" together, not just "CRUZ").
    $parts = preg_split('/\s+/u', $name) ?: [];
    if (count($parts) < 2) return [$name, true];
    $splitAt = count($parts) - 1;
    while ($splitAt > 1 && in_array(strtoupper($parts[$splitAt - 1]), DATS_GRADESHEET_SURNAME_PARTICLES, true)) $splitAt -= 1;
    $surname = implode(' ', array_slice($parts, $splitAt));
    $given = implode(' ', array_slice($parts, 0, $splitAt));
    return [dats_gradesheet_tidy_name($surname, $given), true];
}

/** Converts the older "one student, many subjects" block layout into flat records. */
function dats_gradesheet_records_from_blocks(array $blocks): array
{
    $records = [];
    $recordIndexes = [];
    foreach ($blocks as $block) {
        $rawName = trim((string) ($block['raw_name'] ?? ''));
        [$name, $uncertainBlockName] = $rawName !== '' ? dats_gradesheet_normalize_block_name($rawName) : ['', false];
        foreach ($block['rows'] ?? [] as $row) {
            $record = dats_gradesheet_finalize_record([
                'student_name' => $name !== '' ? $name : 'Unidentified student',
                'student_number' => $block['raw_student_number'] ?? null,
                'uncertain_name' => $uncertainBlockName,
                'year_course' => null,
                'academic_year' => $row['academic_year'] ?? null,
                'semester' => $row['semester'] ?? null,
                'subject_code' => $row['subject_code'] ?? null,
                'subject_name' => $row['subject_name'] ?? null,
                'source_pages' => $row['source_pages'] ?? [],
                'units' => $row['units'] ?? null,
                'grade' => dats_gradesheet_normalize_grade((string) ($row['grade'] ?? '')) ?? (($row['grade'] ?? '') !== '' ? strtoupper((string) $row['grade']) : null),
                'remarks' => $row['remarks'] ?? null,
            ]);
            $key = strtolower(implode('|', [
                $record['student_name'],
                $record['academic_year'] ?? '',
                $record['semester'] ?? '',
                $record['subject_code'] ?? '',
                $record['subject_name'] ?? '',
            ]));
            if (isset($recordIndexes[$key])) {
                $index = $recordIndexes[$key];
                $records[$index]['source_pages'] = array_values(array_unique(array_merge($records[$index]['source_pages'], $record['source_pages'])));
            } else {
                $recordIndexes[$key] = count($records);
                $records[] = $record;
            }
        }
    }
    return $records;
}

/**
 * Runs both supported layouts over the same OCR text and keeps whichever recovers more
 * student/subject records — a document is one or the other, never both.
 */
function dats_extract_gradesheet_records(string $ocrText): array
{
    $ratingRecords = parse_report_of_rating_text($ocrText);
    $blockRecords = dats_gradesheet_records_from_blocks(parse_batch_gradesheet_text($ocrText));
    $records = count($ratingRecords) >= count($blockRecords) ? $ratingRecords : $blockRecords;
    // Names are reconciled across the whole upload: the same student is read once per subject page.
    return dats_gradesheet_consensus_names($records);
}
