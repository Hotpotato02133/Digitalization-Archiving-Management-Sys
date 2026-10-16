<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/scan-intake.php';
require_once __DIR__ . '/../../lib/document-duplicate.php';

function expect_exception(callable $operation, string $messagePart): void
{
    try {
        $operation();
    } catch (InvalidArgumentException|RuntimeException $error) {
        assert(str_contains(strtolower($error->getMessage()), strtolower($messagePart)));
        return;
    }
    throw new RuntimeException("Expected exception containing: {$messagePart}");
}

$valid = dats_validate_new_student_payload([
    'student_number' => ' 2026-0042 ',
    'first_name' => ' Ada ',
    'last_name' => ' Cruz ',
    'academic_year' => '2026',
    'nationality' => ' Filipino ',
]);
assert($valid['student_number'] === '2026-0042');
assert($valid['first_name'] === 'Ada');
assert($valid['nationality'] === 'Filipino');
assert($valid['gender'] === 'Undisclosed');
assert($valid['address'] === null);
assert(dats_normalize_intake_year('2100') === '2100');
assert(dats_normalize_intake_year('1999') === '1999');
assert(dats_normalize_intake_year('2101') === '2101');
expect_exception(fn () => dats_normalize_intake_year('1900'), 'supported range');
expect_exception(fn () => dats_normalize_intake_year('2156'), 'supported range');
foreach (dats_intake_year_levels() as $yearLevel) {
    $yearLevelPayload = dats_validate_new_student_payload([
        'student_number' => '1', 'first_name' => 'A', 'last_name' => 'C',
        'academic_year' => '2026', 'year_level' => $yearLevel,
    ]);
    assert($yearLevelPayload['year_level'] === $yearLevel);
}

expect_exception(fn () => dats_validate_new_student_payload([
    'student_number' => '', 'first_name' => 'A', 'last_name' => 'C', 'academic_year' => '2026',
]), 'student number');
expect_exception(fn () => dats_validate_new_student_payload([
    'student_number' => '1', 'first_name' => '', 'last_name' => 'C', 'academic_year' => '2026',
]), 'first name');
expect_exception(fn () => dats_validate_new_student_payload([
    'student_number' => '1', 'first_name' => 'A', 'last_name' => 'C', 'academic_year' => '2026',
    'nationality' => str_repeat('x', 81),
]), 'nationality');

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec("CREATE TABLE colleges (id INTEGER PRIMARY KEY, name TEXT, status TEXT DEFAULT 'Active')");
$pdo->exec("CREATE TABLE courses (id INTEGER PRIMARY KEY, college_id INTEGER, name TEXT, status TEXT DEFAULT 'Active')");
$pdo->exec("CREATE TABLE course_majors (id INTEGER PRIMARY KEY, course_id INTEGER, name TEXT, status TEXT DEFAULT 'Active')");
$pdo->exec('CREATE TABLE students (id INTEGER PRIMARY KEY AUTOINCREMENT, student_number TEXT UNIQUE, first_name TEXT, middle_initial TEXT, last_name TEXT, gender TEXT, birthdate TEXT, address TEXT, nationality TEXT, civil_status TEXT, contact_number TEXT, college_id INTEGER, course_id INTEGER, major_id INTEGER, academic_year TEXT, year_level TEXT, status TEXT DEFAULT "Active", deleted_at TEXT)');
$pdo->exec("INSERT INTO colleges VALUES (1, 'College One', 'Active'), (2, 'College Two', 'Active')");
$pdo->exec("INSERT INTO courses VALUES (10, 1, 'Course One', 'Active'), (20, 2, 'Course Two', 'Active')");
$pdo->exec("INSERT INTO course_majors VALUES (100, 10, 'Major One', 'Active'), (200, 20, 'Major Two', 'Active')");
$pdo->exec("INSERT INTO students (student_number, first_name, last_name, academic_year, status) VALUES ('2026-0001', 'Existing', 'Student', '2026', 'Active')");

$student = dats_find_existing_student_for_intake($pdo, 1);
assert($student['academic_year'] === '2026');
expect_exception(fn () => dats_find_existing_student_for_intake($pdo, 999), 'not found');

$payload = dats_validate_new_student_payload([
    'student_number' => '2026-0099', 'first_name' => 'New', 'last_name' => 'Student',
    'academic_year' => '2026', 'college_id' => 1, 'course_id' => 10, 'major_id' => 100,
]);
$payload = dats_validate_student_hierarchy($pdo, $payload);
$newId = dats_insert_intake_student($pdo, $payload);
assert($newId === 2);
assert($pdo->query('SELECT nationality FROM students WHERE id = 2')->fetchColumn() === false || $pdo->query('SELECT nationality FROM students WHERE id = 2')->fetchColumn() === null);

