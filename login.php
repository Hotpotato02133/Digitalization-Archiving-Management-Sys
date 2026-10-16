<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: index.php');
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$email = (string) ($payload['email'] ?? '');
$password = (string) ($payload['password'] ?? '');

if (!dats_login($email, $password)) {
    dats_json(['error' => 'Invalid credentials.'], 401);
}

dats_json([
    'message' => 'Login successful.',
    'redirect' => 'admin/index.php',
]);
