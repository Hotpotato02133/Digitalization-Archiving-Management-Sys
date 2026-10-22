<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

function dats_current_user(): ?array
{
    static $user;
    if ($user !== null) {
        return $user;
    }
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    $pdo = dats_db();
    if (!$userId || !$pdo) {
        return null;
    }
    dats_ensure_user_role_column($pdo);
    $statement = $pdo->prepare("SELECT id, full_name, email, status, role FROM users WHERE id = :id AND status = 'Active'");
    $statement->execute([':id' => $userId]);
    $user = $statement->fetch() ?: null;
    return $user;
}

function dats_ensure_user_role_column(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;
    $statement = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role'");
    if ((int) $statement->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN role ENUM('Admin', 'Staff') NOT NULL DEFAULT 'Admin' AFTER email");
    }
}

function dats_login(string $email, string $password): bool
{
    $pdo = dats_db();
    if (!$pdo) {
        return false;
    }
    $statement = $pdo->prepare("SELECT id, full_name, email, password_hash FROM users WHERE email = :email AND status = 'Active' LIMIT 1");
    $statement->execute([':email' => strtolower(trim($email))]);
    $user = $statement->fetch();
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['full_name'] = $user['full_name'];
    $update = $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
    $update->execute([':id' => $user['id']]);
    return true;
}

function dats_user_initials(?array $user): string
{
    $name = trim((string) ($user['full_name'] ?? 'User'));
    $parts = preg_split('/\s+/', $name) ?: [];
    $initials = strtoupper(substr($parts[0] ?? 'U', 0, 1) . substr($parts[count($parts) - 1] ?? '', 0, 1));
    // Echoed unescaped by every dashboard template's short-echo tag; escape here once so a
    // display name starting with an HTML-special character can't inject markup anywhere it's used.
    return htmlspecialchars($initials ?: 'U', ENT_QUOTES, 'UTF-8');
}

function dats_user_role_label(?array $user): string
{
    return match ($user['role'] ?? null) {
        'Admin' => 'System Administrator',
        'Staff' => 'Registrar Staff',
        default => 'User',
    };
}

function dats_require_login(): void
{
    if (!dats_current_user()) {
        header('Location: ../index.php');
        exit;
    }
}

function dats_require_role(string $role): void
{
    dats_require_login();
    $userRole = dats_current_user()['role'] ?? null;
    if ($userRole === 'Admin' || $userRole === $role) {
        return;
    }
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Forbidden';
    exit;
}

function dats_can_access_admin_page(?array $user, string $page): bool
{
    if (($user['role'] ?? null) === 'Admin') {
        return true;
    }
    if (($user['role'] ?? null) !== 'Staff') {
        return false;
    }
    return in_array(basename($page), [
        'index.php',
        'scan.php',
        'gradesheets.php',
        'files.php',
        'students.php',
        'categories.php',
        'settings.php',
        'student-record-view.php',
        'student-gradesheet-view.php',
        'analytics.php',
    ], true);
}

function dats_require_page_access(string $page): void
{
    dats_require_login();
    if (!dats_can_access_admin_page(dats_current_user(), $page)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Forbidden';
        exit;
    }
}

function dats_can_access_api(?array $user, string $method, string $resource): bool
{
    if (($user['role'] ?? null) === 'Admin') {
        return true;
    }
    if (($user['role'] ?? null) !== 'Staff') {
        return false;
    }

    $method = strtoupper($method);
    $getResources = [
        'analytics', 'uploads', 'profile', 'notifications', 'activity', 'requests',
        'students', 'categories', 'colleges', 'documents', 'deleted-students',
        'deleted-documents', 'gradesheets', 'gradesheet-entry-source',
        'gradesheet-students', 'gradesheet-batches', 'gradesheet-batch-entries',
        'enrollment-years', 'document-view', 'document-download',
        'gradesheet-batch-file', 'gradesheet-entry-file',
    ];
    if ($method === 'GET') {
        return in_array($resource, $getResources, true);
    }

    return $method === 'POST' && in_array($resource, [
        'categories', 'students', 'document-ocr', 'documents', 'document-download-bulk', 'gradesheets',
        'gradesheet-batches', 'enrollment-years', 'notifications', 'profile',
    ], true);
}

function dats_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
}
