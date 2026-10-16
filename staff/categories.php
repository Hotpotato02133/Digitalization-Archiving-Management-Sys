<?php require_once __DIR__ . '/../auth.php'; dats_require_role('Staff'); $currentUser = dats_current_user(); $displayName = htmlspecialchars($currentUser['full_name'], ENT_QUOTES, 'UTF-8'); $initials = dats_user_initials($currentUser); ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="Registrar's Office document categories for the ZPPSU digital archive." />
  <title>Document Categories | ZPPSU DATS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="styles.css" />
  <link rel="stylesheet" href="categories.css" />
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
        <a class="active" href="categories.php" aria-current="page"><i data-lucide="folders"></i> Document Categories</a>
        <a href="students.php"><i data-lucide="contact-round"></i> Student Information</a>
        <a href="history.php"><i data-lucide="history"></i> My recent upload files</a>
        <a href="analytics.php"><i data-lucide="chart-no-axes-combined"></i> Analytics</a>
      </nav>
      <div class="sidebar-bottom"><a href="index.php#profile"><i data-lucide="badge-check"></i> Staff profile</a><a class="back-link" href="../logout.php"><i data-lucide="log-out"></i> Return to site</a></div>
    </aside>

    <main class="staff-main categories-main">
      <header class="staff-header"><button class="mobile-menu" id="mobileMenu" aria-label="Open staff navigation" aria-expanded="false"><i data-lucide="menu"></i></button><div><span class="eyebrow">Staff workspace</span><h1>Good morning, <?= $displayName ?>.</h1></div><div class="header-actions"><button class="header-icon" aria-label="Notifications"><i data-lucide="bell"></i><span></span></button><div class="user-chip"><span class="avatar"><?= $initials ?></span><span><strong><?= $displayName ?></strong><small>Archive Staff</small></span><i data-lucide="chevron-down"></i></div></div></header>

      <section class="welcome-row categories-welcome"><div><p class="page-kicker">Registrar's Office · <span data-current-datetime>Loading date and time...</span></p><h2>Document categories</h2><p class="page-intro">Choose the right category before scanning or uploading a student record.</p></div><button class="gold-button" id="addCategoryButton" type="button"><i data-lucide="folder-plus"></i> Add File Category</button></section>

      <section class="category-summary" aria-label="Document category summary"><div><strong>8</strong><span>Active categories</span></div><div><strong data-stat="total_documents">0</strong><span>Archived documents</span></div><div><strong>92.4%</strong><span>Digitized records</span></div></section>

      <section class="category-panel panel" id="categories"><div class="panel-heading"><div><span class="panel-kicker">Registrar's Office archive</span><h3>Common document categories</h3></div><span class="category-note">Use official document names</span></div><div class="category-grid" id="categoryGrid"><article class="category-card featured"><div class="category-icon"><i data-lucide="graduation-cap"></i></div><div><span class="category-code">TOR</span><h4>Transcript of Records</h4><p>Official academic history, grades, and course credits.</p></div><span class="category-count">18,240 files</span></article><article class="category-card"><div class="category-icon gold-icon"><i data-lucide="file-check-2"></i></div><div><span class="category-code">COR</span><h4>Certificate of Registration</h4><p>Proof of a student's registration for an academic term.</p></div><span class="category-count">9,816 files</span></article><article class="category-card"><div class="category-icon rose-icon"><i data-lucide="badge-check"></i></div><div><span class="category-code">COE</span><h4>Certificate of Enrollment</h4><p>Confirms current enrollment and student status.</p></div><span class="category-count">7,432 files</span></article><article class="category-card"><div class="category-icon blue-icon"><i data-lucide="award"></i></div><div><span class="category-code">COG</span><h4>Certificate of Graduation</h4><p>Confirms completion of an academic program.</p></div><span class="category-count">5,106 files</span></article><article class="category-card"><div class="category-icon gold-icon"><i data-lucide="file-text"></i></div><div><span class="category-code">GMC</span><h4>Good Moral Certificate</h4><p>Registrar-issued certification of student conduct.</p></div><span class="category-count">3,884 files</span></article><article class="category-card"><div class="category-icon"><i data-lucide="scroll-text"></i></div><div><span class="category-code">DIP</span><h4>Diploma</h4><p>Formal award issued after program completion.</p></div><span class="category-count">2,745 files</span></article><article class="category-card"><div class="category-icon rose-icon"><i data-lucide="calendar-days"></i></div><div><span class="category-code">ACR</span><h4>Academic Clearance</h4><p>Clearance records completed before graduation.</p></div><span class="category-count">864 files</span></article><article class="category-card"><div class="category-icon blue-icon"><i data-lucide="folder-open"></i></div><div><span class="category-code">OTH</span><h4>Other Registrar Records</h4><p>Supporting forms and approved academic documents.</p></div><span class="category-count">204 files</span></article></div></section>

      <section class="category-help"><i data-lucide="info"></i><p><strong>Need a category that is not listed?</strong><span>Ask the records supervisor before uploading. Do not create a new category for an existing document type.</span></p><a href="mailto:registrar@zppsu.edu.ph">Contact Registrar <i data-lucide="arrow-up-right"></i></a></section>
      <footer><span>Digitalization Archiving Management System</span><span>Document Categories <b>·</b> ZPPSU Registrar</span></footer>
    </main>
  </div>
  <div class="category-modal-backdrop" id="categoryModal" hidden>
    <section class="category-modal" role="dialog" aria-modal="true" aria-labelledby="categoryModalTitle">
      <button class="modal-close" id="closeCategoryModal" type="button" aria-label="Close add category dialog"><i data-lucide="x"></i></button>
      <span class="panel-kicker">Registrar's Office archive</span>
      <h2 id="categoryModalTitle">Add File Category</h2>
      <p class="modal-copy">Create a category for an approved Registrar's Office document type.</p>
      <form id="categoryForm">
        <label for="categoryCode">Category code *<input id="categoryCode" type="text" maxlength="8" placeholder="e.g. CTC" required /></label>
        <label for="categoryName">Category name *<input id="categoryName" type="text" maxlength="60" placeholder="e.g. Certified True Copy" required /></label>
        <label for="categoryDescription">Description *<textarea id="categoryDescription" rows="3" maxlength="140" placeholder="Briefly describe this document type" required></textarea></label>
        <div class="modal-actions"><button class="modal-cancel" id="cancelCategoryButton" type="button">Cancel</button><button class="login-submit" type="submit">Add category <i data-lucide="arrow-right"></i></button></div>
      </form>
    </section>
  </div>
  <script src="../data-sync.js"></script>
  <script src="script.js"></script>
</body>
</html>


