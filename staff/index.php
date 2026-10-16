<?php require_once __DIR__ . '/../auth.php'; dats_require_role('Staff'); $currentUser = dats_current_user(); $displayName = htmlspecialchars($currentUser['full_name'], ENT_QUOTES, 'UTF-8'); $initials = dats_user_initials($currentUser); ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="ZPPSU Digital Archiving Management System staff dashboard." />
  <title>Staff Dashboard | ZPPSU DATS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="styles.css" />
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
  <div class="staff-shell">
    <aside class="sidebar" id="sidebar">
      <a class="brand" href="../index.php"><img src="../img/ZPPSU.png" alt="ZPPSU seal" /><span><strong>ZPPSU</strong><small>Digital Archive</small></span></a>
      <div class="workspace-label">Staff workspace</div>
      <nav aria-label="Staff navigation">
        <a class="active" href="#dashboard" aria-current="page"><i data-lucide="layout-dashboard"></i> Dashboard</a>
        <a href="#records"><i data-lucide="archive"></i> My archive activity</a>
        <a href="scan.php"><i data-lucide="scan-line"></i> File Scanning</a>
        <a href="categories.php"><i data-lucide="folders"></i> Document Categories</a>
        <a href="students.php"><i data-lucide="contact-round"></i> Student Information</a>
        <a href="history.php"><i data-lucide="history"></i> My recent upload files</a>
        <a href="analytics.php"><i data-lucide="chart-no-axes-combined"></i> Analytics</a>
      </nav>
      <div class="sidebar-bottom"><a href="#profile"><i data-lucide="badge-check"></i> Staff profile</a><a class="back-link" href="../logout.php"><i data-lucide="log-out"></i> Return to site</a></div>
    </aside>

    <main class="staff-main" id="dashboard">
      <header class="staff-header"><button class="mobile-menu" id="mobileMenu" aria-label="Open staff navigation" aria-expanded="false"><i data-lucide="menu"></i></button><div><span class="eyebrow">Staff workspace</span><h1>Good morning, <?= $displayName ?>.</h1></div><div class="header-actions"><button class="header-icon" aria-label="Notifications"><i data-lucide="bell"></i><span></span></button><div class="user-chip"><span class="avatar"><?= $initials ?></span><span><strong><?= $displayName ?></strong><small>Archive Staff</small></span><i data-lucide="chevron-down"></i></div></div></header>

      <section class="welcome-row"><div><p class="page-kicker"><span data-current-datetime>Loading date and time...</span></p><h2>Today's work queue</h2><p class="page-intro">Keep records moving from intake to the verified archive.</p></div><button class="gold-button" id="scanButton"><i data-lucide="scan-line"></i> Start a scan</button></section>

      <section class="metric-grid" aria-label="Staff work metrics">
        <article class="metric"><div class="metric-top"><span>Assigned to me</span><i data-lucide="clipboard-list"></i></div><strong>6</strong><small><b>3 due today</b> in your queue</small></article>
        <article class="metric"><div class="metric-top"><span>Processed this week</span><i data-lucide="files"></i></div><strong>128</strong><small><b>+18%</b> from last week</small></article>
        <article class="metric"><div class="metric-top"><span>Awaiting review</span><i data-lucide="file-clock"></i></div><strong>4</strong><small><b class="dark-gold">2 urgent</b> need attention</small></article>
        <article class="metric dark-metric"><div class="metric-top"><span>Archive status</span><i data-lucide="shield-check"></i></div><strong>Online</strong><small><b>99.98%</b> system availability</small></article>
      </section>

      <section class="content-grid" id="tasks">
        <article class="panel task-panel"><div class="panel-heading"><div><span class="panel-kicker">Priority queue</span><h3>Assigned tasks</h3></div><span class="queue-label">6 open</span></div><div class="task-list"><button class="task-row" data-task="Official Transcript"><span class="task-icon gold"><i data-lucide="file-text"></i></span><span><strong>Official Transcript</strong><small>Juan Dela Cruz · Due today</small></span><span class="task-status urgent">Urgent</span><i data-lucide="arrow-up-right"></i></button><button class="task-row" data-task="Certificate of Graduation"><span class="task-icon blue"><i data-lucide="graduation-cap"></i></span><span><strong>Certificate of Graduation</strong><small>Angela Ramos · Due today</small></span><span class="task-status review">Review</span><i data-lucide="arrow-up-right"></i></button><button class="task-row" data-task="Certificate of Enrollment"><span class="task-icon rose"><i data-lucide="stamp"></i></span><span><strong>Certificate of Enrollment</strong><small>Mark Salazar · Tomorrow</small></span><span class="task-status review">Review</span><i data-lucide="arrow-up-right"></i></button></div></article>
        <article class="panel activity-panel" id="records"><div class="panel-heading"><div><span class="panel-kicker">Your archive pulse</span><h3>Recent activity</h3></div><button class="more-button" aria-label="More activity options"><i data-lucide="more-horizontal"></i></button></div><div class="timeline"><div><span class="timeline-dot"></span><p><strong>4 records verified</strong><small>Batch 2026-09-A <em>10:42 AM</em></small></p></div><div><span class="timeline-dot gold-dot"></span><p><strong>New task assigned</strong><small>Certificate review <em>09:18 AM</em></small></p></div><div><span class="timeline-dot rose-dot"></span><p><strong>Scan batch uploaded</strong><small>12 pages · Batch 2026-09-A <em>08:46 AM</em></small></p></div></div></article>
      </section>

      <section class="quick-actions" id="profile"><span class="panel-kicker">Staff actions</span><div><button data-action="scan"><i data-lucide="scan-line"></i><span><strong>Scan a document</strong><small>Add a new record to the queue</small></span></button><button data-action="upload"><i data-lucide="upload"></i><span><strong>Upload a batch</strong><small>Submit prepared archive files</small></span></button><button data-action="help"><i data-lucide="circle-help"></i><span><strong>Get workflow help</strong><small>Contact the records supervisor</small></span></button></div></section>
      <footer><span>Digitalization Archiving Management System</span><span>Staff workspace <b>·</b> ZPPSU Registrar</span></footer>
    </main>
  </div>
  <div class="staff-toast" id="staffToast" role="status"></div>
  <script src="../data-sync.js"></script>
  <script src="script.js"></script>
</body>
</html>


