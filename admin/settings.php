<?php
require_once __DIR__ . '/../auth.php';
dats_require_page_access(basename(__FILE__));
$currentUser = dats_current_user();
$displayName = htmlspecialchars($currentUser['full_name'], ENT_QUOTES, 'UTF-8');
$emailValue = htmlspecialchars($currentUser['email'], ENT_QUOTES, 'UTF-8');
$initials = dats_user_initials($currentUser);
$pdo = dats_db();
$account = [];
if ($pdo) {
    $statement = $pdo->prepare('SELECT status, created_at, last_login_at FROM users WHERE id = :id');
    $statement->execute([':id' => (int) $currentUser['id']]);
    $account = $statement->fetch() ?: [];
}
$statusLabel = $account['status'] ?? 'Active';
$memberSince = !empty($account['created_at']) ? date('F j, Y', strtotime($account['created_at'])) : '—';
$lastLogin = !empty($account['last_login_at']) ? date('F j, Y \a\t g:i A', strtotime($account['last_login_at'])) : 'Not recorded yet';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="Manage your ZPPSU Digital Archiving Management System account settings." />
  <title>Account Settings | ZPPSU DATS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="styles.css?v=20260914" />
  <link rel="stylesheet" href="../settings.css?v=20260914b" />
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
        <details class="setup-menu">
          <summary><i data-lucide="sliders-horizontal"></i> Setup <i class="setup-chevron" data-lucide="chevron-down"></i></summary>
          <div class="setup-submenu">
            <a href="categories.php"><i data-lucide="folder-plus"></i> Document Categories</a>
            <?php if (($currentUser['role'] ?? '') === 'Admin'): ?>
              <a href="users.php"><i data-lucide="users"></i> Users</a>
              <a href="database.php"><i data-lucide="database"></i> Database</a>
            <?php endif; ?>
            
          </div>
        </details>
      </nav>
      <div class="sidebar-bottom"><a class="active" href="settings.php" aria-current="page"><i data-lucide="settings-2"></i> Settings</a></div>
    </aside>
    <main class="admin-main">
      <header class="admin-header"><button class="mobile-menu" id="mobileMenu" aria-label="Open admin navigation" aria-expanded="false"><i data-lucide="menu"></i></button><div><span class="eyebrow">Administrator workspace</span><h1><span data-greeting data-user-name="<?= $displayName ?>">Good morning</span></h1></div><div class="header-actions"><button class="header-icon" aria-label="Notifications"><i data-lucide="bell"></i><span></span></button><div class="user-chip"><span class="avatar"><?= $initials ?></span><span><strong><?= $displayName ?></strong><small><?= htmlspecialchars(dats_user_role_label($currentUser), ENT_QUOTES, 'UTF-8') ?></small></span><i data-lucide="chevron-down"></i></div></div></header>
      <section class="welcome-row"><div><p class="page-kicker">Workspace · <span data-current-datetime>Loading date and time...</span></p><h2>Account settings</h2><p class="page-intro">Manage your profile, password, and account details.</p></div></section>
      <section class="settings-hero">
        <span class="avatar avatar-lg"><?= $initials ?></span>
        <div class="settings-hero-info">
          <h2><?= $displayName ?></h2>
          <p><?= $emailValue ?></p>
          <div class="settings-hero-badges">
            <span class="settings-badge status-<?= strtolower((string) $statusLabel) ?>"><?= htmlspecialchars((string) $statusLabel, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="settings-hero-meta"><i data-lucide="calendar-days"></i> Member since <?= htmlspecialchars($memberSince, ENT_QUOTES, 'UTF-8') ?></span>
          </div>
        </div>
      </section>
      <section class="settings-layout">
        <form class="settings-form account-settings-form" novalidate>
          <div class="settings-card">
            <div class="settings-card-head"><span class="settings-card-icon"><i data-lucide="user-round"></i></span><div><h3>Personal information</h3><p>Your name and email are shown across the archive workspace.</p></div></div>
            <div class="settings-field-grid">
              <label>Full name<input name="full_name" type="text" maxlength="120" required value="<?= $displayName ?>" /></label>
              <label>Work email<input name="email" type="email" maxlength="190" required value="<?= $emailValue ?>" /></label>
            </div>
          </div>
          <div class="settings-card">
            <div class="settings-card-head"><span class="settings-card-icon"><i data-lucide="lock-keyhole"></i></span><div><h3>Password</h3><p>Leave both fields blank to keep your current password.</p></div></div>
            <div class="settings-field-grid">
              <label>New password<span class="settings-input-wrap"><input name="password" id="settingsPassword" type="password" minlength="8" autocomplete="new-password" placeholder="At least 8 characters" /><button type="button" class="settings-toggle-visibility" data-target="settingsPassword" aria-label="Show password"><i data-lucide="eye"></i></button></span></label>
              <label>Confirm new password<span class="settings-input-wrap"><input id="settingsPasswordConfirm" type="password" minlength="8" autocomplete="new-password" placeholder="Re-enter new password" /><button type="button" class="settings-toggle-visibility" data-target="settingsPasswordConfirm" aria-label="Show password"><i data-lucide="eye"></i></button></span></label>
            </div>
            <p class="settings-field-hint" id="settingsPasswordMismatch" hidden><i data-lucide="alert-circle"></i> Passwords do not match.</p>
          </div>
          <div class="settings-save-bar">
            <span class="settings-save-hint" hidden><i data-lucide="info"></i> You have unsaved changes.</span>
            <div class="settings-save-actions">
              <button type="button" class="settings-discard" hidden>Discard</button>
              <button type="submit" class="login-submit">Save changes <i data-lucide="check"></i></button>
            </div>
          </div>
          <p class="settings-form-message" role="alert"></p>
        </form>
        <aside class="settings-side">
          <div class="settings-card account-overview">
            <div class="settings-card-head"><span class="settings-card-icon"><i data-lucide="shield-check"></i></span><div><h3>Account overview</h3><p>Read-only details about your account.</p></div></div>
            <ul class="overview-list">
              <li><i data-lucide="activity"></i><div><dt>Status</dt><dd><span class="settings-badge status-<?= strtolower((string) $statusLabel) ?>"><?= htmlspecialchars((string) $statusLabel, ENT_QUOTES, 'UTF-8') ?></span></dd></div></li>
              <li><i data-lucide="calendar-days"></i><div><dt>Member since</dt><dd><?= htmlspecialchars($memberSince, ENT_QUOTES, 'UTF-8') ?></dd></div></li>
              <li><i data-lucide="clock-3"></i><div><dt>Last sign-in</dt><dd><?= htmlspecialchars($lastLogin, ENT_QUOTES, 'UTF-8') ?></dd></div></li>
            </ul>
          </div>
          <div class="settings-card settings-tip">
            <span class="settings-card-icon"><i data-lucide="shield-alert"></i></span>
            <div><h3>Keep your account secure</h3><p>Use a unique password with at least 8 characters and avoid reusing passwords from other sites.</p></div>
          </div>
        </aside>
      </section>
      <footer><span>Digitalization Archiving Management System</span><span>Account settings <b>·</b> ZPPSU Registrar</span></footer>
    </main>
  </div>
  <div class="admin-toast" id="adminToast" role="status">Profile updated successfully.</div>
  <script src="../data-sync.js?v=<?= filemtime(__DIR__ . '/../data-sync.js') ?>"></script>
  <script src="script.js?v=20260909"></script>
</body>
</html>
