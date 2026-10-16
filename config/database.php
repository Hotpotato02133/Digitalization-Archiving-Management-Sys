<?php
declare(strict_types=1);

function dats_env(string $name, string $default = ''): string
{
    $value = getenv($name);
    if ($value !== false && $value !== '') {
        return $value;
    }

    $value = $_ENV[$name] ?? $_SERVER[$name] ?? null;
    if (is_string($value) && $value !== '') {
        return $value;
    }

    return $default;
}

function dats_db(): ?PDO
{
    static $pdo;
    static $attempted = false;

    if ($attempted) {
        return $pdo;
    }

    $attempted = true;

    $host = dats_env('DATS_DB_HOST', '127.0.0.1');
    $name = dats_env('DATS_DB_NAME', 'dats');
    $user = dats_env('DATS_DB_USER', 'root');
    $password = dats_env('DATS_DB_PASSWORD', '');

    try {
        $pdo = new PDO(
            'mysql:host=' . $host . ';dbname=' . $name . ';charset=utf8mb4',
            $user,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    } catch (PDOException $error) {
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
