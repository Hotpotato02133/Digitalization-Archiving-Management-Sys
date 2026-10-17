<?php
declare(strict_types=1);

// Top-level `const` (unlike `function`) is not hoisted in PHP — it must execute before use,
// so these live here, ahead of the dispatcher below, rather than near the functions that use them.
const DATS_ALLOWED_DOCUMENT_MIME_TYPES = ['image/jpeg', 'image/png', 'application/pdf'];
const DATS_MAX_DOCUMENT_BYTES = 25 * 1024 * 1024;
const DATS_OCR_MAX_PDF_PAGES = 20;
const DATS_OCR_PAGE_TIMEOUT_SECONDS = 25;
const DATS_OCR_RASTER_TIMEOUT_SECONDS = 90;

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/scan-intake.php';
require_once __DIR__ . '/lib/archive-workflow.php';
require_once __DIR__ . '/lib/dashboard-analytics.php';
require_once __DIR__ . '/lib/document-duplicate.php';
require_once __DIR__ . '/lib/gradesheet-parser.php';
require_once __DIR__ . '/lib/student-import.php';
require_once __DIR__ . '/lib/ai-service.php';

if (!dats_current_user()) {
    dats_json(['error' => 'Authentication required.'], 401);
}

$pdo = dats_db();
if (!$pdo) {
    dats_json(['error' => 'Database unavailable. Import dats.sql and start MariaDB.'], 503);
}

$resource = strtolower((string) ($_GET['resource'] ?? ''));
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$input = $_POST ?: (json_decode(file_get_contents('php://input'), true) ?: []);
$currentUser = dats_current_user();
if (!dats_can_access_api($currentUser, $method, $resource)) {
    dats_json(['error' => 'Forbidden.'], 403);
}

ensure_document_ocr_columns($pdo);
ensure_document_content_hash_column($pdo);
ensure_document_deleted_column($pdo);
ensure_document_search_index($pdo);
ensure_major_table($pdo);
ensure_student_major_column($pdo);
ensure_student_nationality_column($pdo);
ensure_user_id_number_column($pdo);
ensure_student_deleted_column($pdo);
ensure_gradesheet_tables($pdo);
ensure_gradesheet_category($pdo);
ensure_gradesheet_batches_table($pdo);

try {
    migrate_pending_documents_to_archive($pdo, (int) ($currentUser['id'] ?? 0));
    if ($method === 'GET') {
        if ($resource === 'document-view') {
            handle_document_view($pdo, $currentUser, (int) ($_GET['document_id'] ?? 0), (int) ($_GET['student_id'] ?? 0));
        }
        if ($resource === 'document-download') {
            handle_document_download($pdo, $currentUser, (int) ($_GET['document_id'] ?? 0), (int) ($_GET['student_id'] ?? 0));
        }
        if ($resource === 'gradesheet-batch-file') {
            handle_gradesheet_batch_file($pdo, (int) ($_GET['batch_id'] ?? 0));
        }
        if ($resource === 'gradesheet-entry-file') {
            handle_gradesheet_entry_file(
                $pdo,
                (int) ($_GET['entry_id'] ?? 0),
                (int) ($_GET['student_id'] ?? 0),
                trim((string) ($_GET['student_name'] ?? '')),
                trim((string) ($_GET['student_key'] ?? '')),
                (int) ($_GET['batch_id'] ?? 0),
                (int) ($_GET['document_id'] ?? 0)
            );
        }
        $result = match ($resource) {
            'analytics' => get_analytics($pdo),
            'annual-report' => get_annual_report($pdo),
            'students' => $pdo->query("SELECT s.*, c.name AS college_name, co.name AS course_name, cm.name AS major_name, COUNT(CASE WHEN d.status = 'Archived' AND d.deleted_at IS NULL THEN 1 END) AS document_count FROM students s LEFT JOIN colleges c ON c.id = s.college_id LEFT JOIN courses co ON co.id = s.course_id LEFT JOIN course_majors cm ON cm.id = s.major_id LEFT JOIN documents d ON d.student_id = s.id WHERE s.deleted_at IS NULL GROUP BY s.id ORDER BY s.last_name, s.first_name")->fetchAll(),
            'deleted-students' => get_deleted_students($pdo),
            'deleted-documents' => get_deleted_documents($pdo),
            'categories' => $pdo->query("SELECT c.*, COUNT(CASE WHEN d.status = 'Archived' AND d.deleted_at IS NULL THEN 1 END) AS document_count FROM document_categories c LEFT JOIN documents d ON d.category_id = c.id WHERE c.status = 'Active' GROUP BY c.id ORDER BY c.name")->fetchAll(),
            'documents' => isset($_GET['student_id']) ? get_student_documents($pdo, (int) ($_GET['student_id'] ?? 0)) : get_document_search_results($pdo, trim((string) ($_GET['search'] ?? ''))),
            'gradesheets' => get_student_gradesheet($pdo, (int) ($_GET['student_id'] ?? 0), trim((string) ($_GET['student_name'] ?? '')), trim((string) ($_GET['student_key'] ?? ''))),
            'gradesheet-entry-source' => get_gradesheet_entry_source(
                $pdo,
                (int) ($_GET['entry_id'] ?? 0),
                (int) ($_GET['student_id'] ?? 0),
                trim((string) ($_GET['student_name'] ?? '')),
                trim((string) ($_GET['student_key'] ?? '')),
                (int) ($_GET['batch_id'] ?? 0),
                (int) ($_GET['document_id'] ?? 0)
            ),
            'gradesheet-students' => search_gradesheet_students($pdo, trim((string) ($_GET['search'] ?? ''))),
            'gradesheet-batches' => get_gradesheet_batches($pdo),
            'gradesheet-batch-entries' => get_gradesheet_batch_entries($pdo, (int) ($_GET['batch_id'] ?? 0)),
            'enrollment-years' => get_enrollment_years($pdo),
            'uploads' => get_uploads($pdo, (int) ($_GET['user_id'] ?? 0), $currentUser),
            'activity' => dats_get_dashboard_activity($pdo),
            'notifications' => get_notifications($pdo, (int) $currentUser['id']),
            'profile' => $currentUser,
            'requests' => $pdo->query("SELECT d.id, d.original_filename, d.status, COALESCE(d.archived_at, d.uploaded_at) AS uploaded_at, s.student_number, CONCAT(s.first_name, ' ', s.last_name) AS student_name, c.name AS category_name, u.full_name AS uploaded_by_name FROM documents d JOIN students s ON s.id = d.student_id JOIN document_categories c ON c.id = d.category_id JOIN users u ON u.id = d.uploaded_by WHERE d.status = 'Archived' AND d.deleted_at IS NULL ORDER BY COALESCE(d.archived_at, d.uploaded_at) DESC, d.id DESC LIMIT 3")->fetchAll(),
            'colleges' => get_colleges($pdo),
            'users' => $pdo->query('SELECT id, full_name, id_number, email, role, status, last_login_at, created_at FROM users ORDER BY full_name')->fetchAll(),
            default => null,
        };
        if ($result === null) {
            dats_json(['error' => 'Unknown resource.'], 404);
        }
        dats_json(['data' => $result]);
    }

    if ($method === 'POST') {
        if ($resource === 'document-download-bulk') {
            handle_document_download_batch($pdo, $currentUser, $input);
        }
        $result = match ($resource) {
            'users' => handle_user_write($pdo, $input),
            'categories' => ($input['action'] ?? '') === 'update' ? update_category($pdo, $input) : create_category($pdo, $input),
            'colleges' => ($input['action'] ?? '') === 'delete' ? delete_college($pdo, $input) : (($input['action'] ?? '') === 'update' ? update_college($pdo, $input, $_FILES['logo'] ?? null) : create_college($pdo, $input, $_FILES['logo'] ?? null)),
            'courses' => ($input['action'] ?? '') === 'delete' ? delete_course($pdo, $input) : (($input['action'] ?? '') === 'update' ? update_course($pdo, $input) : create_course($pdo, $input)),
            'majors' => ($input['action'] ?? '') === 'delete' ? delete_major($pdo, $input) : create_major($pdo, $input),
            'students' => match ($input['action'] ?? '') {
                'delete' => delete_student($pdo, $input, $currentUser),
                'restore' => restore_student($pdo, $input),
                'purge' => purge_student($pdo, $input, $currentUser),
                'update' => update_student($pdo, $input),
                'import-preview' => preview_student_import($pdo, $_FILES['file'] ?? null, $input),
                'import' => import_students($pdo, $currentUser, $_FILES['file'] ?? null, $input),
                default => create_student($pdo, $input),
            },
            'document-ocr' => handle_document_ocr($pdo, $_FILES['file'] ?? null, $input),
            'documents' => match ($input['action'] ?? '') {
                'delete' => delete_document($pdo, $input),
                'restore' => restore_document($pdo, $input),
                'purge' => purge_document($pdo, $input),
                default => create_document($pdo, $input, $_FILES['file'] ?? null),
            },
            'gradesheets' => match ($input['action'] ?? '') {
                'delete-entry' => delete_gradesheet_entry($pdo, $input),
                'index-pages' => index_gradesheet_entry_pages($pdo, $input),
                'add-student' => add_gradesheet_student($pdo, $input),
                'associate-student' => associate_gradesheet_student($pdo, $input),
                default => update_gradesheet_entry($pdo, $input),
            },
            'gradesheet-batches' => match ($input['action'] ?? '') {
                'rescan' => rescan_gradesheet_batch($pdo, $input),
                'delete' => delete_gradesheet_batch($pdo, $input),
                'save' => save_gradesheet_batch($pdo, $input),
                default => upload_gradesheet_batch($pdo, $currentUser, $input, $_FILES['file'] ?? null),
            },
            'enrollment-years' => ($input['action'] ?? '') === 'delete' ? delete_enrollment_year($pdo, $input) : create_enrollment_year($pdo, $input),
            'notifications' => mark_notifications_read($pdo, (int) $currentUser['id'], $input),
            'profile' => update_profile($pdo, $currentUser, $input),
            default => null,
        };
        if ($result === null) {
            dats_json(['error' => 'Unknown resource.'], 404);
        }
        dats_json(['data' => $result], 201);
    }

    dats_json(['error' => 'Method not allowed.'], 405);
} catch (DatsDuplicateDocumentException $error) {
    dats_json(['error' => $error->getMessage(), 'duplicate' => $error->document], 409);
} catch (Throwable $error) {
    dats_json(['error' => $error->getMessage()], 422);
}

function get_analytics(PDO $pdo): array
{
    $analytics = dats_get_dashboard_analytics($pdo);
    $analytics['total_students'] = $analytics['eligible_students'];
    $analytics['total_documents'] = $analytics['archived_documents'];
    $analytics['completed_documents'] = $analytics['archived_documents'];
    $analytics['processing_documents'] = 0;
    $analytics['documents_needing_review'] = 0;
    $analytics['queue_count'] = 0;
    $analytics['review_count'] = 0;
    $analytics['processed_this_week'] = $analytics['archived_this_week'];
    $analytics['processed_last_week'] = $analytics['archived_last_week'];
    $analytics['total_colleges'] = (int) $pdo->query("SELECT COUNT(*) FROM colleges WHERE status = 'Active'")->fetchColumn();
    $analytics['total_courses'] = (int) $pdo->query("SELECT COUNT(*) FROM courses WHERE status = 'Active'")->fetchColumn();
    $analytics['new_courses_this_year'] = (int) $pdo->query("SELECT COUNT(*) FROM courses WHERE status = 'Active' AND YEAR(created_at) = YEAR(CURDATE())")->fetchColumn();
    $distribution = $pdo->query("SELECT c.name, COUNT(CASE WHEN d.status = 'Archived' AND d.deleted_at IS NULL THEN 1 END) AS document_count FROM colleges c LEFT JOIN students s ON s.college_id = c.id AND s.deleted_at IS NULL LEFT JOIN documents d ON d.student_id = s.id WHERE c.status = 'Active' GROUP BY c.id, c.name ORDER BY document_count DESC, c.name")->fetchAll();
    $analytics['archive_by_college'] = $distribution;
    return $analytics;
}

function get_annual_report(PDO $pdo): array
{
    $summary = $pdo->query('SELECT COUNT(*) AS total_documents, SUM(status = \'Archived\') AS completed_documents, SUM(status = \'Processing\') AS processing_documents, SUM(status = \'Needs review\') AS review_documents, SUM(status = \'Rejected\') AS rejected_documents FROM documents WHERE status <> \'Rejected\'')->fetch() ?: [];
    $years = $pdo->query("SELECT COALESCE(NULLIF(academic_year, ''), YEAR(uploaded_at)) AS academic_year, COUNT(*) AS document_count FROM documents WHERE status = 'Archived' GROUP BY COALESCE(NULLIF(academic_year, ''), YEAR(uploaded_at)) ORDER BY academic_year DESC")->fetchAll();
    $categories = $pdo->query('SELECT COALESCE(c.name, \'Uncategorized\') AS category_name, COUNT(CASE WHEN d.status = \'Archived\' THEN 1 END) AS document_count FROM documents d LEFT JOIN document_categories c ON c.id = d.category_id GROUP BY c.id, c.name ORDER BY document_count DESC, category_name')->fetchAll();
    $colleges = $pdo->query("SELECT c.name AS college_name, COUNT(CASE WHEN d.status = 'Archived' THEN 1 END) AS document_count FROM colleges c LEFT JOIN students s ON s.college_id = c.id AND s.deleted_at IS NULL LEFT JOIN documents d ON d.student_id = s.id WHERE c.status = 'Active' GROUP BY c.id, c.name ORDER BY document_count DESC, college_name")->fetchAll();
    return ['summary' => $summary, 'by_year' => $years, 'by_category' => $categories, 'by_college' => $colleges];
}

function update_profile(PDO $pdo, array $currentUser, array $input): array
{
    $fullName = trim((string) ($input['full_name'] ?? ''));
    $email = strtolower(trim((string) ($input['email'] ?? '')));
    $password = (string) ($input['password'] ?? '');
    if ($fullName === '' || mb_strlen($fullName) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
        throw new InvalidArgumentException('Enter a valid name and work email.');
    }
    $duplicate = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1');
    $duplicate->execute([':email' => $email, ':id' => (int) $currentUser['id']]);
    if ($duplicate->fetch()) {
        throw new InvalidArgumentException('That email address is already in use.');
    }
    if ($password !== '' && mb_strlen($password) < 8) {
        throw new InvalidArgumentException('A new password must contain at least 8 characters.');
    }
    $fields = 'full_name = :full_name, email = :email';
    $parameters = [':full_name' => $fullName, ':email' => $email, ':id' => (int) $currentUser['id']];
    if ($password !== '') {
        $fields .= ', password_hash = :password_hash';
        $parameters[':password_hash'] = password_hash($password, PASSWORD_DEFAULT);
    }
    $statement = $pdo->prepare("UPDATE users SET $fields WHERE id = :id");
    $statement->execute($parameters);
    $_SESSION['full_name'] = $fullName;
    return [
        'id' => (int) $currentUser['id'],
        'full_name' => $fullName,
        'email' => $email,
        'status' => $currentUser['status'],
    ];
}

function get_notifications(PDO $pdo, int $userId): array
{
    $statement = $pdo->prepare('SELECT id, title, message, resource, entity_id, is_read, created_at FROM notifications WHERE recipient_id = :recipient_id ORDER BY created_at DESC LIMIT 20');
    $statement->execute([':recipient_id' => $userId]);
    return $statement->fetchAll();
}

function mark_notifications_read(PDO $pdo, int $userId, array $input): array
{
    $statement = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE recipient_id = :recipient_id AND is_read = 0');
    $statement->execute([':recipient_id' => $userId]);
    return ['updated' => $statement->rowCount()];
}

// Broadcasts a system event (new admin, new upload, file deletion) to every
// admin account other than whoever triggered it, since there's a single
// undifferentiated account type and no narrower audience to target.
function notify_all_users(PDO $pdo, int $actorId, string $title, string $message, string $resource, int $entityId): void
{
    $recipients = $pdo->prepare('SELECT id FROM users WHERE id <> :actor_id');
    $recipients->execute([':actor_id' => $actorId]);
    $recipientIds = $recipients->fetchAll(PDO::FETCH_COLUMN);
    if (!$recipientIds) {
        return;
    }

    $insert = $pdo->prepare('INSERT INTO notifications (recipient_id, actor_id, title, message, resource, entity_id) VALUES (:recipient_id, :actor_id, :title, :message, :resource, :entity_id)');
    foreach ($recipientIds as $recipientId) {
        $insert->execute([
            ':recipient_id' => $recipientId,
            ':actor_id' => $actorId ?: null,
            ':title' => $title,
            ':message' => $message,
            ':resource' => $resource,
            ':entity_id' => $entityId,
        ]);
    }
}

function get_uploads(PDO $pdo, int $userId, ?array $currentUser = null): array
{
    $sql = 'SELECT d.id AS document_id, d.original_filename, d.mime_type, d.file_size_bytes, d.student_id, CONCAT(s.first_name, " ", s.last_name) AS student_name, s.student_number, c.code AS category_code, c.name AS category_name, u.id AS uploaded_by, u.full_name AS uploaded_by_name, d.status, d.upload_source, d.ocr_status, d.ocr_text, d.ocr_error, d.ocr_processed_at, d.ai_summary, d.ai_detected_category, d.uploaded_at, d.updated_at FROM documents d JOIN students s ON s.id = d.student_id JOIN document_categories c ON c.id = d.category_id JOIN users u ON u.id = d.uploaded_by';
    if ($userId > 0) {
        $sql .= ' WHERE d.uploaded_by = :user_id';
    }
    $sql .= ' ORDER BY d.uploaded_at DESC';
    $statement = $pdo->prepare($sql);
    if ($userId > 0) {
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
    }
    $statement->execute();
    return $statement->fetchAll();
}

function ensure_document_ocr_columns(PDO $pdo): void
{
    $columns = [
        'ocr_status' => "ALTER TABLE documents ADD COLUMN IF NOT EXISTS ocr_status ENUM('Pending','Processing','Completed','Failed') NOT NULL DEFAULT 'Pending' AFTER status",
        'ocr_text' => "ALTER TABLE documents ADD COLUMN IF NOT EXISTS ocr_text LONGTEXT NULL AFTER ocr_status",
        'ocr_error' => "ALTER TABLE documents ADD COLUMN IF NOT EXISTS ocr_error TEXT NULL AFTER ocr_text",
        'ocr_processed_at' => "ALTER TABLE documents ADD COLUMN IF NOT EXISTS ocr_processed_at DATETIME NULL AFTER ocr_error",
        'ai_summary' => "ALTER TABLE documents ADD COLUMN IF NOT EXISTS ai_summary TEXT NULL AFTER ocr_processed_at",
        'ai_detected_category' => "ALTER TABLE documents ADD COLUMN IF NOT EXISTS ai_detected_category VARCHAR(150) NULL AFTER ai_summary",
    ];

    foreach ($columns as $statement) {
        $pdo->exec($statement);
    }
}

function ensure_document_content_hash_column(PDO $pdo): void
{
    $pdo->exec('ALTER TABLE documents ADD COLUMN IF NOT EXISTS content_hash CHAR(64) NULL AFTER ocr_text');
}

function ensure_document_deleted_column(PDO $pdo): void
{
    $pdo->exec('ALTER TABLE documents ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL DEFAULT NULL AFTER status');
}

function ensure_document_search_index(PDO $pdo): void
{
    // Native (non-emulated) FULLTEXT-backed search over OCR text, so extracted text is
    // genuinely and efficiently searchable at real registrar volumes, not just via LIKE scans.
    $pdo->exec('ALTER TABLE documents ADD FULLTEXT INDEX IF NOT EXISTS ft_documents_ocr_text (original_filename, ocr_text)');
}

