<?php require_once __DIR__ . '/../auth.php'; dats_require_page_access(basename(__FILE__)); $currentUser = dats_current_user(); $displayName = htmlspecialchars($currentUser['full_name'], ENT_QUOTES, 'UTF-8'); $initials = dats_user_initials($currentUser); ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="Manage document categories for the ZPPSU digital archive." />
  <title>Document Categories | ZPPSU DATS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="styles.css?v=20260914" />
  <link rel="stylesheet" href="categories.css?v=<?= filemtime(__DIR__ . '/categories.css') ?>" />
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
             <a class="active" href="categories.php" aria-current="page"><i data-lucide="folder-plus"></i> Document Categories</a>
            <?php if (($currentUser['role'] ?? '') === 'Admin'): ?><a href="users.php"><i data-lucide="users"></i> Users</a><a href="database.php"><i data-lucide="database"></i> Database</a><?php endif; ?>
           
          </div>
        </details>
      </nav>
      <?php if (dats_can_access_admin_page($currentUser, 'settings.php')): ?><div class="sidebar-bottom"><a href="settings.php"><i data-lucide="settings-2"></i> Settings</a></div><?php endif; ?>
    </aside>
    <main class="admin-main categories-main">
      <header class="admin-header"><button class="mobile-menu" id="mobileMenu" aria-label="Open admin navigation" aria-expanded="false"><i data-lucide="menu"></i></button><div><span class="eyebrow">Administrator workspace</span><h1><span data-greeting data-user-name="<?= $displayName ?>">Good morning</span></h1></div><div class="header-actions"><button class="header-icon" aria-label="Notifications"><i data-lucide="bell"></i><span></span></button><div class="user-chip"><span class="avatar"><?= $initials ?></span><span><strong><?= $displayName ?></strong><small><?= htmlspecialchars(dats_user_role_label($currentUser), ENT_QUOTES, 'UTF-8') ?></small></span><i data-lucide="chevron-down"></i></div></div></header>
      <section class="welcome-row categories-welcome"><div><p class="page-kicker">Registrar's Office · <span data-current-datetime>Loading date and time...</span></p><h2>Document categories</h2><p class="page-intro">Manage the document types available throughout the digital archive.</p></div><button class="gold-button" id="addCategoryButton" type="button"><i data-lucide="folder-plus"></i> Add document category</button></section>
      <section class="category-summary" aria-label="Document category summary"><div><strong id="adminCategoryCount">0</strong><span>Active categories</span></div><div><strong data-stat="total_documents">0</strong><span>Archived documents</span></div><div><strong data-stat="completed_documents">0%</strong><span>Digitized records</span></div></section>
      <section class="category-panel panel" id="categories"><div class="panel-heading"><div><span class="panel-kicker">Registrar's Office archive</span><h3>Document categories</h3></div><span class="category-note">Use official document names</span></div><div class="category-grid" id="categoryGrid"></div></section>
      <section class="category-help"><i data-lucide="info"></i><p><strong>Keep categories consistent.</strong><span>Use an existing category when the document type is already listed.</span></p></section>
      <footer><span>Digitalization Archiving Management System</span><span>Document Categories <b>·</b> ZPPSU Registrar</span></footer>
    </main>
  </div>
  <div class="admin-toast" id="adminToast" role="status">Document category updated.</div>
  <div class="category-modal-backdrop" id="categoryModal" hidden><section class="category-modal" role="dialog" aria-modal="true" aria-labelledby="categoryModalTitle"><button class="modal-close" id="closeCategoryModal" type="button" aria-label="Close add category dialog"><i data-lucide="x"></i></button><span class="panel-kicker">Registrar's Office archive</span><h2 id="categoryModalTitle">Add document category</h2><p class="modal-copy">Create a category for an approved Registrar's Office document type.</p><form id="categoryForm"><label for="categoryName">Category name *<input id="categoryName" type="text" maxlength="150" placeholder="e.g. Certified True Copy" required /></label><div class="modal-actions"><button class="modal-cancel" id="cancelCategoryButton" type="button">Cancel</button><button class="login-submit" type="submit">Add category <i data-lucide="arrow-right"></i></button></div></form></section></div>
  <script src="../data-sync.js?v=<?= filemtime(__DIR__ . '/../data-sync.js') ?>"></script>
  <script src="categories.js?v=<?= filemtime(__DIR__ . '/categories.js') ?>"></script>
</body>
</html>
