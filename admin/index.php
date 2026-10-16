<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
dats_require_page_access(basename(__FILE__));
$currentUser = dats_current_user();
$displayName = htmlspecialchars((string) ($currentUser['full_name'] ?? 'User'), ENT_QUOTES, 'UTF-8');
$initials = dats_user_initials($currentUser);
?>
<!doctype html>
<html lang="en">
<head>

  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="ZPPSU Digital Archive administrator dashboard and archive analytics." />
  <title>Admin Dashboard | ZPPSU DATS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="styles.css?v=20260922" />
  <link rel="stylesheet" href="../dashboard-buttons.css?v=20260914" />
  <script src="https://unpkg.com/lucide@latest"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
</head>
<body>
  <div class="admin-shell">
    <aside class="sidebar" id="sidebar">
      <a class="brand" href="../index.php"><img src="../img/ZPPSU.png" alt="ZPPSU seal" /><span><strong>ZPPSU REGISTRAR</strong><small>Digital Archive</small></span></a>
      <div class="workspace-label">Workspace</div>
      <nav aria-label="Admin navigation">
        <a class="active" href="#dashboard" aria-current="page"><i data-lucide="layout-dashboard"></i> Dashboard</a>
        <a href="scan.php"><i data-lucide="scan-line"></i> File Scanning</a>
        <a href="gradesheets.php"><i data-lucide="graduation-cap"></i> Gradesheets</a>
        <a href="files.php"><i data-lucide="files"></i> Student Files</a>
        <a href="students.php"><i data-lucide="contact-round"></i> Students Information</a>
        <details class="setup-menu">
          <summary><i data-lucide="sliders-horizontal"></i> Setup <i class="setup-chevron" data-lucide="chevron-down"></i></summary>
          <div class="setup-submenu">
            <a href="categories.php"><i data-lucide="folder-plus"></i> Document Categories</a>
            <?php if (($currentUser['role'] ?? '') === 'Admin'): ?><a href="users.php"><i data-lucide="users"></i> Users</a><a href="database.php"><i data-lucide="database"></i> Database</a><?php endif; ?>
            
          </div>
        </details>
      </nav>
      <div class="sidebar-bottom"><a href="settings.php"><i data-lucide="settings-2"></i> Settings</a></div>
    </aside>

    <main class="admin-main" id="dashboard" data-dashboard-root>
      <header class="admin-header">
        <button class="mobile-menu" id="mobileMenu" aria-label="Open admin navigation" aria-expanded="false"><i data-lucide="menu"></i></button>
        <div><span class="eyebrow">Administrator workspace</span><h1><span data-greeting data-user-name="<?= $displayName ?>">Good morning</span></h1></div>
        <div class="header-actions"><button class="header-icon" aria-label="Notifications"><i data-lucide="bell"></i><span></span></button><div class="user-chip"><span class="avatar"><?= $initials ?></span><span><strong><?= $displayName ?></strong><small><?= htmlspecialchars(dats_user_role_label($currentUser), ENT_QUOTES, 'UTF-8') ?></small></span><i data-lucide="chevron-down"></i></div></div>
      </header>

      <section class="welcome-row">
        <div><p class="page-kicker"><span data-current-datetime>Loading date and time...</span></p><h2>Archive overview</h2><p class="page-intro">Live archive coverage, throughput, search readiness, and records requiring attention.</p></div>
        <button class="gold-button" id="exportButton"><i data-lucide="download"></i> Export report</button>
      </section>

      <p class="dashboard-error" role="alert" data-dashboard-error hidden></p>

      <section class="metric-grid" aria-label="Archive key performance indicators">
        <article class="metric">
          <div class="metric-top"><span>Archived records</span><i data-lucide="archive"></i></div>
          <strong data-dashboard="archived_documents">—</strong>
          <small><b>Database verified</b> records safely archived</small>
        </article>
        <article class="metric">
          <div class="metric-top"><span>Student archive coverage</span><i data-lucide="users-round"></i></div>
          <strong data-dashboard="student_archive_coverage_rate">—</strong>
          <small><b data-dashboard-detail="covered_students">—</b> students with an archived record</small>
          <div class="metric-progress" role="progressbar" aria-label="Student archive coverage" aria-valuemin="0" aria-valuemax="100"><span data-dashboard-progress="student_archive_coverage_rate"></span></div>
        </article>
        <article class="metric">
          <div class="metric-top"><span>Archived this week</span><i data-lucide="calendar-check-2"></i></div>
          <strong data-dashboard="archived_this_week">—</strong>
          <small><b data-dashboard-detail="archived_week_change">—</b> compared with last week</small>
        </article>
        <article class="metric dark-metric">
          <div class="metric-top"><span>Search-ready records</span><i data-lucide="scan-search"></i></div>
          <strong data-dashboard="search_ready_rate">—</strong>
          <small><b data-dashboard-detail="search_ready_count">—</b> archived records have completed OCR</small>
          <div class="metric-progress metric-progress-light" role="progressbar" aria-label="Search-ready archived records" aria-valuemin="0" aria-valuemax="100"><span data-dashboard-progress="search_ready_rate"></span></div>
        </article>
      </section>

      <section class="dashboard-grid dashboard-grid-primary">
        <article class="panel dashboard-panel">
          <div class="panel-heading"><div><span class="panel-kicker">Student coverage</span><h3>Archive coverage by college</h3></div><span class="analytics-date">Active and graduated students</span></div>
          <div class="dashboard-list coverage-list" data-dashboard="coverage_by_college"><p class="dashboard-loading">Loading college coverage…</p></div>
        </article>
        <article class="panel dashboard-panel">
          <div class="panel-heading"><div><span class="panel-kicker">Archive throughput</span><h3>Last eight weeks</h3></div><span class="analytics-date">Archived records</span></div>
          <div class="throughput-chart" data-dashboard="archive_by_week"><p class="dashboard-loading">Loading weekly throughput…</p></div>
        </article>
      </section>

      <section class="dashboard-grid dashboard-grid-secondary">
        <article class="panel dashboard-panel">
          <div class="panel-heading"><div><span class="panel-kicker">Archive composition</span><h3>Records by document category</h3></div><span class="analytics-date">Includes zero-count categories</span></div>
          <div class="dashboard-list category-list" data-dashboard="archive_by_category"><p class="dashboard-loading">Loading document categories…</p></div>
        </article>
        <article class="panel dashboard-panel">
          <div class="panel-heading"><div><span class="panel-kicker">Operational quality</span><h3>Records requiring attention</h3></div><i class="trend-icon" data-lucide="shield-check"></i></div>
          <div class="attention-grid" data-dashboard="records_requiring_attention">
            <div><span>OCR pending</span><strong data-attention="ocr_pending_archived">—</strong></div>
            <div><span>OCR failed</span><strong data-attention="ocr_failed_archived">—</strong></div>
            <div><span>Missing file path</span><strong data-attention="missing_file_path_count">—</strong></div>
            <div><span>Missing file size</span><strong data-attention="missing_file_size_count">—</strong></div>
          </div>
          <p class="storage-fact"><i data-lucide="database"></i><span>Archived file size</span><strong data-dashboard-detail="archived_storage_bytes">—</strong></p>
        </article>
      </section>

      <section class="dashboard-activity-section">
        <article class="panel dashboard-panel">
          <div class="panel-heading"><div><span class="panel-kicker">Archive pulse</span><h3>Recent activity</h3></div><a href="files.php">View files <i data-lucide="arrow-up-right"></i></a></div>
          <div class="activity-feed" data-dashboard="dashboard_activity"><p class="dashboard-loading">Loading recent activity…</p></div>
        </article>
      </section>

      <section class="quick-actions" id="users">
        <span class="panel-kicker">Quick actions</span>
        <div>
          <a href="scan.php"><i data-lucide="upload"></i><span><strong>Upload records</strong><small>Add a new archive batch</small></span></a>
          <?php if (($currentUser['role'] ?? '') === 'Admin'): ?><a href="users.php"><i data-lucide="user-round-plus"></i><span><strong>Manage users</strong><small>Roles and permissions</small></span></a><?php endif; ?>
          <a href="categories.php"><i data-lucide="folder-plus"></i><span><strong>Add document category</strong><small>Create an archive document type</small></span></a>
        </div>
      </section>
      <footer><span>Digitalization Archiving Management System</span><span>Archive dashboard <b>·</b> ZPPSU Registrar</span></footer>
    </main>
  </div>

  <div class="admin-toast" id="adminToast" role="status">Your report is being prepared.</div>
  <div class="structure-modal-backdrop" id="adminCategoryModal" hidden><section class="structure-modal" role="dialog" aria-modal="true" aria-labelledby="adminCategoryTitle"><button class="row-action modal-close" id="closeAdminCategoryModal" type="button" aria-label="Close category dialog"><i data-lucide="x"></i></button><span class="panel-kicker">Document categories</span><h2 id="adminCategoryTitle">Add document category</h2><p>Create a category for an approved Registrar's Office document type.</p><label>Category code<input id="adminCategoryCode" type="text" maxlength="8" placeholder="e.g. CTC" /></label><label>Category name<input id="adminCategoryName" type="text" maxlength="150" placeholder="e.g. Certified True Copy" /></label><label>Description<textarea id="adminCategoryDescription" rows="3" maxlength="255" placeholder="Briefly describe this document type"></textarea></label><p id="adminCategoryMessage" role="alert"></p><button class="login-submit" id="saveAdminCategory" type="button">Add category <i data-lucide="arrow-right"></i></button></section></div>
  <script src="../data-sync.js?v=<?= filemtime(__DIR__ . '/../data-sync.js') ?>"></script>
  <script src="script.js?v=20260922"></script>
</body>
</html>
