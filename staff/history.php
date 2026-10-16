<?php require_once __DIR__ . '/../auth.php'; dats_require_role('Staff'); $currentUser = dats_current_user(); $displayName = htmlspecialchars($currentUser['full_name'], ENT_QUOTES, 'UTF-8'); $initials = dats_user_initials($currentUser); ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="Track recent files uploaded by staff to the ZPPSU digital archive." />
  <title>My Recent Upload Files | ZPPSU DATS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="styles.css" />
  <link rel="stylesheet" href="history.css" />
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
  <div class="staff-shell">
    <aside class="sidebar" id="sidebar">
      <a class="brand" href="index.php"><img src="../img/ZPPSU.png" alt="ZPPSU seal" /><span><strong>ZPPSU</strong><small>Digital Archive</small></span></a>
      <div class="workspace-label">Staff workspace</div>
      <nav aria-label="Staff navigation">
        <a href="index.php"><i data-lucide="layout-dashboard"></i> Dashboard</a>
        <a href="index.php#records"><i data-lucide="archive"></i> My archive activity</a>
        <a href="scan.php"><i data-lucide="scan-line"></i> File Scanning</a>
        <a href="categories.php"><i data-lucide="folders"></i> Document Categories</a>
        <a href="students.php"><i data-lucide="contact-round"></i> Student Information</a>
        <a class="active" href="history.php" aria-current="page"><i data-lucide="history"></i> My recent upload files</a>
        <a href="analytics.php"><i data-lucide="chart-no-axes-combined"></i> Analytics</a>
      </nav>
      <div class="sidebar-bottom"><a href="index.php#profile"><i data-lucide="badge-check"></i> Staff profile</a><a class="back-link" href="../logout.php"><i data-lucide="log-out"></i> Return to site</a></div>
    </aside>

    <main class="staff-main history-main">
      <header class="staff-header"><button class="mobile-menu" id="mobileMenu" aria-label="Open staff navigation" aria-expanded="false"><i data-lucide="menu"></i></button><div><span class="eyebrow">Staff workspace</span><h1>Good morning, <?= $displayName ?>.</h1></div><div class="header-actions"><button class="header-icon" aria-label="Notifications"><i data-lucide="bell"></i><span></span></button><div class="user-chip"><span class="avatar"><?= $initials ?></span><span><strong><?= $displayName ?></strong><small>Archive Staff</small></span><i data-lucide="chevron-down"></i></div></div></header>

      <section class="welcome-row history-welcome"><div><p class="page-kicker">Upload activity · <span data-current-datetime>Loading date and time...</span></p><h2>My recent upload files</h2><p class="page-intro">Review the files you have submitted and track their archive status.</p></div><a class="gold-button" href="scan.php"><i data-lucide="upload"></i> Upload a file</a></section>

      <section class="history-summary" aria-label="Upload history summary"><div><strong>128</strong><span>Total uploads</span></div><div><strong>118</strong><span>Completed</span></div><div><strong>6</strong><span>Processing</span></div><div><strong>4</strong><span>Needs review</span></div></section>

      <section class="history-panel panel"><div class="history-toolbar"><div class="search-box"><i data-lucide="search"></i><input id="historySearch" type="search" placeholder="Search file name or student ID" /></div><select id="historyStatus" aria-label="Filter uploads by status"><option value="all">All statuses</option><option value="Uploaded">Uploaded</option><option value="Processing">Processing</option><option value="Needs review">Needs review</option></select><select id="historyPeriod" aria-label="Filter uploads by period"><option value="all">All time</option><option value="today">Today</option><option value="week">This week</option></select></div><div class="history-table-wrap"><table class="history-table"><thead><tr><th>File</th><th>Student ID</th><th>Category</th><th>Uploaded</th><th>Status</th><th><span class="sr-only">Action</span></th></tr></thead><tbody id="historyRows"><tr data-status="Uploaded" data-period="today"><td><div class="history-file"><span class="file-icon"><i data-lucide="file-check-2"></i></span><span><strong>Juanita_DelaCruz_TOR.pdf</strong><small>PDF · 2.4 MB</small></span></div></td><td class="mono-cell">2026-001234</td><td>TOR</td><td>Today, 10:42 AM</td><td><span class="history-status uploaded">Uploaded</span></td><td><button class="row-action" type="button" aria-label="View Juanita Dela Cruz upload"><i data-lucide="more-horizontal"></i></button></td></tr><tr data-status="Processing" data-period="today"><td><div class="history-file"><span class="file-icon gold-file-icon"><i data-lucide="file-image"></i></span><span><strong>Miguel_Ramos_COE.jpg</strong><small>JPG · 1.8 MB</small></span></div></td><td class="mono-cell">2026-001198</td><td>COE</td><td>Today, 9:18 AM</td><td><span class="history-status processing">Processing</span></td><td><button class="row-action" type="button" aria-label="View Miguel Ramos upload"><i data-lucide="more-horizontal"></i></button></td></tr><tr data-status="Needs review" data-period="week"><td><div class="history-file"><span class="file-icon rose-file-icon"><i data-lucide="file-clock"></i></span><span><strong>Angela_Santos_COR.pdf</strong><small>PDF · 3.1 MB</small></span></div></td><td class="mono-cell">2026-000987</td><td>COR</td><td>Yesterday, 3:26 PM</td><td><span class="history-status review-status">Needs review</span></td><td><button class="row-action" type="button" aria-label="View Angela Santos upload"><i data-lucide="more-horizontal"></i></button></td></tr><tr data-status="Uploaded" data-period="week"><td><div class="history-file"><span class="file-icon"><i data-lucide="file-check-2"></i></span><span><strong>Mark_Salazar_GMC.pdf</strong><small>PDF · 1.2 MB</small></span></div></td><td class="mono-cell">2026-000876</td><td>GMC</td><td>02 Sep 2026, 11:05 AM</td><td><span class="history-status uploaded">Uploaded</span></td><td><button class="row-action" type="button" aria-label="View Mark Salazar upload"><i data-lucide="more-horizontal"></i></button></td></tr></tbody></table></div><p class="empty-history" id="emptyHistory" hidden>No upload files match your search or filters.</p></section>

      <section class="history-note"><i data-lucide="shield-check"></i><p><strong>Upload history is personal to your staff account.</strong><span>Files remain visible here while they are being processed, reviewed, or archived.</span></p></section>
      <footer><span>Digitalization Archiving Management System</span><span>Upload History <b>·</b> ZPPSU Registrar</span></footer>
    </main>
  </div>
  <script src="../data-sync.js"></script>
  <script src="script.js"></script>
</body>
</html>


