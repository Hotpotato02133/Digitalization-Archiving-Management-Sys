<?php
declare(strict_types=1);

require_once __DIR__ . '/../../auth.php';

$staff = ['role' => 'Staff'];
$admin = ['role' => 'Admin'];
$staffPages = [
    'index.php', 'scan.php', 'gradesheets.php', 'files.php', 'students.php',
    'categories.php', 'settings.php', 'student-record-view.php', 'student-gradesheet-view.php',
];
$restrictedPages = ['users.php', 'colleges.php', 'database.php'];

foreach ($staffPages as $page) {
    if (!dats_can_access_admin_page($staff, $page)) {
        throw new RuntimeException("Staff should be able to access {$page}.");
    }
}
foreach ($restrictedPages as $page) {
    if (dats_can_access_admin_page($staff, $page)) {
        throw new RuntimeException("Staff must not be able to access {$page}.");
    }
    if (!dats_can_access_admin_page($admin, $page)) {
        throw new RuntimeException("Admin should retain access to {$page}.");
    }
}

$staffGetResources = [
    'analytics', 'students', 'categories', 'documents', 'gradesheets',
    'gradesheet-batches', 'enrollment-years', 'document-view', 'document-download',
];
foreach ($staffGetResources as $resource) {
    if (!dats_can_access_api($staff, 'GET', $resource)) {
        throw new RuntimeException("Staff should be able to read {$resource}.");
    }
}

$staffPostResources = ['categories', 'students', 'document-ocr', 'documents', 'document-download-bulk', 'gradesheets', 'gradesheet-batches', 'enrollment-years', 'profile'];
foreach ($staffPostResources as $resource) {
    if (!dats_can_access_api($staff, 'POST', $resource)) {
        throw new RuntimeException("Staff should be able to use {$resource}.");
    }
}

foreach ([['GET', 'users'], ['POST', 'users'], ['POST', 'colleges'], ['POST', 'courses']] as [$method, $resource]) {
    if (dats_can_access_api($staff, $method, $resource)) {
        throw new RuntimeException("Staff must not be able to use {$method} {$resource}.");
    }
    if (!dats_can_access_api($admin, $method, $resource)) {
        throw new RuntimeException("Admin should retain access to {$method} {$resource}.");
    }
}

$projectRoot = dirname(__DIR__, 2);
if (dats_user_role_label($admin) !== 'System Administrator' || dats_user_role_label($staff) !== 'Registrar Staff') {
    throw new RuntimeException('Dashboard role labels must reflect the authenticated account role.');
}
$dashboardPage = (string) file_get_contents($projectRoot . '/admin/index.php');
$settingsPage = (string) file_get_contents($projectRoot . '/admin/settings.php');
if (!str_contains($dashboardPage, 'dats_user_role_label($currentUser)') || !str_contains($settingsPage, 'dats_user_role_label($currentUser)')) {
    throw new RuntimeException('Dashboard and Account Settings must display the authenticated user role.');
}
foreach (glob($projectRoot . '/admin/*.php') ?: [] as $pagePath) {
    if (!str_contains((string) file_get_contents($pagePath), 'dats_require_page_access')) {
        throw new RuntimeException('Admin page is missing a role-aware page guard: ' . basename($pagePath));
    }
}

$api = (string) file_get_contents($projectRoot . '/api.php');
$auth = (string) file_get_contents($projectRoot . '/auth.php');
if (!str_contains($api, 'if (!dats_can_access_api($currentUser, $method, $resource))')) {
    throw new RuntimeException('API dispatcher is missing the role-aware resource guard.');
}
if (!str_contains($auth, "role ENUM('Admin', 'Staff') NOT NULL DEFAULT 'Admin'")) {
    throw new RuntimeException('Existing accounts must default to Admin when roles are introduced.');
}
if (!str_contains((string) file_get_contents($projectRoot . '/admin/users.php'), 'id="userRole"')) {
    throw new RuntimeException('The user form must support creating a Staff account.');
}

$sqlDump = (string) file_get_contents($projectRoot . '/dats.sql');
if (!str_contains($sqlDump, '(2, \'Administrator\', NULL, \'admin@zppsu.edu.ph\', \'Admin\', \'$2y$10$IXI7oFzMkALp.yhV0ECsQOS1uvU5sBcUJ7LciyPXwAdJyVvQ3o/rW\', \'Active\'')) {
    throw new RuntimeException('The Admin account in the SQL backup must use the final name and login email.');
}
if (!str_contains($sqlDump, '(5, \'Registrar Staff\', NULL, \'staff@zppsu.edu.ph\', \'Staff\', \'$2y$10$3kxwlFT0BEKWSCTCWTXQk.ij7U.6ezB1TF.GO0S9IwYphV3h/ihTW\', \'Active\'')) {
    throw new RuntimeException('The Registrar Staff account in the SQL backup must use the final name and login email.');
}
if (str_contains($sqlDump, 'aivanbaron@zppsu.edu.ph') || str_contains($sqlDump, 'carylsagusara@zppsu.edu.ph')) {
    throw new RuntimeException('Old login emails must not remain in the SQL backup.');
}

if (str_contains($sqlDump, 'Iraham') || str_contains($sqlDump, 'Puno')) {
    throw new RuntimeException('The removed sample student rows must not remain in the SQL backup.');
}
if (str_contains($sqlDump, "Document approved and archived: mra3gfo3ius81.png") || str_contains($sqlDump, "Document approved and archived: camera-capture.jpg") || str_contains($sqlDump, "Document rejected and file removed: Bugler 4.png")) {
    throw new RuntimeException('Recent activity sample entries must not remain in the SQL backup.');
}
if (str_contains($sqlDump, "(9, 8, 3, NULL, 'mra3gfo3ius81.png'" ) || str_contains($sqlDump, "(12, 8, 6, NULL, 'camera-capture.jpg'" ) || str_contains($sqlDump, "(15, 10, 16, 2, 'Bugler 4.png'")) {
    throw new RuntimeException('Archived/sample documents for the removed students must not remain in the SQL backup.');
}

echo "auth_access_test: PASS\n";