$typedPayload = dats_validate_new_student_payload([
    'student_number' => '2026-0103', 'first_name' => 'Typed', 'last_name' => 'Student',
    'academic_year' => '2026', 'college_name' => 'New College', 'course_name' => 'New Course',
    'major_name' => 'New Major',
]);
$typedPayload = dats_resolve_intake_academic_hierarchy($pdo, $typedPayload);
$typedPayload = dats_validate_student_hierarchy($pdo, $typedPayload);
$typedId = dats_insert_intake_student($pdo, $typedPayload);
assert($pdo->query("SELECT c.name FROM students s JOIN colleges c ON c.id = s.college_id WHERE s.id = {$typedId}")->fetchColumn() === 'New College');
assert($pdo->query("SELECT co.name FROM students s JOIN courses co ON co.id = s.course_id WHERE s.id = {$typedId}")->fetchColumn() === 'New Course');
assert($pdo->query("SELECT cm.name FROM students s JOIN course_majors cm ON cm.id = s.major_id WHERE s.id = {$typedId}")->fetchColumn() === 'New Major');

expect_exception(fn () => dats_insert_intake_student($pdo, dats_validate_new_student_payload([
    'student_number' => '2026-0001', 'first_name' => 'Duplicate', 'last_name' => 'Number', 'academic_year' => '2026',
])), 'already exists');
expect_exception(fn () => dats_validate_student_hierarchy($pdo, dats_validate_new_student_payload([
    'student_number' => '2026-0100', 'first_name' => 'Wrong', 'last_name' => 'Course',
    'academic_year' => '2026', 'college_id' => 1, 'course_id' => 20,
])), 'course');
expect_exception(fn () => dats_validate_student_hierarchy($pdo, dats_validate_new_student_payload([
    'student_number' => '2026-0101', 'first_name' => 'Wrong', 'last_name' => 'Major',
    'academic_year' => '2026', 'college_id' => 1, 'course_id' => 10, 'major_id' => 200,
])), 'major');

$pdo->exec('CREATE TABLE tx_probe (value TEXT)');
$pdo->exec('CREATE TABLE documents (id INTEGER PRIMARY KEY AUTOINCREMENT, student_id INTEGER, category_id INTEGER, original_filename TEXT, file_path TEXT, content_hash TEXT, ocr_text TEXT, status TEXT, deleted_at TEXT)');
$documentText = 'Official Transcript of Records Student Number 2026-0001 Semester 1 Grades';
$fingerprint = dats_document_content_fingerprint($documentText);
$pdo->prepare("INSERT INTO documents (student_id, category_id, original_filename, content_hash, ocr_text, status) VALUES (1, 1, 'old-name.pdf', :content_hash, :ocr_text, 'Archived')")->execute([
    ':content_hash' => $fingerprint['hash'], ':ocr_text' => $documentText,
]);
assert(dats_normalize_document_content("  OFFICIAL  Transcript\n of Records Student Number 2026-0001 Semester 1 Grades ") === dats_normalize_document_content($documentText));
$duplicateDocument = dats_find_duplicate_document($pdo, 1, 'OFFICIAL Transcript of Records Student Number 2026-0001 Semester 1 Grades', $fingerprint['hash']);
assert($duplicateDocument['original_filename'] === 'old-name.pdf');
assert(dats_find_duplicate_document($pdo, 2, $documentText, $fingerprint['hash']) === null);
dats_run_transaction($pdo, function (PDO $pdo): void {
    $pdo->exec("INSERT INTO tx_probe VALUES ('committed')");
});
assert((int) $pdo->query('SELECT COUNT(*) FROM tx_probe')->fetchColumn() === 1);
expect_exception(function () use ($pdo): void {
    dats_run_transaction($pdo, function (PDO $pdo): void {
        $pdo->exec("INSERT INTO tx_probe VALUES ('rolled-back')");
        throw new RuntimeException('forced failure');
    });
}, 'forced failure');
assert((int) $pdo->query('SELECT COUNT(*) FROM tx_probe')->fetchColumn() === 1);

$temporaryUpload = tempnam(sys_get_temp_dir(), 'dats-intake-');
assert(is_string($temporaryUpload) && is_file($temporaryUpload));
dats_remove_new_upload($temporaryUpload);
assert(!is_file($temporaryUpload));
dats_remove_new_upload(null);

$existingResolution = dats_resolve_intake_student($pdo, [
    'student_mode' => 'existing', 'student_id' => 1, 'academic_year' => '2099',
]);
assert($existingResolution === ['student_id' => 1, 'academic_year' => '2026', 'created' => false]);

$newResolution = dats_run_transaction($pdo, fn (PDO $pdo): array => dats_resolve_intake_student($pdo, [
    'student_mode' => 'new', 'student_number' => '2026-0102',
    'first_name' => 'Atomic', 'last_name' => 'Student', 'academic_year' => '2026',
]));
assert($newResolution['created'] === true);
assert($newResolution['academic_year'] === '2026');

$legacyResolution = dats_resolve_intake_student($pdo, ['student_id' => 1, 'academic_year' => '2026']);
assert($legacyResolution['student_id'] === 1 && $legacyResolution['created'] === false);

expect_exception(fn () => dats_require_document_upload(
    ['student_mode' => 'new'], ['error' => UPLOAD_ERR_PARTIAL]
), 'upload failed');
expect_exception(fn () => dats_require_document_upload(
    ['student_mode' => 'existing'], null
), 'upload a document');
dats_require_document_upload(
    ['student_mode' => 'existing'], ['error' => UPLOAD_ERR_OK]
);
dats_require_document_upload(
    ['student_id' => 1, 'file_path' => 'uploads/legacy.pdf'], null
);

echo "scan_intake_test: PASS\n";
