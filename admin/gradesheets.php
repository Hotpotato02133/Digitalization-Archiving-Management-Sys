<?php require_once __DIR__ . '/../auth.php'; dats_require_page_access(basename(__FILE__)); $currentUser = dats_current_user(); $displayName = htmlspecialchars($currentUser['full_name'], ENT_QUOTES, 'UTF-8'); $initials = dats_user_initials($currentUser); ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="Upload gradesheets and view digitized student grades." />
  <title>Gradesheets | ZPPSU DATS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="styles.css?v=20260920b" />
  <link rel="stylesheet" href="gradesheets.css?v=<?= filemtime(__DIR__ . '/gradesheets.css') ?>" />
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body data-digit-model="assets/grade-digits.json?v=<?= filemtime(__DIR__ . '/assets/grade-digits.json') ?>">
  <div class="admin-shell">
    <aside class="sidebar" id="sidebar">
      <a class="brand" href="../index.php"><img src="../img/ZPPSU.png" alt="ZPPSU seal" /><span><strong>ZPPSU REGISTRAR</strong><small>Digital Archive</small></span></a>
      <div class="workspace-label">Workspace</div>
      <nav aria-label="Admin navigation">
        <a href="index.php"><i data-lucide="layout-dashboard"></i> Dashboard</a>
        <a href="scan.php"><i data-lucide="scan-line"></i> File Scanning</a>
        <a class="active" href="gradesheets.php" aria-current="page"><i data-lucide="graduation-cap"></i> Gradesheets</a>
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
    <main class="admin-main gradesheets-main">
      <header class="admin-header"><button class="mobile-menu" id="mobileMenu" aria-label="Open admin navigation" aria-expanded="false"><i data-lucide="menu"></i></button><div><span class="eyebrow">Administrator workspace</span><h1><span data-greeting data-user-name="<?= $displayName ?>">Good morning</span></h1></div><div class="header-actions"><button class="header-icon" aria-label="Notifications"><i data-lucide="bell"></i><span></span></button><div class="user-chip"><span class="avatar"><?= $initials ?></span><span><strong><?= $displayName ?></strong><small><?= htmlspecialchars(dats_user_role_label($currentUser), ENT_QUOTES, 'UTF-8') ?></small></span><i data-lucide="chevron-down"></i></div></div></header>
      <section class="welcome-row"><div><p class="page-kicker">Grade records · <span data-current-datetime>Loading date and time...</span></p><h2>Gradesheets</h2><p class="page-intro">Upload gradesheets or Reports of Rating — every student on them is read automatically. Review the records in Upload history and select Save before they appear in student search.</p></div>
        <button class="login-submit gs-primary gs-upload-trigger" id="gsOpenUpload" type="button" aria-haspopup="dialog" aria-controls="gsUploadModal">
          <span class="gs-trigger-idle"><i data-lucide="upload-cloud"></i> Upload gradesheets</span>
          <span class="gs-trigger-busy"><span class="gs-spinner gs-spinner-light" aria-hidden="true"></span> Reading <b id="gsTriggerPercent">0%</b></span>
        </button>
      </section>

      <section class="panel gradesheet-search-panel">
        <div class="panel-heading"><div><span class="panel-kicker">Student lookup</span><h3>Search student grades</h3></div></div>
        <div class="gs-search-toolbar">
          <div class="search-box">
            <i data-lucide="search"></i>
            <input id="gsStudentSearch" type="search" placeholder="Search by student name or student ID" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="gsStudentSuggestions" />
            <div id="gsStudentSuggestions" class="search-suggestions" role="listbox" aria-label="Student suggestions" hidden></div>
          </div>
        </div>
        <div id="gsStudentGradesResult" class="gs-result" aria-live="polite">
          <div class="gs-empty"><i data-lucide="graduation-cap"></i><strong>Search for a student</strong><span>Type a name or student ID to see their grades per subject, grouped by semester and school year.</span></div>
        </div>
      </section>

      <section class="panel gradesheet-history-panel">
        <div class="panel-heading"><div><span class="panel-kicker">Upload history</span><h3>Uploaded gradesheets</h3></div><div class="gs-history-actions"><button class="ghost-button" id="gsAddStudentOpen" type="button"><i data-lucide="user-round-plus"></i> Add Student</button><button class="ghost-button" type="button" data-open-upload><i data-lucide="plus"></i> Upload more</button></div></div>
        <div class="gs-table-wrap">
          <table class="gs-table gs-batch-table">
            <thead><tr><th>File</th><th>Status</th><th>Students</th><th>Records</th><th>To check</th><th>Uploaded</th><th><span class="sr-only">Actions</span></th></tr></thead>
            <tbody id="gsBatchRows"><tr><td colspan="7" class="gs-table-note">Loading uploads…</td></tr></tbody>
          </table>
        </div>
      </section>

      <footer><span>Digitalization Archiving Management System</span><span>Gradesheets <b>·</b> ZPPSU Registrar</span></footer>
    </main>
  </div>

  <div class="gs-modal-backdrop" id="gsUploadModal" hidden>
    <section class="gs-modal gs-upload-modal" role="dialog" aria-modal="true" aria-labelledby="gsUploadModalTitle" aria-describedby="gsUploadModalIntro">
      <div class="gs-modal-header">
        <div><span class="panel-kicker">Batch intake</span><h2 id="gsUploadModalTitle">Upload gradesheets</h2><small id="gsUploadModalIntro" class="field-help">Every student on the sheet is read automatically — review the table and select Save before it's searchable.</small></div>
        <button class="row-action modal-close" type="button" data-close-modal aria-label="Close"><i data-lucide="x"></i></button>
      </div>
      <label class="upload-zone gs-upload-zone" id="gsUploadZone" for="gsFileInput" tabindex="0" role="button" aria-describedby="gsUploadHint">
        <input id="gsFileInput" type="file" accept="image/*,.pdf" multiple tabindex="-1" />
        <span class="upload-icon"><i data-lucide="upload-cloud"></i></span>
        <strong><span class="gs-drop-idle">Drop files here or <u>browse</u></span><span class="gs-drop-active">Release to add files</span></strong>
        <small id="gsUploadHint">PDF, JPG, or PNG · up to 25 MB each · several files at once is fine</small>
      </label>
      <div class="gs-progress" id="gsProgress" hidden>
        <div class="gs-progress-head">
          <span class="gs-spinner" aria-hidden="true"></span>
          <strong id="gsProgressLabel">Preparing…</strong>
          <span class="gs-progress-percent" id="gsProgressPercent">0%</span>
        </div>
        <div class="gs-progress-track" role="progressbar" aria-labelledby="gsProgressLabel" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="gsProgressTrack"><div class="gs-progress-bar" id="gsProgressBar"></div></div>
        <small class="gs-progress-detail" id="gsProgressDetail"></small>
      </div>
      <div class="gs-queue" id="gsQueueBox" hidden>
        <div class="gs-queue-head"><span class="panel-kicker">This session</span><button class="gs-text-button" id="gsQueueClear" type="button">Clear finished</button></div>
        <ul class="gs-queue-list" id="gsQueueList" aria-live="polite"></ul>
      </div>
      <details class="gs-upload-help">
        <summary><i data-lucide="info"></i> How grades are read</summary>
        <p>Pages are read in your browser — nothing is sent to outside services, and pages can be sideways or upside down. Final grades are read from each row's Final Grade cell, including handwritten grades; remarks follow the grading system on the form. A grade or name the reader isn't sure about — including one a pen mark or checkmark may have smudged — is still recorded but flagged <span class="gs-flag">Check</span> so you can confirm it. Nothing is searchable yet: review the table in Upload history and select Save when it looks right.</p>
      </details>
      <div class="gs-modal-actions gs-upload-footer">
        <small class="gs-background-note" id="gsBackgroundNote" hidden><i data-lucide="info"></i> You can close this window — reading continues in the background.</small>
        <span class="gs-actions-spacer"></span>
        <button class="ghost-button" type="button" data-close-modal id="gsUploadDone">Close</button>
      </div>
    </section>
  </div>

  <div class="gs-modal-backdrop" id="gsBatchModal" hidden>
    <section class="gs-modal gs-modal-wide" role="dialog" aria-modal="true" aria-labelledby="gsBatchModalTitle">
      <div class="gs-modal-header">
        <div><span class="panel-kicker">Recorded from this upload</span><h2 id="gsBatchModalTitle">Gradesheet records</h2><small id="gsBatchModalSummary" class="field-help"></small></div>
        <button class="row-action modal-close" type="button" data-close-modal aria-label="Close"><i data-lucide="x"></i></button>
      </div>
      <p class="gs-pending-note" id="gsBatchPendingNote" hidden><i data-lucide="info"></i> Not saved yet — these records are not searchable until you review them and select Save.</p>
      <div class="gs-batch-toolbar">
        <div class="search-box gs-batch-search">
          <i data-lucide="search"></i>
          <input id="gsBatchSearch" type="search" placeholder="Search by student name, subject, or year &amp; course" autocomplete="off" />
        </div>
        <label class="gs-filter-toggle"><input id="gsBatchOnlyFlagged" type="checkbox" /> Show only records to check</label>
      </div>
      <div class="gs-table-wrap gs-modal-scroll">
        <table class="gs-table">
          <thead><tr><th>Student</th><th>Year &amp; course</th><th>Subject</th><th>S.Y. / Semester</th><th>Final grade</th><th>Remarks</th><th><span class="sr-only">Actions</span></th></tr></thead>
          <tbody id="gsBatchEntryRows"></tbody>
        </table>
      </div>
      <div class="gs-modal-actions gs-batch-footer">
        <span class="gs-actions-spacer"></span>
        <button class="ghost-button" type="button" data-close-modal>Close</button>
        <button class="login-submit gs-primary" id="gsBatchSave" type="button"><i data-lucide="check-check"></i> Save records</button>
      </div>
    </section>
  </div>

  <div class="gs-modal-backdrop gs-modal-top" id="gsAddStudentModal" hidden>
    <section class="gs-modal gs-modal-wide gs-add-student-modal" role="dialog" aria-modal="true" aria-labelledby="gsAddStudentTitle">
      <div class="gs-modal-header">
        <div><span class="panel-kicker">Gradesheets student</span><h2 id="gsAddStudentTitle">Add Student</h2><small class="field-help">Enter the grade record details to find the student in uploaded gradesheets.</small></div>
        <button class="row-action modal-close" type="button" data-close-add-student aria-label="Close"><i data-lucide="x"></i></button>
      </div>
      <form class="gs-edit-form gs-add-student-form" id="gsAddStudentForm" novalidate>
        <label class="gs-span-2">Student name (as printed)<input id="gsAddStudentName" maxlength="160" autocomplete="off" /></label>
        <label>Year<input id="gsAddStudentYear" maxlength="40" placeholder="e.g. 1st Year" /></label>
        <label>Course<input id="gsAddStudentCourse" maxlength="80" placeholder="e.g. BSIT" /></label>
        <label>Semester<select id="gsAddSemester"><option value="">Select</option></select></label>
        <label>School year (start)<input id="gsAddYear" inputmode="numeric" maxlength="4" placeholder="e.g. 2014" /></label>
        <label>Subject code<input id="gsAddCode" maxlength="20" /></label>
        <label>Units<input id="gsAddUnits" inputmode="decimal" maxlength="5" /></label>
        <label class="gs-span-2">Subject title<input id="gsAddTitle" maxlength="255" /></label>
        <label>Final grade<input id="gsAddGrade" maxlength="10" placeholder="e.g. 1.75, INC, DRP" /></label>
        <label>Remarks<input id="gsAddRemarks" maxlength="100" placeholder="e.g. PASSED" /></label>
        <p class="gs-form-error gs-span-2" id="gsAddStudentError" role="alert" hidden></p>
        <div class="gs-modal-actions gs-span-2">
          <span class="gs-actions-spacer"></span>
          <button class="ghost-button" type="button" data-close-add-student>Cancel</button>
          <button class="login-submit gs-primary" id="gsAddStudentSave" type="submit"><i data-lucide="user-round-plus"></i> Save &amp; find grades</button>
        </div>
      </form>
    </section>
  </div>

  <div class="gs-modal-backdrop gs-modal-top" id="gsEditModal" hidden>
    <section class="gs-modal" role="dialog" aria-modal="true" aria-labelledby="gsEditTitle">
      <div class="gs-modal-header">
        <div><span class="panel-kicker">Verify grade record</span><h2 id="gsEditTitle">Edit grade record</h2></div>
        <button class="row-action modal-close" type="button" data-close-modal aria-label="Close"><i data-lucide="x"></i></button>
      </div>
      <form class="gs-edit-form" id="gsEditForm" novalidate>
        <label class="gs-span-2">Student name (as printed)<input id="gsEditStudentName" maxlength="160" autocomplete="off" /><small class="field-help" id="gsEditStudentHelp"></small></label>
        <div class="gs-span-2 gs-suggest-row" id="gsEditNameSuggest" hidden></div>
        <label class="gs-span-2 gs-check" id="gsEditApplyAllRow" hidden><input type="checkbox" id="gsEditApplyAll" checked /> <span id="gsEditApplyAllText"></span></label>
        <label>Year<input id="gsEditStudentYear" maxlength="40" placeholder="e.g. 1st Year" /></label>
        <label>Course<input id="gsEditStudentCourse" maxlength="80" placeholder="e.g. BSIT" /></label>
        <label>Semester<select id="gsEditSemester"><option value="">Select</option></select></label>
        <label>School year (start)<input id="gsEditYear" inputmode="numeric" maxlength="4" placeholder="e.g. 2014" /></label>
        <label>Subject code<input id="gsEditCode" maxlength="20" /></label>
        <label>Units<input id="gsEditUnits" inputmode="decimal" maxlength="5" /></label>
        <label class="gs-span-2">Subject title<input id="gsEditTitle" maxlength="255" /></label>
        <label>Final grade<input id="gsEditGrade" maxlength="10" placeholder="e.g. 1.75, INC, DRP" /></label>
        <label>Remarks<input id="gsEditRemarks" maxlength="100" placeholder="e.g. PASSED" /></label>
        <p class="gs-form-error gs-span-2" id="gsEditError" role="alert" hidden></p>
        <div class="gs-modal-actions gs-span-2">
          <button class="ghost-button gs-danger" id="gsEditDelete" type="button"><i data-lucide="trash-2"></i> Delete record</button>
          <span class="gs-actions-spacer"></span>
          <button class="ghost-button" type="button" data-close-modal>Cancel</button>
          <button class="login-submit gs-primary" id="gsEditSave" type="submit"><i data-lucide="check"></i> Save &amp; mark verified</button>
        </div>
      </form>
    </section>
  </div>

  <div class="admin-toast" id="adminToast" role="status"></div>
  <script src="../data-sync.js?v=<?= filemtime(__DIR__ . '/../data-sync.js') ?>"></script>
  <script src="gradesheet-flow.js?v=<?= filemtime(__DIR__ . '/gradesheet-flow.js') ?>"></script>
  <script src="gradesheet-ocr-engine.js?v=<?= filemtime(__DIR__ . '/gradesheet-ocr-engine.js') ?>"></script>
  <script src="gradesheets.js?v=<?= filemtime(__DIR__ . '/gradesheets.js') ?>"></script>
</body>
</html>
