<?php require_once __DIR__ . '/../auth.php'; dats_require_role('Staff'); $currentUser = dats_current_user(); $displayName = htmlspecialchars($currentUser['full_name'], ENT_QUOTES, 'UTF-8'); $initials = dats_user_initials($currentUser); ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="Scan and upload records to the ZPPSU digital archive." />
  <title>File Scanning | ZPPSU DATS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../admin/styles.css" />
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
  <div class="admin-shell">
    <aside class="sidebar" id="sidebar">
      <a class="brand" href="index.php"><img src="../img/ZPPSU.png" alt="ZPPSU seal" /><span><strong>ZPPSU</strong><small>Digital Archive</small></span></a>
      <div class="workspace-label">Staff workspace</div>
      <nav aria-label="Staff navigation">
        <a href="index.php"><i data-lucide="layout-dashboard"></i> Dashboard</a>
        <a href="index.php#records"><i data-lucide="archive"></i> My archive activity</a>
        <a class="active" href="scan.php" aria-current="page"><i data-lucide="scan-line"></i> File Scanning</a>
        <a href="categories.php"><i data-lucide="folders"></i> Document Categories</a>
        <a href="students.php"><i data-lucide="contact-round"></i> Student Information</a>
        <a href="history.php"><i data-lucide="history"></i> My recent upload files</a>
        <a href="analytics.php"><i data-lucide="chart-no-axes-combined"></i> Analytics</a>
      </nav>
      <div class="sidebar-bottom"><a href="index.php#profile"><i data-lucide="badge-check"></i> Staff profile</a><a class="back-link" href="../logout.php"><i data-lucide="log-out"></i> Return to site</a></div>
    </aside>
    <main class="admin-main scan-main">
      <header class="admin-header"><button class="mobile-menu" id="mobileMenu" aria-label="Open staff navigation" aria-expanded="false"><i data-lucide="menu"></i></button><div><span class="eyebrow">Staff workspace</span><h1>Good morning, <?= $displayName ?>.</h1></div><div class="header-actions"><button class="header-icon" aria-label="Notifications"><i data-lucide="bell"></i><span></span></button><div class="user-chip"><span class="avatar"><?= $initials ?></span><span><strong><?= $displayName ?></strong><small>Archive Staff</small></span><i data-lucide="chevron-down"></i></div></div></header>
      <section class="welcome-row scan-welcome"><div><p class="page-kicker">Archive intake · <span data-current-datetime>Loading date and time...</span></p><h2>File Scanning</h2><p class="page-intro">Capture a document with your camera or add an existing file to the archive.</p></div><span class="scanner-status"><i></i> Scanner ready</span></section>
      <section class="scan-layout">
        <article class="panel scan-card"><div class="panel-heading"><div><span class="panel-kicker">Method one</span><h3>Scan with camera</h3></div><span class="method-number">01</span></div><div class="camera-stage" id="cameraStage"><video id="cameraVideo" autoplay playsinline hidden></video><div class="camera-placeholder" id="cameraPlaceholder"><i data-lucide="camera"></i><strong>Camera preview</strong><small>Position the document inside the frame</small></div><span class="scan-frame"></span></div><div class="camera-actions"><button class="gold-button" id="startCamera"><i data-lucide="camera"></i> Start camera</button><button class="outline-button scan-outline" id="captureButton" disabled><i data-lucide="scan-line"></i> Capture page</button><p class="permission-note"><i data-lucide="info"></i> Use live camera on secure sites, or device capture from a local file.</p></div><input class="camera-capture" id="cameraCapture" type="file" accept="image/*" capture="environment" /></article>
        <article class="panel scan-card"><div class="panel-heading"><div><span class="panel-kicker">Method two</span><h3>Manual upload</h3></div><span class="method-number">02</span></div><label class="upload-zone" id="uploadZone" for="fileInput"><input id="fileInput" type="file" accept="image/*,.pdf" multiple /><span class="upload-icon"><i data-lucide="upload-cloud"></i></span><strong>Drop files here or browse</strong><small>PDF, JPG, or PNG · up to 25 MB each</small></label><div class="document-preview" id="documentPreview" hidden aria-label="Selected document preview"></div><div class="upload-result" id="uploadResult" aria-live="polite"><i data-lucide="file-plus-2"></i><span>No files selected yet</span></div></article>
      </section>
      <section class="scan-details panel"><div class="panel-heading"><div><span class="panel-kicker">Record details</span><h3>Prepare for archive</h3></div><span class="required-note">* Required fields</span></div><div class="details-grid"><label>Student name *<input id="scanStudentName" list="scanStudentNames" type="text" placeholder="Type or select a student name" autocomplete="off" /><datalist id="scanStudentNames"></datalist></label><label>Document type *<select id="scanCategory"><option value="">Select document type</option></select></label><label>Academic year<input type="text" placeholder="e.g. 2026-2027" /></label></div><button class="login-submit archive-button" id="archiveButton" type="button"><i data-lucide="archive-restore"></i> Add to archive</button></section>
      <section class="recent-scans"><div class="panel-heading"><div><span class="panel-kicker">Today's intake</span><h3>Recently added</h3></div><a href="index.php#records">View archive <i data-lucide="arrow-up-right"></i></a></div><div class="recent-list"><div><span class="recent-file"><i data-lucide="file-check-2"></i></span><p><strong>Certificate of Graduation</strong><small>ZP-2026-004821 · just now</small></p><span class="pill success">Uploaded</span></div><div><span class="recent-file gold-file"><i data-lucide="file-image"></i></span><p><strong>Official Transcript</strong><small>ZP-2026-004820 · 18 minutes ago</small></p><span class="pill pending">Processing</span></div></div></section>
      <footer><span>Digitalization Archiving Management System</span><span>File Scanning <b>·</b> ZPPSU Registrar</span></footer>
    </main>
  </div>
  <div class="admin-toast" id="adminToast" role="status">Your scan has been added to the queue.</div>
  <script src="../data-sync.js?v=20260909"></script>
  <script src="../admin/script.js?v=20260909"></script>
</body>
</html>