function get_document_search_results(PDO $pdo, string $search): array
{
    $searchTerm = trim($search);
    if ($searchTerm === '') {
        $statement = $pdo->query('SELECT d.*, s.student_number, CONCAT(s.first_name, " ", s.last_name) AS student_name, c.name AS category_name FROM documents d LEFT JOIN students s ON s.id = d.student_id LEFT JOIN document_categories c ON c.id = d.category_id WHERE d.deleted_at IS NULL ORDER BY d.uploaded_at DESC LIMIT 200');
        return $statement->fetchAll();
    }

    // NOTE: PDO_MySQL with EMULATE_PREPARES=false (real server-side prepared statements)
    // rejects a named placeholder used more than once per query ("Invalid parameter number"),
    // so every occurrence below gets its own placeholder even though the bound value repeats.
    $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $searchTerm) . '%';
    $statement = $pdo->prepare('SELECT d.*, s.student_number, CONCAT(s.first_name, " ", s.last_name) AS student_name, c.name AS category_name, MATCH(d.original_filename, d.ocr_text) AGAINST(:search_rank IN NATURAL LANGUAGE MODE) AS relevance FROM documents d LEFT JOIN students s ON s.id = d.student_id LEFT JOIN document_categories c ON c.id = d.category_id WHERE d.deleted_at IS NULL AND (MATCH(d.original_filename, d.ocr_text) AGAINST(:search_match IN NATURAL LANGUAGE MODE) OR d.original_filename LIKE :search1 OR d.ocr_text LIKE :search2 OR s.student_number LIKE :search3 OR CONCAT(s.first_name, " ", s.last_name) LIKE :search4 OR c.name LIKE :search5) ORDER BY relevance DESC, d.uploaded_at DESC LIMIT 200');
    $statement->execute([
        ':search_rank' => $searchTerm,
        ':search_match' => $searchTerm,
        ':search1' => $like,
        ':search2' => $like,
        ':search3' => $like,
        ':search4' => $like,
        ':search5' => $like,
    ]);
    return $statement->fetchAll();
}

function get_student_documents(PDO $pdo, int $studentId): array
{
    if ($studentId < 1) {
        throw new InvalidArgumentException('Student ID is required.');
    }
    $statement = $pdo->prepare("SELECT d.id, d.student_id, d.category_id, d.original_filename, d.file_path, d.mime_type, d.file_size_bytes, d.status, d.ocr_status, d.ocr_text, d.ocr_error, d.ocr_processed_at, d.ai_summary, d.ai_detected_category, d.uploaded_at, d.academic_year, c.code AS category_code, c.name AS category_name FROM documents d LEFT JOIN document_categories c ON c.id = d.category_id WHERE d.student_id = :student_id AND d.status = 'Archived' AND d.deleted_at IS NULL AND d.file_path IS NOT NULL ORDER BY c.name ASC, d.original_filename ASC, d.uploaded_at DESC");
    $statement->execute([':student_id' => $studentId]);
    return $statement->fetchAll();
}

/**
 * Identity of a student across spellings of the same printed name: letters only, uppercased.
 * "AMPER HAZZELJOYC." and "AMPER, Hazzel Joy C." are the same student read two ways.
 */
function dats_gradesheet_name_key(?string $name): string
{
    $upper = function_exists('mb_strtoupper') ? mb_strtoupper((string) $name, 'UTF-8') : strtoupper((string) $name);
    return preg_replace('/[^A-ZÑ]/u', '', $upper) ?? '';
}

/** "amper, hazzel joy c" -> "AMPER, Hazzel Joy C.": family name in capitals, given names, middle initial. */
function dats_format_student_name(?string $name): string
{
    $name = trim(preg_replace('/\s+/u', ' ', (string) $name) ?? '');
    if ($name === '') return '';
    if (str_contains($name, ',')) {
        [$surname, $given] = array_map('trim', explode(',', $name, 2));
    } else {
        $parts = explode(' ', $name, 2);
        $surname = $parts[0];
        $given = $parts[1] ?? '';
    }
    $upper = static fn (string $value): string => function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);
    $title = static fn (string $value): string => function_exists('mb_convert_case') ? mb_convert_case($value, MB_CASE_TITLE, 'UTF-8') : ucwords(strtolower($value), ' -');
    $words = [];
    foreach (explode(' ', str_replace(',', ' ', $given)) as $word) {
        $letters = trim($word, '.');
        if ($letters === '') continue;
        // A lone letter, with or without its period, is an initial.
        $words[] = preg_match('/^\pL$/u', $letters) ? $upper($letters) . '.' : $title($letters);
    }
    $surname = $upper(trim($surname, ' .-'));
    return $words ? $surname . ', ' . implode(' ', $words) : $surname;
}

/**
 * Picks the best-read spelling among one student's recorded names: comma-separated and in mixed
 * case beats a run-together all-caps OCR read; then more words, then the most records.
 */
function dats_best_gradesheet_name(array $variants): string
{
    $best = '';
    $bestScore = null;
    foreach ($variants as $name => $count) {
        $name = (string) $name;
        $given = str_contains($name, ',') ? trim(explode(',', $name, 2)[1]) : '';
        $score = [str_contains($name, ',') ? 1 : 0, preg_match('/\p{Ll}/u', $name) ? 1 : 0, substr_count($given, ' '), (int) $count];
        if ($bestScore === null || $score > $bestScore) {
            $best = $name;
            $bestScore = $score;
        }
    }
    return dats_format_student_name($best);
}

/**
 * Everyone with digitized grades, one entry per student. Names from gradesheets that share the
 * same letters are merged, and folded into the Students-directory record with those letters.
 */
