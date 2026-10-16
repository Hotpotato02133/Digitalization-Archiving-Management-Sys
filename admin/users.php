<?php require_once __DIR__ . '/../auth.php'; dats_require_page_access(basename(__FILE__)); $currentUser = dats_current_user(); $displayName = htmlspecialchars($currentUser['full_name'], ENT_QUOTES, 'UTF-8'); $initials = dats_user_initials($currentUser); ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="Manage ZPPSU Digital Archiving Management System users." />
  <title>Users | ZPPSU DATS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="styles.css?v=20260914" />
  <link rel="stylesheet" href="user-actions.css?v=20260914" />
  <link rel="stylesheet" href="../dashboard-tables.css?v=20260914" />
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
            <a class="active" href="users.php" aria-current="page"><i data-lucide="users"></i> Users</a>
            <a href="database.php"><i data-lucide="database"></i> Database</a>
            
          </div>
        </details>
      </nav>
      <div class="sidebar-bottom"><a href="settings.php"><i data-lucide="settings-2"></i> Settings</a></div>
    </aside>
    <main class="admin-main users-main">
      <header class="admin-header"><button class="mobile-menu" id="mobileMenu" aria-label="Open admin navigation" aria-expanded="false"><i data-lucide="menu"></i></button><div><span class="eyebrow">Administrator workspace</span><h1><span data-greeting data-user-name="<?= $displayName ?>">Good morning</span></h1></div><div class="header-actions"><button class="header-icon" aria-label="Notifications"><i data-lucide="bell"></i><span></span></button><div class="user-chip"><span class="avatar"><?= $initials ?></span><span><strong><?= $displayName ?></strong><small><?= htmlspecialchars(dats_user_role_label($currentUser), ENT_QUOTES, 'UTF-8') ?></small></span><i data-lucide="chevron-down"></i></div></div></header>
      <section class="welcome-row users-welcome"><div><p class="page-kicker">Access control · <span data-current-datetime>Loading date and time...</span></p><h2>Manage users</h2><p class="page-intro">Control who can access the digital archive and what they can do.</p></div><button class="gold-button" id="addUserButton"><i data-lucide="user-round-plus"></i> Add user</button></section>
      <section class="user-summary"><div><strong>24</strong><span>All users</span></div><div><strong>22</strong><span>Active now</span></div></section>
      <section class="users-panel panel"><div class="users-toolbar"><div class="search-box"><i data-lucide="search"></i><input id="userSearch" type="search" placeholder="Search by name, ID, or email" /></div><select id="statusFilter" aria-label="Filter by status"><option value="all">All status</option><option value="Active">Active</option><option value="Inactive">Inactive</option></select></div><div class="users-table-wrap"><table class="users-table"><thead><tr><th>User</th><th>Email</th><th>Last active</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead><tbody id="userRows"><tr><td colspan="5" class="table-loading">Loading user accounts...</td></tr></tbody></table><p class="empty-users" id="emptyUsers" hidden>No users match your filters.</p></div></section>
      <footer><span>Digitalization Archiving Management System</span><span>User management <b>·</b> ZPPSU Registrar</span></footer>
    </main>
  </div>
  <div class="admin-toast" id="adminToast" role="status">User management updated.</div>
  <div class="user-modal-backdrop" id="userModal" hidden><section class="user-modal" role="dialog" aria-modal="true" aria-labelledby="userModalTitle"><button class="row-action modal-close" id="closeUserModal" aria-label="Close add user dialog"><i data-lucide="x"></i></button><span class="panel-kicker">Access control</span><h2 id="userModalTitle">Add a new user</h2><p>Create an account for a member of the Registrar's Office.</p><label>Full name<input id="userFullName" type="text" placeholder="Enter full name" /></label><label>ID number<input id="userIdNumber" type="text" placeholder="e.g. 2026-0001" /></label><label>Work email<input id="userEmail" type="email" placeholder="name@zppsu.edu.ph" /></label><label>Account type<select id="userRole"><option value="Admin">Admin</option><option value="Staff">Staff</option></select></label><label>Password<input id="userPassword" type="password" minlength="8" placeholder="At least 8 characters" autocomplete="new-password" /></label><p class="user-form-message" id="userFormMessage" role="alert"></p><button class="login-submit" id="saveUserButton" type="button">Create user <i data-lucide="arrow-right"></i></button></section></div>
  <script src="../data-sync.js?v=20260914"></script>
  <script src="script.js?v=20260910"></script>
  <script src="../dashboard-tables.js?v=20260914"></script>
</body>
</html>
