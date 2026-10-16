<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
dats_require_page_access(basename(__FILE__));
require_once __DIR__ . '/../lib/database-backup.php';

$currentUser = dats_current_user();
$displayName = htmlspecialchars((string) $currentUser['full_name'], ENT_QUOTES, 'UTF-8');
$initials = dats_user_initials($currentUser);
$pdo = dats_db();
$message = '';
$messageType = 'success';

if (!$pdo) {
    http_response_code(503);
    $message = 'The database connection is unavailable. Check the configured database connection before continuing.';
    $messageType = 'error';
}

if ($pdo && ($_GET['action'] ?? '') === 'backup') {
    try {
        set_time_limit(0);
        $backupPath = dats_database_backup_file($pdo);
        $filename = 'DATS_Database_Backup_' . date('Y-m-d') . '.sql';
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($backupPath));
        header('Cache-Control: private, no-store, max-age=0');
        readfile($backupPath);
        @unlink($backupPath);
        exit;
    } catch (Throwable $error) {
        if (isset($backupPath) && is_file($backupPath)) {
          @unlink($backupPath);
        }
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'The database backup could not be created. Check database permissions and server error logs.';
        exit;
    }
}

if (!isset($_SESSION['database_backup_csrf'])) {
    $_SESSION['database_backup_csrf'] = bin2hex(random_bytes(32));
}

if (isset($_SESSION['database_backup_flash'])) {
  $message = (string) ($_SESSION['database_backup_flash']['message'] ?? '');
  $messageType = (string) ($_SESSION['database_backup_flash']['type'] ?? 'success');
  unset($_SESSION['database_backup_flash']);
}

if ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $submittedToken = (string) ($_POST['csrf_token'] ?? '');
        if (!hash_equals((string) $_SESSION['database_backup_csrf'], $submittedToken)) {
            throw new InvalidArgumentException('Your session could not be verified. Refresh the page and try again.');
        }

        $file = $_FILES['backup_file'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Choose a valid SQL backup file to restore.');
        }
        if (strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION)) !== 'sql') {
            throw new InvalidArgumentException('Only .sql backup files are supported.');
        }
        if ((int) ($file['size'] ?? 0) > 268435456 || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            throw new InvalidArgumentException('The uploaded file is invalid or exceeds the 256 MB restore limit.');
        }

        $objectCount = dats_restore_database_backup($pdo, (string) $file['tmp_name']);
        $_SESSION['database_backup_csrf'] = bin2hex(random_bytes(32));
        $message = 'Database restored successfully. ' . $objectCount . ' database objects were recreated.';
    } catch (Throwable $error) {
        $message = $error->getMessage();
        $messageType = 'error';
    }
      $_SESSION['database_backup_flash'] = ['message' => $message, 'type' => $messageType];
      header('Location: database.php');
      exit;
}

