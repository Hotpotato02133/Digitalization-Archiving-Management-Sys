<?php
declare(strict_types=1);

// Never leak stack traces, file paths, or query text to the browser on an uncaught error —
// log them server-side instead (still visible in Apache's error log).
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Load zero-dependency environment variables from root .env if present
require_once __DIR__ . '/env.php';
dats_load_env();

// Optional local-only configuration overrides and secrets.
// NEVER commit config.local.php or secrets.local.php to version control.
if (is_file(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}
if (is_file(__DIR__ . '/secrets.local.php')) {
    require_once __DIR__ . '/secrets.local.php';
}

function dats_is_local_dev(): bool
{
    $env = strtolower(dats_env('APP_ENV', dats_env('DATS_ENV', 'development')));
    return in_array($env, ['development', 'local', 'dev', 'test'], true);
}

function dats_db(): ?PDO
{
    static $pdo;
    static $attempted = false;

    if ($attempted) {
        return $pdo;
    }

    $attempted = true;

    $isLocal = dats_is_local_dev();

    // Read strictly from environment variables. Safe defaults apply only when
    // explicitly configured for local development.
    $host = dats_env('DB_HOST', dats_env('DATS_DB_HOST', $isLocal ? '127.0.0.1' : ''));
    $port = dats_env('DB_PORT', dats_env('DATS_DB_PORT', $isLocal ? '3306' : ''));
    $name = dats_env('DB_NAME', dats_env('DATS_DB_NAME', $isLocal ? 'dats' : ''));
    $user = dats_env('DB_USER', dats_env('DATS_DB_USER', $isLocal ? 'root' : ''));
    $password = dats_env('DB_PASS', dats_env('DB_PASSWORD', dats_env('DATS_DB_PASSWORD', $isLocal ? '' : '')));

    // In production, prevent connection attempts with incomplete configuration
    if ($host === '' || $name === '' || $user === '') {
        error_log('DATS Database configuration missing: DB_HOST, DB_NAME, or DB_USER is not configured.');
        $pdo = null;
        return null;
    }

    $dsn = 'mysql:host=' . $host;
    if ($port !== '' && is_numeric($port)) {
        $dsn .= ';port=' . (int) $port;
    }
    $dsn .= ';dbname=' . $name . ';charset=utf8mb4';

    try {
        $pdo = new PDO(
            $dsn,
            $user,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    } catch (PDOException $error) {
        error_log('DATS Database connection error: ' . $error->getMessage());
        $pdo = null;
    }

    return $pdo;
}

function dats_json(mixed $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
