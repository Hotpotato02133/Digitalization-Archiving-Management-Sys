<?php require_once __DIR__ . '/../auth.php'; dats_require_page_access(basename(__FILE__)); $currentUser = dats_current_user(); $displayName = htmlspecialchars($currentUser['full_name'], ENT_QUOTES, 'UTF-8'); $initials = dats_user_initials($currentUser); $pdo = dats_db(); $studentTotal = $pdo ? (int) $pdo->query("SELECT COUNT(*) FROM students WHERE status <> 'Inactive'")->fetchColumn() : 0; $collegeTotal = $pdo ? (int) $pdo->query("SELECT COUNT(*) FROM colleges WHERE status = 'Active'")->fetchColumn() : 0; $documentTotal = $pdo ? (int) $pdo->query("SELECT COUNT(*) FROM documents WHERE status = 'Archived' AND deleted_at IS NULL")->fetchColumn() : 0; ?>
<?php require_once __DIR__ . '/../lib/scan-intake.php'; ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="Manage student information and scanned documents for the ZPPSU archive." />
  <title>Students Information | ZPPSU DATS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&amp;family=Manrope:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="styles.css?v=20260914" />
  <link rel="stylesheet" href="student-table.css?v=<?= filemtime(__DIR__ . '/student-table.css') ?>" />
  <link rel="stylesheet" href="../dashboard-tables.css?v=20260914" />
  <link rel="stylesheet" href="../dashboard-buttons.css?v=20260914" />
  <link rel="stylesheet" href="student-gradesheet-modal.css?v=20260926" />
  <link rel="stylesheet" href="student-import.css?v=<?= filemtime(__DIR__ . '/student-import.css') ?>" />
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
        <a class="active" href="students.php" aria-current="page"><i data-lucide="contact-round"></i> Students Information</a>
        <details class="setup-menu">
          <summary><i data-lucide="sliders-horizontal"></i> Setup <i class="setup-chevron" data-lucide="chevron-down"></i></summary>
          <div class="setup-submenu">
            <a href="categories.php"><i data-lucide="folder-plus"></i> Document Categories</a>
            <?php if (($currentUser['role'] ?? '') === 'Admin'): ?><a href="users.php"><i data-lucide="users"></i> Users</a><a href="database.php"><i data-lucide="database"></i> Database</a><?php endif; ?>
            
          </div>
        </details>
      </nav>
      <?php if (dats_can_access_admin_page($currentUser, 'settings.php')): ?><div class="sidebar-bottom"><a href="settings.php"><i data-lucide="settings-2"></i> Settings</a></div><?php endif; ?>
    </aside>
    <main class="admin-main students-main">
      <header class="admin-header"><button class="mobile-menu" id="mobileMenu" aria-label="Open admin navigation" aria-expanded="false"><i data-lucide="menu"></i></button><div><span class="eyebrow">Administrator workspace</span><h1><span data-greeting data-user-name="<?= $displayName ?>">Good morning</span></h1></div><div class="header-actions"><button class="header-icon" aria-label="Notifications"><i data-lucide="bell"></i><span></span></button><div class="user-chip"><span class="avatar"><?= $initials ?></span><span><strong><?= $displayName ?></strong><small><?= htmlspecialchars(dats_user_role_label($currentUser), ENT_QUOTES, 'UTF-8') ?></small></span><i data-lucide="chevron-down"></i></div></div></header>
      <section class="welcome-row students-welcome"><div><p class="page-kicker">Student records · <span data-current-datetime>Loading date and time...</span></p><h2>Students information</h2><p class="page-intro">Manage student profiles and the documents scanned under each student.</p></div><div class="students-actions"><button class="ghost-button" id="importStudentsButton" type="button"><i data-lucide="file-up"></i> Import students</button><button class="gold-button" id="addStudentButton"><i data-lucide="user-round-plus"></i> Add student</button></div></section>
      <section class="student-summary"><div><strong data-stat="total_students"><?= number_format($studentTotal) ?></strong><span>Total students</span></div><div><strong data-stat="total_colleges"><?= number_format($collegeTotal) ?></strong><span>Colleges</span></div><div><strong data-stat="total_documents"><?= number_format($documentTotal) ?></strong><span>Documents scanned</span></div></section>
      <section class="student-toolbar panel"><div class="student-search-field"><div class="search-box"><i data-lucide="search"></i><input id="studentSearch" type="search" placeholder="Search student number or name" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="studentSearchSuggestions" /></div><div id="studentSearchSuggestions" class="student-search-suggestions" role="listbox" aria-label="Student name suggestions" hidden></div></div><select id="studentGender" aria-label="Filter by gender"><option value="all">All genders</option><option>Female</option><option>Male</option></select><select id="studentCollege" aria-label="Filter by college"><option value="all">All colleges</option><option>College of Computing</option><option>College of Education</option><option>College of Business &amp; Accountancy</option></select></section>
      <section class="student-table-panel panel"><div class="table-heading"><div><span class="panel-kicker">Student directory</span><h3>Registered students</h3></div><span class="table-note">Documents are listed below each student's name</span></div><div class="students-table-wrap"><table class="students-table"><thead><tr><th>Student</th><th>College</th><th>Course</th><th>Academic Year</th><th>Year Level</th><th>Gender</th><th>Birthdate / Age</th><th>Address</th><th>Civil status</th><th>Contact number</th><th>Documents</th><th>Record status</th><th>Created</th><th><span class="sr-only">Actions</span></th></tr></thead><tbody id="studentRows"><tr><td colspan="14" class="table-loading">Loading student records...</td></tr></tbody></table></div></section>
      <p class="empty-students" id="emptyStudents" hidden>No students match your search or filters.</p>
      <footer><span>Digitalization Archiving Management System</span><span>Students Information <b>·</b> ZPPSU Registrar</span></footer>
    </main>
  </div>
  <div class="admin-toast" id="adminToast" role="status">Student information updated.</div>
  <div class="student-modal-backdrop" id="studentModal" hidden><section class="student-modal" role="dialog" aria-modal="true" aria-labelledby="studentModalTitle"><button class="row-action modal-close" id="closeStudentModal" aria-label="Close add student dialog"><i data-lucide="x"></i></button><span class="panel-kicker">Student directory</span><h2 id="studentModalTitle">Add student</h2><p>Register a student profile before attaching scanned documents.</p><div class="student-form-grid"><label>Student number<input id="studentNumber" maxlength="30" placeholder="2026-001234" /></label><label>Last name<input id="studentLastName" maxlength="80" placeholder="Last name" /></label><label>First name<input id="studentFirstName" maxlength="80" placeholder="First name" /></label><label>Middle initial<input id="studentMiddleInitial" maxlength="10" placeholder="M.I." /></label><label>Gender<select id="studentGenderField"><option>Undisclosed</option><option>Female</option><option>Male</option><option>Other</option></select></label><label>Birthdate<input id="studentBirthdate" type="date" /></label><label>Address<input id="studentAddress" maxlength="255" placeholder="Complete address" /></label><label>Nationality<input id="studentNationality" maxlength="80" autocomplete="country-name" placeholder="e.g. Filipino" /></label><label>Civil status<select id="studentCivilStatus"><option>Undisclosed</option><option>Single</option><option>Married</option><option>Widowed</option><option>Separated</option></select></label><label>Contact number<input id="studentContactNumber" maxlength="30" placeholder="09XX XXX XXXX" /></label></div><button class="login-submit" id="saveStudentButton" type="button">Save student <i data-lucide="arrow-right"></i></button></section></div>
  <div class="student-modal-backdrop" id="studentImportModal" hidden><section class="student-modal si-modal" role="dialog" aria-modal="true" aria-labelledby="siTitle"><button class="row-action modal-close" id="siClose" type="button" aria-label="Close import dialog"><i data-lucide="x"></i></button><span class="panel-kicker">Student directory</span><h2 id="siTitle">Import students</h2><p>Upload a CSV list of students (in Excel: File &rarr; Save As &rarr; CSV). Each row is checked first; nothing is added until you confirm. Students whose number already exists are skipped, never overwritten.</p><div class="si-controls"><label>CSV file<input type="file" id="siFile" accept=".csv,.txt,text/csv" /></label><label>Academic year if a row has none<input type="number" id="siYear" min="1901" max="2155" /></label><button class="si-link" id="siTemplate" type="button">Download template</button></div><p class="si-status" id="siStatus" role="status"></p><div id="siResults"></div><div class="si-actions"><button class="ghost-button" id="siCancel" type="button">Close</button><button class="login-submit" id="siConfirm" type="button" disabled>Import students</button></div></section></div>
  <script type="application/json" id="studentYearLevelOptions"><?= json_encode(dats_intake_year_levels(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
  <script src="../data-sync.js?v=<?= filemtime(__DIR__ . '/../data-sync.js') ?>"></script>
  <script src="scan-flow.js?v=20260922"></script>
  <script src="gradesheet-flow.js?v=20260926"></script>
  <script src="student-gradesheet-modal.js?v=20260926"></script>
  <script src="script.js?v=20260926"></script>
  <script src="student-import.js?v=<?= filemtime(__DIR__ . '/student-import.js') ?>"></script>
  <script src="../dashboard-tables.js?v=20260914"></script>
</body>
</html>
