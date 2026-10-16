<?php require_once __DIR__ . '/../auth.php'; dats_require_role('Staff'); $currentUser = dats_current_user(); $displayName = htmlspecialchars($currentUser['full_name'], ENT_QUOTES, 'UTF-8'); $initials = dats_user_initials($currentUser); ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="ZPPSU Digital Archiving Management System staff analytics." />
  <title>Analytics | ZPPSU DATS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&amp;family=Manrope:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../admin/styles.css" />
  <script src="https://unpkg.com/lucide@latest"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
</head>
<body>
  <div class="admin-shell">
    <aside class="sidebar" id="sidebar">
      <a class="brand" href="index.php"><img src="../img/ZPPSU.png" alt="ZPPSU seal" /><span><strong>ZPPSU</strong><small>Digital Archive</small></span></a>
      <div class="workspace-label">Staff workspace</div>
      <nav aria-label="Staff navigation">
        <a href="index.php"><i data-lucide="layout-dashboard"></i> Dashboard</a>
        <a href="index.php#records"><i data-lucide="archive"></i> My archive activity</a>
        <a href="scan.php"><i data-lucide="scan-line"></i> File Scanning</a>
        <a href="categories.php"><i data-lucide="folders"></i> Document Categories</a>
        <a href="students.php"><i data-lucide="contact-round"></i> Student Information</a>
        <a href="history.php"><i data-lucide="history"></i> My recent upload files</a>
        <a class="active" href="analytics.php" aria-current="page"><i data-lucide="chart-no-axes-combined"></i> Analytics</a>
      </nav>
      <div class="sidebar-bottom"><a href="index.php#profile"><i data-lucide="badge-check"></i> Staff profile</a><a class="back-link" href="../logout.php"><i data-lucide="log-out"></i> Return to site</a></div>
    </aside>
    <main class="admin-main analytics-main">
      <header class="admin-header"><button class="mobile-menu" id="mobileMenu" aria-label="Open staff navigation" aria-expanded="false"><i data-lucide="menu"></i></button><div><span class="eyebrow">Staff workspace</span><h1>Good morning, <?= $displayName ?>.</h1></div><div class="header-actions"><button class="header-icon" aria-label="Notifications"><i data-lucide="bell"></i><span></span></button><div class="user-chip"><span class="avatar"><?= $initials ?></span><span><strong><?= $displayName ?></strong><small>Archive Staff</small></span><i data-lucide="chevron-down"></i></div></div></header>
      <section class="welcome-row analytics-welcome"><div><p class="page-kicker">Institution snapshot · <span data-current-datetime>Loading date and time...</span></p><h2>Analytics</h2><p class="page-intro">A high-level view of the university's academic records and archive activity.</p></div><button class="gold-button" id="exportButton" type="button"><i data-lucide="download"></i> Export report</button></section>
      <section class="analytics-metrics" aria-label="Institution analytics">
        <article class="analytics-card maroon-card"><div class="analytics-card-top"><span>Total number of students</span><i data-lucide="graduation-cap"></i></div><strong data-stat="total_students">0</strong><small><b>SQL synced</b> active student records</small><div class="analytics-bars maroon-bars"><span></span><span></span><span></span><span></span><span></span><span></span><span></span></div></article>
        <article class="analytics-card"><div class="analytics-card-top"><span>Total Documents</span><i data-lucide="files"></i></div><strong>48,291</strong><small><b>92.4%</b> fully digitized</small><div class="analytics-bars"><span></span><span></span><span></span><span></span><span></span><span></span><span></span></div></article>
        <article class="analytics-card"><div class="analytics-card-top"><span>Total Colleges</span><i data-lucide="building-2"></i></div><strong>8</strong><small><b>100%</b> connected to archive</small><div class="college-dots"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div></article>
        <article class="analytics-card gold-card"><div class="analytics-card-top"><span>Total Courses</span><i data-lucide="book-open"></i></div><strong>73</strong><small><b>+5</b> new courses this year</small><div class="course-track"><span></span></div></article>
      </section>
      <section class="analytics-lower"><article class="panel insight-panel"><div class="panel-heading"><div><span class="panel-kicker">Document distribution</span><h3>Archive by college</h3></div><span class="analytics-date">SQL synced</span></div><div class="distribution" data-archive-distribution></div></article><article class="panel insight-panel"><div class="panel-heading"><div><span class="panel-kicker">Key indicator</span><h3>Digitization progress</h3></div><i class="trend-icon" data-lucide="trending-up"></i></div><div class="progress-summary"><strong>92.4%</strong><span>of all student records are digitally archived.</span></div><div class="wide-progress"><span></span></div><small class="progress-note"><b>+8.2%</b> since January 2026</small></article></section>
      <footer><span>Digitalization Archiving Management System</span><span>Analytics <b>·</b> ZPPSU Registrar</span></footer>
    </main>
  </div>
  <div class="admin-toast" id="adminToast" role="status">Your report is being prepared.</div>
  <script src="../data-sync.js?v=20260908"></script>
  <script src="../admin/script.js"></script>
</body>
</html>