function dats_gradesheet_people(PDO $pdo): array
{
    $people = [];
    $slotByKey = [];
    $linked = $pdo->query("SELECT g.student_id, MAX(s.last_name) AS last_name, MAX(s.first_name) AS first_name, MAX(s.middle_initial) AS middle_initial,
            COALESCE(MAX(s.student_number), MAX(g.student_number)) AS student_number,
            GROUP_CONCAT(DISTINCT g.year_course SEPARATOR ', ') AS year_course,
            COUNT(*) AS entry_count, SUM(g.needs_review) AS needs_review_count
        FROM gradesheet_entries g
        JOIN students s ON s.id = g.student_id AND s.deleted_at IS NULL
        WHERE g.confirmed = 1
        GROUP BY g.student_id")->fetchAll();
    foreach ($linked as $row) {
        $display = dats_format_student_name($row['last_name'] . ', ' . $row['first_name'] . ($row['middle_initial'] ? ' ' . $row['middle_initial'] : ''));
        $slot = 'id:' . $row['student_id'];
        $key = dats_gradesheet_name_key($display);
        $people[$slot] = [
            'student_id' => (int) $row['student_id'],
            'student_key' => $key,
            'display_name' => $display,
            'names' => [$display],
            'student_number' => $row['student_number'],
            'year_course' => $row['year_course'],
            'entry_count' => (int) $row['entry_count'],
            'needs_review_count' => (int) $row['needs_review_count'],
            'in_directory' => true,
            'similar' => false,
        ];
        $slotByKey[$key] = $slotByKey[$key] ?? $slot;
    }

    $unlinked = $pdo->query("SELECT student_name, MAX(student_number) AS student_number,
            GROUP_CONCAT(DISTINCT year_course SEPARATOR ', ') AS year_course,
            COUNT(*) AS entry_count, SUM(needs_review) AS needs_review_count
        FROM gradesheet_entries
        WHERE student_id IS NULL AND student_name IS NOT NULL AND student_name <> '' AND confirmed = 1
        GROUP BY student_name LIMIT 20000")->fetchAll();
    $variants = [];
    foreach ($unlinked as $row) {
        $key = dats_gradesheet_name_key($row['student_name']);
        if ($key === '') continue;
        $slot = $slotByKey[$key] ?? 'key:' . $key;
        $slotByKey[$key] = $slot;
        $people[$slot] = $people[$slot] ?? [
            'student_id' => null, 'student_key' => $key, 'display_name' => '', 'names' => [],
            'student_number' => null, 'year_course' => null, 'entry_count' => 0, 'needs_review_count' => 0,
            'in_directory' => false, 'similar' => false,
        ];
        $person = &$people[$slot];
        $person['names'][] = $row['student_name'];
        $person['entry_count'] += (int) $row['entry_count'];
        $person['needs_review_count'] += (int) $row['needs_review_count'];
        $person['student_number'] = $person['student_number'] ?: $row['student_number'];
        $courses = array_unique(array_filter(array_map('trim', explode(',', ($person['year_course'] ?? '') . ',' . ($row['year_course'] ?? '')))));
        $person['year_course'] = $courses ? implode(', ', $courses) : null;
        unset($person);
        if (!str_starts_with($slot, 'id:')) $variants[$slot][$row['student_name']] = (int) $row['entry_count'];
    }
    foreach ($variants as $slot => $names) {
        $people[$slot]['display_name'] = dats_best_gradesheet_name($names);
    }
    return $people;
}

/**
 * A student's digitized grades — a Students-directory student (by id), or a student who so far
 * only exists on uploaded gradesheets (by name key, which gathers every spelling of the name).
 * `$studentName` is still accepted for older links and resolves to the same key.
 */
function get_student_gradesheet(PDO $pdo, int $studentId, string $studentName = '', string $studentKey = ''): array
{
    $order = "ORDER BY academic_year IS NULL, academic_year, FIELD(semester, '1st Semester', '2nd Semester', 'Summer', 'Midyear'), subject_code";
    $people = dats_gradesheet_people($pdo);
    if ($studentId > 0) {
        $studentStatement = $pdo->prepare('SELECT s.id, s.student_number, s.first_name, s.middle_initial, s.last_name, s.academic_year, c.name AS college_name, co.name AS course_name FROM students s LEFT JOIN colleges c ON c.id = s.college_id LEFT JOIN courses co ON co.id = s.course_id WHERE s.id = :id AND s.deleted_at IS NULL LIMIT 1');
        $studentStatement->execute([':id' => $studentId]);
        $student = $studentStatement->fetch();
        if (!$student) {
            throw new InvalidArgumentException('Student not found.');
        }
        $student['display_name'] = dats_format_student_name($student['last_name'] . ', ' . $student['first_name'] . ($student['middle_initial'] ? ' ' . $student['middle_initial'] : ''));
        $student['in_directory'] = true;
        $person = $people['id:' . $studentId] ?? null;
        $names = $person ? array_values(array_diff($person['names'], [$person['display_name']])) : [];
        $names = array_values(array_unique(array_merge($names, dats_unlinked_gradesheet_names($people, $student))));
    } else {
        $key = dats_gradesheet_name_key($studentKey !== '' ? $studentKey : $studentName);
        $person = $key !== '' ? ($people['key:' . $key] ?? null) : null;
        if (!$person) {
            throw new InvalidArgumentException($key === '' ? 'Select a student to view.' : 'No grade records were found for that student.');
        }
        $names = $person['names'];
        $student = [
            'id' => null,
            'student_key' => $person['student_key'],
            'display_name' => $person['display_name'],
            'student_number' => $person['student_number'],
            'course_name' => $person['year_course'],
            'college_name' => null,
            'in_directory' => false,
        ];
    }

    $conditions = [];
    $params = [];
    if ($studentId > 0) {
        $conditions[] = 'student_id = :student_id';
        $params[':student_id'] = $studentId;
    }
    foreach (array_values($names) as $index => $name) {
        $conditions[] = "(student_id IS NULL AND student_name = :name{$index})";
        $params[":name{$index}"] = $name;
    }
    $entriesStatement = $pdo->prepare('SELECT * FROM gradesheet_entries WHERE confirmed = 1 AND (' . implode(' OR ', $conditions) . ") {$order}");
    $entriesStatement->execute($params);
    return ['student' => $student, 'entries' => $entriesStatement->fetchAll()];
}

/** Resolves and validates a grade entry's existing source file against the selected student. */
function dats_get_gradesheet_entry_source(PDO $pdo, int $entryId, int $studentId, string $studentName, string $studentKey, int $expectedBatchId, int $expectedDocumentId): array
{
    if ($entryId < 1) throw new InvalidArgumentException('Grade record ID is required.');
    $grades = get_student_gradesheet($pdo, $studentId, $studentName, $studentKey);
    $entry = null;
    foreach ($grades['entries'] as $candidate) {
        if ((int) $candidate['id'] === $entryId) {
            $entry = $candidate;
            break;
        }
    }
    if (!$entry) throw new InvalidArgumentException('This grade record does not belong to the selected student.');
    $batchId = (int) ($entry['batch_id'] ?? 0);
    $documentId = (int) ($entry['document_id'] ?? 0);
    if ($batchId !== $expectedBatchId || $documentId !== $expectedDocumentId) {
        throw new InvalidArgumentException('The source file does not match this grade record. Refresh the student grades and try again.');
    }
    if ($batchId > 0) {
        $source = $pdo->prepare('SELECT original_filename, mime_type, ocr_text, ocr_status, file_path FROM gradesheet_batches WHERE id = :id LIMIT 1');
        $source->execute([':id' => $batchId]);
        $file = $source->fetch();
        $type = 'batch';
    } elseif ($documentId > 0) {
        $source = $pdo->prepare("SELECT original_filename, mime_type, ocr_text, ocr_status, file_path, student_id FROM documents WHERE id = :id AND student_id = :student_id AND status = 'Archived' AND deleted_at IS NULL LIMIT 1");
        $source->execute([':id' => $documentId, ':student_id' => $studentId]);
        $file = $source->fetch();
        if ($studentId < 1) {
            throw new InvalidArgumentException('The archived document does not match this grade record.');
        }
        $type = 'document';
    } else {
        throw new InvalidArgumentException('The original gradesheet is not available for this record.');
    }
    if (!$file || !($absolutePath = safe_document_path($file['file_path'] ?? null)) || !is_file($absolutePath)) {
        throw new InvalidArgumentException('The original gradesheet file is missing.');
    }
    $sourcePages = json_decode((string) ($entry['source_pages'] ?? ''), true);
    return [
        'entry_id' => $entryId,
        'student_id' => $studentId,
        'batch_id' => $batchId,
        'document_id' => $documentId,
        'student_name' => (string) ($entry['student_name'] ?? ''),
        'original_filename' => (string) ($file['original_filename'] ?? ''),
        'mime_type' => (string) ($file['mime_type'] ?? ''),
        'ocr_text' => (string) ($file['ocr_text'] ?? ''),
        'ocr_status' => (string) ($file['ocr_status'] ?? ''),
        'source_pages' => is_array($sourcePages) ? array_values(array_unique(array_filter(array_map('intval', $sourcePages), static fn (int $page): bool => $page > 0))) : null,
        'subject_code' => (string) ($entry['subject_code'] ?? ''),
        'subject_name' => (string) ($entry['subject_name'] ?? ''),
        'source_type' => $type,
        'absolute_path' => $absolutePath,
    ];
}

function get_gradesheet_entry_source(PDO $pdo, int $entryId, int $studentId, string $studentName, string $studentKey, int $batchId, int $documentId): array
{
    $source = dats_get_gradesheet_entry_source($pdo, $entryId, $studentId, $studentName, $studentKey, $batchId, $documentId);
    unset($source['absolute_path']);
    return $source;
}

function handle_gradesheet_entry_file(PDO $pdo, int $entryId, int $studentId, string $studentName, string $studentKey, int $batchId, int $documentId): never
{
    $source = dats_get_gradesheet_entry_source($pdo, $entryId, $studentId, $studentName, $studentKey, $batchId, $documentId);
    $mimeType = in_array($source['mime_type'], DATS_ALLOWED_DOCUMENT_MIME_TYPES, true) ? $source['mime_type'] : 'application/octet-stream';
    $downloadName = str_replace(['"', "\\", "\r", "\n"], '', $source['original_filename']);
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . filesize($source['absolute_path']));
    header('Content-Disposition: inline; filename="' . $downloadName . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    readfile($source['absolute_path']);
    exit;
}

function index_gradesheet_entry_pages(PDO $pdo, array $input): array
{
    $entryId = (int) ($input['id'] ?? 0);
    $studentId = (int) ($input['student_id'] ?? 0);
    $studentName = trim((string) ($input['student_name'] ?? ''));
    $studentKey = trim((string) ($input['student_key'] ?? ''));
    $batchId = (int) ($input['batch_id'] ?? 0);
    $documentId = (int) ($input['document_id'] ?? 0);
    $pages = array_values(array_unique(array_filter(
        array_map('intval', (array) ($input['source_pages'] ?? [])),
        static fn (int $page): bool => $page > 0 && $page <= 60
    )));
    dats_get_gradesheet_entry_source($pdo, $entryId, $studentId, $studentName, $studentKey, $batchId, $documentId);
    $statement = $pdo->prepare('UPDATE gradesheet_entries SET source_pages = :source_pages WHERE id = :id AND COALESCE(batch_id, 0) = :batch_id AND COALESCE(document_id, 0) = :document_id');
    $statement->execute([
        ':source_pages' => json_encode($pages, JSON_THROW_ON_ERROR),
        ':id' => $entryId,
        ':batch_id' => $batchId,
        ':document_id' => $documentId,
    ]);
    return ['id' => $entryId, 'source_pages' => $pages];
}

function add_gradesheet_student(PDO $pdo, array $input): array
{
    return dats_run_transaction($pdo, function (PDO $pdo) use ($input): array {
        $criteria = dats_validate_add_gradesheet_criteria($input);
        $identity = dats_gradesheet_directory_name_parts($criteria['student_name']);
        $association = associate_gradesheet_records($pdo, $identity, 0, $criteria);
        $draftEntryId = null;
        if ($association['matched_records'] === 0) {
            $findDraft = $pdo->prepare('SELECT id FROM gradesheet_entries WHERE batch_id IS NULL AND document_id IS NULL AND student_id IS NULL AND student_name = :student_name AND (year_course <=> :year_course) AND (academic_year <=> :academic_year) AND (semester <=> :semester) AND subject_code = :subject_code AND subject_name = :subject_name LIMIT 1');
            $findDraft->execute([
                ':student_name' => $criteria['student_name'], ':year_course' => $criteria['year_course'] !== '' ? $criteria['year_course'] : null,
                ':academic_year' => $criteria['academic_year'], ':semester' => $criteria['semester'],
                ':subject_code' => $criteria['subject_code'], ':subject_name' => $criteria['subject_name'],
            ]);
            $draftEntryId = (int) ($findDraft->fetchColumn() ?: 0);
            if ($draftEntryId < 1) {
                // A manually-entered draft record: the reviewer typed every field themselves, so it
                // is confirmed immediately rather than waiting on a batch-level Save.
                $insert = $pdo->prepare('INSERT INTO gradesheet_entries (batch_id, document_id, student_id, student_name, student_number, year_course, academic_year, semester, subject_code, subject_name, source_pages, units, grade, remarks, needs_review, confirmed) VALUES (NULL, NULL, NULL, :student_name, NULL, :year_course, :academic_year, :semester, :subject_code, :subject_name, NULL, :units, :grade, :remarks, 0, 1)');
                $insert->execute([
                    ':student_name' => $criteria['student_name'], ':year_course' => $criteria['year_course'] !== '' ? $criteria['year_course'] : null,
                    ':academic_year' => $criteria['academic_year'], ':semester' => $criteria['semester'],
                    ':subject_code' => $criteria['subject_code'], ':subject_name' => $criteria['subject_name'],
                    ':units' => $criteria['units'] !== '' ? (float) $criteria['units'] : null,
                    ':grade' => $criteria['grade'] !== '' ? $criteria['grade'] : null,
                    ':remarks' => $criteria['remarks'] !== '' ? $criteria['remarks'] : null,
                ]);
                $draftEntryId = (int) $pdo->lastInsertId();
            }
        }
        return [
            'student' => ['id' => null, 'student_key' => dats_gradesheet_name_key($criteria['student_name']), 'display_name' => dats_format_student_name($criteria['student_name'])],
            'association' => $association,
            'draft_entry_id' => $draftEntryId,
        ];
    });
}

function associate_gradesheet_student(PDO $pdo, array $input): array
{
    $criteria = dats_validate_add_gradesheet_criteria($input);
    $identity = dats_gradesheet_directory_name_parts($criteria['student_name']);
    $batchId = (int) ($input['batch_id'] ?? 0);
    $ocrText = trim((string) ($input['ocr_text'] ?? ''));
    if ($batchId > 0 && $ocrText !== '') {
        $stored = $pdo->prepare('SELECT ocr_text FROM gradesheet_batches WHERE id = :id');
        $stored->execute([':id' => $batchId]);
        $existingText = (string) ($stored->fetchColumn() ?: '');
        $pageNumbers = array_values(array_filter(array_map('intval', (array) ($input['ocr_page_numbers'] ?? [])), static fn (int $page): bool => $page > 0 && $page <= 60));
        if (trim($existingText) === '') {
            $mergedText = $ocrText;
        } elseif ($pageNumbers) {
            $existingPages = explode("\f", $existingText);
            $newPages = explode("\f", $ocrText);
            foreach ($pageNumbers as $index => $pageNumber) {
                if (!isset($newPages[$index]) || trim($newPages[$index]) === '') continue;
                $existingPages[$pageNumber - 1] = $newPages[$index];
            }
            $mergedText = implode("\f", $existingPages);
        } else {
            $mergedText = $existingText;
        }
        $saveOcr = $pdo->prepare("UPDATE gradesheet_batches SET ocr_status = 'Completed', ocr_text = :ocr_text, ocr_error = NULL WHERE id = :id");
        $saveOcr->execute([':ocr_text' => $mergedText, ':id' => $batchId]);
    }

    $association = associate_gradesheet_records($pdo, $identity, $batchId, $criteria);
    $draftEntryId = (int) ($input['draft_entry_id'] ?? 0);
    if ($association['matched_records'] > 0 && $draftEntryId > 0) {
        $deleteDraft = $pdo->prepare('DELETE FROM gradesheet_entries WHERE id = :id AND batch_id IS NULL AND document_id IS NULL AND student_id IS NULL AND student_name = :student_name');
        $deleteDraft->execute([':id' => $draftEntryId, ':student_name' => $criteria['student_name']]);
    }
    return $association;
}

function dats_validate_add_gradesheet_criteria(array $criteria): array
{
    $studentName = trim((string) ($criteria['student_name'] ?? ''));
    $subjectCode = trim((string) ($criteria['subject_code'] ?? ''));
    $subjectName = trim((string) ($criteria['subject_name'] ?? ''));
    $academicYear = normalize_academic_year((string) ($criteria['academic_year'] ?? ''));
    $semester = trim((string) ($criteria['semester'] ?? ''));
    if ($studentName === '' || strlen($studentName) > 160) throw new InvalidArgumentException('Student name is required and must be 160 characters or fewer.');
    if ($subjectCode === '' || strlen($subjectCode) > 20) throw new InvalidArgumentException('Subject code is required and must be 20 characters or fewer.');
    if ($subjectName === '' || strlen($subjectName) > 255) throw new InvalidArgumentException('Subject title is required and must be 255 characters or fewer.');
    if ($academicYear === null) throw new InvalidArgumentException('A valid academic year between 2000 and 2100 is required.');
    if (!in_array($semester, DATS_GRADESHEET_SEMESTERS, true)) throw new InvalidArgumentException('Select a valid semester.');
    $units = trim((string) ($criteria['units'] ?? ''));
    if ($units !== '' && (!is_numeric($units) || (float) $units < 0 || (float) $units > 99.99)) {
        throw new InvalidArgumentException('Units must be a number between 0 and 99.99.');
    }
    $grade = trim((string) ($criteria['grade'] ?? ''));
    $remarks = trim((string) ($criteria['remarks'] ?? ''));
    $yearCourse = trim((string) ($criteria['year_course'] ?? ''));
    if ($yearCourse === '') $yearCourse = dats_normalize_year_course((string) ($criteria['student_year'] ?? ''), (string) ($criteria['student_course'] ?? ''));
    if (strlen($grade) > 10) throw new InvalidArgumentException('Grade must be 10 characters or fewer.');
    if (strlen($remarks) > 100) throw new InvalidArgumentException('Remarks must be 100 characters or fewer.');
    if (strlen($yearCourse) > 80) throw new InvalidArgumentException('Year and course must be 80 characters or fewer.');
    return array_merge($criteria, [
        'student_name' => $studentName, 'subject_code' => $subjectCode, 'subject_name' => $subjectName,
        'academic_year' => $academicYear, 'semester' => $semester, 'units' => $units,
        'grade' => strtoupper($grade), 'remarks' => strtoupper($remarks), 'year_course' => $yearCourse,
    ]);
}

function associate_gradesheet_records(PDO $pdo, array $student, int $onlyBatchId = 0, array $criteria = []): array
{
    if ($onlyBatchId > 0) {
        $batchesQuery = $pdo->prepare('SELECT id, ocr_text FROM gradesheet_batches WHERE id = :id');
        $batchesQuery->execute([':id' => $onlyBatchId]);
    } else {
        $batchesQuery = $pdo->query('SELECT id, ocr_text FROM gradesheet_batches ORDER BY id');
    }

    // Every write below comes from a reviewer explicitly searching for and confirming a specific
    // student/record match (the Add Student flow), so it is confirmed immediately rather than
    // waiting on that record's batch to be bulk-saved.
    $insert = $pdo->prepare('INSERT INTO gradesheet_entries (batch_id, student_id, student_name, student_number, year_course, academic_year, semester, subject_code, subject_name, source_pages, units, grade, remarks, needs_review, review_reasons, confirmed) VALUES (:batch_id, :student_id, :student_name, :student_number, :year_course, :academic_year, :semester, :subject_code, :subject_name, :source_pages, :units, :grade, :remarks, :needs_review, :review_reasons, 1)');
    $find = $pdo->prepare('SELECT id, student_id, student_name, source_pages FROM gradesheet_entries WHERE batch_id = :batch_id AND subject_code = :subject_code AND subject_name = :subject_name AND (academic_year <=> :academic_year) AND (semester <=> :semester)');
    $link = $pdo->prepare('UPDATE gradesheet_entries SET source_pages = :source_pages, confirmed = 1, updated_at = NOW() WHERE id = :id AND student_id IS NULL');
    $updatePages = $pdo->prepare('UPDATE gradesheet_entries SET source_pages = :source_pages, confirmed = 1, updated_at = NOW() WHERE id = :id');
    $matchingBatches = [];
    $missingBatches = [];
    $matchedRecords = 0;
    $createdRecords = 0;
    $linkedRecords = 0;
    $unparsedMatchingBatches = [];

    foreach ($batchesQuery->fetchAll(PDO::FETCH_ASSOC) as $batch) {
        $batchId = (int) $batch['id'];
        $text = trim((string) ($batch['ocr_text'] ?? ''));
        if ($text === '') {
            $missingBatches[] = ['id' => $batchId];
            continue;
        }
        $batchMatched = false;
        $parsedPages = [];
        foreach (dats_extract_gradesheet_records($text) as $record) {
            if (!dats_gradesheet_name_matches_student((string) ($record['student_name'] ?? ''), $student)) continue;
            $pages = array_values(array_unique(array_filter(array_map('intval', (array) ($record['source_pages'] ?? [])), static fn (int $page): bool => $page > 0 && $page <= 60)));
            if (!$pages) continue;
            if (!dats_gradesheet_record_matches_criteria($record, $criteria)) continue;
            $parsedPages = array_merge($parsedPages, $pages);
            $batchMatched = true;
            $matchedRecords++;
            $academicYear = normalize_academic_year((string) ($record['academic_year'] ?? ''));
            $semester = in_array($record['semester'] ?? null, DATS_GRADESHEET_SEMESTERS, true) ? $record['semester'] : null;
            $subjectCode = dats_gradesheet_clip($record['subject_code'] ?? null, 20) ?? '';
            $subjectName = dats_gradesheet_clip($record['subject_name'] ?? null, 255) ?? '';
            $find->execute([
                ':batch_id' => $batchId, ':subject_code' => $subjectCode, ':subject_name' => $subjectName,
                ':academic_year' => $academicYear, ':semester' => $semester,
            ]);
            $existing = null;
            foreach ($find->fetchAll(PDO::FETCH_ASSOC) as $candidate) {
                if (dats_gradesheet_name_matches_student((string) $candidate['student_name'], $student)) {
                    $existing = $candidate;
                    break;
                }
            }
            if ($existing) {
                $existingPages = json_decode((string) ($existing['source_pages'] ?? ''), true);
                $mergedPages = array_values(array_unique(array_merge(is_array($existingPages) ? array_map('intval', $existingPages) : [], $pages)));
                sort($mergedPages);
                if ($existing['student_id'] === null) {
                    $link->execute([':source_pages' => json_encode($mergedPages, JSON_THROW_ON_ERROR), ':id' => $existing['id']]);
                    $linkedRecords += $link->rowCount();
                } elseif ($mergedPages !== (is_array($existingPages) ? array_values(array_map('intval', $existingPages)) : [])) {
                    $updatePages->execute([':source_pages' => json_encode($mergedPages, JSON_THROW_ON_ERROR), ':id' => $existing['id']]);
                }
                continue;
            }
            $needsReview = !empty($record['needs_review']) || $academicYear === null || $semester === null;
            $insert->execute([
                ':batch_id' => $batchId, ':student_id' => null,
                ':student_name' => dats_gradesheet_clip($record['student_name'] ?? null, 160),
                ':student_number' => null,
                ':year_course' => dats_gradesheet_clip($record['year_course'] ?? null, 80),
                ':academic_year' => $academicYear, ':semester' => $semester,
                ':subject_code' => $subjectCode, ':subject_name' => $subjectName,
                ':source_pages' => json_encode($pages, JSON_THROW_ON_ERROR),
                ':units' => isset($record['units']) && is_numeric($record['units']) ? (float) $record['units'] : null,
                ':grade' => dats_gradesheet_clip($record['grade'] ?? null, 10),
                ':remarks' => dats_gradesheet_clip($record['remarks'] ?? null, 100),
                ':needs_review' => $needsReview ? 1 : 0,
                ':review_reasons' => implode(',', dats_gn_reasons($record)) ?: null,
            ]);
            $createdRecords++;
        }
        $textPages = dats_gradesheet_student_page_numbers($text, $student);
        $unparsedPages = array_values(array_diff($textPages, array_unique($parsedPages)));
        $textPageList = explode("\f", $text);
        $criteriaPages = array_values(array_filter($unparsedPages, static function (int $page) use ($textPageList, $criteria): bool {
            return isset($textPageList[$page - 1]) && dats_gradesheet_page_matches_criteria($textPageList[$page - 1], $criteria);
        }));
        if ($criteriaPages) {
            $academicYear = normalize_academic_year((string) $criteria['academic_year']);
            $semester = $criteria['semester'];
            $subjectCode = dats_gradesheet_clip($criteria['subject_code'], 20) ?? '';
            $subjectName = dats_gradesheet_clip($criteria['subject_name'], 255) ?? '';
            $find->execute([
                ':batch_id' => $batchId, ':subject_code' => $subjectCode, ':subject_name' => $subjectName,
                ':academic_year' => $academicYear, ':semester' => $semester,
            ]);
            $existing = null;
            foreach ($find->fetchAll(PDO::FETCH_ASSOC) as $candidate) {
                if (dats_gradesheet_name_matches_student((string) $candidate['student_name'], $student)) {
                    $existing = $candidate;
                    break;
                }
            }
            if ($existing) {
                $existingPages = json_decode((string) ($existing['source_pages'] ?? ''), true);
                $mergedPages = array_values(array_unique(array_merge(is_array($existingPages) ? array_map('intval', $existingPages) : [], $criteriaPages)));
                sort($mergedPages);
                $update = $existing['student_id'] === null ? $link : $updatePages;
                $update->execute([':source_pages' => json_encode($mergedPages, JSON_THROW_ON_ERROR), ':id' => $existing['id']]);
                $linkedRecords += $update->rowCount();
            } else {
                $insert->execute([
                    ':batch_id' => $batchId, ':student_id' => null,
                    ':student_name' => dats_gradesheet_clip($criteria['student_name'], 160), ':student_number' => null,
                    ':year_course' => dats_gradesheet_clip($criteria['year_course'], 80),
                    ':academic_year' => $academicYear, ':semester' => $semester,
                    ':subject_code' => $subjectCode, ':subject_name' => $subjectName,
                    ':source_pages' => json_encode($criteriaPages, JSON_THROW_ON_ERROR),
                    ':units' => $criteria['units'] !== '' ? (float) $criteria['units'] : null,
                    ':grade' => $criteria['grade'] !== '' ? dats_gradesheet_clip($criteria['grade'], 10) : null,
                    ':remarks' => $criteria['remarks'] !== '' ? dats_gradesheet_clip($criteria['remarks'], 100) : null,
                    ':needs_review' => 0,
                    ':review_reasons' => null,
                ]);
                $createdRecords++;
            }
            $matchedRecords++;
            $batchMatched = true;
            $parsedPages = array_merge($parsedPages, $criteriaPages);
        }
        $remainingPages = array_values(array_diff($unparsedPages, $criteriaPages));
        if ($remainingPages) $unparsedMatchingBatches[] = ['id' => $batchId, 'page_numbers' => $remainingPages];
        if ($batchMatched) {
            $matchingBatches[] = $batchId;
            dats_refresh_gradesheet_batch_status($pdo, $batchId);
        }
    }

    return [
        'matching_batches' => $matchingBatches,
        'missing_ocr_batches' => $missingBatches,
        'unparsed_matching_batches' => $unparsedMatchingBatches,
        'matched_records' => $matchedRecords,
        'created_records' => $createdRecords,
        'linked_records' => $linkedRecords,
    ];
}

function dats_gradesheet_student_page_numbers(string $ocrText, array $student): array
{
    return dats_gradesheet_pages_matching_student($ocrText, $student);
}

/**
 * Gradesheet-only records that belong to a Students-directory student but were never linked to it:
 * the same printed name (with or without the middle initial) or the same student number.
 */
function dats_unlinked_gradesheet_names(array $people, array $student): array
{
    $fullKey = dats_gradesheet_name_key($student['display_name'] ?? '');
    $baseKey = dats_gradesheet_name_key(($student['last_name'] ?? '') . ($student['first_name'] ?? ''));
    $number = trim((string) ($student['student_number'] ?? ''));
    $names = [];
    foreach ($people as $slot => $person) {
        if (!str_starts_with((string) $slot, 'key:')) continue;
        $key = (string) $person['student_key'];
        $sameName = $baseKey !== '' && ($key === $fullKey || $key === $baseKey || preg_match('/^' . preg_quote($baseKey, '/') . '\pL$/u', $key));
        $sameNumber = $number !== '' && trim((string) ($person['student_number'] ?? '')) === $number;
        if ($sameName || $sameNumber) $names = array_merge($names, $person['names']);
    }
    return $names;
}

/** Name search over everyone with digitized grades — one result per student, never one per spelling. */
function search_gradesheet_students(PDO $pdo, string $search): array
{
    $lower = static fn (?string $value): string => function_exists('mb_strtolower') ? mb_strtolower((string) $value, 'UTF-8') : strtolower((string) $value);
    $needle = $lower($search);
    $needleKey = dats_gradesheet_name_key($search);
    $results = [];
    $rest = [];
    foreach (dats_gradesheet_people($pdo) as $person) {
        $hit = $search === '';
        foreach (array_merge($person['names'], [$person['display_name'], $person['student_number']]) as $value) {
            if ($hit) break;
            $hit = (string) $value !== '' && str_contains($lower($value), $needle);
        }
        // "Hazzel Joy" still finds the run-together read "HAZZELJOYC.".
        if (!$hit && strlen($needleKey) >= 3) $hit = str_contains($person['student_key'], $needleKey);
        if ($hit) $results[] = $person;
        else $rest[] = $person;
    }
    usort($results, static fn (array $a, array $b): int => strcmp($a['display_name'], $b['display_name']));
    if ($search !== '' && count($results) < 25) {
        $results = array_merge($results, dats_fuzzy_gradesheet_name_matches($search, $rest));
    }
    return array_map(static function (array $person): array {
        unset($person['names']);
        return $person;
    }, array_slice($results, 0, 25));
}

/**
 * Names read from scanned sheets are often a letter off ("Shermaine" for "Sharmaine"), so a
 * student must still be findable by the correct spelling. Every word typed has to match some
 * word of the recorded name as a prefix, allowing 1 typo from 4 letters and 2 from 7.
 */
function dats_fuzzy_gradesheet_name_matches(string $search, array $people): array
{
    $normalize = static fn (string $value): array => array_values(array_filter(
        preg_split('/[^A-Z]+/', strtoupper($value)) ?: [],
        static fn (string $token): bool => strlen($token) >= 3
    ));
    $queryTokens = $normalize($search);
    if (!$queryTokens) return [];

    $matches = [];
    foreach ($people as $person) {
        $nameTokens = [];
        foreach (array_merge($person['names'], [$person['display_name']]) as $name) {
            $nameTokens = array_merge($nameTokens, $normalize((string) $name));
        }
        if (!$nameTokens) continue;
        $distance = 0;
        foreach ($queryTokens as $query) {
            $allowed = strlen($query) >= 7 ? 2 : (strlen($query) >= 4 ? 1 : 0);
            $best = PHP_INT_MAX;
            foreach ($nameTokens as $token) {
                $best = min($best, levenshtein($query, substr($token, 0, strlen($query))));
            }
            if ($best > $allowed) continue 2;
            $distance += $best;
        }
        $person['similar'] = true;
        $person['distance'] = $distance;
        $matches[] = $person;
    }
    usort($matches, static fn (array $a, array $b): int => $a['distance'] <=> $b['distance'] ?: strcmp($a['display_name'], $b['display_name']));
    return array_map(static function (array $match): array { unset($match['distance']); return $match; }, $matches);
}

function update_gradesheet_entry(PDO $pdo, array $input): array
{
    $id = (int) ($input['id'] ?? 0);
    if ($id < 1) {
        throw new InvalidArgumentException('Gradesheet entry ID is required.');
    }
    // Checked up front, separately from the UPDATE below: PDO's rowCount() for an UPDATE reflects
    // rows actually CHANGED, not rows matched, so re-saving a row with unchanged values would
    // otherwise be wrongly reported as "not found."
    $existing = $pdo->prepare('SELECT id, student_id, student_name, batch_id FROM gradesheet_entries WHERE id = :id');
    $existing->execute([':id' => $id]);
    $entry = $existing->fetch();
    if (!$entry) {
        throw new InvalidArgumentException('Gradesheet entry not found.');
    }

    // The name as printed on the sheet; OCR can misread it, so it is correctable.
    $studentName = trim((string) ($input['student_name'] ?? $entry['student_name'] ?? ''));
    if ($entry['student_id'] === null && $studentName === '') {
        throw new InvalidArgumentException('Student name is required.');
    }
    if (strlen($studentName) > 160) {
        throw new InvalidArgumentException('Student name must be 160 characters or fewer.');
    }

    $yearCourse = dats_normalize_year_course(
        trim((string) ($input['year'] ?? '')) !== '' ? trim((string) ($input['year'] ?? '')) : trim((string) ($input['student_year'] ?? '')),
        trim((string) ($input['course'] ?? '')) !== '' ? trim((string) ($input['course'] ?? '')) : trim((string) ($input['student_course'] ?? ''))
    );
    if (array_key_exists('year_course', $input)) {
        $yearCourse = dats_normalize_year_course(
            trim((string) ($input['year'] ?? '')) !== '' ? trim((string) ($input['year'] ?? '')) : trim((string) ($input['student_year'] ?? '')),
            trim((string) ($input['course'] ?? '')) !== '' ? trim((string) ($input['course'] ?? '')) : trim((string) ($input['student_course'] ?? ''))
        );
        if (trim((string) ($input['year_course'] ?? '')) !== '') {
            $yearCourse = dats_normalize_year_course(trim((string) ($input['year_course'] ?? '')), '');
        }
    }

    $subjectCode = trim((string) ($input['subject_code'] ?? ''));
    $subjectName = trim((string) ($input['subject_name'] ?? ''));
    if ($subjectCode === '' || $subjectName === '') {
        throw new InvalidArgumentException('Subject code and title are required.');
    }
    if (strlen($subjectCode) > 20) {
        throw new InvalidArgumentException('Subject code must be 20 characters or fewer.');
    }
    if (strlen($subjectName) > 255) {
        throw new InvalidArgumentException('Subject title must be 255 characters or fewer.');
    }
    $academicYear = normalize_academic_year((string) ($input['academic_year'] ?? ''));
    if (!$academicYear) {
        throw new InvalidArgumentException('A valid academic year between 2000 and 2100 is required.');
    }
    $semester = trim((string) ($input['semester'] ?? ''));
    if (!in_array($semester, DATS_GRADESHEET_SEMESTERS, true)) {
        throw new InvalidArgumentException('Select a valid semester.');
    }
    $unitsRaw = trim((string) ($input['units'] ?? ''));
    $units = null;
    if ($unitsRaw !== '') {
        if (!is_numeric($unitsRaw) || (float) $unitsRaw < 0 || (float) $unitsRaw > 99.99) {
            throw new InvalidArgumentException('Units must be a number between 0 and 99.99.');
        }
        $units = (float) $unitsRaw;
    }
    $grade = trim((string) ($input['grade'] ?? ''));
    if (strlen($grade) > 10) {
        throw new InvalidArgumentException('Grade must be 10 characters or fewer.');
    }
    $remarks = trim((string) ($input['remarks'] ?? ''));
    if (strlen($remarks) > 100) {
        throw new InvalidArgumentException('Remarks must be 100 characters or fewer.');
    }

    // Saving through this form is an admin verifying the record, so it no longer needs review.
    $statement = $pdo->prepare('UPDATE gradesheet_entries SET student_name = :student_name, year_course = :year_course, academic_year = :academic_year, semester = :semester, subject_code = :subject_code, subject_name = :subject_name, units = :units, grade = :grade, remarks = :remarks, needs_review = 0, review_reasons = NULL, suggested_name = NULL, updated_at = NOW() WHERE id = :id');
    $statement->execute([
        ':student_name' => $studentName === '' ? null : $studentName,
        ':year_course' => $yearCourse === '' ? null : $yearCourse,
        ':academic_year' => $academicYear,
        ':semester' => $semester,
        ':subject_code' => $subjectCode,
        ':subject_name' => $subjectName,
        ':units' => $units,
        ':grade' => $grade === '' ? null : $grade,
        ':remarks' => $remarks === '' ? null : $remarks,
        ':id' => $id,
    ]);
    $bulkUpdated = 0;
    $oldName = trim((string) ($entry['student_name'] ?? ''));
    if (!empty($input['apply_to_batch']) && $entry['batch_id'] !== null && $entry['student_id'] === null && $oldName !== '' && $studentName !== '' && $oldName !== $studentName) {
        $bulkUpdated = dats_apply_gradesheet_name_to_batch($pdo, (int) $entry['batch_id'], $id, $oldName, $studentName);
    }
    dats_refresh_gradesheet_batch_status($pdo, $entry['batch_id'] !== null ? (int) $entry['batch_id'] : null);

    $result = $pdo->prepare('SELECT * FROM gradesheet_entries WHERE id = :id');
    $result->execute([':id' => $id]);
    return array_merge($result->fetch(), ['bulk_updated' => $bulkUpdated]);
}

/**
 * Applies a reviewer's name correction to every other record in the same upload that carries the
 * identical misread spelling (the same student appears once per subject). Only the NAME concern is
 * cleared: a row still flagged for an unreadable grade or missing details keeps its flag.
 */
function dats_apply_gradesheet_name_to_batch(PDO $pdo, int $batchId, int $exceptId, string $oldName, string $newName): int
{
    $rows = $pdo->prepare('SELECT id, needs_review, review_reasons FROM gradesheet_entries WHERE batch_id = :batch_id AND student_id IS NULL AND student_name = :old AND id <> :id');
    $rows->execute([':batch_id' => $batchId, ':old' => $oldName, ':id' => $exceptId]);
    $update = $pdo->prepare('UPDATE gradesheet_entries SET student_name = :name, suggested_name = NULL, needs_review = :needs_review, review_reasons = :reasons, updated_at = NOW() WHERE id = :id');
    $count = 0;
    foreach ($rows->fetchAll() as $row) {
        $reasons = dats_gn_reasons($row);
        $remaining = array_values(array_diff($reasons, ['name']));
        // A flagged row with no recorded reasons predates reason tracking, so its flag is left alone.
        $needsReview = $reasons ? ($remaining !== [] ? 1 : 0) : (int) $row['needs_review'];
        $update->execute([':name' => $newName, ':needs_review' => $needsReview, ':reasons' => $remaining ? implode(',', $remaining) : null, ':id' => $row['id']]);
        $count++;
    }
    return $count;
}

function delete_gradesheet_entry(PDO $pdo, array $input): array
{
    $id = (int) ($input['id'] ?? 0);
    if ($id < 1) {
        throw new InvalidArgumentException('Gradesheet entry ID is required.');
    }
    $batch = $pdo->prepare('SELECT batch_id FROM gradesheet_entries WHERE id = :id');
    $batch->execute([':id' => $id]);
    $batchId = $batch->fetchColumn();
    $statement = $pdo->prepare('DELETE FROM gradesheet_entries WHERE id = :id');
    $statement->execute([':id' => $id]);
    if ($statement->rowCount() !== 1) {
        throw new InvalidArgumentException('Gradesheet entry not found.');
    }
    dats_refresh_gradesheet_batch_status($pdo, $batchId ? (int) $batchId : null);
    return ['id' => $id, 'status' => 'Deleted'];
}

/**
 * Keeps a batch's counts and status in step with its entries after an edit, delete, or save.
 * A batch with any unconfirmed entry is still 'Pending Review' — awaiting the reviewer's Save —
 * regardless of per-row needs_review flags, which only matter once the batch is confirmed.
 */
function dats_refresh_gradesheet_batch_status(PDO $pdo, ?int $batchId): void
{
    if (!$batchId) return;
    $counts = $pdo->prepare('SELECT COUNT(*) AS entries, COALESCE(SUM(needs_review), 0) AS review, COALESCE(SUM(confirmed), 0) AS confirmed, COUNT(DISTINCT COALESCE(CAST(student_id AS CHAR), student_name)) AS students FROM gradesheet_entries WHERE batch_id = :id');
    $counts->execute([':id' => $batchId]);
    $row = $counts->fetch();
    $entries = (int) $row['entries'];
    $status = $entries === 0 ? 'Failed'
        : ((int) $row['confirmed'] < $entries ? 'Pending Review' : ((int) $row['review'] > 0 ? 'Needs Review' : 'Completed'));
    $update = $pdo->prepare('UPDATE gradesheet_batches SET status = :status, committed_entry_count = :entries, detected_student_count = :students WHERE id = :id');
    $update->execute([':status' => $status, ':entries' => $entries, ':students' => (int) $row['students'], ':id' => $batchId]);
}

function get_deleted_documents(PDO $pdo): array
{
    $statement = $pdo->query("SELECT d.id, d.student_id, d.original_filename, d.file_size_bytes, d.academic_year, d.deleted_at, s.student_number, CONCAT(s.first_name, ' ', s.last_name) AS student_name, c.name AS category_name FROM documents d JOIN students s ON s.id = d.student_id LEFT JOIN document_categories c ON c.id = d.category_id WHERE d.deleted_at IS NOT NULL ORDER BY d.deleted_at DESC, d.id DESC");
    return $statement->fetchAll();
}

function delete_document(PDO $pdo, array $input): array
{
    $documentId = (int) ($input['document_id'] ?? $input['id'] ?? 0);
    if ($documentId < 1) {
        throw new InvalidArgumentException('Document ID is required.');
    }

    $statement = $pdo->prepare("UPDATE documents SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id AND status = 'Archived' AND deleted_at IS NULL");
    $statement->execute([':id' => $documentId]);
    if ($statement->rowCount() !== 1) {
        throw new InvalidArgumentException('Document not found or already in the Recycle Bin.');
    }

    return ['id' => $documentId, 'status' => 'Deleted'];
}

function restore_document(PDO $pdo, array $input): array
{
    $documentId = (int) ($input['document_id'] ?? $input['id'] ?? 0);
    if ($documentId < 1) {
        throw new InvalidArgumentException('Document ID is required.');
    }

    $statement = $pdo->prepare("UPDATE documents SET deleted_at = NULL, updated_at = NOW() WHERE id = :id AND deleted_at IS NOT NULL");
    $statement->execute([':id' => $documentId]);
    if ($statement->rowCount() !== 1) {
        throw new InvalidArgumentException('Document not found in the Recycle Bin.');
    }

    $result = $pdo->prepare('SELECT id, student_id, original_filename, status FROM documents WHERE id = :id');
    $result->execute([':id' => $documentId]);
    return $result->fetch();
}

function purge_document(PDO $pdo, array $input): array
{
    $documentId = (int) ($input['document_id'] ?? $input['id'] ?? 0);
    if ($documentId < 1) {
        throw new InvalidArgumentException('Document ID is required.');
    }

    $lookup = $pdo->prepare('SELECT id, original_filename, file_path FROM documents WHERE id = :id AND deleted_at IS NOT NULL LIMIT 1');
    $lookup->execute([':id' => $documentId]);
    $document = $lookup->fetch();
    if (!$document) {
        throw new InvalidArgumentException('Document must be in the Recycle Bin before it can be permanently deleted.');
    }

    $filePath = trim((string) ($document['file_path'] ?? ''));
    $absolutePath = $filePath !== '' ? safe_document_path($filePath) : null;
    if ($filePath !== '' && !$absolutePath) {
        $candidatePath = __DIR__ . '/' . ltrim($filePath, '/\\');
        if (str_contains(str_replace('\\', '/', $filePath), '..') || file_exists($candidatePath)) {
            throw new InvalidArgumentException('Document file path is invalid.');
        }
    }

    $pdo->beginTransaction();
    try {
        $reviews = $pdo->prepare('DELETE FROM document_reviews WHERE document_id = :document_id');
        $reviews->execute([':document_id' => $documentId]);

        $statement = $pdo->prepare('DELETE FROM documents WHERE id = :id AND deleted_at IS NOT NULL');
        $statement->execute([':id' => $documentId]);
        if ($statement->rowCount() !== 1) {
            throw new InvalidArgumentException('Document is no longer in the Recycle Bin.');
        }
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }

    if ($absolutePath && is_file($absolutePath)) {
        @unlink($absolutePath);
    }

    return ['id' => $documentId, 'original_filename' => $document['original_filename'], 'status' => 'Purged'];
}

function ensure_enrollment_years_table(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS enrollment_years (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        year SMALLINT UNSIGNED NOT NULL,
        created_by BIGINT UNSIGNED DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_enrollment_year (year),
        KEY fk_years_creator (created_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $yearType = $pdo->query("SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enrollment_years' AND COLUMN_NAME = 'year'")->fetchColumn();
    if (strtolower((string) $yearType) === 'year') {
        $pdo->exec('ALTER TABLE enrollment_years MODIFY year SMALLINT UNSIGNED NOT NULL');
    }

    try {
        $pdo->exec('ALTER TABLE enrollment_years ADD CONSTRAINT fk_years_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE');
    } catch (Throwable $ignored) {
        // Ignore when the foreign key already exists or the users table is not available yet.
    }
}

function sync_enrollment_years(PDO $pdo): void
{
    ensure_enrollment_years_table($pdo);
    $statement = $pdo->query("SELECT DISTINCT s.academic_year AS year FROM students s WHERE s.academic_year IS NOT NULL AND s.academic_year <> '0000' AND s.deleted_at IS NULL UNION SELECT DISTINCT d.academic_year AS year FROM documents d JOIN students s ON s.id = d.student_id AND s.deleted_at IS NULL AND s.academic_year = d.academic_year WHERE d.academic_year IS NOT NULL AND d.academic_year <> '0000' ORDER BY year DESC");
    $years = $statement->fetchAll(PDO::FETCH_COLUMN);
    if (!$years) {
        return;
    }
    $insert = $pdo->prepare('INSERT IGNORE INTO enrollment_years (year) VALUES (:year)');
    foreach ($years as $year) {
        $insert->execute([':year' => $year]);
    }
}

function get_enrollment_years(PDO $pdo): array
{
    ensure_enrollment_years_table($pdo);
    sync_enrollment_years($pdo);
    $statement = $pdo->query("SELECT ey.*, COUNT(DISTINCT s.id) AS student_count, COUNT(DISTINCT CASE WHEN d.status = 'Archived' AND d.deleted_at IS NULL THEN d.id END) AS document_count FROM enrollment_years ey LEFT JOIN students s ON s.academic_year = ey.year AND s.deleted_at IS NULL LEFT JOIN documents d ON d.student_id = s.id AND d.academic_year = ey.year GROUP BY ey.id ORDER BY ey.year DESC");
    return $statement->fetchAll();
}

function create_enrollment_year(PDO $pdo, array $input): array
{
    $yearValue = trim((string) ($input['year'] ?? ''));
    if ($yearValue === '') {
        throw new InvalidArgumentException('A year is required.');
    }
    $yearNumber = (int) $yearValue;
    if ((string) $yearNumber !== $yearValue || $yearNumber < 1800 || $yearNumber > 9999) {
        throw new InvalidArgumentException('Enter a valid year between 1800 and 9999.');
    }
    ensure_enrollment_years_table($pdo);
    $year = sprintf('%04d', $yearNumber);
    $existing = $pdo->prepare('SELECT id FROM enrollment_years WHERE year = :year LIMIT 1');
    $existing->execute([':year' => $year]);
    if ($existing->fetch()) {
        throw new InvalidArgumentException('This enrollment year already exists.');
    }
    $statement = $pdo->prepare('INSERT INTO enrollment_years (year, created_by) VALUES (:year, :created_by)');
    $statement->execute([
        ':year' => $year,
        ':created_by' => (int) (dats_current_user()['id'] ?? 1),
    ]);
    $id = (int) $pdo->lastInsertId();
    $lookup = $pdo->prepare('SELECT * FROM enrollment_years WHERE id = :id');
    $lookup->execute([':id' => $id]);
    return $lookup->fetch();
}

function delete_enrollment_year(PDO $pdo, array $input): array
{
    ensure_enrollment_years_table($pdo);
    $yearValue = trim((string) ($input['year'] ?? ''));
    if (!preg_match('/^\d{4}$/', $yearValue)) {
        throw new InvalidArgumentException('A four-digit year is required.');
    }
    $year = sprintf('%04d', (int) $yearValue);

    $lookup = $pdo->prepare('SELECT id FROM enrollment_years WHERE year = :year LIMIT 1');
    $lookup->execute([':year' => $year]);
    $folder = $lookup->fetch();
    if (!$folder) {
        throw new InvalidArgumentException('That year folder no longer exists.');
    }

    $pdo->beginTransaction();
    try {
        $students = $pdo->prepare('UPDATE students SET deleted_at = NOW() WHERE academic_year = :year AND deleted_at IS NULL');
        $students->execute([':year' => $year]);
        $studentCount = $students->rowCount();

        $delete = $pdo->prepare('DELETE FROM enrollment_years WHERE year = :year');
        $delete->execute([':year' => $year]);
        if ($delete->rowCount() !== 1) {
            throw new InvalidArgumentException('Unable to delete this year folder.');
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }


    return ['id' => (int) $folder['id'], 'year' => $year, 'status' => 'Deleted', 'students_moved' => $studentCount];
}
function handle_user_write(PDO $pdo, array $input): array
{
    $action = $input['action'] ?? '';

    $userId = (int) ($input['user_id'] ?? 0);
    $fullName = trim((string) ($input['full_name'] ?? ''));
    $idNumber = trim((string) ($input['id_number'] ?? ''));
    $email = strtolower(trim((string) ($input['email'] ?? '')));
    if ($action === 'create') {
        $password = (string) ($input['password'] ?? '');
        $role = trim((string) ($input['role'] ?? 'Admin'));
        if (!in_array($role, ['Admin', 'Staff'], true)) {
            throw new InvalidArgumentException('Choose a valid account type.');
        }
        if ($fullName === '' || mb_strlen($fullName) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
            throw new InvalidArgumentException('Enter a valid name and work email.');
        }
        if (mb_strlen($idNumber) > 30) {
            throw new InvalidArgumentException('ID number must be 30 characters or fewer.');
        }
        if (mb_strlen($password) < 8) {
            throw new InvalidArgumentException('Password must contain at least 8 characters.');
        }

        $duplicate = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $duplicate->execute([':email' => $email]);
        if ($duplicate->fetch()) {
            throw new InvalidArgumentException('That email address is already in use.');
        }
        $statement = $pdo->prepare('INSERT INTO users (full_name, id_number, email, role, password_hash, status) VALUES (:full_name, :id_number, :email, :role, :password_hash, :status)');
        $statement->execute([
            ':full_name' => $fullName,
            ':id_number' => $idNumber !== '' ? $idNumber : null,
            ':email' => $email,
            ':role' => $role,
            ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ':status' => 'Active',
        ]);
        $newUserId = (int) $pdo->lastInsertId();
        $actorId = (int) (dats_current_user()['id'] ?? 0);
        notify_all_users(
            $pdo,
            $actorId,
            'New account registered',
            sprintf('%s (ID: %s) was registered as a new %s account.', $fullName, $idNumber !== '' ? $idNumber : 'N/A', strtolower($role)),
            'users',
            $newUserId
        );
        return ['id' => $newUserId, 'full_name' => $fullName, 'id_number' => $idNumber, 'email' => $email, 'role' => $role, 'status' => 'Active'];
    }
    if ($userId < 1) {
        throw new InvalidArgumentException('User ID is required.');
    }
    if ($action === 'delete') {
        $currentUserId = (int) (dats_current_user()['id'] ?? 0);
        if ($userId === $currentUserId) {
            throw new InvalidArgumentException('You cannot deactivate your own account.');
        }
        $pdo->beginTransaction();
        try {
            $documents = $pdo->prepare('UPDATE documents SET uploaded_by = :replacement_id WHERE uploaded_by = :user_id');
            $documents->execute([':replacement_id' => $currentUserId, ':user_id' => $userId]);

            $statement = $pdo->prepare('DELETE FROM users WHERE id = :id');
            $statement->execute([':id' => $userId]);
            if ($statement->rowCount() !== 1) {
                throw new InvalidArgumentException('User not found.');
            }
            $pdo->commit();
        } catch (Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
        return ['id' => $userId, 'status' => 'Deleted'];
    }
    if ($action === 'update') {
        if (mb_strlen($idNumber) > 30) {
            throw new InvalidArgumentException('ID number must be 30 characters or fewer.');
        }
        $statement = $pdo->prepare('UPDATE users SET full_name = :full_name, id_number = :id_number, email = :email, status = :status WHERE id = :id');
        $statement->execute([':full_name' => $fullName, ':id_number' => $idNumber !== '' ? $idNumber : null, ':email' => $email, ':status' => $input['status'] ?? 'Active', ':id' => $userId]);
        return ['id' => $userId, 'full_name' => $fullName, 'id_number' => $idNumber, 'email' => $email, 'status' => $input['status'] ?? 'Active'];
    }
    throw new InvalidArgumentException('Unsupported user action.');
}

function get_colleges(PDO $pdo): array
{
    ensure_major_table($pdo);
    $colleges = $pdo->query("SELECT c.*, COUNT(DISTINCT co.id) AS course_count, COUNT(DISTINCT s.id) AS student_count FROM colleges c LEFT JOIN courses co ON co.college_id = c.id LEFT JOIN students s ON s.college_id = c.id AND s.deleted_at IS NULL WHERE c.status = 'Active' GROUP BY c.id ORDER BY c.name")->fetchAll();
    $courses = $pdo->query("SELECT co.id, co.college_id, co.name, co.short_name, co.status, COUNT(DISTINCT s.id) AS student_count FROM courses co LEFT JOIN students s ON s.course_id = co.id AND s.status <> 'Inactive' AND s.deleted_at IS NULL WHERE co.status = 'Active' GROUP BY co.id ORDER BY co.name")->fetchAll();
    $majors = $pdo->query("SELECT id, course_id, name FROM course_majors WHERE status = 'Active' ORDER BY name")->fetchAll();
    foreach ($colleges as &$college) {
        $college['courses'] = array_values(array_filter(array_map(static function (array $course) use ($majors): array {
            $course['majors'] = array_values(array_filter($majors, static fn (array $major): bool => (int) $major['course_id'] === (int) $course['id']));
            return $course;
        }, array_filter($courses, static fn (array $course): bool => (int) $course['college_id'] === (int) $college['id']))));
    }
    unset($college);
    return $colleges;
}

function ensure_major_table(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS course_majors (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        course_id BIGINT UNSIGNED NOT NULL,
        name VARCHAR(150) NOT NULL,
        status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_course_major_name (course_id, name),
        KEY fk_course_majors_course (course_id),
        CONSTRAINT fk_course_majors_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
}

function ensure_student_major_column(PDO $pdo): void
{
    $columns = $pdo->query("SHOW COLUMNS FROM students LIKE 'major_id'")->fetch();
    if (!$columns) {
        $pdo->exec('ALTER TABLE students ADD COLUMN major_id BIGINT UNSIGNED DEFAULT NULL AFTER course_id');
        $pdo->exec('ALTER TABLE students ADD KEY fk_students_major (major_id)');
        $pdo->exec('ALTER TABLE students ADD CONSTRAINT fk_students_major FOREIGN KEY (major_id) REFERENCES course_majors (id) ON DELETE SET NULL ON UPDATE CASCADE');
    }
}

function ensure_student_nationality_column(PDO $pdo): void
{
    $pdo->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS nationality VARCHAR(80) NULL AFTER address");
}

function ensure_user_id_number_column(PDO $pdo): void
{
    $column = $pdo->query("SHOW COLUMNS FROM users LIKE 'id_number'")->fetch();
    if (!$column) {
        $pdo->exec('ALTER TABLE users ADD COLUMN id_number VARCHAR(30) DEFAULT NULL AFTER full_name');
    }
}

function ensure_gradesheet_tables(PDO $pdo): void
{
    // document_id is nullable: legacy rows (created via a single-document upload) may still
    // reference one, but current inserts always come through a gradesheet_batches batch instead
    // (see ensure_gradesheet_batches_table()), so a fresh install creates it nullable directly.
    // student_id is nullable because a gradesheet lists students who may not be in the Students
    // directory yet; those are recorded by the name printed on the sheet (student_name).
    $pdo->exec("CREATE TABLE IF NOT EXISTS gradesheet_entries (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        document_id BIGINT UNSIGNED NULL,
        batch_id BIGINT UNSIGNED NULL,
        student_id BIGINT UNSIGNED NULL,
        student_name VARCHAR(160) NULL,
        student_number VARCHAR(30) NULL,
        year_course VARCHAR(80) NULL,
        academic_year YEAR NULL,
        semester VARCHAR(30) NULL,
        subject_code VARCHAR(20) NOT NULL,
        subject_name VARCHAR(255) NOT NULL,
        source_pages TEXT NULL,
        units DECIMAL(4,2) DEFAULT NULL,
        grade VARCHAR(10) DEFAULT NULL,
        remarks VARCHAR(100) DEFAULT NULL,
        needs_review TINYINT(1) NOT NULL DEFAULT 0,
        review_reasons VARCHAR(60) NULL,
        suggested_name VARCHAR(160) NULL,
        confirmed TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_gradesheet_student (student_id, academic_year, semester),
        KEY idx_gradesheet_student_name (student_name),
        KEY idx_gradesheet_document (document_id),
        KEY idx_gradesheet_batch (batch_id),
        CONSTRAINT fk_gradesheet_document FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_gradesheet_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
}

function ensure_gradesheet_category(PDO $pdo): void
{
    $existing = $pdo->query("SELECT id FROM document_categories WHERE code = 'GRD'")->fetchColumn();
    if ($existing) return;
    $pdo->exec("INSERT INTO document_categories (code, name, description, status) VALUES ('GRD', 'Grade Sheet', 'Per-semester subject grade report used to build a student\\'s digitized transcript.', 'Active')");
}

/**
 * gradesheet_batches holds one row per uploaded gradesheet file (a batch is one file — it can
 * cover many students, unlike `documents`, which is always owned by exactly one). Self-heals both
 * the new table and, for installs where gradesheet_entries already existed with the older
 * single-document-only shape (document_id NOT NULL, no batch_id), the columns it needs.
 */
function ensure_gradesheet_batches_table(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS gradesheet_batches (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        original_filename VARCHAR(255) NOT NULL,
        stored_filename VARCHAR(255) NULL,
        file_path VARCHAR(500) NULL,
        mime_type VARCHAR(100) NULL,
        file_size_bytes BIGINT UNSIGNED NULL,
        uploaded_by BIGINT UNSIGNED NOT NULL,
        ocr_status ENUM('Pending','Processing','Completed','Failed') NOT NULL DEFAULT 'Pending',
        ocr_text LONGTEXT NULL,
        ocr_error TEXT NULL,
        status ENUM('Pending Review','Needs Review','Completed','Failed') NOT NULL DEFAULT 'Pending Review',
        detected_student_count INT UNSIGNED NOT NULL DEFAULT 0,
        committed_entry_count INT UNSIGNED NOT NULL DEFAULT 0,
        uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        reviewed_at DATETIME NULL,
        PRIMARY KEY (id),
        KEY idx_batch_status (status, uploaded_at),
        CONSTRAINT fk_gradesheet_batch_uploader FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // Older installs created the status column without the 'Pending Review' value this draft/save
    // workflow needs (a batch that has been parsed but not yet confirmed by a reviewer).
    $statusColumn = $pdo->query("SHOW COLUMNS FROM gradesheet_batches LIKE 'status'")->fetch();
    if ($statusColumn && !str_contains((string) $statusColumn['Type'], "'Pending Review'")) {
        $pdo->exec("ALTER TABLE gradesheet_batches MODIFY status ENUM('Pending Review','Needs Review','Completed','Failed') NOT NULL DEFAULT 'Pending Review'");
    }

    // Migrations for installs that created gradesheet_entries with an older shape. Each is guarded
    // by a column check because this runs on every API request and an ALTER that "changes nothing"
    // can still rebuild the table.
    $columns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM gradesheet_entries')->fetchAll() as $column) {
        $columns[$column['Field']] = $column;
    }
    foreach (['document_id' => 'BIGINT UNSIGNED NULL', 'student_id' => 'BIGINT UNSIGNED NULL', 'academic_year' => 'YEAR NULL', 'semester' => 'VARCHAR(30) NULL'] as $name => $definition) {
        if (isset($columns[$name]) && $columns[$name]['Null'] !== 'YES') {
            $pdo->exec("ALTER TABLE gradesheet_entries MODIFY {$name} {$definition}");
        }
    }
    $additions = [
        'batch_id' => 'BIGINT UNSIGNED NULL AFTER document_id',
        'student_name' => 'VARCHAR(160) NULL AFTER student_id',
        'student_number' => 'VARCHAR(30) NULL AFTER student_name',
        'year_course' => 'VARCHAR(80) NULL AFTER student_number',
        'source_pages' => 'TEXT NULL AFTER subject_name',
        'needs_review' => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER remarks',
        // Separates "read from the file" from "a reviewer checked the table and saved it" — a
        // gradesheet's rows are recorded immediately on upload but stay invisible to student grade
        // search/lookup until confirmed=1, so a misread name never surfaces before someone checks it.
        // Why a row is flagged (name, grade, remarks, details) and the directory student a misread
        // name most resembles, so the review screen can show the concern and offer a one-click fix.
        'review_reasons' => 'VARCHAR(60) NULL AFTER needs_review',
        'suggested_name' => 'VARCHAR(160) NULL AFTER review_reasons',
        'confirmed' => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER needs_review',
    ];
    foreach ($additions as $name => $definition) {
        if (!isset($columns[$name])) {
            $pdo->exec("ALTER TABLE gradesheet_entries ADD COLUMN {$name} {$definition}");
            if ($name === 'student_name') $pdo->exec('ALTER TABLE gradesheet_entries ADD KEY idx_gradesheet_student_name (student_name)');
        }
    }
    $hasBatchForeignKey = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'gradesheet_entries' AND CONSTRAINT_NAME = 'fk_gradesheet_entries_batch'")->fetchColumn();
    if (!$hasBatchForeignKey) {
        $pdo->exec('ALTER TABLE gradesheet_entries ADD CONSTRAINT fk_gradesheet_entries_batch FOREIGN KEY (batch_id) REFERENCES gradesheet_batches (id) ON DELETE CASCADE ON UPDATE CASCADE');
    }
}

function validate_category_payload(PDO $pdo, array $input, ?int $excludeId = null): array
{
    $code = strtoupper(trim((string) ($input['code'] ?? '')));
    $name = trim((string) ($input['name'] ?? ''));
    $description = trim((string) ($input['description'] ?? ''));

    if ($name === '') {
        throw new InvalidArgumentException('Category name is required.');
    }

    $duplicateName = $pdo->prepare("SELECT id FROM document_categories WHERE status = 'Active' AND LOWER(TRIM(name)) = LOWER(:name) AND id != :exclude_id LIMIT 1");
    $duplicateName->execute([':name' => $name, ':exclude_id' => $excludeId ?? -1]);
    if ($duplicateName->fetch()) {
        throw new InvalidArgumentException('A category with that name already exists.');
    }

    if ($code !== '') {
        $duplicateCode = $pdo->prepare("SELECT id FROM document_categories WHERE status = 'Active' AND LOWER(TRIM(code)) = LOWER(:code) AND id != :exclude_id LIMIT 1");
        $duplicateCode->execute([':code' => $code, ':exclude_id' => $excludeId ?? -1]);
        if ($duplicateCode->fetch()) {
            throw new InvalidArgumentException('A category with that code already exists.');
        }
    }

    return [$code, $name, $description];
}

function create_category(PDO $pdo, array $input): array
{
    [$code, $name, $description] = validate_category_payload($pdo, $input);
    $creator = dats_current_user();

    $statement = $pdo->prepare('INSERT INTO document_categories (code, name, description, created_by) VALUES (:code, :name, :description, :created_by)');
    $statement->execute([
        ':code' => $code,
        ':name' => $name,
        ':description' => $description !== '' ? $description : null,
        ':created_by' => (int) ($creator['id'] ?? 1),
    ]);
    return $pdo->query('SELECT * FROM document_categories WHERE id = ' . (int) $pdo->lastInsertId())->fetch();
}

function update_category(PDO $pdo, array $input): array
{
    $categoryId = (int) ($input['id'] ?? 0);
    if ($categoryId < 1) {
        throw new InvalidArgumentException('Category ID is required.');
    }

    $existing = $pdo->query('SELECT * FROM document_categories WHERE id = ' . $categoryId)->fetch();
    if (!$existing) {
        throw new InvalidArgumentException('Category not found or already inactive.');
    }

    $name = trim((string) ($input['name'] ?? ''));
    if ($name === '') {
        throw new InvalidArgumentException('Category name is required.');
    }

    $duplicateName = $pdo->prepare("SELECT id FROM document_categories WHERE status = 'Active' AND LOWER(TRIM(name)) = LOWER(:name) AND id != :exclude_id LIMIT 1");
    $duplicateName->execute([':name' => $name, ':exclude_id' => $categoryId]);
    if ($duplicateName->fetch()) {
        throw new InvalidArgumentException('A category with that name already exists.');
    }

    // Code and description are no longer editable from this form; keep whatever the category already has.
    $code = $existing['code'];
    $description = $existing['description'];

    $statement = $pdo->prepare('UPDATE document_categories SET code = :code, name = :name, description = :description, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND status = "Active"');
    $statement->execute([
        ':code' => $code,
        ':name' => $name,
        ':description' => $description,
        ':id' => $categoryId,
    ]);

    return $pdo->query('SELECT * FROM document_categories WHERE id = ' . $categoryId)->fetch();
}

function upsert_enrollment_year(PDO $pdo, ?string $academicYear): void
{
    $normalizedYear = normalize_academic_year($academicYear);
    if ($normalizedYear === null) {
        return;
    }
    $statement = $pdo->prepare('INSERT IGNORE INTO enrollment_years (year) VALUES (:year)');
    $statement->execute([':year' => $normalizedYear]);
}

/**
 * Extracts a single valid YEAR(4) value from arbitrary academic-year input
 * ("2026-2027", "AY 2026-2027", "2026"), or null when nothing valid is found.
 * Never returns '' or '0000' — callers must bind null instead of storing those.
 */
function normalize_academic_year(?string $raw): ?string
{
    $value = trim((string) ($raw ?? ''));
    if ($value === '') {
        return null;
    }
    if (!preg_match('/\b(\d{4})\b/', $value, $matches)) {
        return null;
    }
    $yearNumber = (int) $matches[1];
    if ($yearNumber < 2000 || $yearNumber > 2100) {
        return null;
    }
    return sprintf('%04d', $yearNumber);
}

/** Validates an uploaded student CSV and returns its temp path. */
function dats_student_import_upload_path(?array $file): string
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('Choose a CSV file to import.');
    }
    if ((int) $file['size'] > DATS_STUDENT_IMPORT_MAX_BYTES) {
        throw new InvalidArgumentException('The file is larger than 5 MB. Split it into smaller files.');
    }
    if (!preg_match('/\.(csv|txt)$/i', (string) $file['name'])) {
        throw new InvalidArgumentException('Import a .csv file. In Excel use File > Save As > CSV.');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new InvalidArgumentException('Upload failed. Try again.');
    }
    return $file['tmp_name'];
}

function dats_student_import_default_year(array $input): string
{
    $year = trim((string) ($input['default_academic_year'] ?? ''));
    return preg_match('/^\d{4}$/', $year) && (int) $year >= 1901 && (int) $year <= 2155 ? $year : date('Y');
}

/** Dry run of a student CSV: nothing is created. */
function preview_student_import(PDO $pdo, ?array $file, array $input): array
{
    $plan = dats_student_import_plan($pdo, dats_student_import_upload_path($file), dats_student_import_default_year($input));
    return dats_student_import_preview_payload($plan);
}

/** Creates every valid, non-duplicate student in the CSV; the rest are reported, not created. */
function import_students(PDO $pdo, array $currentUser, ?array $file, array $input): array
{
    $plan = dats_student_import_plan($pdo, dats_student_import_upload_path($file), dats_student_import_default_year($input));
    $result = dats_student_import_commit($pdo, $plan, (int) ($currentUser['id'] ?? 0));
    return ['created' => $result['created'], 'skipped' => array_slice($result['skipped'], 0, 300), 'skipped_count' => count($result['skipped'])];
}

function create_student(PDO $pdo, array $input): array
{
    return dats_run_transaction($pdo, function (PDO $pdo) use ($input): array {
        $student = dats_validate_new_student_payload($input);
        $student = dats_resolve_intake_academic_hierarchy($pdo, $student);
        $student = dats_validate_student_hierarchy($pdo, $student);
        $studentId = dats_insert_intake_student($pdo, $student);
        upsert_enrollment_year($pdo, $student['academic_year']);
        $result = $pdo->prepare('SELECT * FROM students WHERE id = :id');
        $result->execute([':id' => $studentId]);
        return $result->fetch(PDO::FETCH_ASSOC);
    });
}

function validate_major_for_course(PDO $pdo, int $majorId, int $courseId): ?int
{
    if ($majorId < 1) {
        return null;
    }
    $statement = $pdo->prepare("SELECT id FROM course_majors WHERE id = :major_id AND course_id = :course_id AND status = 'Active'");
    $statement->execute([':major_id' => $majorId, ':course_id' => $courseId]);
    if (!$statement->fetch()) {
        throw new InvalidArgumentException('The selected major does not belong to this course.');
    }
    return $majorId;
}

function create_college(PDO $pdo, array $input, ?array $logo = null): array
{
    $name = trim((string) ($input['name'] ?? ''));
    if ($name === '') {
        throw new InvalidArgumentException('College name is required.');
    }
    $logoPath = store_college_logo($logo);
    $statement = $pdo->prepare('INSERT INTO colleges (name, short_name, logo_path) VALUES (:name, :short_name, :logo_path)');
    $statement->execute([':name' => $name, ':short_name' => trim((string) ($input['short_name'] ?? '')) ?: null, ':logo_path' => $logoPath]);
    return $pdo->query('SELECT * FROM colleges WHERE id = ' . (int) $pdo->lastInsertId())->fetch();
}

function update_college(PDO $pdo, array $input, ?array $logo = null): array
{
    $collegeId = (int) ($input['college_id'] ?? 0);
    $name = trim((string) ($input['name'] ?? ''));
    if ($collegeId < 1 || $name === '') {
        throw new InvalidArgumentException('College ID and name are required.');
    }
    $fields = 'name = :name';
    $parameters = [':name' => $name, ':id' => $collegeId];
    $logoPath = store_college_logo($logo);
    if ($logoPath) {
        $fields .= ', logo_path = :logo_path';
        $parameters[':logo_path'] = $logoPath;
    }
    $statement = $pdo->prepare("UPDATE colleges SET $fields WHERE id = :id AND status = 'Active'");
    $statement->execute($parameters);
    $result = $pdo->prepare('SELECT * FROM colleges WHERE id = :id');
    $result->execute([':id' => $collegeId]);
    $college = $result->fetch();
    if (!$college) {
        throw new InvalidArgumentException('College not found.');
    }
    return $college;
}

function store_college_logo(?array $logo): ?string
{
    if (!$logo || ($logo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($logo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || (int) ($logo['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('College logo must be an image no larger than 5 MB.');
    }
    if (!is_uploaded_file($logo['tmp_name']) || @getimagesize($logo['tmp_name']) === false) {
        throw new InvalidArgumentException('Upload a valid college logo image.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($logo['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime])) {
        throw new InvalidArgumentException('College logo must be JPG, PNG, GIF, or WEBP.');
    }
    $directory = __DIR__ . '/uploads/colleges';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create the college logo folder.');
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($logo['tmp_name'], $directory . '/' . $filename)) {
        throw new RuntimeException('Unable to save the college logo.');
    }
    return 'uploads/colleges/' . $filename;
}

function delete_college(PDO $pdo, array $input): array
{
    $collegeId = (int) ($input['college_id'] ?? 0);
    if ($collegeId < 1) {
        throw new InvalidArgumentException('College ID is required.');
    }

    $pdo->beginTransaction();
    try {
        $students = $pdo->prepare('UPDATE students SET course_id = NULL WHERE course_id IN (SELECT id FROM courses WHERE college_id = :college_id)');
        $students->execute([':college_id' => $collegeId]);
        $collegeStudents = $pdo->prepare('UPDATE students SET college_id = NULL WHERE college_id = :college_id');
        $collegeStudents->execute([':college_id' => $collegeId]);

        $courses = $pdo->prepare('DELETE FROM courses WHERE college_id = :college_id');
        $courses->execute([':college_id' => $collegeId]);

        $college = $pdo->prepare('DELETE FROM colleges WHERE id = :id');
        $college->execute([':id' => $collegeId]);
        if ($college->rowCount() !== 1) {
            throw new InvalidArgumentException('College not found.');
        }
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }

    return ['id' => $collegeId, 'status' => 'Deleted'];
}

function update_student(PDO $pdo, array $input): array
{
    $studentId = (int) ($input['student_id'] ?? 0);
    if ($studentId < 1) throw new InvalidArgumentException('Student ID is required.');
    return dats_run_transaction($pdo, function (PDO $pdo) use ($input, $studentId): array {
        $exists = $pdo->prepare('SELECT id FROM students WHERE id = :id LIMIT 1');
        $exists->execute([':id' => $studentId]);
        if (!$exists->fetchColumn()) throw new InvalidArgumentException('Student not found.');
        $student = dats_validate_new_student_payload($input);
        $student = dats_resolve_intake_academic_hierarchy($pdo, $student);
        $student = dats_validate_student_hierarchy($pdo, $student);
        $statement = $pdo->prepare('UPDATE students SET student_number = :student_number, first_name = :first_name, middle_initial = :middle_initial, last_name = :last_name, gender = :gender, birthdate = :birthdate, address = :address, nationality = :nationality, civil_status = :civil_status, contact_number = :contact_number, college_id = :college_id, course_id = :course_id, major_id = :major_id, academic_year = :academic_year, year_level = :year_level WHERE id = :id');
        foreach (['student_number', 'first_name', 'middle_initial', 'last_name', 'gender', 'birthdate', 'address', 'nationality', 'civil_status', 'contact_number', 'college_id', 'course_id', 'major_id', 'academic_year', 'year_level'] as $field) {
            $parameters[':' . $field] = $student[$field] ?? null;
        }
        $parameters[':id'] = $studentId;
        $statement->execute($parameters);
        upsert_enrollment_year($pdo, $student['academic_year']);
        $result = $pdo->prepare('SELECT * FROM students WHERE id = :id');
        $result->execute([':id' => $studentId]);
        return $result->fetch(PDO::FETCH_ASSOC);
    });
}

function ensure_student_deleted_column(PDO $pdo): void
{
    $pdo->exec('ALTER TABLE students ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL DEFAULT NULL AFTER status');

    // Recreates the dashboard analytics view so a trashed student stops counting
    // toward total_students. Wrapped defensively: a DB user without CREATE VIEW
    // privilege just keeps the old (slightly stale) view instead of breaking every request.
    try {
        $pdo->exec("CREATE OR REPLACE VIEW vw_archive_analytics AS SELECT
            (SELECT COUNT(0) FROM students WHERE status <> 'Inactive' AND deleted_at IS NULL) AS total_students,
            (SELECT COUNT(0) FROM documents WHERE status = 'Archived') AS total_documents,
            (SELECT COUNT(0) FROM colleges WHERE status = 'Active') AS total_colleges,
            (SELECT COUNT(0) FROM courses WHERE status = 'Active') AS total_courses,
            (SELECT COUNT(0) FROM documents WHERE status = 'Archived') AS completed_documents,
            (SELECT COUNT(0) FROM documents WHERE status = 'Processing') AS processing_documents,
            (SELECT COUNT(0) FROM documents WHERE status = 'Needs review') AS documents_needing_review");
    } catch (Throwable $ignored) {
        // Ignore when the connected DB user cannot alter views.
    }
}

function get_deleted_students(PDO $pdo): array
{
    $statement = $pdo->query("SELECT s.*, c.name AS college_name, co.name AS course_name, cm.name AS major_name FROM students s LEFT JOIN colleges c ON c.id = s.college_id LEFT JOIN courses co ON co.id = s.course_id LEFT JOIN course_majors cm ON cm.id = s.major_id WHERE s.deleted_at IS NOT NULL ORDER BY s.deleted_at DESC");
    return $statement->fetchAll();
}

function delete_student(PDO $pdo, array $input, array $currentUser): array
{
    $studentId = (int) ($input['student_id'] ?? $input['id'] ?? 0);
    if ($studentId < 1) {
        throw new InvalidArgumentException('Student ID is required.');
    }

    // A soft delete: the record and its files move to the recycle bin rather
    // than being destroyed immediately, so it can be restored from there.
    $statement = $pdo->prepare('UPDATE students SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL');
    $statement->execute([':id' => $studentId]);
    if ($statement->rowCount() !== 1) {
        throw new InvalidArgumentException('Student not found.');
    }

    return ['id' => $studentId, 'status' => 'Deleted'];
}

function restore_student(PDO $pdo, array $input): array
{
    $studentId = (int) ($input['student_id'] ?? $input['id'] ?? 0);
    if ($studentId < 1) {
        throw new InvalidArgumentException('Student ID is required.');
    }

    $statement = $pdo->prepare('UPDATE students SET deleted_at = NULL WHERE id = :id AND deleted_at IS NOT NULL');
    $statement->execute([':id' => $studentId]);
    if ($statement->rowCount() !== 1) {
        throw new InvalidArgumentException('Student not found in the recycle bin.');
    }

    $result = $pdo->prepare('SELECT * FROM students WHERE id = :id');
    $result->execute([':id' => $studentId]);
    return $result->fetch();
}

function purge_student(PDO $pdo, array $input, array $currentUser): array
{
    $studentId = (int) ($input['student_id'] ?? $input['id'] ?? 0);
    if ($studentId < 1) {
        throw new InvalidArgumentException('Student ID is required.');
    }

    // Only a student already sitting in the recycle bin can be purged, so this
    // is never reachable as a way to skip the confirm-then-restore-window flow.
    $lookup = $pdo->prepare('SELECT id, first_name, last_name FROM students WHERE id = :id AND deleted_at IS NOT NULL LIMIT 1');
    $lookup->execute([':id' => $studentId]);
    $studentRow = $lookup->fetch();
    if (!$studentRow) {
        throw new InvalidArgumentException('Student must be in the recycle bin before it can be permanently deleted.');
    }

    // The FK on documents.student_id has no ON DELETE CASCADE, so the files
    // (and their rows) are removed explicitly before the student record —
    // otherwise the delete would fail with a foreign key constraint error.
    $files = $pdo->prepare('SELECT file_path FROM documents WHERE student_id = :student_id AND file_path IS NOT NULL');
    $files->execute([':student_id' => $studentId]);
    $filePaths = $files->fetchAll(PDO::FETCH_COLUMN);

    $pdo->beginTransaction();
    try {
        $documents = $pdo->prepare('DELETE FROM documents WHERE student_id = :student_id');
        $documents->execute([':student_id' => $studentId]);

        $student = $pdo->prepare('DELETE FROM students WHERE id = :id');
        $student->execute([':id' => $studentId]);
        if ($student->rowCount() !== 1) {
            throw new InvalidArgumentException('Student not found.');
        }
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }

    foreach ($filePaths as $filePath) {
        $absolutePath = __DIR__ . '/' . ltrim((string) $filePath, '/');
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    $fileCount = count($filePaths);
    if ($fileCount > 0) {
        $studentName = trim((string) $studentRow['first_name'] . ' ' . (string) $studentRow['last_name']);
        notify_all_users(
            $pdo,
            (int) ($currentUser['id'] ?? 0),
            'Files deleted',
            sprintf('%d %s permanently deleted for %s.', $fileCount, $fileCount === 1 ? 'file was' : 'files were', $studentName !== '' ? $studentName : 'a student'),
            'students',
            $studentId
        );
    }

    return ['id' => $studentId, 'status' => 'Purged'];
}

function create_course(PDO $pdo, array $input): array
{
    $name = trim((string) ($input['name'] ?? ''));
    $collegeId = (int) ($input['college_id'] ?? 0);
    if ($name === '' || $collegeId < 1) {
        throw new InvalidArgumentException('Course name and college are required.');
    }
    $statement = $pdo->prepare('INSERT INTO courses (college_id, name, short_name) VALUES (:college_id, :name, :short_name)');
    $statement->execute([':college_id' => $collegeId, ':name' => $name, ':short_name' => trim((string) ($input['short_name'] ?? '')) ?: null]);
    return $pdo->query('SELECT * FROM courses WHERE id = ' . (int) $pdo->lastInsertId())->fetch();
}

function create_major(PDO $pdo, array $input): array
{
    ensure_major_table($pdo);
    $name = trim((string) ($input['name'] ?? ''));
    $courseId = (int) ($input['course_id'] ?? 0);
    if ($name === '' || $courseId < 1) {
        throw new InvalidArgumentException('Major name and course are required.');
    }
    $course = $pdo->prepare("SELECT id FROM courses WHERE id = :id AND status = 'Active'");
    $course->execute([':id' => $courseId]);
    if (!$course->fetch()) {
        throw new InvalidArgumentException('Course not found.');
    }
    $statement = $pdo->prepare('INSERT INTO course_majors (course_id, name) VALUES (:course_id, :name)');
    $statement->execute([':course_id' => $courseId, ':name' => $name]);
    return $pdo->query('SELECT id, course_id, name, status FROM course_majors WHERE id = ' . (int) $pdo->lastInsertId())->fetch();
}

function delete_major(PDO $pdo, array $input): array
{
    ensure_major_table($pdo);
    $majorId = (int) ($input['major_id'] ?? 0);
    if ($majorId < 1) {
        throw new InvalidArgumentException('Major ID is required.');
    }
    $statement = $pdo->prepare('DELETE FROM course_majors WHERE id = :id');
    $statement->execute([':id' => $majorId]);
    if ($statement->rowCount() !== 1) {
        throw new InvalidArgumentException('Major not found.');
    }
    return ['id' => $majorId, 'status' => 'Deleted'];
}

function update_course(PDO $pdo, array $input): array
{
    $courseId = (int) ($input['course_id'] ?? 0);
    $name = trim((string) ($input['name'] ?? ''));
    if ($courseId < 1 || $name === '') {
        throw new InvalidArgumentException('Course ID and name are required.');
    }
    $statement = $pdo->prepare("UPDATE courses SET name = :name, short_name = :short_name WHERE id = :id AND status = 'Active'");
    $statement->execute([':name' => $name, ':short_name' => trim((string) ($input['short_name'] ?? '')) ?: null, ':id' => $courseId]);
    $result = $pdo->prepare('SELECT * FROM courses WHERE id = :id');
    $result->execute([':id' => $courseId]);
    $course = $result->fetch();
    if (!$course) {
        throw new InvalidArgumentException('Course not found.');
    }
    return $course;
}

function delete_course(PDO $pdo, array $input): array
{
    $courseId = (int) ($input['course_id'] ?? 0);
    $collegeId = (int) ($input['college_id'] ?? 0);
    if ($collegeId < 1 && !empty($input['college_name'])) {
        $collegeLookup = $pdo->prepare('SELECT id FROM colleges WHERE name = :name LIMIT 1');
        $collegeLookup->execute([':name' => trim((string) $input['college_name'])]);
        $collegeId = (int) ($collegeLookup->fetchColumn() ?: 0);
    }
    if ($courseId < 1 && !empty($input['course_name']) && $collegeId > 0) {
        $lookup = $pdo->prepare('SELECT id FROM courses WHERE name = :name AND college_id = :college_id LIMIT 1');
        $lookup->execute([':name' => trim((string) $input['course_name']), ':college_id' => $collegeId]);
        $courseId = (int) ($lookup->fetchColumn() ?: 0);
    }
    if ($courseId < 1) {
        throw new InvalidArgumentException('Course ID is required.');
    }

    $pdo->beginTransaction();
    try {
        $students = $pdo->prepare('UPDATE students SET course_id = NULL WHERE course_id = :course_id');
        $students->execute([':course_id' => $courseId]);

        $statement = $pdo->prepare('DELETE FROM courses WHERE id = :id');
        $statement->execute([':id' => $courseId]);
        if ($statement->rowCount() !== 1) {
            throw new InvalidArgumentException('Course not found.');
        }
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }

    return ['id' => $courseId, 'status' => 'Deleted'];
}

function detect_server_ocr_binary(): ?string
{
    $candidates = [
        'C:\\Program Files\\Tesseract-OCR\\tesseract.exe',
        'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe',
        'C:\\xampp\\tesseract\\tesseract.exe',
        '/usr/bin/tesseract',
        '/usr/local/bin/tesseract',
    ];

    foreach ($candidates as $candidate) {
        if ($candidate !== '' && is_file($candidate) && is_executable($candidate)) {
            return $candidate;
        }
    }

    $path = getenv('PATH') ?: '';
    $pathEntries = array_filter(preg_split('/[:;]+/', $path) ?: []);
    foreach ($pathEntries as $entry) {
        $binary = rtrim($entry, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'tesseract';
        if (is_file($binary) && is_executable($binary)) {
            return $binary;
        }
    }

    $output = [];
    $code = 0;
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        exec('where.exe tesseract 2>NUL', $output, $code);
    } else {
        exec('which tesseract 2>/dev/null', $output, $code);
    }
    if ($code === 0 && !empty($output[0])) {
        return trim((string) $output[0]);
    }

    return null;
}

function detect_server_ghostscript_binary(): ?string
{
    $candidates = [
        '/usr/bin/gs',
        '/usr/local/bin/gs',
    ];
    foreach ($candidates as $candidate) {
        if ($candidate !== '' && is_file($candidate) && is_executable($candidate)) {
            return $candidate;
        }
    }
    // Ghostscript's Windows installer path is version-specific (C:\Program Files\gs\gs10.03.1\...),
    // so glob for it instead of hardcoding a version.
    foreach (['C:\\Program Files\\gs\\*\\bin\\gswin64c.exe', 'C:\\Program Files (x86)\\gs\\*\\bin\\gswin32c.exe'] as $pattern) {
        $matches = glob($pattern) ?: [];
        if (!empty($matches)) {
            rsort($matches);
            return $matches[0];
        }
    }

    $path = getenv('PATH') ?: '';
    $pathEntries = array_filter(preg_split('/[:;]+/', $path) ?: []);
    $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    $binaryName = $isWindows ? 'gswin64c.exe' : 'gs';
    foreach ($pathEntries as $entry) {
        $binary = rtrim($entry, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $binaryName;
        if (is_file($binary) && is_executable($binary)) {
            return $binary;
        }
    }

    $output = [];
    $code = 0;
    exec($isWindows ? 'where.exe gswin64c 2>NUL' : 'which gs 2>/dev/null', $output, $code);
    if ($code === 0 && !empty($output[0])) {
        return trim((string) $output[0]);
    }

    return null;
}

/**
 * Runs a shell command with a hard wall-clock timeout so a corrupt or oversized upload
 * can't hang an Apache worker indefinitely (plain exec() has no timeout of its own).
 * Returns [stdout, exitCode]; exitCode -1 means the process was killed for exceeding $timeoutSeconds.
 */
function run_shell_command_with_timeout(string $command, int $timeoutSeconds): array
{
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        return ['', 1];
    }
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $stdout = '';
    $start = time();
    while (true) {
        $stdout .= stream_get_contents($pipes[1]);
        $status = proc_get_status($process);
        if (!$status['running']) {
            break;
        }
        if (time() - $start > $timeoutSeconds) {
            proc_terminate($process, 9);
            usleep(200000);
            $stdout .= stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            return [$stdout, -1];
        }
        usleep(100000);
    }
    $stdout .= stream_get_contents($pipes[1]);

    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    return [$stdout, $exitCode];
}

function run_tesseract_on_image(string $binary, string $imagePath): ?string
{
    $safeBinary = str_replace(['"', "'"], '', $binary);
    $safeFile = str_replace(['"', "'"], '', $imagePath);
    [$output, $code] = run_shell_command_with_timeout(sprintf('"%s" "%s" stdout --psm 6 2>&1', $safeBinary, $safeFile), DATS_OCR_PAGE_TIMEOUT_SECONDS);
    $text = trim($output);
    return $code === 0 && $text !== '' ? $text : null;
}

/**
 * Rasterizes a PDF to per-page PNGs via Ghostscript (Tesseract cannot read PDFs natively),
 * or rasterization fails, so the caller can fall back to whatever the client already has.
 */
function run_server_ocr_on_pdf(string $tesseractBinary, string $pdfPath): ?string
{
    $ghostscript = detect_server_ghostscript_binary();
    if ($ghostscript === null) {
        return null;
    }
    $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dats_ocr_' . bin2hex(random_bytes(6));
    if (!mkdir($tempDir, 0755, true)) {
        return null;
    }
    try {
        $safeGhostscript = str_replace(['"', "'"], '', $ghostscript);
        $safePdf = str_replace(['"', "'"], '', $pdfPath);
        $outputPattern = $tempDir . DIRECTORY_SEPARATOR . 'page-%03d.png';
        $command = sprintf(
            '"%s" -q -dSAFER -dBATCH -dNOPAUSE -sDEVICE=png16m -r300 -dLastPage=%d -sOutputFile="%s" "%s" 2>&1',
            $safeGhostscript,
            DATS_OCR_MAX_PDF_PAGES,
            $outputPattern,
            $safePdf
        );
        [, $code] = run_shell_command_with_timeout($command, DATS_OCR_RASTER_TIMEOUT_SECONDS);
        $pages = glob($tempDir . DIRECTORY_SEPARATOR . 'page-*.png') ?: [];
        if ($code !== 0 || empty($pages)) {
            return null;
        }
        sort($pages);
        $textChunks = [];
        foreach ($pages as $pagePath) {
            $pageText = run_tesseract_on_image($tesseractBinary, $pagePath);
            if ($pageText !== null) {
                $textChunks[] = $pageText;
            }
        }
        return $textChunks !== [] ? implode("\n\n", $textChunks) : null;
    } finally {
        foreach (glob($tempDir . DIRECTORY_SEPARATOR . '*') ?: [] as $leftoverFile) {
            @unlink($leftoverFile);
        }
        @rmdir($tempDir);
    }
}

function run_server_ocr_if_available(string $filePath, ?string $mimeType = null, array $existingInput = []): array
{
    $existingText = trim((string) ($existingInput['ocr_text'] ?? ''));
    $existingStatus = trim((string) ($existingInput['ocr_status'] ?? 'Processing'));
    $deferToClient = function (string $reason) use ($existingText, $existingStatus, $existingInput): array {
        return [
            'ocr_text' => $existingText,
            'ocr_status' => in_array($existingStatus, ['Pending', 'Processing', 'Completed', 'Failed'], true) ? $existingStatus : 'Processing',
            'ocr_error' => $existingText !== '' ? null : $reason,
            'ai_summary' => trim((string) ($existingInput['ai_summary'] ?? '')),
            'ai_detected_category' => trim((string) ($existingInput['ai_detected_category'] ?? '')),
        ];
    };

    $binary = detect_server_ocr_binary();
    if ($binary === null) {
        return $deferToClient('Server OCR is not installed. Install Tesseract OCR to enable backend OCR processing.');
    }

    $mime = strtolower((string) ($mimeType ?? ''));
    if ($mime === 'application/pdf') {
        $pdfText = run_server_ocr_on_pdf($binary, $filePath);
        if ($pdfText === null) {
            return $deferToClient('Install Ghostscript alongside Tesseract to OCR PDF files server-side; the browser will process this PDF instead.');
        }
        return ['ocr_text' => $pdfText, 'ocr_status' => 'Completed', 'ocr_error' => null, 'ai_summary' => '', 'ai_detected_category' => ''];
    }

    $ocrText = run_tesseract_on_image($binary, $filePath);
    if ($ocrText === null) {
        $fallbackText = $existingText;
        return [
            'ocr_text' => $fallbackText,
            'ocr_status' => $fallbackText !== '' ? 'Completed' : 'Failed',
            'ocr_error' => $fallbackText !== '' ? null : 'Backend OCR could not extract readable text from the uploaded file.',
            'ai_summary' => trim((string) ($existingInput['ai_summary'] ?? '')),
            'ai_detected_category' => trim((string) ($existingInput['ai_detected_category'] ?? '')),
        ];
    }

    return ['ocr_text' => $ocrText, 'ocr_status' => 'Completed', 'ocr_error' => null, 'ai_summary' => '', 'ai_detected_category' => ''];
}

/**
 * Validates an uploaded document against the app's allow-list/size cap using the file's
 * real sniffed MIME type (never the client-supplied one), the same defense-in-depth
 * pattern already used for college logos in store_college_logo().
 */
function validate_uploaded_document_file(array $file): string
{
    $size = (int) ($file['size'] ?? 0);
    if ($size < 1) {
        throw new InvalidArgumentException('The uploaded file is empty.');
    }
    if ($size > DATS_MAX_DOCUMENT_BYTES) {
        throw new InvalidArgumentException('The uploaded file exceeds the 25 MB limit.');
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $finfo ? (finfo_file($finfo, (string) $file['tmp_name']) ?: '') : '';
    if ($finfo) {
        finfo_close($finfo);
    }
    if (!in_array($mimeType, DATS_ALLOWED_DOCUMENT_MIME_TYPES, true)) {
        throw new InvalidArgumentException('Unsupported file type. Only JPG, PNG, and PDF documents are allowed.');
    }
    return $mimeType;
}

function handle_document_ocr(PDO $pdo, ?array $file = null, array $input = []): array
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('Select a file before OCR processing.');
    }
    $sniffedMimeType = validate_uploaded_document_file($file);

    $uploadDirectory = __DIR__ . '/uploads';
    if (!is_dir($uploadDirectory)) {
        mkdir($uploadDirectory, 0755, true);
    }

    $storedFilename = bin2hex(random_bytes(12)) . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', basename((string) $file['name']));
    $filePath = $uploadDirectory . '/' . $storedFilename;
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        throw new RuntimeException('Unable to store the uploaded file for OCR processing.');
    }

    $result = run_server_ocr_if_available($filePath, $sniffedMimeType, $input);
    $ocrText = trim((string) ($result['ocr_text'] ?? ''));
    if ($ocrText === '' && ($result['ocr_status'] ?? 'Failed') === 'Completed') {
        $result['ocr_status'] = 'Failed';
        $result['ocr_error'] = 'No readable text could be extracted from the uploaded document.';
    }

    return [
        'ocr_text' => $ocrText,
        'ocr_status' => $result['ocr_status'] ?? 'Failed',
        'ocr_error' => $result['ocr_error'] ?? null,
        'ai_summary' => $result['ai_summary'] ?? '',
        'ai_detected_category' => $result['ai_detected_category'] ?? '',
    ];
}

function get_gradesheet_batches(PDO $pdo): array
{
    return $pdo->query('SELECT b.id, b.original_filename, b.mime_type, b.file_size_bytes, b.ocr_status, b.ocr_error, b.status, b.detected_student_count, b.committed_entry_count, b.uploaded_at, b.reviewed_at, u.full_name AS uploaded_by_name, (SELECT COUNT(*) FROM gradesheet_entries g WHERE g.batch_id = b.id AND g.needs_review = 1) AS needs_review_count FROM gradesheet_batches b LEFT JOIN users u ON u.id = b.uploaded_by ORDER BY b.uploaded_at DESC, b.id DESC LIMIT 100')->fetchAll();
}

function get_gradesheet_batch_entries(PDO $pdo, int $batchId): array
{
    if ($batchId < 1) {
        throw new InvalidArgumentException('Gradesheet batch ID is required.');
    }
    $batch = $pdo->prepare('SELECT id, original_filename, status, detected_student_count, committed_entry_count, ocr_error, uploaded_at FROM gradesheet_batches WHERE id = :id');
    $batch->execute([':id' => $batchId]);
    $row = $batch->fetch();
    if (!$row) {
        throw new InvalidArgumentException('Gradesheet batch not found.');
    }
    $entries = $pdo->prepare("SELECT g.*, CASE WHEN s.id IS NULL THEN NULL ELSE CONCAT(s.last_name, ', ', s.first_name) END AS directory_name FROM gradesheet_entries g LEFT JOIN students s ON s.id = g.student_id WHERE g.batch_id = :id ORDER BY g.needs_review DESC, g.subject_code, g.student_name");
    $entries->execute([':id' => $batchId]);
    return ['batch' => $row, 'entries' => $entries->fetchAll()];
}

/**
 * Links a name/number read from a gradesheet to a Students-directory record — but only on an
 * unambiguous match (exact student number, or exact surname + first given name belonging to one
 * student). Anything less stays unlinked and is still recorded under the printed name, since
 * attaching grades to the wrong student would be worse than not linking at all.
 */
function dats_gradesheet_student_matcher(PDO $pdo): callable
{
    $normalize = static fn (?string $value): string => preg_replace('/[^A-Z]/', '', strtoupper((string) $value)) ?? '';
    $byNumber = [];
    $byName = [];
    foreach ($pdo->query('SELECT id, student_number, first_name, last_name FROM students WHERE deleted_at IS NULL')->fetchAll() as $student) {
        if ($student['student_number']) $byNumber[strtoupper(trim($student['student_number']))] = (int) $student['id'];
        $firstGiven = $normalize(strtok((string) $student['first_name'], ' ') ?: '');
        $key = $normalize($student['last_name']) . '|' . $firstGiven;
        $byName[$key] = isset($byName[$key]) ? 0 : (int) $student['id']; // 0 marks an ambiguous name
    }
    return static function (string $name, ?string $number) use ($byNumber, $byName, $normalize): ?int {
        if ($number !== null && isset($byNumber[strtoupper(trim($number))])) return $byNumber[strtoupper(trim($number))];
        if (!str_contains($name, ',')) return null;
        [$surname, $given] = array_map('trim', explode(',', $name, 2));
        $key = $normalize($surname) . '|' . $normalize(strtok($given, ' ') ?: '');
        return !empty($byName[$key]) ? $byName[$key] : null;
    };
}

/** Students-directory names, prepared for matching misread gradesheet names against them. */
function dats_gradesheet_roster(PDO $pdo): array
{
    $students = [];
    foreach ($pdo->query('SELECT id, first_name, middle_initial, last_name FROM students WHERE deleted_at IS NULL')->fetchAll() as $student) {
        $student['display'] = dats_format_student_name($student['last_name'] . ', ' . $student['first_name'] . ($student['middle_initial'] ? ' ' . $student['middle_initial'] : ''));
        $students[] = $student;
    }
    return dats_gradesheet_prepare_roster($students);
}

function dats_gradesheet_clip(?string $value, int $length): ?string
{
    if ($value === null) return null;
    $value = trim($value);
    if ($value === '') return null;
    return function_exists('mb_substr') ? mb_substr($value, 0, $length) : substr($value, 0, $length);
}

/**
 * Parses a batch's OCR text and records EVERY detected student's grades immediately — replacing
 * whatever this batch recorded before, so a re-scan never duplicates rows. Records the parser was
 * unsure about (unreadable handwriting, a garbled name, a missing header) are saved flagged
 * needs_review instead of being dropped.
 */
function dats_record_gradesheet_batch(PDO $pdo, int $batchId, string $ocrText, ?string $ocrError): array
{
    $records = trim($ocrText) === '' ? [] : dats_extract_gradesheet_records($ocrText);
    // Correct misread names against the Students directory before anything is stored.
    $records = dats_gradesheet_apply_roster($records, dats_gradesheet_roster($pdo));
    $match = dats_gradesheet_student_matcher($pdo);

    return dats_run_transaction($pdo, function (PDO $pdo) use ($batchId, $records, $match, $ocrError): array {
        $pdo->prepare('DELETE FROM gradesheet_entries WHERE batch_id = :id')->execute([':id' => $batchId]);
        // confirmed stays 0 here even on a re-scan of a previously-saved batch: fresh OCR output
        // must be reviewed and saved again before it replaces what was visible before.
        $insert = $pdo->prepare('INSERT INTO gradesheet_entries (batch_id, student_id, student_name, student_number, year_course, academic_year, semester, subject_code, subject_name, source_pages, units, grade, remarks, needs_review, review_reasons, suggested_name, confirmed) VALUES (:batch_id, :student_id, :student_name, :student_number, :year_course, :academic_year, :semester, :subject_code, :subject_name, :source_pages, :units, :grade, :remarks, :needs_review, :review_reasons, :suggested_name, 0)');
        $students = [];
        $linked = 0;
        $review = 0;
        foreach ($records as $record) {
            $name = dats_gradesheet_clip($record['student_name'] ?? null, 160) ?? 'Unidentified student';
            $studentId = !empty($record['student_id_hint']) ? (int) $record['student_id_hint'] : $match($name, $record['student_number'] ?? null);
            $academicYear = normalize_academic_year((string) ($record['academic_year'] ?? ''));
            $semester = in_array($record['semester'] ?? null, DATS_GRADESHEET_SEMESTERS, true) ? $record['semester'] : null;
            $units = isset($record['units']) && is_numeric($record['units']) && (float) $record['units'] >= 0 && (float) $record['units'] <= 99.99 ? (float) $record['units'] : null;
            $needsReview = !empty($record['needs_review']) || $academicYear === null || $semester === null;
            $reasons = dats_gn_reasons($record);
            if ($needsReview && !$reasons) $reasons = ['details'];
            $insert->execute([
                ':batch_id' => $batchId,
                ':student_id' => $studentId,
                ':student_name' => $name,
                ':student_number' => dats_gradesheet_clip($record['student_number'] ?? null, 30),
                ':year_course' => dats_gradesheet_clip($record['year_course'] ?? null, 80),
                ':academic_year' => $academicYear,
                ':semester' => $semester,
                ':subject_code' => dats_gradesheet_clip($record['subject_code'] ?? null, 20) ?? '',
                ':subject_name' => dats_gradesheet_clip($record['subject_name'] ?? null, 255) ?? '',
                ':source_pages' => json_encode(array_values(array_unique(array_filter(array_map('intval', (array) ($record['source_pages'] ?? [])), static fn (int $page): bool => $page > 0))), JSON_THROW_ON_ERROR),
                ':units' => $units,
                ':grade' => dats_gradesheet_clip($record['grade'] ?? null, 10),
                ':remarks' => dats_gradesheet_clip($record['remarks'] ?? null, 100),
                ':needs_review' => $needsReview ? 1 : 0,
                ':review_reasons' => $reasons ? implode(',', $reasons) : null,
                ':suggested_name' => dats_gradesheet_clip($record['suggested_name'] ?? null, 160),
            ]);
            $students[$studentId ? "id:{$studentId}" : 'name:' . strtolower($name)] = true;
            if ($studentId) $linked += 1;
            if ($needsReview) $review += 1;
        }

        $entryCount = count($records);
        // Nothing is confirmed yet after a (re)parse, however clean it looks — the reviewer always
        // gets a chance to check the table before it is saved and becomes visible in student search.
        $status = $entryCount === 0 ? 'Failed' : 'Pending Review';
        $error = $entryCount === 0
            ? ($ocrError ?: 'No student grade rows could be read from this file. Check that it is a gradesheet and the scan is legible, then use Re-scan.')
            : null;
        $update = $pdo->prepare('UPDATE gradesheet_batches SET status = :status, detected_student_count = :students, committed_entry_count = :entries, ocr_error = :error, reviewed_at = NULL WHERE id = :id');
        $update->execute([':status' => $status, ':students' => count($students), ':entries' => $entryCount, ':error' => $error, ':id' => $batchId]);

        return ['students' => count($students), 'entries' => $entryCount, 'needs_review' => $review, 'linked_entries' => $linked, 'status' => $status];
    });
}

/** OCR / Transcription text from Gemini AI — or browser OCR — or server OCR fallback. */
function dats_resolve_gradesheet_ocr(array $input, string $absolutePath, string $mimeType): array
{
    if (dats_gemini_is_available()) {
        try {
            $aiText = dats_gemini_transcribe_gradesheet($absolutePath, $mimeType);
            if ($aiText !== null && trim($aiText) !== '') {
                return [
                    'ocr_text' => $aiText,
                    'ocr_status' => 'Completed',
                    'ocr_error' => null,
                ];
            }
        } catch (Throwable $error) {
            error_log('Gemini transcription error: ' . $error->getMessage());
        }
    }

    $clientText = isset($input['ocr_text']) ? trim((string) $input['ocr_text']) : '';
    if (($input['ocr_status'] ?? '') === 'Completed' && $clientText !== '') {
        return ['ocr_text' => $clientText, 'ocr_status' => 'Completed', 'ocr_error' => null];
    }
    $ocr = run_server_ocr_if_available($absolutePath, $mimeType, []);
    $text = trim((string) ($ocr['ocr_text'] ?? ''));
    $clientError = trim((string) ($input['ocr_error'] ?? ''));
    return [
        'ocr_text' => $text,
        'ocr_status' => $text !== '' ? 'Completed' : 'Failed',
        'ocr_error' => $text !== '' ? null : ($clientError !== '' ? $clientError : ($ocr['ocr_error'] ?? 'No readable text could be extracted.')),
    ];
}

function upload_gradesheet_batch(PDO $pdo, array $currentUser, array $input, ?array $file): array
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('Select a gradesheet file to upload.');
    }
    $sniffedMimeType = validate_uploaded_document_file($file);
    $directory = __DIR__ . '/uploads/gradesheet-batches';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create the gradesheet upload folder.');
    }
    $storedFilename = bin2hex(random_bytes(12)) . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', basename((string) $file['name']));
    $absolutePath = $directory . '/' . $storedFilename;
    if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
        throw new RuntimeException('Unable to store the uploaded file.');
    }

    try {
        $ocr = dats_resolve_gradesheet_ocr($input, $absolutePath, $sniffedMimeType);
        $statement = $pdo->prepare("INSERT INTO gradesheet_batches (original_filename, stored_filename, file_path, mime_type, file_size_bytes, uploaded_by, ocr_status, ocr_text, ocr_error, status) VALUES (:original_filename, :stored_filename, :file_path, :mime_type, :file_size_bytes, :uploaded_by, :ocr_status, :ocr_text, :ocr_error, 'Pending Review')");
        $statement->execute([
            ':original_filename' => dats_gradesheet_clip((string) $file['name'], 255) ?? 'gradesheet',
            ':stored_filename' => $storedFilename,
            ':file_path' => 'uploads/gradesheet-batches/' . $storedFilename,
            ':mime_type' => $sniffedMimeType,
            ':file_size_bytes' => (int) $file['size'],
            ':uploaded_by' => (int) ($currentUser['id'] ?? 0),
            ':ocr_status' => $ocr['ocr_status'],
            ':ocr_text' => $ocr['ocr_text'] === '' ? null : $ocr['ocr_text'],
            ':ocr_error' => $ocr['ocr_error'],
        ]);
    } catch (Throwable $error) {
        dats_remove_new_upload($absolutePath);
        throw $error;
    }
    $batchId = (int) $pdo->lastInsertId();
    $summary = dats_record_gradesheet_batch($pdo, $batchId, $ocr['ocr_text'], $ocr['ocr_error']);
    return [
        'batch' => dats_find_gradesheet_batch($pdo, $batchId),
        'summary' => $summary,
        'entries' => $summary['entries'] ?? 0,
        'students' => $summary['students'] ?? 0,
        'needs_review' => $summary['needs_review'] ?? 0,
        'linked_entries' => $summary['linked_entries'] ?? 0,
        'status' => $summary['status'] ?? 'Pending Review',
    ];
}

/**
 * Re-reads a stored batch with fresh OCR text (the browser re-runs OCR on the stored file) and
 * re-records its students, replacing the previous rows. Lets an admin recover a batch that was
 * read badly without re-uploading it — and keeps manual edits only if they re-scan by choice.
 */
function rescan_gradesheet_batch(PDO $pdo, array $input): array
{
    $batchId = (int) ($input['batch_id'] ?? 0);
    $batch = $batchId > 0 ? dats_find_gradesheet_batch($pdo, $batchId, true) : null;
    if (!$batch) {
        throw new InvalidArgumentException('Gradesheet batch not found.');
    }
    $absolutePath = safe_document_path($batch['file_path']);
    if (!$absolutePath || !is_file($absolutePath)) {
        throw new RuntimeException('The stored gradesheet file is missing, so it cannot be re-scanned. Upload it again.');
    }
    $ocr = dats_resolve_gradesheet_ocr($input, $absolutePath, (string) $batch['mime_type']);
    $update = $pdo->prepare('UPDATE gradesheet_batches SET ocr_status = :ocr_status, ocr_text = :ocr_text, ocr_error = :ocr_error WHERE id = :id');
    $update->execute([':ocr_status' => $ocr['ocr_status'], ':ocr_text' => $ocr['ocr_text'] === '' ? null : $ocr['ocr_text'], ':ocr_error' => $ocr['ocr_error'], ':id' => $batchId]);
    $summary = dats_record_gradesheet_batch($pdo, $batchId, $ocr['ocr_text'], $ocr['ocr_error']);
    return [
        'batch' => dats_find_gradesheet_batch($pdo, $batchId),
        'summary' => $summary,
        'entries' => $summary['entries'] ?? 0,
        'students' => $summary['students'] ?? 0,
        'needs_review' => $summary['needs_review'] ?? 0,
        'linked_entries' => $summary['linked_entries'] ?? 0,
        'status' => $summary['status'] ?? 'Pending Review',
    ];
}

/**
 * Confirms a batch's extracted rows after a reviewer has checked them on the Upload history
 * table/modal — only then do they become visible in student grade search (see the `confirmed`
 * filter in dats_gradesheet_people()/get_student_gradesheet()). Rows still flagged needs_review
 * can be saved as-is; that flag only controls the "to check" badge, not visibility.
 */
function save_gradesheet_batch(PDO $pdo, array $input): array
{
    $batchId = (int) ($input['batch_id'] ?? 0);
    $batch = $batchId > 0 ? dats_find_gradesheet_batch($pdo, $batchId) : null;
    if (!$batch) {
        throw new InvalidArgumentException('Gradesheet batch not found.');
    }
    $count = $pdo->prepare('SELECT COUNT(*) FROM gradesheet_entries WHERE batch_id = :id');
    $count->execute([':id' => $batchId]);
    if ((int) $count->fetchColumn() === 0) {
        throw new InvalidArgumentException('There is nothing to save yet — this upload has no readable grade records.');
    }
    $pdo->prepare('UPDATE gradesheet_entries SET confirmed = 1 WHERE batch_id = :id')->execute([':id' => $batchId]);
    $pdo->prepare('UPDATE gradesheet_batches SET reviewed_at = NOW() WHERE id = :id')->execute([':id' => $batchId]);
    dats_refresh_gradesheet_batch_status($pdo, $batchId);
    return ['batch' => dats_find_gradesheet_batch($pdo, $batchId)];
}

function delete_gradesheet_batch(PDO $pdo, array $input): array
{
    $batchId = (int) ($input['batch_id'] ?? 0);
    $batch = $batchId > 0 ? dats_find_gradesheet_batch($pdo, $batchId, true) : null;
    if (!$batch) {
        throw new InvalidArgumentException('Gradesheet batch not found.');
    }
    // Entries go with the batch via ON DELETE CASCADE.
    $pdo->prepare('DELETE FROM gradesheet_batches WHERE id = :id')->execute([':id' => $batchId]);
    $absolutePath = safe_document_path($batch['file_path']);
    if ($absolutePath && is_file($absolutePath)) @unlink($absolutePath);
    return ['batch_id' => $batchId, 'status' => 'Deleted'];
}

function dats_find_gradesheet_batch(PDO $pdo, int $batchId, bool $withStorage = false): ?array
{
    $columns = 'id, original_filename, mime_type, file_size_bytes, ocr_status, ocr_error, status, detected_student_count, committed_entry_count, uploaded_at, reviewed_at' . ($withStorage ? ', file_path' : '');
    $statement = $pdo->prepare("SELECT {$columns} FROM gradesheet_batches WHERE id = :id");
    $statement->execute([':id' => $batchId]);
    return $statement->fetch() ?: null;
}

/** Streams a batch's stored file to the browser (used to re-run OCR on it for a re-scan). */
function handle_gradesheet_batch_file(PDO $pdo, int $batchId): never
{
    $batch = $batchId > 0 ? dats_find_gradesheet_batch($pdo, $batchId, true) : null;
    $absolutePath = $batch ? safe_document_path($batch['file_path']) : null;
    if (!$absolutePath || !is_file($absolutePath)) {
        dats_json(['error' => 'Gradesheet file not found.'], 404);
    }
    header('Content-Type: ' . (in_array($batch['mime_type'], DATS_ALLOWED_DOCUMENT_MIME_TYPES, true) ? $batch['mime_type'] : 'application/octet-stream'));
    header('Content-Length: ' . filesize($absolutePath));
    header('Content-Disposition: inline; filename="' . str_replace(['"', "\r", "\n"], '', (string) $batch['original_filename']) . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    readfile($absolutePath);
    exit;
}

function create_document(PDO $pdo, array $input, ?array $file = null): array
{
    dats_require_document_upload($input, $file);
    $currentUser = dats_current_user();
    if (empty($input['original_filename'])) throw new InvalidArgumentException('A file name is required.');
    $storedFilename = $input['stored_filename'] ?? null;
    $filePath = $input['file_path'] ?? null;
    $newUploadAbsolutePath = null;
    $createdArchiveAbsolutePath = null;
    $ocrText = isset($input['ocr_text']) ? trim((string) $input['ocr_text']) : null;
    $ocrStatusInput = (string) ($input['ocr_status'] ?? 'Processing');
    $ocrStatus = in_array($ocrStatusInput, ['Pending', 'Processing', 'Completed', 'Failed'], true) ? $ocrStatusInput : 'Processing';
    $ocrError = isset($input['ocr_error']) ? trim((string) $input['ocr_error']) : null;
    $aiSummary = isset($input['ai_summary']) ? trim((string) $input['ai_summary']) : null;
    $aiDetectedCategory = isset($input['ai_detected_category']) ? trim((string) $input['ai_detected_category']) : null;
    $serverOcr = ['ocr_text' => $ocrText, 'ocr_status' => $ocrStatus, 'ocr_error' => $ocrError, 'ai_summary' => $aiSummary, 'ai_detected_category' => $aiDetectedCategory];
    try {
        if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $sniffedMimeType = validate_uploaded_document_file($file);
            $uploadDirectory = __DIR__ . '/uploads';
            if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
                throw new RuntimeException('Unable to create the upload folder.');
            }
            $storedFilename = bin2hex(random_bytes(12)) . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', basename((string) $file['name']));
            $filePath = 'uploads/' . $storedFilename;
            $newUploadAbsolutePath = $uploadDirectory . '/' . $storedFilename;
            if (!move_uploaded_file($file['tmp_name'], $newUploadAbsolutePath)) throw new RuntimeException('Unable to store the uploaded file.');

            $clientAlreadyCompletedOcr = $ocrStatus === 'Completed' && $ocrText !== '' && $ocrText !== null;
            $serverOcr = $clientAlreadyCompletedOcr
                ? ['ocr_text' => $ocrText, 'ocr_status' => $ocrStatus, 'ocr_error' => $ocrError, 'ai_summary' => $aiSummary, 'ai_detected_category' => $aiDetectedCategory]
                : run_server_ocr_if_available($newUploadAbsolutePath, $sniffedMimeType, $input);
            if (trim((string) ($serverOcr['ocr_text'] ?? '')) !== '') {
                $ocrText = trim((string) $serverOcr['ocr_text']);
                $ocrStatus = $serverOcr['ocr_status'];
                $ocrError = $serverOcr['ocr_error'];
                $aiSummary = $serverOcr['ai_summary'] ?: $aiSummary;
                $aiDetectedCategory = $serverOcr['ai_detected_category'] ?: $aiDetectedCategory;
            } elseif ($ocrText === '' || $ocrText === null) {
                $ocrText = trim((string) ($serverOcr['ocr_text'] ?? ''));
                $ocrStatus = $serverOcr['ocr_status'];
                $ocrError = $serverOcr['ocr_error'];
            }
        }

        $contentFingerprint = dats_document_content_fingerprint($ocrText, $newUploadAbsolutePath);
        $id = dats_run_transaction($pdo, function (PDO $pdo) use ($input, $currentUser, &$storedFilename, &$filePath, &$newUploadAbsolutePath, &$createdArchiveAbsolutePath, $ocrStatus, $ocrText, $ocrError, $aiSummary, $aiDetectedCategory, $contentFingerprint): int {
            $student = dats_resolve_intake_student($pdo, $input);
            $categoryId = (int) ($input['category_id'] ?? 0);
            if ($categoryId > 0) {
                $category = $pdo->prepare("SELECT id FROM document_categories WHERE id = :id AND status = 'Active'");
                $category->execute([':id' => $categoryId]);
                $categoryId = (int) ($category->fetchColumn() ?: 0);
            } elseif (!empty($input['category_code'])) {
                $category = $pdo->prepare("SELECT id FROM document_categories WHERE code = :code AND status = 'Active'");
                $category->execute([':code' => strtoupper(trim((string) $input['category_code']))]);
                $categoryId = (int) ($category->fetchColumn() ?: 0);
            }
            if ($categoryId < 1) throw new InvalidArgumentException('Select an active document type.');

            $duplicate = dats_find_duplicate_document($pdo, (int) $student['student_id'], $ocrText, $contentFingerprint['hash'], $contentFingerprint['type']);
            if ($duplicate) throw new DatsDuplicateDocumentException($duplicate);

            $sourcePath = safe_document_path($filePath);
            if (!$sourcePath || !is_file($sourcePath)) {
                throw new RuntimeException('The uploaded document could not be found for archiving.');
            }
            $target = dats_archive_target($filePath, $storedFilename, normalize_academic_year((string) $student['academic_year']));
            $archiveDirectory = __DIR__ . '/' . $target['directory'];
            if (!is_dir($archiveDirectory) && !mkdir($archiveDirectory, 0755, true) && !is_dir($archiveDirectory)) {
                throw new RuntimeException('Unable to create the academic-year archive folder.');
            }
            $createdArchiveAbsolutePath = __DIR__ . '/' . $target['relative_path'];
            if (!rename($sourcePath, $createdArchiveAbsolutePath)) {
                throw new RuntimeException('Unable to move the document into the archive folder.');
            }
            $newUploadAbsolutePath = null;
            $filePath = $target['relative_path'];
            $storedFilename = $target['filename'];

            $statement = $pdo->prepare('INSERT INTO documents (student_id, category_id, uploaded_by, original_filename, stored_filename, file_path, mime_type, file_size_bytes, academic_year, upload_source, status, ocr_status, ocr_text, content_hash, ocr_error, ai_summary, ai_detected_category, archived_at) VALUES (:student_id, :category_id, :uploaded_by, :original_filename, :stored_filename, :file_path, :mime_type, :file_size_bytes, :academic_year, :upload_source, \'Archived\', :ocr_status, :ocr_text, :content_hash, :ocr_error, :ai_summary, :ai_detected_category, NOW())');
            $statement->execute([
                ':student_id' => $student['student_id'], ':category_id' => $categoryId,
                ':uploaded_by' => (int) ($currentUser['id'] ?? 2),
                ':original_filename' => trim((string) $input['original_filename']),
                ':stored_filename' => $storedFilename, ':file_path' => $filePath,
                ':mime_type' => $input['mime_type'] ?? null, ':file_size_bytes' => $input['file_size_bytes'] ?? null,
                ':academic_year' => $student['academic_year'], ':upload_source' => $input['upload_source'] ?? 'Manual upload',
                ':ocr_status' => $ocrStatus,
                ':ocr_text' => $ocrText === '' ? null : $ocrText, ':content_hash' => $contentFingerprint['hash'], ':ocr_error' => $ocrError === '' ? null : $ocrError,
                ':ai_summary' => $aiSummary === '' ? null : $aiSummary,
                ':ai_detected_category' => $aiDetectedCategory === '' ? null : $aiDetectedCategory,
            ]);
            $documentId = (int) $pdo->lastInsertId();
            if ($documentId < 1) throw new RuntimeException('The uploaded document could not be saved.');
            upsert_enrollment_year($pdo, $student['academic_year']);
            $review = $pdo->prepare("INSERT INTO document_reviews (document_id, reviewed_by, decision, notes, reviewed_at) VALUES (:document_id, :reviewed_by, 'Approved', :notes, NOW())");
            $review->execute([':document_id' => $documentId, ':reviewed_by' => (int) ($currentUser['id'] ?? 0), ':notes' => 'Automatically archived on upload.']);
            $log = $pdo->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description) VALUES (:user_id, 'ARCHIVED', 'document', :entity_id, :description)");
            $log->execute([
                ':user_id' => (int) ($currentUser['id'] ?? 0),
                ':entity_id' => $documentId,
                ':description' => 'Document added directly to archive: ' . trim((string) $input['original_filename']),
            ]);
            return $documentId;
        });
    } catch (Throwable $error) {
        dats_remove_new_upload($newUploadAbsolutePath);
        dats_remove_new_upload($createdArchiveAbsolutePath);
        throw $error;
    }
    notify_all_users(
        $pdo,
        (int) ($currentUser['id'] ?? 0),
        'New file uploaded',
        sprintf('%s uploaded "%s" to the archive.', (string) ($currentUser['full_name'] ?? 'An admin'), trim((string) $input['original_filename'])),
        'documents',
        $id
    );
    return $pdo->query('SELECT * FROM vw_recent_uploads WHERE document_id = ' . $id)->fetch();
}

function migrate_pending_documents_to_archive(PDO $pdo, int $actorId): int
{
    $documents = $pdo->query("SELECT DISTINCT d.id, d.original_filename, d.stored_filename, d.file_path, d.academic_year FROM documents d LEFT JOIN document_reviews r ON r.document_id = d.id WHERE d.status IN ('Processing', 'Needs review') OR r.decision = 'Pending'")->fetchAll();
    $migrated = 0;
    foreach ($documents as $document) {
        $sourcePath = safe_document_path($document['file_path'] ?? null);
        $target = dats_archive_target($document['file_path'] ?? null, $document['stored_filename'] ?? null, normalize_academic_year((string) ($document['academic_year'] ?? '')));
        $archivePath = null;
        if ($target['requires_move'] && $sourcePath && is_file($sourcePath)) {
            $archiveDirectory = __DIR__ . '/' . $target['directory'];
            if (!is_dir($archiveDirectory) && !mkdir($archiveDirectory, 0755, true) && !is_dir($archiveDirectory)) {
                throw new RuntimeException('Unable to create an academic-year archive folder during migration.');
            }
            $archivePath = __DIR__ . '/' . $target['relative_path'];
            if (!rename($sourcePath, $archivePath)) {
                throw new RuntimeException('Unable to migrate a pending document into the archive.');
            }
        }

        try {
            dats_run_transaction($pdo, function (PDO $pdo) use ($document, $target, $archivePath, $actorId): void {
                $filePath = $archivePath ? $target['relative_path'] : ($document['file_path'] ?? null);
                $storedFilename = $archivePath ? $target['filename'] : ($document['stored_filename'] ?? null);
                $update = $pdo->prepare("UPDATE documents SET status = 'Archived', file_path = :file_path, stored_filename = :stored_filename, archived_at = COALESCE(archived_at, NOW()), updated_at = NOW() WHERE id = :id");
                $update->execute([':file_path' => $filePath, ':stored_filename' => $storedFilename, ':id' => (int) $document['id']]);
                $review = $pdo->prepare("INSERT INTO document_reviews (document_id, reviewed_by, decision, notes, reviewed_at) VALUES (:document_id, :reviewed_by, 'Approved', :notes, NOW()) ON DUPLICATE KEY UPDATE reviewed_by = VALUES(reviewed_by), decision = 'Approved', notes = VALUES(notes), reviewed_at = NOW()");
                $review->execute([':document_id' => (int) $document['id'], ':reviewed_by' => $actorId ?: null, ':notes' => 'Automatically archived when the review workflow was retired.']);
                $log = $pdo->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description) VALUES (:user_id, 'ARCHIVED', 'document', :entity_id, :description)");
                $log->execute([':user_id' => $actorId ?: null, ':entity_id' => (int) $document['id'], ':description' => 'Pending document automatically archived: ' . $document['original_filename']]);
            });
            $migrated++;
        } catch (Throwable $error) {
            if ($archivePath && is_file($archivePath) && $sourcePath) {
                rename($archivePath, $sourcePath);
            }
            throw $error;
        }
    }
    return $migrated;
}

function handle_document_view(PDO $pdo, array $currentUser, int $documentId, int $studentId): void
{
    if ($documentId < 1 || $studentId < 1) {
        send_download_error('Invalid file request.', 400);
    }

    $statement = $pdo->prepare('SELECT d.id, d.student_id, d.original_filename, d.file_path, d.mime_type FROM documents d WHERE d.id = :document_id AND d.student_id = :student_id LIMIT 1');
    $statement->execute([':document_id' => $documentId, ':student_id' => $studentId]);
    $document = $statement->fetch();
    if (!$document) {
        send_download_error('The requested file was not found for this student.', 404);
    }

    $filePath = safe_document_path($document['file_path']);
    if (!$filePath || !is_file($filePath)) {
        send_download_error('The requested file is missing or has been deleted.', 404);
    }

    $mimeType = trim((string) ($document['mime_type'] ?? ''));
    if ($mimeType === '') {
        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
    }

    $downloadName = trim((string) ($document['original_filename'] ?? basename($filePath)));
    if ($downloadName === '') {
        $downloadName = basename($filePath);
    }
    $downloadName = str_replace(['"', '\\'], '', $downloadName);

    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . (string) filesize($filePath));
    header('Content-Disposition: inline; filename="' . $downloadName . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');

    if (@readfile($filePath) === false) {
        send_download_error('The requested file could not be read.', 500);
    }
    exit;
}

function handle_document_download(PDO $pdo, array $currentUser, int $documentId, int $studentId): void
{
    if ($documentId < 1 || $studentId < 1) {
        send_download_error('Invalid file request.', 400);
    }

    $statement = $pdo->prepare('SELECT d.id, d.student_id, d.original_filename, d.file_path, d.mime_type FROM documents d WHERE d.id = :document_id AND d.student_id = :student_id AND d.deleted_at IS NULL LIMIT 1');
    $statement->execute([':document_id' => $documentId, ':student_id' => $studentId]);
    $document = $statement->fetch();
    if (!$document) {
        send_download_error('The requested file was not found for this student.', 404);
    }

    $filePath = safe_document_path($document['file_path']);
    if (!$filePath || !is_file($filePath)) {
        send_download_error('The requested file is missing or has been deleted.', 404);
    }

    $mimeType = trim((string) ($document['mime_type'] ?? ''));
    if ($mimeType === '') {
        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
    }

    $downloadName = trim((string) ($document['original_filename'] ?? basename($filePath)));
    if ($downloadName === '') {
        $downloadName = basename($filePath);
    }
    $downloadName = str_replace(['"', '\\'], '', $downloadName);

    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . (string) filesize($filePath));
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');

    if (@readfile($filePath) === false) {
        send_download_error('The requested file could not be read.', 500);
    }
    exit;
}