$csrfToken = htmlspecialchars((string) $_SESSION['database_backup_csrf'], ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="Create and restore DATS database backups." />
  <title>Database | ZPPSU DATS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="styles.css?v=20260914" />
  <link rel="stylesheet" href="database.css?v=20261002" />
  <link rel="stylesheet" href="../dashboard-buttons.css?v=20260914" />
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
  <div class="admin-shell">
    <aside class="sidebar" id="sidebar">
      <a class="brand" href="../index.php"><img src="../img/ZPPSU.png" alt="ZPPSU seal" /><span><strong>ZPPSU REGISTRAR</strong><small>Digital Archive</small></span></a>
      <div class="workspace-label">Workspace</div>
      <nav aria-label="Admin navigation">
        <a href="index.php"><i data-lucide="layout-dashboard"></i> Dashboard</a>
        <a href="scan.php"><i data-lucide="scan-line"></i> File Scanning</a>
        <a href="gradesheets.php"><i data-lucide="graduation-cap"></i> Gradesheets</a>
        <a href="files.php"><i data-lucide="files"></i> Student Files</a>
        <a href="students.php"><i data-lucide="contact-round"></i> Students Information</a>
        <details class="setup-menu" open>
          <summary class="active"><i data-lucide="sliders-horizontal"></i> Setup <i class="setup-chevron" data-lucide="chevron-down"></i></summary>
          <div class="setup-submenu">
            <a href="categories.php"><i data-lucide="folder-plus"></i> Document Categories</a>
            <a href="users.php"><i data-lucide="users"></i> Users</a>
            <a class="active" href="database.php" aria-current="page"><i data-lucide="database"></i> Database</a>
          </div>
        </details>
      </nav>
      <div class="sidebar-bottom"><a href="settings.php"><i data-lucide="settings-2"></i> Settings</a></div>
    </aside>
    <main class="admin-main">
      <header class="admin-header"><button class="mobile-menu" id="mobileMenu" aria-label="Open admin navigation" aria-expanded="false"><i data-lucide="menu"></i></button><div><span class="eyebrow">Administrator workspace</span><h1><span data-greeting data-user-name="<?= $displayName ?>">Good morning</span></h1></div><div class="header-actions"><button class="header-icon" aria-label="Notifications"><i data-lucide="bell"></i><span></span></button><div class="user-chip"><span class="avatar"><?= $initials ?></span><span><strong><?= $displayName ?></strong><small><?= htmlspecialchars(dats_user_role_label($currentUser), ENT_QUOTES, 'UTF-8') ?></small></span><i data-lucide="chevron-down"></i></div></div></header>
      <section class="welcome-row database-welcome"><div><p class="page-kicker">Setup · <span data-current-datetime>Loading date and time...</span></p><h2>Database</h2><p class="page-intro">Create a downloadable database backup or restore from a supported DATS SQL backup.</p></div></section>
      <?php if ($message !== ''): ?><div class="database-message <?= $messageType === 'error' ? 'is-error' : '' ?>" role="<?= $messageType === 'error' ? 'alert' : 'status' ?>"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
      <section class="database-actions" aria-label="Database backup and restore">
        <article class="database-action">
          <span class="database-icon"><i data-lucide="hard-drive-download"></i></span>
          <p class="panel-kicker">Database backup</p>
          <h3>Download a full SQL backup</h3>
          <p>Includes database tables, data, and views. The dated file is generated for this download and is not retained on the web server.</p>
          <a class="gold-button" href="database.php?action=backup"><i data-lucide="download"></i> Backup Database</a>
        </article>
        <article class="database-action database-restore">
          <span class="database-icon"><i data-lucide="database-backup"></i></span>
          <p class="panel-kicker">Database restore</p>
          <h3>Restore from a DATS backup</h3>
          <p>Restoring replaces the current database structure and data. Only a supported DATS-generated .sql backup can be restored.</p>
          <form method="post" enctype="multipart/form-data" onsubmit="return confirm('Restore this database backup? This replaces the current database structure and data and cannot be undone.');">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>" />
            <label class="database-file-label" for="backupFile">SQL backup file</label>
            <input class="database-file-input" id="backupFile" name="backup_file" type="file" accept=".sql,application/sql" required />
            <button class="database-restore-button" type="submit"><i data-lucide="rotate-ccw"></i> Restore Database</button>
          </form>
        </article>
      </section>
      <section class="database-storage" aria-labelledby="databaseStorageTitle">
        <i data-lucide="cloud-upload"></i>
        <div><h3 id="databaseStorageTitle">Keep a copy off the server</h3><p>Google Drive upload is not configured in this system. Save the downloaded SQL file in a restricted Google Drive backup folder manually, and verify the uploaded file is available before deleting local copies.</p></div>
      </section>
      <footer><span>Digitalization Archiving Management System</span><span>Database <b>·</b> ZPPSU Registrar</span></footer>
    </main>
  </div>
  <script src="../data-sync.js?v=20260914"></script>
  <script src="script.js?v=20260909"></script>
  <script>document.querySelector('[name="backup_file"]')?.addEventListener('change', event => { const file = event.currentTarget.files[0]; if (file && !file.name.toLowerCase().endsWith('.sql')) { event.currentTarget.value = ''; window.alert('Choose a .sql backup file.'); } });</script>
</body>
</html>