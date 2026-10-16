<?php
declare(strict_types=1);

$library = __DIR__ . '/../../lib/dashboard-analytics.php';
if (!is_file($library)) {
    throw new RuntimeException('Missing dashboard analytics library.');
}
require_once $library;

function dashboard_test_database(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $pdo->exec('CREATE TABLE colleges (id INTEGER PRIMARY KEY, name TEXT, status TEXT)');
    $pdo->exec('CREATE TABLE document_categories (id INTEGER PRIMARY KEY, name TEXT, status TEXT)');
    $pdo->exec('CREATE TABLE students (id INTEGER PRIMARY KEY, college_id INTEGER, first_name TEXT, last_name TEXT, status TEXT, deleted_at TEXT)');
    $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, full_name TEXT)');
    $pdo->exec('CREATE TABLE activity_logs (id INTEGER PRIMARY KEY, user_id INTEGER, action TEXT, entity_type TEXT, entity_id INTEGER, description TEXT, created_at TEXT)');
    $pdo->exec('CREATE TABLE documents (
        id INTEGER PRIMARY KEY,
        student_id INTEGER,
        category_id INTEGER,
        original_filename TEXT,
        status TEXT,
        ocr_status TEXT,
        file_path TEXT,
        file_size_bytes INTEGER,
        uploaded_at TEXT,
        archived_at TEXT
    )');

    return $pdo;
}

$pdo = dashboard_test_database();
$pdo->exec("INSERT INTO colleges VALUES
    (1, 'College One', 'Active'),
    (2, 'Empty College', 'Active'),
    (3, 'Inactive College', 'Inactive')");
$pdo->exec("INSERT INTO document_categories VALUES
    (1, 'Transcript', 'Active'),
    (2, 'Diploma', 'Active'),
    (3, 'Inactive Category', 'Inactive')");
$pdo->exec("INSERT INTO students VALUES
    (1, 1, 'Ada', 'Cruz', 'Active', NULL),
    (2, 1, 'Ben', 'Santos', 'Active', NULL),
    (3, 1, 'Cara', 'Reyes', 'Graduated', NULL),
    (4, 1, 'Dan', 'Lim', 'Inactive', NULL),
    (5, 1, 'Eve', 'Tan', 'Active', '2026-09-01 00:00:00')");
$pdo->exec("INSERT INTO documents VALUES
    (1, 1, 1, 'one.pdf', 'Archived', 'Completed', 'archive/one.pdf', 100, '2026-09-21 08:00:00', '2026-09-21 09:00:00'),
    (2, 1, 1, 'two.pdf', 'Archived', 'Pending', 'archive/two.pdf', 200, '2026-09-21 10:00:00', '2026-09-21 11:00:00'),
    (3, 2, 2, 'three.pdf', 'Archived', 'Completed', 'archive/three.pdf', 300, '2026-09-20 08:00:00', '2026-09-20 09:00:00'),
    (4, 4, 1, 'four.pdf', 'Archived', 'Pending', 'archive/four.pdf', 400, '2026-09-01 08:00:00', '2026-09-01 09:00:00'),
    (5, 5, 1, 'five.pdf', 'Archived', 'Failed', NULL, NULL, '2026-08-15 08:00:00', '2026-08-15 09:00:00'),
    (6, 3, 2, 'rejected.pdf', 'Rejected', 'Completed', NULL, 600, '2026-09-21 08:00:00', NULL)");

$now = new DateTimeImmutable('2026-09-21 12:00:00');
$metrics = dats_get_dashboard_analytics($pdo, $now);

assert($metrics['archived_documents'] === 5);
assert($metrics['eligible_students'] === 3);
assert($metrics['students_with_archived_documents'] === 2);
assert($metrics['student_archive_coverage_rate'] === 66.7);
assert($metrics['archived_this_week'] === 2);
assert($metrics['archived_last_week'] === 1);
assert($metrics['archived_week_change'] === 1);
assert($metrics['ocr_completed_archived'] === 2);
assert($metrics['ocr_pending_archived'] === 2);
assert($metrics['ocr_failed_archived'] === 1);
assert($metrics['search_ready_rate'] === 40.0);
assert($metrics['archived_storage_bytes'] === 1000);
assert($metrics['missing_file_path_count'] === 1);
assert($metrics['missing_file_size_count'] === 1);
assert(count($metrics['archive_by_week']) === 8);
assert(array_sum(array_column($metrics['archive_by_week'], 'document_count')) === 5);
assert($metrics['coverage_by_college'][0]['college_name'] === 'College One');
assert($metrics['coverage_by_college'][0]['eligible_students'] === 3);
assert($metrics['coverage_by_college'][0]['covered_students'] === 2);
assert($metrics['coverage_by_college'][0]['coverage_rate'] === 66.7);
assert($metrics['coverage_by_college'][1]['coverage_label'] === 'No registered students');
assert($metrics['archive_by_category'][0]['category_name'] === 'Transcript');
assert($metrics['archive_by_category'][0]['document_count'] === 4);
assert($metrics['archive_by_category'][1]['document_count'] === 1);

$empty = dashboard_test_database();
$empty->exec("INSERT INTO colleges VALUES (1, 'No Students College', 'Active')");
$empty->exec("INSERT INTO document_categories VALUES (1, 'Transcript', 'Active')");
$zero = dats_get_dashboard_analytics($empty, $now);
foreach ([
    'archived_documents', 'eligible_students', 'students_with_archived_documents',
    'archived_this_week', 'archived_last_week', 'archived_week_change',
    'ocr_completed_archived', 'ocr_pending_archived', 'ocr_failed_archived',
    'archived_storage_bytes', 'missing_file_path_count', 'missing_file_size_count',
] as $key) {
    assert($zero[$key] === 0, $key);
}
assert($zero['student_archive_coverage_rate'] === 0.0);
assert($zero['search_ready_rate'] === 0.0);
assert(count($zero['archive_by_week']) === 8);
assert($zero['coverage_by_college'][0]['coverage_label'] === 'No registered students');
assert($zero['archive_by_category'][0]['document_count'] === 0);

$pdo->exec("INSERT INTO users VALUES (1, 'Archive Admin')");
$pdo->exec("INSERT INTO activity_logs VALUES
    (1, 1, 'ARCHIVED', 'document', 1, 'Archived one.pdf', '2026-09-21 09:05:00'),
    (2, 1, 'ARCHIVED', 'document', 999, 'Archived removed.pdf', '2026-09-21 09:10:00')");
$activity = dats_get_dashboard_activity($pdo, 12);
assert(count($activity) === 2);
assert($activity[0]['description'] === 'Archived removed.pdf');
assert($activity[0]['document_name'] === null);
assert($activity[0]['student_name'] === null);
assert($activity[1]['document_name'] === 'one.pdf');
assert($activity[1]['category_name'] === 'Transcript');
assert($activity[1]['student_name'] === 'Ada Cruz');
assert($activity[1]['user_name'] === 'Archive Admin');

echo "dashboard_analytics_test: PASS\n";