function handle_document_download_batch(PDO $pdo, array $currentUser, array $input): void
{

    $studentId = (int) ($input['student_id'] ?? 0);
    if ($studentId < 1) {
        send_download_error('Student ID is required.', 400);
    }

    $student = $pdo->prepare('SELECT id, first_name, last_name, student_number FROM students WHERE id = :id LIMIT 1');
    $student->execute([':id' => $studentId]);
    $studentRecord = $student->fetch();
    if (!$studentRecord) {
        send_download_error('Student record not found.', 404);
    }

    $documentIds = [];
    $allDocuments = filter_var((string) ($input['download_all'] ?? ''), FILTER_VALIDATE_BOOLEAN);
    if ($allDocuments) {
        $statement = $pdo->prepare('SELECT id FROM documents WHERE student_id = :student_id AND deleted_at IS NULL ORDER BY original_filename ASC');
        $statement->execute([':student_id' => $studentId]);
        $documentIds = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    } else {
        foreach ((array) ($input['document_ids'] ?? []) as $documentId) {
            $parsedId = (int) $documentId;
            if ($parsedId > 0) {
                $documentIds[] = $parsedId;
            }
        }
        $documentIds = array_values(array_unique($documentIds));
    }

    if (!$documentIds) {
        send_download_error('No files were selected for download.', 400);
    }

    $inClause = [];
    $params = [':student_id' => $studentId];
    foreach ($documentIds as $index => $documentId) {
        $placeholder = ':document_' . $index;
        $inClause[] = $placeholder;
        $params[$placeholder] = $documentId;
    }

    $statement = $pdo->prepare('SELECT id, original_filename, file_path FROM documents WHERE student_id = :student_id AND deleted_at IS NULL AND id IN (' . implode(', ', $inClause) . ') ORDER BY original_filename ASC');
    $statement->execute($params);
    $documents = $statement->fetchAll();

    if (!$documents) {
        send_download_error('The selected files do not belong to this student.', 404);
    }

    if (!class_exists('ZipArchive')) {
        send_download_error('ZIP support is not available on this server.', 500);
    }

    $studentName = trim((string) ($studentRecord['first_name'] ?? '') . ' ' . (string) ($studentRecord['last_name'] ?? ''));
    $studentIdLabel = trim((string) ($studentRecord['student_number'] ?? ''));
    $zipName = 'student_' . preg_replace('/[^A-Za-z0-9._-]+/', '_', $studentName ?: 'student') . '_' . preg_replace('/[^A-Za-z0-9._-]+/', '_', $studentIdLabel ?: 'records') . '.zip';
    $zipPath = tempnam(sys_get_temp_dir(), 'dats_zip_');
    if ($zipPath === false) {
        send_download_error('Unable to create a temporary ZIP file.', 500);
    }

    $zip = new ZipArchive();
    $zipOpen = $zip->open($zipPath, ZipArchive::OVERWRITE | ZipArchive::CREATE);
    if ($zipOpen !== true) {
        unlink($zipPath);
        send_download_error('Unable to prepare the ZIP archive.', 500);
    }

    $addedCount = 0;
    foreach ($documents as $document) {
        $safePath = safe_document_path($document['file_path'] ?? null);
        if (!$safePath || !is_file($safePath)) {
            continue;
        }
        $originalName = trim((string) ($document['original_filename'] ?? ''));
        $zipEntryName = basename(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $originalName ?: basename($safePath)));
        if ($zipEntryName === '' || $zipEntryName === '.' || $zipEntryName === '..') {
            $zipEntryName = basename($safePath);
        }
        $uniqueName = $zipEntryName;
        $counter = 1;
        while ($zip->locateName($uniqueName) !== false) {
            $baseName = pathinfo($zipEntryName, PATHINFO_FILENAME);
            $extension = pathinfo($zipEntryName, PATHINFO_EXTENSION);
            $uniqueName = $baseName . '_' . $counter . ($extension !== '' ? '.' . $extension : '');
            $counter++;
        }
        $zip->addFile($safePath, $uniqueName);
        $addedCount++;
    }

    $zip->close();
    if ($addedCount === 0) {
        @unlink($zipPath);
        send_download_error('No valid files were available to include in the ZIP download.', 404);
    }

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipName . '"');
    header('Content-Length: ' . (string) filesize($zipPath));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');

    $fileStream = fopen($zipPath, 'rb');
    if ($fileStream === false) {
        @unlink($zipPath);
        send_download_error('Unable to read the temporary ZIP archive.', 500);
    }

    while (!feof($fileStream)) {
        echo fread($fileStream, 8192);
    }
    fclose($fileStream);
    @unlink($zipPath);
    exit;
}

function send_download_error(string $message, int $statusCode): void
{
    http_response_code($statusCode);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $message;
    exit;
}

function safe_document_path(?string $relativePath): ?string
{
    if (!$relativePath || str_contains($relativePath, '..')) {
        return null;
    }
    $path = realpath(__DIR__ . '/' . ltrim($relativePath, '/\\'));
    $root = realpath(__DIR__);
    return $path && $root && str_starts_with($path, $root . DIRECTORY_SEPARATOR) ? $path : null;
}
