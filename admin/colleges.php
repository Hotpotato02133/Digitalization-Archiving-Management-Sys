<?php require_once __DIR__ . '/../auth.php'; dats_require_page_access(basename(__FILE__)); $currentUser = dats_current_user(); $displayName = htmlspecialchars($currentUser['full_name'], ENT_QUOTES, 'UTF-8'); $initials = dats_user_initials($currentUser); ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="Manage ZPPSU colleges and courses." />
  <title>Colleges &amp; Courses | ZPPSU DATS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&amp;family=Manrope:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="styles.css?v=20260914" />
  <style>
    .structure-modal [hidden] { display: none; }
    .course-list .course-edit { margin-left: auto; }
    .course-list > div { display: grid; grid-template-columns: minmax(0, 1fr) auto 24px 24px 24px; align-items: center; gap: 8px; min-width: 0; }
    .course-list > div > span { min-width: 0; line-height: 1.35; overflow-wrap: anywhere; word-break: break-word; }
    .course-list .course-name { display: block; }
    .course-list .major-list { display: grid; gap: 3px; margin: 6px 0 0; padding-left: 17px; color: var(--muted); font-size: 9px; line-height: 1.35; }
    .course-list .major-list li::marker { color: var(--gold); }
    .course-list > div > b { min-width: 0; margin-left: 0; text-align: right; white-space: nowrap; overflow-wrap: anywhere; }
    .course-list .course-add-major, .course-list .course-edit, .course-list .course-delete { margin-left: 0; flex-shrink: 0; }
    .college-heading, .college-title, .college-title > div, .course-list, .course-list > div { min-width: 0; }
    .college-title h3 { max-width: 100%; overflow-wrap: anywhere; word-break: break-word; }
    .college-actions { flex-shrink: 0; }
    .college-actions [data-edit-courses] { display: none; }
  </style>
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
            <a href="users.php"><i data-lucide="users"></i> Users</a>
            <a href="database.php"><i data-lucide="database"></i> Database</a>
          
          </div>
        </details>
      </nav>
      <div class="sidebar-bottom"><a href="settings.php"><i data-lucide="settings-2"></i> Settings</a></div>
    </aside>
    <main class="admin-main colleges-main">
      <header class="admin-header"><button class="mobile-menu" id="mobileMenu" aria-label="Open admin navigation" aria-expanded="false"><i data-lucide="menu"></i></button><div><span class="eyebrow">Administrator workspace</span><h1><span data-greeting data-user-name="<?= $displayName ?>">Good morning</span></h1></div><div class="header-actions"><button class="header-icon" aria-label="Notifications"><i data-lucide="bell"></i><span></span></button><div class="user-chip"><span class="avatar"><?= $initials ?></span><span><strong><?= $displayName ?></strong><small><?= htmlspecialchars(dats_user_role_label($currentUser), ENT_QUOTES, 'UTF-8') ?></small></span><i data-lucide="chevron-down"></i></div></div></header>
      <section class="welcome-row colleges-welcome"><div><p class="page-kicker">Academic structure · <span data-current-datetime>Loading date and time...</span></p><h2>Colleges &amp; Courses</h2><p class="page-intro">Manage every college and the courses available under each one.</p></div><button class="gold-button" id="addCollegeButton"><i data-lucide="plus"></i> Add college</button></section>
      <section class="structure-summary"><div><strong data-stat="total_colleges">0</strong><span>Colleges</span></div><div><strong data-stat="total_courses">0</strong><span>Total courses</span></div><div><strong data-stat="total_students">0</strong><span>Students enrolled</span></div></section>
      <section class="structure-toolbar panel"><div class="search-box"><i data-lucide="search"></i><input id="structureSearch" type="search" placeholder="Search colleges or courses" /></div></section>
      <section class="college-list" id="collegeList">
        <article class="college-card" data-name="College of Computing"><div class="college-heading"><div class="college-title"><span class="college-icon maroon-college"><i data-lucide="monitor"></i></span><div><span class="panel-kicker">College 01</span><h3>College of Computing</h3><small>12 courses · 3,240 students</small></div></div><div class="college-actions"><button class="mini-action" data-edit-college aria-label="College settings"><i data-lucide="settings-2"></i></button><button class="mini-action" aria-label="Add course to College of Computing" data-add-course="College of Computing"><i data-lucide="plus"></i></button><button class="mini-action delete-college" aria-label="Delete College of Computing" data-delete-college><i data-lucide="trash-2"></i></button></div></div><div class="course-list"><div><span>BS Information Technology</span><b>1,840 students</b><button class="course-delete" aria-label="Delete BS Information Technology" data-delete-course><i data-lucide="x"></i></button></div><div><span>BS Computer Science</span><b>1,120 students</b><button class="course-delete" aria-label="Delete BS Computer Science" data-delete-course><i data-lucide="x"></i></button></div><div><span>Associate in Computer Technology</span><b>280 students</b><button class="course-delete" aria-label="Delete Associate in Computer Technology" data-delete-course><i data-lucide="x"></i></button></div></div></article>
        <article class="college-card" data-name="College of Education"><div class="college-heading"><div class="college-title"><span class="college-icon gold-college"><i data-lucide="graduation-cap"></i></span><div><span class="panel-kicker">College 02</span><h3>College of Education</h3><small>10 courses · 4,185 students</small></div></div><div class="college-actions"><button class="mini-action" data-edit-college aria-label="Edit College of Education name"><i data-lucide="pencil"></i></button><button class="mini-action" data-edit-courses aria-label="Edit College of Education courses"><i data-lucide="list-edit"></i></button><button class="mini-action" aria-label="Add course to College of Education" data-add-course="College of Education"><i data-lucide="plus"></i></button><button class="mini-action delete-college" aria-label="Delete College of Education" data-delete-college><i data-lucide="trash-2"></i></button></div></div><div class="course-list"><div><span class="course-name">Bachelor of Elementary Education</span><b>2,260 students</b><button class="course-delete" aria-label="Delete Bachelor of Elementary Education" data-delete-course><i data-lucide="x"></i></button></div><div><span class="course-name">Bachelor of Secondary Education<ul class="major-list"><li>Major in English</li><li>Major in Mathematics</li></ul></span><b>1,925 students</b><button class="course-delete" aria-label="Delete Bachelor of Secondary Education" data-delete-course><i data-lucide="x"></i></button></div></div></article>
        <article class="college-card" data-name="College of Business and Accountancy"><div class="college-heading"><div class="college-title"><span class="college-icon rose-college"><i data-lucide="briefcase-business"></i></span><div><span class="panel-kicker">College 03</span><h3>College of Business &amp; Accountancy</h3><small>9 courses · 2,870 students</small></div></div><div class="college-actions"><button class="mini-action" data-edit-college aria-label="Edit College of Business and Accountancy name"><i data-lucide="pencil"></i></button><button class="mini-action" data-edit-courses aria-label="Edit College of Business and Accountancy courses"><i data-lucide="list-edit"></i></button><button class="mini-action" aria-label="Add course to College of Business and Accountancy" data-add-course="College of Business and Accountancy"><i data-lucide="plus"></i></button><button class="mini-action delete-college" aria-label="Delete College of Business and Accountancy" data-delete-college><i data-lucide="trash-2"></i></button></div></div><div class="course-list"><div><span>BS Accountancy</span><b>1,120 students</b><button class="course-delete" aria-label="Delete BS Accountancy" data-delete-course><i data-lucide="x"></i></button></div><div><span>BS Business Administration</span><b>1,750 students</b><button class="course-delete" aria-label="Delete BS Business Administration" data-delete-course><i data-lucide="x"></i></button></div></div></article>
      </section>
      <p class="empty-structure" id="emptyStructure" hidden>No colleges or courses match your search.</p>
      <footer><span>Digitalization Archiving Management System</span><span>Colleges &amp; Courses <b>·</b> ZPPSU Registrar</span></footer>
    </main>
  </div>
  <div class="admin-toast" id="adminToast" role="status">Academic structure updated.</div>
  <div class="structure-modal-backdrop" id="structureModal" hidden><section class="structure-modal" role="dialog" aria-modal="true" aria-labelledby="structureModalTitle"><button class="row-action modal-close" id="closeStructureModal" aria-label="Close dialog"><i data-lucide="x"></i></button><span class="panel-kicker">Academic structure</span><h2 id="structureModalTitle">Add college</h2><p id="structureModalCopy">Create a new college for the university archive.</p><label id="structureFieldLabel">Name<textarea id="structureName" rows="4" placeholder="Enter name"></textarea></label><label id="collegeLogoField">College logo<input id="collegeLogo" type="file" accept="image/jpeg,image/png,image/gif,image/webp" /></label><button class="login-submit" id="saveStructureButton" type="button">Add <i data-lucide="arrow-right"></i></button></section></div>
  <script src="../data-sync.js?v=20260914"></script>
  <script src="script.js?v=20260913"></script>
</body>
</html>
