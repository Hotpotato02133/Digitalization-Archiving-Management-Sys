<?php require_once __DIR__ . '/../auth.php'; dats_require_role('Staff'); $currentUser = dats_current_user(); $displayName = htmlspecialchars($currentUser['full_name'], ENT_QUOTES, 'UTF-8'); $initials = dats_user_initials($currentUser); ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="Manage student information and scanned documents for the ZPPSU archive." />
  <title>Student Information | ZPPSU DATS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&amp;family=Manrope:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../admin/styles.css" />
  <link rel="stylesheet" href="student-table.css" />
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
        <a href="scan.php"><i data-lucide="scan-line"></i> File Scanning</a>
        <a href="categories.php"><i data-lucide="folders"></i> Document Categories</a>
        <a class="active" href="students.php" aria-current="page"><i data-lucide="contact-round"></i> Student Information</a>
        <a href="history.php"><i data-lucide="history"></i> My recent upload files</a>
        <a href="analytics.php"><i data-lucide="chart-no-axes-combined"></i> Analytics</a>
      </nav>
      <div class="sidebar-bottom"><a href="index.php#profile"><i data-lucide="badge-check"></i> Staff profile</a><a class="back-link" href="../logout.php"><i data-lucide="log-out"></i> Return to site</a></div>
    </aside>
    <main class="admin-main students-main">
      <header class="admin-header"><button class="mobile-menu" id="mobileMenu" aria-label="Open staff navigation" aria-expanded="false"><i data-lucide="menu"></i></button><div><span class="eyebrow">Staff workspace</span><h1>Good morning, <?= $displayName ?>.</h1></div><div class="header-actions"><button class="header-icon" aria-label="Notifications"><i data-lucide="bell"></i><span></span></button><div class="user-chip"><span class="avatar"><?= $initials ?></span><span><strong><?= $displayName ?></strong><small>Archive Staff</small></span><i data-lucide="chevron-down"></i></div></div></header>
      <section class="welcome-row students-welcome"><div><p class="page-kicker">Student records · <span data-current-datetime>Loading date and time...</span></p><h2>Student information</h2><p class="page-intro">Manage student profiles and the documents scanned under each student.</p></div><button class="gold-button" id="addStudentButton" type="button"><i data-lucide="user-round-plus"></i> Add student</button></section>
      <section class="student-summary"><div><strong data-stat="total_students">0</strong><span>Total students</span></div><div><strong data-stat="total_colleges">0</strong><span>Colleges</span></div><div><strong data-stat="total_documents">0</strong><span>Documents scanned</span></div></section>
      <section class="student-toolbar panel"><div class="search-box"><i data-lucide="search"></i><input id="studentSearch" type="search" placeholder="Search student number or name" /></div><select id="studentGender" aria-label="Filter by gender"><option value="all">All genders</option><option>Female</option><option>Male</option></select><select id="studentCollege" aria-label="Filter by college"><option value="all">All colleges</option><option>College of Computing</option><option>College of Education</option><option>College of Business &amp; Accountancy</option></select></section>
      <section class="student-table-panel panel"><div class="table-heading"><div><span class="panel-kicker">Student directory</span><h3>Registered students</h3></div><span class="table-note">Documents are listed below each student's name</span></div><div class="students-table-wrap"><table class="students-table"><thead><tr><th>Student</th><th>College</th><th>Course</th><th>Academic Year</th><th>Year Level</th><th>Gender</th><th>Birthdate / Age</th><th>Address</th><th>Civil status</th><th>Contact number</th><th>Documents</th><th>Record status</th><th>Created</th><th><span class="sr-only">Actions</span></th></tr></thead><tbody id="studentRows"><tr data-gender="Female" data-college="College of Computing"><td><div class="student-identity"><span class="student-avatar">JD</span><span><strong>2026-001234 · Juanita Dela Cruz</strong><small>Middle initial: R. · College of Computing</small><em><i data-lucide="file-check-2"></i> Document scanned: Official Transcript</em></span></div></td><td>Female</td><td>14 Jan 2004<br /><small>Age 22</small></td><td>San Roque, Zamboanga City</td><td>Single</td><td>0917 555 0134</td><td><button class="row-action" type="button" aria-label="More options for Juanita Dela Cruz"><i data-lucide="more-horizontal"></i></button></td></tr><tr data-gender="Male" data-college="College of Education"><td><div class="student-identity"><span class="student-avatar gold-student">MR</span><span><strong>2026-001198 · Miguel Ramos</strong><small>Middle initial: T. · College of Education</small><em><i data-lucide="file-check-2"></i> Document scanned: Certificate of Enrollment</em></span></div></td><td>Male</td><td>22 Aug 2003<br /><small>Age 23</small></td><td>Tetuan, Zamboanga City</td><td>Single</td><td>0920 424 7882</td><td><button class="row-action" type="button" aria-label="More options for Miguel Ramos"><i data-lucide="more-horizontal"></i></button></td></tr><tr data-gender="Female" data-college="College of Business &amp; Accountancy"><td><div class="student-identity"><span class="student-avatar rose-student">AS</span><span><strong>2026-000987 · Angela Santos</strong><small>Middle initial: L. · College of Business &amp; Accountancy</small><em><i data-lucide="file-check-2"></i> Document scanned: Certificate of Graduation</em></span></div></td><td>Female</td><td>03 May 2005<br /><small>Age 21</small></td><td>Guiwan, Zamboanga City</td><td>Single</td><td>0918 302 6641</td><td><button class="row-action" type="button" aria-label="More options for Angela Santos"><i data-lucide="more-horizontal"></i></button></td></tr></tbody></table></div><p class="empty-students" id="emptyStudents" hidden>No students match your search or filters.</p></section>
      <footer><span>Digitalization Archiving Management System</span><span>Student Information <b>·</b> ZPPSU Registrar</span></footer>
    </main>
  </div>
  <div class="admin-toast" id="adminToast" role="status">Student information updated.</div>
  <div class="student-modal-backdrop" id="studentModal" hidden><section class="student-modal" role="dialog" aria-modal="true" aria-labelledby="studentModalTitle"><button class="row-action modal-close" id="closeStudentModal" type="button" aria-label="Close add student dialog"><i data-lucide="x"></i></button><span class="panel-kicker">Student directory</span><h2 id="studentModalTitle">Add student</h2><p>Register a student profile before attaching scanned documents.</p><div class="student-form-grid"><label>Student number<input placeholder="2026-001234" /></label><label>Last name<input placeholder="Last name" /></label><label>First name<input placeholder="First name" /></label><label>Middle initial<input placeholder="M.I." /></label><label>Gender<select><option>Female</option><option>Male</option></select></label><label>Birthdate<input type="date" /></label><label>Address<input placeholder="Complete address" /></label><label>Civil status<select><option>Single</option><option>Married</option><option>Widowed</option></select></label><label>Contact number<input placeholder="09XX XXX XXXX" /></label></div><button class="login-submit" id="saveStudentButton" type="button">Save student <i data-lucide="arrow-right"></i></button></section></div>
  <script src="../data-sync.js?v=20260905"></script>
  <script src="../admin/script.js?v=20260906"></script>
</body>
</html>




