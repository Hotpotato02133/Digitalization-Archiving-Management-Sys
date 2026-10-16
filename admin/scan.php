<?php require_once __DIR__ . '/../auth.php'; require_once __DIR__ . '/../lib/scan-intake.php'; dats_require_page_access(basename(__FILE__)); $currentUser = dats_current_user(); $displayName = htmlspecialchars($currentUser['full_name'], ENT_QUOTES, 'UTF-8'); $initials = dats_user_initials($currentUser); ?>
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
  <link rel="stylesheet" href="styles.css?v=20260920b" />
  <style>
    .archive-button {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 7px;
      width: fit-content;
      margin-top: 18px;
      padding: 10px 13px;
      color: var(--maroon-dark);
      background: var(--gold);
      border: 0;
      border-radius: 2px;
      font: 700 10px var(--sans);
      box-shadow: none;
      margin-left: auto;
    }
    .archive-button svg { width: 15px; height: 15px; }
    .ocr-status-banner { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; width: 88.8%; max-width: 1180px; margin: 0 auto 13px; padding: 10px 14px; border-radius: 6px; background: var(--gold-pale); border: 1px solid rgba(214,173,58,.5); font-size: 11px; color: var(--maroon-dark); }
    .ocr-status-banner[hidden] { display: none; }
    .ocr-status-banner.is-error { background: #f8e5e2; border-color: rgba(180,60,50,.4); color: #7a2a20; }
    .ocr-status-banner strong { font-weight: 800; }
    .preview-thumb-item[role="button"] { cursor: pointer; outline: 0; }
    .preview-thumb-item[role="button"]:focus-visible .preview-thumb-box { outline: 2px solid var(--gold); outline-offset: 2px; }
    .preview-thumb-item.is-active .preview-thumb-box { border-color: var(--maroon); box-shadow: 0 0 0 2px rgba(107,29,42,.18); }
    .preview-thumb-item.is-active small { color: var(--maroon); font-weight: 700; }
    .preview-cover.is-zoomed { cursor: grab; user-select: none; }
    .preview-cover.is-zoomed img { cursor: grab; }
    .preview-cover.is-panning, .preview-cover.is-panning img { cursor: grabbing; }
    .student-mode-group { display: flex; gap: 10px; margin: 0 0 18px; padding: 0; border: 0; }
    .student-mode-option { display: flex; align-items: center; gap: 8px; padding: 5px 0; border: 0; outline: 0; background: transparent; cursor: pointer; font-size: 10px; font-weight: 700; }
    .student-mode-option:has(input:checked) { color: var(--maroon); }
    .student-mode-option input { accent-color: var(--maroon); }
    .scan-field { position: relative; display: grid; gap: 7px; color: var(--ink); font-size: 10px; font-weight: 700; }
    .scan-field[hidden], .details-grid label[hidden] { display: none !important; }
    .scan-combobox-wrap { position: relative; }
    .student-suggestions { position: absolute; top: calc(100% + 4px); left: 0; right: 0; z-index: 6; max-height: 260px; overflow-y: auto; padding: 5px; background: var(--white); border: 1px solid var(--line); box-shadow: 0 14px 30px rgba(42,23,26,.16); }
    .student-suggestions[hidden] { display: none; }
    .student-suggestion { display: grid; width: 100%; gap: 3px; padding: 10px; text-align: left; color: var(--ink); background: transparent; border: 0; cursor: pointer; }
    .student-suggestion:hover, .student-suggestion.is-active { background: var(--gold-pale); }
    .student-suggestion strong { font-size: 10px; }
    .student-suggestion small, .field-help { color: var(--muted); font: 8px var(--mono); font-weight: 400; }
    .field-help.is-detected { color: var(--maroon); font-weight: 600; }
    .details-grid input[readonly] { background: #f4efed; color: var(--muted); cursor: not-allowed; }
    .new-student-fields { margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--line); }
    .new-student-fields[hidden] { display: none; }
    .registration-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 13px; }
    .registration-grid label { display: grid; gap: 7px; color: var(--ink); font-size: 10px; font-weight: 700; }
    .registration-grid input, .registration-grid select { width: 100%; padding: 12px; color: var(--ink); background: var(--white); border: 1px solid var(--line); outline: 0; font: 10px var(--sans); }
    .registration-grid input:focus, .registration-grid select:focus { border-color: var(--maroon); }
    .span-two { grid-column: span 2; }
    @media (max-width: 850px) { .registration-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 640px) { .student-mode-group { flex-direction: column; }.student-mode-option { width: 100%; }.registration-grid { grid-template-columns: 1fr; }.span-two { grid-column: auto; } }
  </style>
  <link rel="stylesheet" href="../dashboard-buttons.css?v=20260914" />
  <style>
    .scan-details > .archive-button {
      display: flex;
      width: fit-content;
      margin-left: auto;
    }
  </style>
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
  <div class="admin-shell">
    <aside class="sidebar" id="sidebar">
      <a class="brand" href="../index.php"><img src="../img/ZPPSU.png" alt="ZPPSU seal" /><span><strong>ZPPSU REGISTRAR</strong><small>Digital Archive</small></span></a>
      <div class="workspace-label">Workspace</div>
      <nav aria-label="Admin navigation">
        <a href="index.php"><i data-lucide="layout-dashboard"></i> Dashboard</a>
        <a class="active" href="scan.php" aria-current="page"><i data-lucide="scan-line"></i> File Scanning</a>
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
      <?php if (dats_can_access_admin_page($currentUser, 'settings.php')): ?><div class="sidebar-bottom"><a href="settings.php"><i data-lucide="settings-2"></i> Settings</a></div><?php endif; ?>
    </aside>
    <main class="admin-main scan-main">
      <header class="admin-header"><button class="mobile-menu" id="mobileMenu" aria-label="Open admin navigation" aria-expanded="false"><i data-lucide="menu"></i></button><div><span class="eyebrow">Administrator workspace</span><h1><span data-greeting data-user-name="<?= $displayName ?>">Good morning</span></h1></div><div class="header-actions"><button class="header-icon" aria-label="Notifications"><i data-lucide="bell"></i><span></span></button><div class="user-chip"><span class="avatar"><?= $initials ?></span><span><strong><?= $displayName ?></strong><small><?= htmlspecialchars(dats_user_role_label($currentUser), ENT_QUOTES, 'UTF-8') ?></small></span><i data-lucide="chevron-down"></i></div></div></header>
      <section class="welcome-row scan-welcome"><div><p class="page-kicker">Archive intake · <span data-current-datetime>Loading date and time...</span></p><h2>File Scanning</h2><p class="page-intro">Upload an existing file to add it to the archive.</p></div><span class="scanner-status"><i></i> Scanner ready</span></section>
      <section class="scan-layout single">
        <article class="panel scan-card"><div class="panel-heading"><div><span class="panel-kicker">Manual upload</span><h3>Upload document</h3></div></div><div class="document-preview" id="documentPreview" hidden aria-label="Selected document preview"><div class="preview-cover" id="previewCover"></div><div class="preview-thumbs" id="previewThumbs"></div></div><label class="upload-zone" id="uploadZone" for="fileInput"><input id="fileInput" type="file" accept="image/*,.pdf" multiple /><span class="upload-icon"><i data-lucide="upload-cloud"></i></span><strong>Drop files here or browse</strong><small>PDF, JPG, or PNG · up to 25 MB each</small></label><div class="upload-result" id="uploadResult" aria-live="polite"><i data-lucide="file-plus-2"></i><span>No files selected yet</span></div></article>
      </section>
      <div class="ocr-status-banner" id="scanOcrBanner" hidden aria-live="polite"></div>
      <section class="scan-details panel">
        <div class="panel-heading"><div><span class="panel-kicker">Record details</span><h3>Prepare for archive</h3></div><span class="required-note">* Required fields</span></div>
        <fieldset class="student-mode-group" aria-label="Student record type">
          <label class="student-mode-option"><input id="scanStudentExisting" type="radio" name="scanStudentMode" value="existing" checked /> Existing student</label>
          <label class="student-mode-option"><input id="scanStudentNew" type="radio" name="scanStudentMode" value="new" /> New student registration</label>
        </fieldset>
        <div class="details-grid">
          <div class="scan-field" id="scanExistingStudentField">
            <label for="scanStudentSearch">Student *</label>
            <div class="scan-combobox-wrap">
              <input id="scanStudentSearch" type="text" role="combobox" aria-autocomplete="list" aria-controls="scanStudentSuggestions" aria-expanded="false" placeholder="Search name or student number" autocomplete="off" />
              <input id="scanStudentId" type="hidden" />
              <div class="student-suggestions" id="scanStudentSuggestions" role="listbox" hidden></div>
            </div>
            <small class="field-help">Choose a registered student from the suggestions.</small>
          </div>
          <label>Document type *<select id="scanCategory"><option value="">Select document type</option></select><small class="field-help" id="scanCategorySource">Select a type or upload a document for automatic detection.</small></label>
          <label>Academic year *<input id="scanAcademicYear" type="text" inputmode="numeric" placeholder="Select a student" maxlength="4" readonly /><small class="field-help" id="scanAcademicYearHelp">Filled from the selected student record.</small></label>
        </div>
        <div class="new-student-fields" id="scanNewStudentFields" hidden>
          <div class="registration-grid">
            <label>Student number *<input id="scanNewStudentNumber" maxlength="30" autocomplete="off" placeholder="e.g. 2026-001234" /></label>
            <label>First name *<input id="scanNewFirstName" maxlength="80" autocomplete="given-name" /></label>
            <label>Middle initial<input id="scanNewMiddleInitial" maxlength="10" /></label>
            <label>Last name *<input id="scanNewLastName" maxlength="80" autocomplete="family-name" /></label>
            <label>Nationality<input id="scanNewNationality" maxlength="80" autocomplete="country-name" /></label>
            <label>Gender<select id="scanNewGender"><option value="">Select gender</option><option>Undisclosed</option><option>Female</option><option>Male</option><option>Other</option></select></label>
            <label>Birthdate<input id="scanNewBirthdate" type="date" /></label>
            <label>Academic year *<input id="scanNewAcademicYear" type="text" inputmode="numeric" maxlength="4" placeholder="e.g. 2026" /></label>
            <label class="span-two">Address<input id="scanNewAddress" maxlength="255" autocomplete="street-address" /></label>
            <label>Civil status<select id="scanNewCivilStatus"><option>Undisclosed</option><option>Single</option><option>Married</option><option>Widowed</option><option>Separated</option></select></label>
            <label>Contact number<input id="scanNewContactNumber" maxlength="30" autocomplete="tel" /></label>
            <label>College<input id="scanNewCollege" list="scanNewCollegeOptions" autocomplete="off" placeholder="Type or select a college" /><datalist id="scanNewCollegeOptions"></datalist></label>
            <label>Course<input id="scanNewCourse" type="text" list="scanNewCourseOptions" autocomplete="off" placeholder="Type or select a course" /><datalist id="scanNewCourseOptions"></datalist></label>
            <label>Major<input id="scanNewMajor" type="text" list="scanNewMajorOptions" autocomplete="off" placeholder="Type or select a major" /><datalist id="scanNewMajorOptions"></datalist></label>
            <label>Year level<select id="scanNewYearLevel"><option value="">Select year level</option><?php foreach (dats_intake_year_levels() as $yearLevel): ?><option><?= htmlspecialchars($yearLevel, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label>
          </div>
        </div>
        <button class="login-submit archive-button" id="archiveButton" type="button"><i data-lucide="archive-restore"></i> Add to archive</button>
      </section>
      <section class="recent-scans"><div class="panel-heading"><div><span class="panel-kicker">Today's intake</span><h3>Recently archived</h3></div><a href="files.php">View archive <i data-lucide="arrow-up-right"></i></a></div><div class="recent-list"><div><span class="recent-file"><i data-lucide="file-check-2"></i></span><p><strong>Certificate of Graduation</strong><small>ZP-2026-004821 · just now</small></p><span class="pill success">Archived</span></div><div><span class="recent-file gold-file"><i data-lucide="file-image"></i></span><p><strong>Official Transcript</strong><small>ZP-2026-004820 · 18 minutes ago</small></p><span class="pill success">Archived</span></div></div></section>
      <footer><span>Digitalization Archiving Management System</span><span>File Scanning <b>·</b> ZPPSU Registrar</span></footer>
    </main>
  </div>
  <div class="admin-toast" id="adminToast" role="status">Your scan has been added to the queue.</div>
  <script src="../data-sync.js?v=<?= filemtime(__DIR__ . '/../data-sync.js') ?>"></script>
  <script src="scan-flow.js?v=<?= filemtime(__DIR__ . '/scan-flow.js') ?>"></script>
  <script src="script.js?v=<?= filemtime(__DIR__ . '/script.js') ?>"></script>
</body>
</html>
