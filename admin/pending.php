<?php require_once __DIR__ . '/../auth.php'; dats_require_role('Admin'); $currentUser = dats_current_user(); $displayName = htmlspecialchars($currentUser['full_name'], ENT_QUOTES, 'UTF-8'); $initials = dats_user_initials($currentUser); ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="Review scanned documents before adding them to the ZPPSU digital archive." />
  <title>Pending Reviews | ZPPSU DATS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="styles.css" />
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
  <div class="admin-shell">
    <aside class="sidebar" id="sidebar">
      <a class="brand" href="../index.php"><img src="../img/ZPPSU.png" alt="ZPPSU seal" /><span><strong>ZPPSU</strong><small>Digital Archive</small></span></a>
      <div class="workspace-label">Workspace</div>
      <nav aria-label="Admin navigation">
        <a href="index.php"><i data-lucide="layout-dashboard"></i> Dashboard</a>
        <a href="index.php#records"><i data-lucide="archive"></i> Records</a>
        <a href="users.php"><i data-lucide="users"></i> Users</a>
        <a href="scan.php"><i data-lucide="scan-line"></i> File Scanning</a>
        <a class="active" href="pending.php" aria-current="page"><i data-lucide="file-clock"></i> Pending Reviews</a>
        <a href="colleges.php"><i data-lucide="building-2"></i> Colleges &amp; Courses</a>
        <a href="categories.php"><i data-lucide="folder-plus"></i> Document Categories</a>
        <a href="students.php"><i data-lucide="contact-round"></i> Students Information</a>
        <a href="analytics.php"><i data-lucide="chart-no-axes-combined"></i> Analytics</a>
      </nav>
      <div class="sidebar-bottom"><a href="index.php#settings"><i data-lucide="settings-2"></i> Settings</a><a class="back-link" href="../logout.php"><i data-lucide="log-out"></i> Return to site</a></div>
    </aside>
    <main class="admin-main pending-main">
      <header class="admin-header"><button class="mobile-menu" id="mobileMenu" aria-label="Open admin navigation" aria-expanded="false"><i data-lucide="menu"></i></button><div><span class="eyebrow">Administrator workspace</span><h1>Good morning, <?= $displayName ?>.</h1></div><div class="header-actions"><button class="header-icon" aria-label="Notifications"><i data-lucide="bell"></i><span></span></button><div class="user-chip"><span class="avatar"><?= $initials ?></span><span><strong><?= $displayName ?></strong><small>System Administrator</small></span><i data-lucide="chevron-down"></i></div></div></header>
      <section class="welcome-row pending-welcome"><div><p class="page-kicker">Archive intake · <span data-current-datetime>Loading date and time...</span></p><h2>Pending reviews</h2><p class="page-intro">Review scanned documents before they become part of the official archive.</p></div><span class="review-count"><strong id="pendingCount">4</strong> documents waiting</span></section>
      <section class="review-toolbar panel"><div class="search-box"><i data-lucide="search"></i><input id="reviewSearch" type="search" placeholder="Search student ID or document type" /></div><select id="reviewType" aria-label="Filter by document type"><option value="all">All document types</option><option>Official Transcript</option><option>Certificate of Graduation</option><option>Certificate of Enrollment</option></select><span class="sort-label"><i data-lucide="arrow-down-up"></i> Newest first</span></section>
      <section class="review-list" id="reviewList">
        <article class="review-card"><div class="review-preview"><div class="fake-document transcript"><span>OFFICIAL<br />TRANSCRIPT</span><small>ZPPSU · 2026</small></div><span class="preview-label">Page 1 of 2</span></div><div class="review-info"><div class="review-title"><div><span class="panel-kicker">Official Transcript</span><h3>Juan Dela Cruz</h3></div><span class="review-pill urgent-pill">Urgent</span></div><div class="review-meta"><span><i data-lucide="fingerprint"></i> 2026-001234</span><span><i data-lucide="clock-3"></i> Uploaded 8 min ago</span><span><i data-lucide="user-round"></i> Scanned by Angela Santos</span></div><div class="review-actions"><button class="approve-button" data-action="approved"><i data-lucide="check"></i> Approve</button><button class="reject-button" data-action="rejected"><i data-lucide="x"></i> Reject</button><button class="view-button" aria-label="View full document"><i data-lucide="maximize-2"></i></button></div></div></article>
        <article class="review-card"><div class="review-preview"><div class="fake-document certificate"><span>CERTIFICATE<br />OF GRADUATION</span><small>ZPPSU · 2026</small></div><span class="preview-label">Page 1 of 1</span></div><div class="review-info"><div class="review-title"><div><span class="panel-kicker">Certificate of Graduation</span><h3>Angela Ramos</h3></div><span class="review-pill review-pill-neutral">Review</span></div><div class="review-meta"><span><i data-lucide="fingerprint"></i> 2026-001198</span><span><i data-lucide="clock-3"></i> Uploaded 24 min ago</span><span><i data-lucide="user-round"></i> Scanned by Maria Cruz</span></div><div class="review-actions"><button class="approve-button" data-action="approved"><i data-lucide="check"></i> Approve</button><button class="reject-button" data-action="rejected"><i data-lucide="x"></i> Reject</button><button class="view-button" aria-label="View full document"><i data-lucide="maximize-2"></i></button></div></div></article>
        <article class="review-card"><div class="review-preview"><div class="fake-document enrollment"><span>CERTIFICATE<br />OF ENROLLMENT</span><small>ZPPSU · 2026</small></div><span class="preview-label">Page 1 of 1</span></div><div class="review-info"><div class="review-title"><div><span class="panel-kicker">Certificate of Enrollment</span><h3>Mark Salazar</h3></div><span class="review-pill review-pill-neutral">Review</span></div><div class="review-meta"><span><i data-lucide="fingerprint"></i> 2026-000987</span><span><i data-lucide="clock-3"></i> Uploaded 41 min ago</span><span><i data-lucide="user-round"></i> Scanned by Rafael Bautista</span></div><div class="review-actions"><button class="approve-button" data-action="approved"><i data-lucide="check"></i> Approve</button><button class="reject-button" data-action="rejected"><i data-lucide="x"></i> Reject</button><button class="view-button" aria-label="View full document"><i data-lucide="maximize-2"></i></button></div></div></article>
        <article class="review-card"><div class="review-preview"><div class="fake-document moral"><span>GOOD MORAL<br />CERTIFICATE</span><small>ZPPSU · 2026</small></div><span class="preview-label">Page 1 of 1</span></div><div class="review-info"><div class="review-title"><div><span class="panel-kicker">Good Moral Certificate</span><h3>Leah Mendoza</h3></div><span class="review-pill review-pill-neutral">Review</span></div><div class="review-meta"><span><i data-lucide="fingerprint"></i> 2026-000876</span><span><i data-lucide="clock-3"></i> Uploaded 1 hr ago</span><span><i data-lucide="user-round"></i> Scanned by Angela Santos</span></div><div class="review-actions"><button class="approve-button" data-action="approved"><i data-lucide="check"></i> Approve</button><button class="reject-button" data-action="rejected"><i data-lucide="x"></i> Reject</button><button class="view-button" aria-label="View full document"><i data-lucide="maximize-2"></i></button></div></div></article>
      </section>
      <p class="empty-reviews" id="emptyReviews" hidden>No pending documents match your search.</p>
      <footer><span>Digitalization Archiving Management System</span><span>Pending Reviews <b>·</b> ZPPSU Registrar</span></footer>
    </main>
  </div>
  <div class="admin-toast" id="adminToast" role="status">Document review updated.</div>
  <script src="../data-sync.js?v=20260911"></script>
  <script src="script.js"></script>
</body>
</html>


