<?php require_once __DIR__ . '/../auth.php'; dats_require_page_access(basename(__FILE__)); $currentUser = dats_current_user(); $displayName = htmlspecialchars($currentUser['full_name'], ENT_QUOTES, 'UTF-8'); $initials = dats_user_initials($currentUser); ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="File management for the ZPPSU digital archive." />
  <title>Files | ZPPSU DATS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="styles.css?v=20260914" />
  <script src="https://unpkg.com/lucide@latest"></script>
  <style>
    .files-filters, .files-panel { width: 88.8%; max-width: 1180px; margin-left: auto; margin-right: auto; }
    .files-filters { display: flex; justify-content: flex-end; align-items: center; gap: 12px; margin-bottom: 18px; }
    .enrollment-year-filter { display: grid; grid-template-columns: auto 150px auto; align-items: center; justify-content: end; gap: 9px; width: max-content; max-width: 100%; }
    .enrollment-year-filter select { width: 150px; min-height: 36px; padding: 0 9px; color: var(--ink); background: var(--white); border: 1px solid var(--line); border-radius: 4px; font: 10px var(--sans); }
    .enrollment-year-filter select:focus-visible { outline: 2px solid var(--maroon); outline-offset: 2px; }
    .enrollment-year-filter .folder-delete { width: 36px; height: 36px; border-color: var(--line); border-radius: 4px; }
    .enrollment-year-filter .folder-delete:disabled { opacity: .45; cursor: not-allowed; }
    @media (max-width: 700px) { .enrollment-year-filter { grid-template-columns: auto minmax(0, 150px) auto; gap: 7px; }.enrollment-year-filter label { white-space: nowrap; } }
      @media (max-width: 360px) { .enrollment-year-filter { grid-template-columns: minmax(0, 1fr) 150px 36px; width: 100%; }.enrollment-year-filter label { grid-column: 1 / -1; }.enrollment-year-filter select { grid-column: 2; }.enrollment-year-filter .folder-delete { grid-column: 3; } }
    .files-panel { padding: 24px; }
    .files-panel + .files-panel { margin-top: 18px; }
    .folder-icon { display: inline-flex; width: 42px; height: 42px; align-items: center; justify-content: center; border-radius: 12px; background: #f7efef; color: var(--maroon); }
    .folder-delete { display: grid; flex: 0 0 28px; place-items: center; width: 28px; height: 28px; padding: 0; color: var(--muted); background: transparent; border: 1px solid transparent; border-radius: 50%; cursor: pointer; transition: color .16s ease, background .16s ease; }
    .folder-delete:hover { color: var(--maroon); background: var(--maroon-soft); }
    .folder-delete:focus-visible { outline: 2px solid var(--maroon); outline-offset: 2px; }
    .folder-delete svg { width: 14px; height: 14px; }
    .file-list { display: flex; flex-direction: column; gap: 10px; }
    .file-item { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 14px; border: 1px solid var(--line); border-radius: 12px; background: #fff; }
    .file-item strong { display: block; font-size: 13px; }
    .file-item small { color: var(--muted); }
    .student-year-table-wrap { overflow-x: auto; }
    .student-year-table { width: 100%; min-width: 680px; border-collapse: collapse; }
    .student-year-table th, .student-year-table td { padding: 14px 12px; border-bottom: 1px solid var(--line); text-align: left; }
    .student-year-table th { color: var(--muted); font: 9px var(--mono); letter-spacing: .08em; text-transform: uppercase; }
    .student-year-table td { color: var(--ink); font-size: 10px; }
      .student-file-row { cursor: pointer; }
      .student-file-row:hover td { background: var(--gold-pale); }
      .student-file-row:focus-visible { outline: 2px solid var(--maroon); outline-offset: -2px; }
    .student-year-table th.sortable-th { padding: 0; }
    .sort-th { display: inline-flex; align-items: center; gap: 4px; width: 100%; padding: 14px 12px; color: var(--muted); background: transparent; border: 0; cursor: pointer; font: 9px var(--mono); letter-spacing: .08em; text-transform: uppercase; transition: color .16s ease; }
    .sort-th:hover { color: var(--maroon); }
    .sort-th.active { color: var(--maroon-dark); font-weight: 700; }
    .sort-th svg { width: 12px; height: 12px; }
    .sort-th:focus-visible { outline: 2px solid var(--maroon); outline-offset: -2px; }
    .student-name-cell { display: flex; align-items: center; gap: 10px; }
    .student-mini-avatar { display: grid; place-items: center; width: 30px; height: 30px; color: var(--white); background: var(--maroon); border-radius: 50%; font: 8px var(--mono); }
    .student-meta { display: grid; gap: 3px; }
    .student-meta strong { font-size: 10px; }
    .student-meta small { color: var(--muted); font-size: 9px; }
    .year-student-toolbar { display: flex; margin: 0 0 14px; }
    .year-student-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: 0 0 14px; }
    .year-student-toolbar .search-box { position: relative; flex: 1 1 300px; min-width: 0; min-height: 40px; width: 100%; max-width: 420px; }
    .year-student-count { display: flex; flex: 0 0 auto; align-items: center; gap: 9px; min-height: 40px; padding: 0 11px; background: var(--paper); border: 1px solid var(--line); white-space: nowrap; }
    .year-student-count span { color: var(--muted); font: 8px var(--mono); text-transform: uppercase; }
    .year-student-count strong { color: var(--maroon); font-size: 12px; }
    @media (max-width: 420px) { .year-student-toolbar { flex-wrap: wrap; gap: 7px; }.year-student-toolbar .search-box { flex-basis: 100%; max-width: none; }.year-student-count { gap: 6px; margin-left: auto; padding: 0 7px; }.year-student-count span { font-size: 7px; } }
    .search-suggestions { position: absolute; top: calc(100% + 8px); left: -1px; right: -1px; z-index: 6; max-height: 248px; overflow-y: auto; padding: 6px; background: var(--white); border: 1px solid var(--line); border-radius: 12px; box-shadow: 0 16px 34px rgba(48,39,35,.14); }
    .search-suggestions[hidden] { display: none; }
    .search-suggestion-item { display: flex; align-items: center; gap: 10px; width: 100%; padding: 9px 10px; color: var(--ink); background: transparent; border: 0; border-radius: 8px; text-align: left; cursor: pointer; font: inherit; }
    .search-suggestion-item:hover, .search-suggestion-item.active { background: var(--gold-pale); }
    .search-suggestion-item .student-mini-avatar { flex: 0 0 26px; width: 26px; height: 26px; font-size: 7px; }
    .search-suggestion-item strong { display: block; font-size: 10px; }
    .search-suggestion-item small { display: block; margin-top: 2px; color: var(--muted); font-size: 9px; }
    .search-suggestion-empty { padding: 12px 10px; color: var(--muted); font-size: 10px; text-align: center; }
    .student-row-actions { display: flex; align-items: center; gap: 6px; }
    .student-row-delete { display: grid; flex: 0 0 28px; place-items: center; width: 28px; height: 28px; padding: 0; color: var(--muted); background: transparent; border: 1px solid transparent; border-radius: 50%; cursor: pointer; transition: color .16s ease, background .16s ease; }
    .student-row-delete:hover { color: var(--maroon); background: var(--maroon-soft); }
    .student-row-delete:focus-visible { outline: 2px solid var(--maroon); outline-offset: 2px; }
    .student-row-delete svg { width: 14px; height: 14px; }
    .year-pagination { display: flex; justify-content: flex-end; align-items: center; gap: 8px; margin-top: 14px; }
    .year-empty-state { padding: 20px; border: 1px dashed var(--line); border-radius: 12px; color: var(--muted); text-align: center; }
    .student-files-modal { width: min(92vw, 760px); }
    .student-files-header { display: grid; gap: 8px; margin-bottom: 18px; }
    .student-files-meta { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-bottom: 18px; }
    .student-files-meta .meta-box { padding: 12px 14px; background: var(--paper); border: 1px solid var(--line); border-radius: 10px; }
    .student-files-meta .meta-box span { display: block; color: var(--muted); font: 8px var(--mono); text-transform: uppercase; letter-spacing: .08em; }
    .student-files-meta .meta-box strong { display: block; margin-top: 6px; font-size: 12px; }
    .student-file-list { display: grid; gap: 16px; }
    .student-file-group { display: grid; gap: 10px; }
    .student-file-group h3 { margin: 0; color: var(--maroon); font-size: 11px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
    .student-file-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 16px; padding: 10px 12px; border: 1px solid var(--line); border-radius: 10px; background: var(--paper); }
    .bulk-select-toggle { display: inline-flex; align-items: center; gap: 8px; color: var(--ink); font: 700 10px var(--sans); }
    .student-file-checkbox { width: 16px; height: 16px; accent-color: var(--maroon); }
    .student-file-preview-panel { display: none; }
    .student-file-detail-panel { display: none; }
    .student-file-preview { display: none; }
    .student-file-preview img,
    .student-file-preview iframe { width: 100%; height: 100%; min-height: 260px; border: 0; border-radius: 8px; object-fit: contain; background: #f7f4f3; }
    .student-file-preview.empty { color: var(--muted); font-size: 11px; text-align: center; }
    .student-preview-placeholder { display: grid; place-items: center; gap: 8px; text-align: center; color: var(--muted); }
    .student-preview-placeholder svg { width: 28px; height: 28px; color: var(--maroon); }
    .student-detail-panel h3 { margin: 0 0 12px; font-size: 11px; letter-spacing: .08em; text-transform: uppercase; color: var(--maroon); }
    .student-detail-grid { display: grid; gap: 10px; }
    .student-detail-item { display: grid; gap: 4px; }
    .student-detail-item span { color: var(--muted); font: 8px var(--mono); text-transform: uppercase; letter-spacing: .08em; }
    .student-detail-item strong, .student-detail-item p { margin: 0; font-size: 11px; line-height: 1.5; word-break: break-word; }
    .student-file-item { display: grid; grid-template-columns: 24px 48px 1fr auto; gap: 12px; align-items: center; padding: 12px 14px; background: #fff; border: 1px solid var(--line); border-radius: 10px; cursor: pointer; transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease; }
    .student-file-item:hover { transform: translateY(-1px); border-color: rgba(214,173,58,.7); }
    .student-file-item.selected { border-color: rgba(214,173,58,.85); box-shadow: inset 0 0 0 1px rgba(214,173,58,.2); }
    .student-file-select { display: grid; place-items: center; }
    .student-file-icon { display: grid; place-items: center; width: 42px; height: 42px; background: var(--gold-pale); color: var(--maroon); border-radius: 10px; }
    .student-file-icon svg { width: 18px; }
    .student-file-details strong, .student-file-details small { display: block; }
    .student-file-details strong { font-size: 11px; word-break: break-word; }
    .student-file-details small { margin-top: 6px; color: var(--muted); font-size: 9px; line-height: 1.5; }
    .student-file-actions { display: flex; gap: 8px; }
    .student-file-actions a,
    .student-file-actions button.outline-button { min-width: 90px; max-width: 150px; overflow: hidden; text-overflow: ellipsis; }
    .student-file-actions button.student-file-delete { color: var(--maroon); }
    .student-file-actions a.disabled { pointer-events: none; opacity: .45; }
    .student-files-empty { padding: 18px; background: var(--paper); border: 1px dashed var(--line); border-radius: 10px; color: var(--muted); text-align: center; }
    .folder-modal, .delete-folder-modal { width: min(92vw, 430px); padding: 30px 30px 26px; }
    .folder-modal > p, .delete-folder-modal > p { margin: 0 0 20px; }
    .folder-modal form { display: grid; gap: 18px; }

    /* Footer buttons ---------------------------------------------------
       .login-submit ships with width/padding/margin meant for a button
       standing alone under a field. In this footer row that margin drops it
       below Cancel and the auto width makes the pair lopsided, so the row
       states the shared box itself rather than relying on load order. */
    .folder-modal .modal-actions,
    .delete-folder-modal .modal-actions { display: flex; align-items: center; justify-content: flex-end; gap: 10px; margin: 2px 0 0; padding-top: 18px; border-top: 1px solid var(--line); }
    .folder-modal .modal-actions > button,
    .delete-folder-modal .modal-actions > button { flex: 0 0 auto; align-self: center; box-sizing: border-box; width: auto; min-width: 118px; height: 38px; margin: 0; padding: 0 18px; line-height: 1; }

    /* Delete confirmation ----------------------------------------------- */
    .delete-folder-target { display: flex; align-items: center; gap: 13px; padding: 13px 15px; background: var(--paper); border: 1px solid var(--line); border-radius: 12px; }
    .delete-folder-target .folder-icon { flex: 0 0 42px; }
    .delete-folder-target div { min-width: 0; }
    .delete-folder-target strong { display: block; font-size: 22px; letter-spacing: -.04em; color: var(--ink); }
    .delete-folder-target small { display: block; margin-top: 3px; color: var(--muted); font-size: 10px; }
    .delete-folder-note { display: flex; align-items: flex-start; gap: 8px; margin: 14px 0 0; padding: 11px 13px; border-radius: 10px; font-size: 10px; line-height: 1.5; }
    .delete-folder-note svg { flex: 0 0 14px; width: 14px; height: 14px; margin-top: 1px; }
    .delete-folder-note.warning { color: var(--maroon); background: var(--maroon-soft); }
    .delete-folder-note.blocked { color: var(--maroon-dark); background: var(--gold-pale); }
    #confirmDeleteFolderModal .panel-kicker { display: block; margin-bottom: 4px; }
    #confirmDeleteFolderModal h2 { margin-bottom: 14px; }
    #confirmDeleteFolderModal #confirmDeleteFolderIntro { margin-bottom: 24px; line-height: 1.55; }
    #confirmDeleteFolderModal .delete-folder-target { margin-bottom: 4px; }
    #confirmDeleteFolderModal #confirmDeleteFolderNote { margin-top: 20px; }

    /* Year field ------------------------------------------------------- */
    .year-field { display: grid; gap: 9px; }
    .year-field > label { color: var(--ink); font: 700 10px var(--sans); }

    .year-stepper { display: grid; grid-template-columns: 38px 1fr 38px; align-items: center; gap: 0; border: 1px solid var(--line); border-radius: 999px; background: var(--white); overflow: hidden; transition: border-color .16s ease, box-shadow .16s ease; }
    .year-stepper:focus-within { border-color: var(--maroon); box-shadow: 0 0 0 3px rgba(107, 29, 42, .1); }
    .year-stepper.invalid { border-color: var(--maroon); box-shadow: 0 0 0 3px rgba(107, 29, 42, .12); }

    .year-stepper input { width: 100%; height: 44px; padding: 0; color: var(--ink); background: transparent; border: 0; outline: 0; text-align: center; font: 500 19px/1 var(--mono); letter-spacing: .1em; font-variant-numeric: tabular-nums; }
    .year-stepper input::placeholder { color: #c4b6b4; letter-spacing: .1em; }
    /* Native spinners duplicate the stepper buttons. */
    .year-stepper input::-webkit-outer-spin-button,
    .year-stepper input::-webkit-inner-spin-button { margin: 0; -webkit-appearance: none; appearance: none; }
    .year-stepper input[type=number] { -moz-appearance: textfield; appearance: textfield; }

    .year-step { display: grid; place-items: center; width: 38px; height: 44px; padding: 0; color: var(--muted); background: transparent; border: 0; cursor: pointer; transition: color .16s ease, background .16s ease; }
    .year-step:hover { color: var(--maroon); background: var(--gold-pale); }
    .year-step:focus-visible { outline: 2px solid var(--maroon); outline-offset: -3px; }
    .year-step svg { width: 15px; height: 15px; }

    .year-hint { margin: 0; color: var(--muted); font-size: 9px; }

    .year-suggestions { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 3px; }
    .year-chip { height: 26px; padding: 0 12px; color: var(--ink); background: var(--white); border: 1px solid var(--line); border-radius: 999px; font: 500 9px/1 var(--mono); cursor: pointer; transition: color .16s ease, background .16s ease, border-color .16s ease; }
    .year-chip:hover { color: var(--maroon); background: var(--paper); border-color: #d9c9c5; }
    .year-chip.selected { color: var(--maroon-dark); background: var(--gold-pale); border-color: rgba(214, 173, 58, .85); font-weight: 700; }
    .year-chip:disabled { color: var(--muted); background: var(--paper); cursor: not-allowed; opacity: .6; }
    .year-chip:focus-visible { outline: 2px solid var(--maroon); outline-offset: 2px; }

    /* Only takes up space once there is something to say. */
    .folder-modal-message { display: flex; align-items: center; gap: 7px; margin: 0; padding: 9px 12px; color: var(--maroon); background: var(--maroon-soft); border-radius: 8px; font-size: 10px; line-height: 1.4; }
    .folder-modal-message[hidden] { display: none; }
    .folder-modal-message svg { width: 14px; height: 14px; flex: 0 0 14px; }
    /* Recycle bin --------------------------------------------------------- */
    .files-welcome-actions { display: flex; align-items: center; gap: 10px; }
    .recycle-bin-button { position: relative; }
    .recycle-bin-modal { width: min(92vw, 760px); }
    .recycle-bin-list { display: flex; flex-direction: column; gap: 10px; }
    .recycle-row-actions { display: flex; align-items: center; gap: 6px; }
    .recycle-document-delete { color: var(--maroon); }
    @media (max-width: 700px) {
      .files-filters { justify-content: flex-end; align-items: center; }
      .welcome-row.files-welcome { flex-direction: column; align-items: stretch; gap: 14px; }
      .files-welcome-actions { justify-content: stretch; }
      .files-welcome-actions > button { flex: 1 1 auto; }
    }
    @media (max-width: 420px) {
      .folder-modal .modal-actions > button,
      .delete-folder-modal .modal-actions > button { flex: 1 1 auto; min-width: 0; }
    }
    @media (max-width: 640px) {
      .files-filters, .files-panel { width: 88%; }
    }
  </style>
  <link rel="stylesheet" href="../dashboard-tables.css?v=20260914" />
  <link rel="stylesheet" href="../dashboard-buttons.css?v=20260914" />
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
        <a class="active" href="files.php" aria-current="page"><i data-lucide="files"></i> Student Files</a>
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

    <main class="admin-main files-main">
      <header class="admin-header">
        <button class="mobile-menu" id="mobileMenu" aria-label="Open admin navigation" aria-expanded="false"><i data-lucide="menu"></i></button>
        <div>
          <span class="eyebrow">Administrator workspace</span>
          <h1><span data-greeting data-user-name="<?= $displayName ?>">Good morning</span></h1>
        </div>
        <div class="header-actions">
          <button class="header-icon" aria-label="Notifications"><i data-lucide="bell"></i><span></span></button>
          <div class="user-chip">
            <span class="avatar"><?= $initials ?></span>
            <span><strong><?= $displayName ?></strong><small><?= htmlspecialchars(dats_user_role_label($currentUser), ENT_QUOTES, 'UTF-8') ?></small></span>
            <i data-lucide="chevron-down"></i>
          </div>
        </div>
      </header>

      <section class="welcome-row files-welcome">
        <div>
          <p class="page-kicker">Archive files · <span data-current-datetime>Loading date and time...</span></p>
          <h2>Files</h2>
          <p class="page-intro">Manage student records by enrollment year.</p>
        </div>
        <div class="files-welcome-actions">
          <button class="outline-button recycle-bin-button" id="recycleBinButton" type="button"><i data-lucide="archive-restore"></i> Recycle Bin</button>
          <button class="gold-button" id="addFolderButton" type="button"><i data-lucide="folder-plus"></i> Add Folder</button>
        </div>
      </section>

      <section class="files-filters" aria-label="Filter student files by enrollment year">
        <div class="enrollment-year-filter">
          <label class="panel-kicker" for="enrollmentYearFilter">Enrollment Year</label>
          <select id="enrollmentYearFilter" aria-label="Select enrollment year">
            <option value="ALL">All enrollment years</option>
          </select>
          <button class="folder-delete" id="deleteYearButton" type="button" aria-label="Delete selected enrollment year" title="Delete selected enrollment year" disabled><i data-lucide="trash-2"></i></button>
        </div>
      </section>

      <section class="panel files-panel">
        <div class="panel-heading">
          <div>
            <span class="panel-kicker">Selected year</span>
            <h3 id="selectedYearTitle">All students</h3>
          </div>
        </div>
        <div class="year-student-toolbar" aria-label="Search students in the selected year">
          <div class="search-box">
            <i data-lucide="search"></i>
            <input id="yearStudentSearch" type="search" placeholder="Search by student name or student ID" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="yearStudentSuggestions" />
            <div id="yearStudentSuggestions" class="search-suggestions" role="listbox" aria-label="Student suggestions" hidden></div>
          </div>
          <div class="year-student-count" role="status" aria-live="polite">
            <span>Total Students</span>
            <strong id="totalStudentCount">--</strong>
          </div>
        </div>
        <div id="yearStudentList" class="file-list" aria-live="polite"></div>
      </section>

      <footer>
        <span>Digitalization Archiving Management System</span>
        <span>Files <b>·</b> ZPPSU Registrar</span>
      </footer>
    </main>
  </div>

  <div class="admin-toast" id="adminToast" role="status">Year folder updated.</div>

  <div class="student-modal-backdrop" id="folderModal" hidden>
    <section class="student-modal folder-modal" role="dialog" aria-modal="true" aria-labelledby="folderModalTitle">
      <button class="row-action modal-close" id="closeFolderModal" type="button" aria-label="Close add folder dialog"><i data-lucide="x"></i></button>
      <span class="panel-kicker">Archive folder</span>
      <h2 id="folderModalTitle">Add year folder</h2>
      <p>Create an enrollment year folder for organizing student records.</p>
      <form id="folderForm" novalidate>
        <div class="year-field">
          <label for="folderYear">Enrollment year</label>
          <div class="year-stepper">
            <button type="button" class="year-step" data-year-step="-1" aria-label="Previous year"><i data-lucide="minus"></i></button>
            <input id="folderYear" name="year" type="number" inputmode="numeric" min="1800" max="9999" step="1" placeholder="2026" required aria-describedby="folderYearHint" />
            <button type="button" class="year-step" data-year-step="1" aria-label="Next year"><i data-lucide="plus"></i></button>
          </div>
          <p class="year-hint" id="folderYearHint">Valid enrollment years are 1800 to 2100.</p>
          <div class="year-suggestions" id="folderYearSuggestions" role="group" aria-label="Suggested years"></div>
        </div>
        <p class="folder-modal-message" id="folderModalMessage" role="alert" hidden><i data-lucide="alert-circle"></i><span></span></p>
        <div class="modal-actions">
          <button type="button" class="outline-button" id="cancelFolderButton">Cancel</button>
          <button class="login-submit" type="submit">Save folder <i data-lucide="arrow-right"></i></button>
        </div>
      </form>
    </section>
  </div>

  <div class="student-modal-backdrop" id="deleteFolderModal" hidden>
    <section class="student-modal delete-folder-modal" role="alertdialog" aria-modal="true" aria-labelledby="deleteFolderTitle" aria-describedby="deleteFolderIntro">
      <button class="row-action modal-close" id="closeDeleteFolderModal" type="button" aria-label="Close delete folder dialog"><i data-lucide="x"></i></button>
      <span class="panel-kicker">Archive folder</span>
      <h2 id="deleteFolderTitle">Delete year folder</h2>
      <p id="deleteFolderIntro">This removes the enrollment year folder and moves its student records to the Recycle Bin.</p>
      <div class="delete-folder-target">
        <span class="folder-icon"><i data-lucide="folder"></i></span>
        <div>
          <strong id="deleteFolderYear">—</strong>
          <small id="deleteFolderCounts">—</small>
        </div>
      </div>
      <p class="delete-folder-note" id="deleteFolderNote"><i data-lucide="alert-circle"></i><span></span></p>
      <div class="modal-actions">
        <button type="button" class="outline-button" id="cancelDeleteFolderButton">Cancel</button>
        <button type="button" class="login-submit" id="openDeleteFolderConfirmationButton">Delete folder <i data-lucide="trash-2"></i></button>
      </div>
    </section>
  </div>
  <div class="student-modal-backdrop" id="confirmDeleteFolderModal" hidden>
    <section class="student-modal delete-folder-modal" role="alertdialog" aria-modal="true" aria-labelledby="confirmDeleteFolderTitle" aria-describedby="confirmDeleteFolderIntro">
      <button class="row-action modal-close" id="closeConfirmDeleteFolderModal" type="button" aria-label="Close final delete folder confirmation"><i data-lucide="x"></i></button>
      <span class="panel-kicker">Final confirmation</span>
      <h2 id="confirmDeleteFolderTitle">Delete year folder?</h2>
      <p id="confirmDeleteFolderIntro">Are you sure you want to delete this year folder <strong id="confirmDeleteFolderYear">—</strong>?</p>
      <div class="delete-folder-target">
        <span class="folder-icon"><i data-lucide="folder-x"></i></span>
        <div>
          <strong id="confirmDeleteFolderQuestion">Delete folder</strong>
          <small id="confirmDeleteFolderCounts">—</small>
        </div>
      </div>
      <p class="delete-folder-note warning" id="confirmDeleteFolderNote"><i data-lucide="alert-circle"></i><span>This action moves all students in this year to the Recycle Bin. Their files remain recoverable until you choose Delete Permanently.</span></p>
      <div class="modal-actions">
        <button type="button" class="outline-button" id="cancelConfirmDeleteFolderButton">Cancel</button>
        <button type="button" class="login-submit" id="confirmDeleteFolderButton">Yes, delete folder <i data-lucide="trash-2"></i></button>
      </div>
    </section>
  </div>
  <div class="student-modal-backdrop" id="deleteStudentModal" hidden>
    <section class="student-modal delete-folder-modal" role="alertdialog" aria-modal="true" aria-labelledby="deleteStudentTitle" aria-describedby="deleteStudentIntro">
      <button class="row-action modal-close" id="closeDeleteStudentModal" type="button" aria-label="Close remove student dialog"><i data-lucide="x"></i></button>
      <span class="panel-kicker">Student record</span>
      <h2 id="deleteStudentTitle">Remove student</h2>
      <p id="deleteStudentIntro">This moves the student record to the Recycle Bin, along with all of their archived files.</p>
      <div class="delete-folder-target">
        <span class="folder-icon"><i data-lucide="user-round"></i></span>
        <div>
          <strong id="deleteStudentName">—</strong>
          <small id="deleteStudentMeta">—</small>
        </div>
      </div>
      <p class="delete-folder-note warning"><i data-lucide="alert-circle"></i><span>You can restore this student from the Recycle Bin at any time, or delete them permanently from there.</span></p>
      <div class="modal-actions">
        <button type="button" class="outline-button" id="cancelDeleteStudentButton">Cancel</button>
        <button type="button" class="login-submit" id="confirmDeleteStudentButton">Remove student <i data-lucide="trash-2"></i></button>
      </div>
    </section>
  </div>
  <div class="student-modal-backdrop" id="recycleBinModal" hidden>
    <section class="student-modal recycle-bin-modal" role="dialog" aria-modal="true" aria-labelledby="recycleBinTitle">
      <button class="row-action modal-close" id="closeRecycleBinModal" type="button" aria-label="Close recycle bin"><i data-lucide="x"></i></button>
      <div class="student-files-header">
        <span class="panel-kicker">Files</span>
        <h2 id="recycleBinTitle">Recycle bin</h2>
        <p class="page-intro">Students and documents removed from the archive. Restore an item or delete it permanently.</p>
      </div>
      <div id="recycleBinList" class="recycle-bin-list" aria-live="polite"></div>
    </section>
  </div>
  <div class="student-modal-backdrop" id="deleteDocumentModal" hidden>
    <section class="student-modal delete-folder-modal" role="alertdialog" aria-modal="true" aria-labelledby="deleteDocumentTitle" aria-describedby="deleteDocumentIntro">
      <button class="row-action modal-close" id="closeDeleteDocumentModal" type="button" aria-label="Close remove document dialog"><i data-lucide="x"></i></button>
      <span class="panel-kicker">Student file</span>
      <h2 id="deleteDocumentTitle">Delete document</h2>
      <p id="deleteDocumentIntro">This document will be moved to the Recycle Bin. It will remain available there and can be restored later.</p>
      <div class="delete-folder-target">
        <span class="folder-icon"><i data-lucide="file-text"></i></span>
        <div>
          <strong id="deleteDocumentName">—</strong>
          <small id="deleteDocumentMeta">—</small>
        </div>
      </div>
      <p class="delete-folder-note warning"><i data-lucide="alert-circle"></i><span>The file will not be permanently removed from the database or storage.</span></p>
      <div class="modal-actions">
        <button type="button" class="outline-button" id="cancelDeleteDocumentButton">Cancel</button>
        <button type="button" class="login-submit" id="confirmDeleteDocumentButton">Move to Recycle Bin <i data-lucide="trash-2"></i></button>
      </div>
    </section>
  </div>
  <div class="student-modal-backdrop" id="purgeStudentModal" hidden>
    <section class="student-modal delete-folder-modal" role="alertdialog" aria-modal="true" aria-labelledby="purgeStudentTitle" aria-describedby="purgeStudentIntro">
      <button class="row-action modal-close" id="closePurgeStudentModal" type="button" aria-label="Close delete forever dialog"><i data-lucide="x"></i></button>
      <span class="panel-kicker">Recycle bin</span>
      <h2 id="purgeStudentTitle">Delete permanently</h2>
      <p id="purgeStudentIntro">This permanently erases the student record and all of their archived files.</p>
      <div class="delete-folder-target">
        <span class="folder-icon"><i data-lucide="user-round"></i></span>
        <div>
          <strong id="purgeStudentName">—</strong>
          <small id="purgeStudentMeta">—</small>
        </div>
      </div>
      <p class="delete-folder-note warning"><i data-lucide="alert-circle"></i><span>This cannot be undone. There is no further recovery once this is confirmed.</span></p>
      <div class="modal-actions">
        <button type="button" class="outline-button" id="cancelPurgeStudentButton">Cancel</button>
        <button type="button" class="login-submit" id="confirmPurgeStudentButton">Delete <i data-lucide="trash-2"></i></button>
      </div>
    </section>
  </div>
  <div class="student-modal-backdrop" id="purgeDocumentModal" hidden>
    <section class="student-modal delete-folder-modal" role="alertdialog" aria-modal="true" aria-labelledby="purgeDocumentTitle" aria-describedby="purgeDocumentIntro">
      <button class="row-action modal-close" id="closePurgeDocumentModal" type="button" aria-label="Close delete document dialog"><i data-lucide="x"></i></button>
      <span class="panel-kicker">Recycle bin</span>
      <h2 id="purgeDocumentTitle">Delete document permanently</h2>
      <p id="purgeDocumentIntro">This permanently erases the document and its stored file.</p>
      <div class="delete-folder-target">
        <span class="folder-icon"><i data-lucide="file-text"></i></span>
        <div>
          <strong id="purgeDocumentName">—</strong>
          <small id="purgeDocumentMeta">—</small>
        </div>
      </div>
      <p class="delete-folder-note warning"><i data-lucide="alert-circle"></i><span>This cannot be undone. There is no further recovery once this is confirmed.</span></p>
      <div class="modal-actions">
        <button type="button" class="outline-button" id="cancelPurgeDocumentButton">Cancel</button>
        <button type="button" class="login-submit" id="confirmPurgeDocumentButton">Delete<i data-lucide="trash-2"></i></button>
      </div>
    </section>
  </div>
  <div class="student-modal-backdrop" id="studentFilesModal" hidden>
    <section class="student-modal student-files-modal" role="dialog" aria-modal="true" aria-labelledby="studentFilesTitle">
      <button class="row-action modal-close" id="closeStudentFilesModal" type="button" aria-label="Close student files dialog"><i data-lucide="x"></i></button>
      <div class="student-files-header">
        <span class="panel-kicker">Student files</span>
        <h2 id="studentFilesTitle">Student files</h2>
      </div>
      <div class="student-files-meta">
        <div class="meta-box"><span>Student Name</span><strong id="studentFilesName">—</strong></div>
        <div class="meta-box"><span>Student ID</span><strong id="studentFilesId">—</strong></div>
        <div class="meta-box"><span>Enrollment Year</span><strong id="studentFilesYear">—</strong></div>
      </div>
      <div id="studentFilesList" class="student-file-list"></div>
    </section>
  </div>

  <script src="../data-sync.js?v=<?= filemtime(__DIR__ . '/../data-sync.js') ?>"></script>
  <script src="script.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', async () => {
      const enrollmentYearFilter = document.querySelector('#enrollmentYearFilter');
      const deleteYearButton = document.querySelector('#deleteYearButton');
      const yearStudentList = document.querySelector('#yearStudentList');
      const selectedYearTitle = document.querySelector('#selectedYearTitle');
      const yearStudentSearch = document.querySelector('#yearStudentSearch');
      const totalStudentCount = document.querySelector('#totalStudentCount');
      const addFolderButton = document.querySelector('#addFolderButton');
      const folderModal = document.querySelector('#folderModal');
      const folderForm = document.querySelector('#folderForm');
      const folderYearInput = document.querySelector('#folderYear');
      const folderModalMessage = document.querySelector('#folderModalMessage');
      const closeFolderModal = document.querySelector('#closeFolderModal');
      const cancelFolderButton = document.querySelector('#cancelFolderButton');
      const folderYearStepper = document.querySelector('#folderForm .year-stepper');
      const folderYearSuggestions = document.querySelector('#folderYearSuggestions');
      const deleteFolderModal = document.querySelector('#deleteFolderModal');
      const confirmDeleteFolderModal = document.querySelector('#confirmDeleteFolderModal');
      const deleteFolderYear = document.querySelector('#deleteFolderYear');
      const deleteFolderCounts = document.querySelector('#deleteFolderCounts');
      const deleteFolderNote = document.querySelector('#deleteFolderNote');
      const closeDeleteFolderModal = document.querySelector('#closeDeleteFolderModal');
      const cancelDeleteFolderButton = document.querySelector('#cancelDeleteFolderButton');
      const openDeleteFolderConfirmationButton = document.querySelector('#openDeleteFolderConfirmationButton');
      const confirmDeleteFolderYear = document.querySelector('#confirmDeleteFolderYear');
      const confirmDeleteFolderQuestion = document.querySelector('#confirmDeleteFolderQuestion');
      const confirmDeleteFolderCounts = document.querySelector('#confirmDeleteFolderCounts');
      const confirmDeleteFolderNote = document.querySelector('#confirmDeleteFolderNote');
      const closeConfirmDeleteFolderModal = document.querySelector('#closeConfirmDeleteFolderModal');
      const cancelConfirmDeleteFolderButton = document.querySelector('#cancelConfirmDeleteFolderButton');
      const confirmDeleteFolderButton = document.querySelector('#confirmDeleteFolderButton');
      const studentFilesModal = document.querySelector('#studentFilesModal');
      const studentFilesTitle = document.querySelector('#studentFilesTitle');
      const studentFilesName = document.querySelector('#studentFilesName');
      const studentFilesId = document.querySelector('#studentFilesId');
      const studentFilesYear = document.querySelector('#studentFilesYear');
      const studentFilesList = document.querySelector('#studentFilesList');
      const closeStudentFilesModal = document.querySelector('#closeStudentFilesModal');
      const yearStudentSuggestions = document.querySelector('#yearStudentSuggestions');
      const deleteStudentModal = document.querySelector('#deleteStudentModal');
      const deleteStudentName = document.querySelector('#deleteStudentName');
      const deleteStudentMeta = document.querySelector('#deleteStudentMeta');
      const closeDeleteStudentModal = document.querySelector('#closeDeleteStudentModal');
      const cancelDeleteStudentButton = document.querySelector('#cancelDeleteStudentButton');
      const confirmDeleteStudentButton = document.querySelector('#confirmDeleteStudentButton');
      const deleteDocumentModal = document.querySelector('#deleteDocumentModal');
      const deleteDocumentName = document.querySelector('#deleteDocumentName');
      const deleteDocumentMeta = document.querySelector('#deleteDocumentMeta');
      const closeDeleteDocumentModal = document.querySelector('#closeDeleteDocumentModal');
      const cancelDeleteDocumentButton = document.querySelector('#cancelDeleteDocumentButton');
      const confirmDeleteDocumentButton = document.querySelector('#confirmDeleteDocumentButton');
      const recycleBinButton = document.querySelector('#recycleBinButton');
      const recycleBinModal = document.querySelector('#recycleBinModal');
      const recycleBinList = document.querySelector('#recycleBinList');
      const closeRecycleBinModal = document.querySelector('#closeRecycleBinModal');
      const purgeStudentModal = document.querySelector('#purgeStudentModal');
      const purgeStudentName = document.querySelector('#purgeStudentName');
      const purgeStudentMeta = document.querySelector('#purgeStudentMeta');
      const closePurgeStudentModal = document.querySelector('#closePurgeStudentModal');
      const cancelPurgeStudentButton = document.querySelector('#cancelPurgeStudentButton');
      const confirmPurgeStudentButton = document.querySelector('#confirmPurgeStudentButton');
      const purgeDocumentModal = document.querySelector('#purgeDocumentModal');
      const purgeDocumentName = document.querySelector('#purgeDocumentName');
      const purgeDocumentMeta = document.querySelector('#purgeDocumentMeta');
      const closePurgeDocumentModal = document.querySelector('#closePurgeDocumentModal');
      const cancelPurgeDocumentButton = document.querySelector('#cancelPurgeDocumentButton');
      const confirmPurgeDocumentButton = document.querySelector('#confirmPurgeDocumentButton');
      const toast = document.querySelector('#adminToast');
      let activeYearStudents = [];
      let availableYears = [];
      let studentSortColumn = 'name';
      let studentSortDirection = 'asc';

      const formatStudentName = (student) => {
        const lastName = String(student.last_name || '').trim();
        const givenNames = [student.first_name, student.middle_initial]
          .map((name) => String(name || '').trim())
          .filter(Boolean)
          .join(' ');
        return lastName ? `${lastName}, ${givenNames}` : givenNames || 'Unknown student';
      };

      const compareLastNames = (left, right) => String(left.last_name || '').localeCompare(String(right.last_name || ''), undefined, { sensitivity: 'base' });

      const compareStudents = (left, right) => {
        let result = 0;
        if (studentSortColumn === 'student_id') {
          const idA = String(left.student_number || '').toLowerCase();
          const idB = String(right.student_number || '').toLowerCase();
          result = idA < idB ? -1 : idA > idB ? 1 : 0;
        } else if (studentSortColumn === 'academic_year') {
          const yearA = Number(left.academic_year) || 0;
          const yearB = Number(right.academic_year) || 0;
          result = yearA - yearB;
        } else {
          result = compareLastNames(left, right);
        }
        return studentSortDirection === 'desc' ? -result : result;
      };

      const renderSortableHeader = (key, label) => {
        const isActive = studentSortColumn === key;
        const icon = isActive ? (studentSortDirection === 'asc' ? 'chevron-up' : 'chevron-down') : 'chevrons-up-down';
        return `<th class="sortable-th"><button type="button" class="sort-th${isActive ? ' active' : ''}" data-sort-key="${key}" aria-label="Sort by ${label}">${label} <i data-lucide="${icon}"></i></button></th>`;
      };

      const showToast = (message) => {
        if (!toast) return;
        toast.textContent = message;
        toast.classList.add('show');
        window.setTimeout(() => toast.classList.remove('show'), 3000);
      };

      const renderEmptyState = (message) => {
        yearStudentList.innerHTML = `<div class="year-empty-state">${message}</div>`;
      };

      const YEAR_MIN = 1800;
      const YEAR_MAX = 9999;

      const setFolderMessage = (text) => {
        folderModalMessage.querySelector('span').textContent = text || '';
        folderModalMessage.hidden = !text;
        folderYearStepper?.classList.toggle('invalid', Boolean(text));
      };

      const existingYears = () => new Set(availableYears.map((entry) => String(entry.year)));

      const markSelectedChip = () => {
        const current = folderYearInput.value.trim();
        folderYearSuggestions?.querySelectorAll('.year-chip').forEach((chip) => {
          chip.classList.toggle('selected', chip.dataset.year === current);
        });
      };

      const renderYearSuggestions = () => {
        if (!folderYearSuggestions) return;
        const taken = existingYears();
        const thisYear = new Date().getFullYear();
        folderYearSuggestions.replaceChildren(...[thisYear - 1, thisYear, thisYear + 1]
          .filter((year) => year >= YEAR_MIN && year <= YEAR_MAX)
          .map((year) => {
            const chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'year-chip';
            chip.dataset.year = String(year);
            chip.textContent = String(year);
            if (taken.has(String(year))) {
              chip.disabled = true;
              chip.title = `${year} already has a folder`;
            }
            return chip;
          }));
        markSelectedChip();
      };

      const stepYear = (delta) => {
        const current = Number(folderYearInput.value.trim()) || new Date().getFullYear();
        const next = Math.min(YEAR_MAX, Math.max(YEAR_MIN, current + delta));
        folderYearInput.value = String(next);
        setFolderMessage('');
        markSelectedChip();
      };

      const openFolderModal = () => {
        folderModal.hidden = false;
        setFolderMessage('');
        folderYearInput.value = '';
        renderYearSuggestions();
        if (window.lucide) lucide.createIcons();
        folderYearInput.focus();
      };

      const closeFolderForm = () => {
        folderModal.hidden = true;
        setFolderMessage('');
        folderForm.reset();
      };

      addFolderButton?.addEventListener('click', openFolderModal);
      closeFolderModal?.addEventListener('click', closeFolderForm);
      cancelFolderButton?.addEventListener('click', closeFolderForm);
      folderModal?.addEventListener('click', (event) => {
        if (event.target === folderModal) closeFolderForm();
      });
      document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        if (purgeDocumentModal && !purgeDocumentModal.hidden) return closePurgeDocument();
        if (purgeStudentModal && !purgeStudentModal.hidden) return closePurgeStudent();
        if (deleteDocumentModal && !deleteDocumentModal.hidden) return closeDeleteDocument();
        if (recycleBinModal && !recycleBinModal.hidden) return closeRecycleBin();
        if (deleteStudentModal && !deleteStudentModal.hidden) return closeDeleteStudent();
        if (confirmDeleteFolderModal && !confirmDeleteFolderModal.hidden) return closeConfirmDeleteFolder();
        if (deleteFolderModal && !deleteFolderModal.hidden) return closeDeleteFolder();
        if (folderModal && !folderModal.hidden) closeFolderForm();
      });

      folderForm?.addEventListener('click', (event) => {
        const step = event.target.closest('.year-step');
        if (step) return stepYear(Number(step.dataset.yearStep));
        const chip = event.target.closest('.year-chip');
        if (!chip || chip.disabled) return;
        folderYearInput.value = chip.dataset.year;
        setFolderMessage('');
        markSelectedChip();
      });

      folderYearInput?.addEventListener('input', () => {
        setFolderMessage('');
        markSelectedChip();
      });

      folderForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const year = folderYearInput.value.trim();
        const numericYear = Number(year);
        if (!/^\d{4}$/.test(year) || !Number.isInteger(numericYear) || numericYear < YEAR_MIN || numericYear > YEAR_MAX) {
          setFolderMessage('Enter a valid year between 1800 and 2100.');
          folderYearInput.focus();
          return;
        }
        if (existingYears().has(String(numericYear))) {
          setFolderMessage(`${numericYear} already has a folder.`);
          folderYearInput.focus();
          return;
        }

        try {
          const response = await fetch('../api.php?resource=enrollment-years', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ year: String(numericYear) })
          });
          const result = await response.json().catch(() => ({}));
          if (!response.ok) {
            setFolderMessage(result.error || 'Unable to save the year folder.');
            return;
          }
          closeFolderForm();
          showToast('Year folder created successfully.');
          await loadYears();
        } catch (error) {
          setFolderMessage('Unable to reach the server. Check that Apache and MariaDB are running.');
        }
      });

      /* Deleting a year folder ---------------------------------------------
         Folder deletion soft-deletes every active student in the year. Their
         documents stay attached and recoverable until the student is purged. */
      let folderPendingDelete = null;

      const setDeleteNote = (text, tone) => {
        deleteFolderNote.className = `delete-folder-note ${tone}`;
        deleteFolderNote.querySelector('span').textContent = text;
      };

      const openDeleteFolder = (folder) => {
        folderPendingDelete = folder;
        const { year, studentCount, documentCount } = folder;

        deleteFolderYear.textContent = year;
        deleteFolderCounts.textContent = `${studentCount.toLocaleString()} student ${studentCount === 1 ? 'record' : 'records'} · ${documentCount.toLocaleString()} ${documentCount === 1 ? 'file' : 'files'}`;
        setDeleteNote(
          `${year} will be removed and its ${studentCount.toLocaleString()} student ${studentCount === 1 ? 'record' : 'records'} moved to the Recycle Bin. Their files will remain recoverable until you choose Delete permanently.`,
          'warning'
        );

        confirmDeleteFolderButton.disabled = false;
        deleteFolderModal.hidden = false;
        if (window.lucide) lucide.createIcons();
        cancelDeleteFolderButton.focus();
      };

      deleteYearButton?.addEventListener('click', () => {
        const year = enrollmentYearFilter.value;
        const folder = availableYears.find((entry) => String(entry.year) === String(year));
        if (folder) openDeleteFolder({
          year: String(folder.year),
          studentCount: Number(folder.student_count || 0),
          documentCount: Number(folder.document_count || 0)
        });
      });

      const openDeleteFolderConfirmation = () => {
        if (!folderPendingDelete) return;
        const { year, studentCount, documentCount } = folderPendingDelete;
        deleteFolderModal.hidden = true;
        confirmDeleteFolderYear.textContent = year;
        confirmDeleteFolderQuestion.textContent = `Delete the ${year} folder?`;
        confirmDeleteFolderCounts.textContent = `${studentCount.toLocaleString()} student ${studentCount === 1 ? 'record' : 'records'} · ${documentCount.toLocaleString()} ${documentCount === 1 ? 'file' : 'files'}`;
        confirmDeleteFolderNote.querySelector('span').textContent = 'This action moves all students in this year to the Recycle Bin. Their files remain recoverable until you choose Delete permanently.';
        confirmDeleteFolderModal.hidden = false;
        if (window.lucide) lucide.createIcons();
        cancelConfirmDeleteFolderButton.focus();
      };

      const closeDeleteFolder = () => {
        deleteFolderModal.hidden = true;
        confirmDeleteFolderButton.disabled = false;
        folderPendingDelete = null;
      };

      const closeConfirmDeleteFolder = (returnToOverview = true) => {
        confirmDeleteFolderModal.hidden = true;
        confirmDeleteFolderButton.disabled = false;
        if (returnToOverview && folderPendingDelete) {
          deleteFolderModal.hidden = false;
          openDeleteFolderConfirmationButton.focus();
        }
      };

      closeDeleteFolderModal?.addEventListener('click', closeDeleteFolder);
      cancelDeleteFolderButton?.addEventListener('click', closeDeleteFolder);
      deleteFolderModal?.addEventListener('click', (event) => {
        if (event.target === deleteFolderModal) closeDeleteFolder();
      });
      openDeleteFolderConfirmationButton?.addEventListener('click', openDeleteFolderConfirmation);
      closeConfirmDeleteFolderModal?.addEventListener('click', () => closeConfirmDeleteFolder());
      cancelConfirmDeleteFolderButton?.addEventListener('click', () => closeConfirmDeleteFolder());
      confirmDeleteFolderModal?.addEventListener('click', (event) => {
        if (event.target === confirmDeleteFolderModal) closeConfirmDeleteFolder();
      });

      confirmDeleteFolderButton?.addEventListener('click', async () => {
        if (!folderPendingDelete) return;
        const { year } = folderPendingDelete;
        confirmDeleteFolderButton.disabled = true;

        try {
          const response = await fetch('../api.php?resource=enrollment-years', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ action: 'delete', year: String(year) })
          });
          const result = await response.json().catch(() => ({}));
          if (!response.ok) {
            confirmDeleteFolderNote.querySelector('span').textContent = result.error || 'Unable to delete this year folder.';
            if (window.lucide) lucide.createIcons();
            confirmDeleteFolderButton.disabled = false;
            return;
          }

          /* The open list below the grid belongs to the folder that just went
             away, so drop the selection and let loadYears() pick the next one. */
          if (String(selectedYearTitle.dataset.year || '') === String(year)) {
            delete selectedYearTitle.dataset.year;
            selectedYearTitle.textContent = 'All students';
            renderEmptyState('No enrollment-year folder selected.');
          }

          closeConfirmDeleteFolder(false);
          closeDeleteFolder();
          const movedCount = Number(result.data?.students_moved || 0);
          showToast(`Year folder ${year} deleted. ${movedCount.toLocaleString()} student ${movedCount === 1 ? 'record' : 'records'} moved to the Recycle Bin.`);
          await loadYears();
        } catch (error) {
          confirmDeleteFolderNote.querySelector('span').textContent = 'Unable to reach the server. Check that Apache and MariaDB are running.';
          if (window.lucide) lucide.createIcons();
          confirmDeleteFolderButton.disabled = false;
        }
      });
      /* Removing a student -------------------------------------------------
         Deleting a student also deletes their archived files server-side, so
         the confirmation dialog says so up front rather than surprising the
         user after the fact. */
      let studentPendingDelete = null;

      const openDeleteStudent = (student) => {
        studentPendingDelete = student;
        const fullName = formatStudentName(student);
        deleteStudentName.textContent = fullName;
        deleteStudentMeta.textContent = `${student.student_number || '—'} · Enrollment year ${student.academic_year || '—'}`;
        confirmDeleteStudentButton.disabled = false;
        deleteStudentModal.hidden = false;
        if (window.lucide) lucide.createIcons();
        cancelDeleteStudentButton.focus();
      };

      const closeDeleteStudent = () => {
        deleteStudentModal.hidden = true;
        confirmDeleteStudentButton.disabled = false;
        studentPendingDelete = null;
      };

      closeDeleteStudentModal?.addEventListener('click', closeDeleteStudent);
      cancelDeleteStudentButton?.addEventListener('click', closeDeleteStudent);
      deleteStudentModal?.addEventListener('click', (event) => {
        if (event.target === deleteStudentModal) closeDeleteStudent();
      });

      confirmDeleteStudentButton?.addEventListener('click', async () => {
        if (!studentPendingDelete) return;
        const student = studentPendingDelete;
        confirmDeleteStudentButton.disabled = true;

        try {
          const response = await fetch('../api.php?resource=students', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ action: 'delete', student_id: student.id }),
          });
          const result = await response.json().catch(() => ({}));
          if (!response.ok) {
            showToast(result.error || 'Unable to remove this student.');
            confirmDeleteStudentButton.disabled = false;
            return;
          }

          closeDeleteStudent();
          showToast(`${student.first_name || ''} ${student.last_name || ''}`.trim() + ' moved to the Recycle Bin.');
          const year = selectedYearTitle.dataset.year;
          if (year) await selectYear(year);
          await loadYears();
        } catch (error) {
          showToast('Unable to reach the server. Check that Apache and MariaDB are running.');
          confirmDeleteStudentButton.disabled = false;
        }
      });

      /* Recycle bin ----------------------------------------------------------
         Removing a student is a soft delete server-side (see delete_student()
         in api.php): the row and its files stay on disk with deleted_at set,
         so they can be restored here or purged for good. */
      const formatDeletedAt = (value) => {
        if (!value) return 'Unknown date';
        const parsed = new Date(String(value).replace(' ', 'T'));
        return Number.isNaN(parsed.getTime()) ? 'Unknown date' : parsed.toLocaleString();
      };

      const renderRecycleBinList = (students, documents) => {
        if (!students.length && !documents.length) {
          recycleBinList.innerHTML = '<div class="year-empty-state">Recycle bin is empty.</div>';
          return;
        }

        const documentSection = documents.length ? `
          <div class="student-year-table-wrap">
            <h3>Documents</h3>
            <table class="student-year-table">
              <thead>
                <tr><th>Document</th><th>Student</th><th>School Year</th><th>Removed</th><th>Action</th></tr>
              </thead>
              <tbody>
                ${documents.map((document) => `
                  <tr>
                    <td><a href="student-record-view.php?student_id=${encodeURIComponent(document.student_id || '')}&document_id=${encodeURIComponent(document.id || '')}" target="_blank" rel="noopener"><strong>${escapeHtml(document.original_filename || 'Uploaded file')}</strong></a><small>${escapeHtml(document.category_name || 'Uncategorized')}</small></td>
                    <td>${escapeHtml(document.student_name || 'Unknown student')}<br /><small>${escapeHtml(document.student_number || '—')}</small></td>
                    <td>${document.academic_year || '—'}</td>
                    <td>${formatDeletedAt(document.deleted_at)}</td>
                    <td>
                      <div class="recycle-row-actions">
                        <button class="view-files-btn" type="button" data-restore-document-id="${document.id || ''}"><i data-lucide="rotate-ccw"></i> Restore</button>
                        <button class="view-files-btn recycle-document-delete" type="button" data-purge-document-id="${document.id || ''}"><i data-lucide="trash-2"></i> Delete</button>
                      </div>
                    </td>
                  </tr>
                `).join('')}
              </tbody>
            </table>
          </div>
        ` : '';

        recycleBinList.innerHTML = `${students.length ? `
          <div class="student-year-table-wrap">
            <table class="student-year-table">
              <thead>
                <tr>
                  <th>Student</th>
                  <th>Student ID</th>
                  <th>Removed</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                ${students.map((student) => {
                  const fullName = `${student.first_name || ''} ${student.last_name || ''}`.trim() || 'Unknown student';
                  const initials = `${(student.first_name || 'U').charAt(0)}${(student.last_name || 'S').charAt(0)}`.toUpperCase();
                  return `
                    <tr>
                      <td>
                        <div class="student-name-cell">
                          <span class="student-mini-avatar">${escapeHtml(initials)}</span>
                          <div class="student-meta">
                            <strong>${escapeHtml(fullName)}</strong>
                            <small>Enrollment year ${student.academic_year || '—'}</small>
                          </div>
                        </div>
                      </td>
                      <td>${escapeHtml(student.student_number || '—')}</td>
                      <td>${formatDeletedAt(student.deleted_at)}</td>
                      <td>
                        <div class="recycle-row-actions">
                          <button class="view-files-btn" type="button" data-restore-id="${student.id || ''}"><i data-lucide="rotate-ccw"></i> Restore</button>
                          <button class="student-row-delete" type="button" aria-label="Delete ${escapeHtml(fullName)} forever" title="Delete forever" data-purge-id="${student.id || ''}"><i data-lucide="trash-2"></i></button>
                        </div>
                      </td>
                    </tr>
                  `;
                }).join('')}
              </tbody>
            </table>
          </div>
        ` : ''}${documentSection}`;

        recycleBinList.querySelectorAll('[data-restore-id]').forEach((button) => {
          button.addEventListener('click', () => restoreStudent(Number(button.dataset.restoreId)));
        });
        recycleBinList.querySelectorAll('[data-purge-id]').forEach((button) => {
          button.addEventListener('click', () => {
            const student = recycleBinStudents.find((entry) => Number(entry.id) === Number(button.dataset.purgeId));
            if (student) openPurgeStudent(student);
          });
        });
        recycleBinList.querySelectorAll('[data-restore-document-id]').forEach((button) => {
          button.addEventListener('click', () => restoreDocument(Number(button.dataset.restoreDocumentId)));
        });
        recycleBinList.querySelectorAll('[data-purge-document-id]').forEach((button) => {
          button.addEventListener('click', () => {
            const document = recycleBinDocuments.find((entry) => Number(entry.id) === Number(button.dataset.purgeDocumentId));
            if (document) openPurgeDocument(document);
          });
        });

        if (window.lucide) lucide.createIcons();
      };

      let recycleBinStudents = [];
      let recycleBinDocuments = [];

      const loadRecycleBin = async () => {
        recycleBinList.innerHTML = '<div class="year-empty-state">Loading recycle bin...</div>';
        try {
          const [studentResponse, documentResponse] = await Promise.all([
            fetch('../api.php?resource=deleted-students', { credentials: 'same-origin' }),
            fetch('../api.php?resource=deleted-documents', { credentials: 'same-origin' }),
          ]);
          if (!studentResponse.ok || !documentResponse.ok) throw new Error('Unable to load recycle bin');
          const [studentResult, documentResult] = await Promise.all([studentResponse.json(), documentResponse.json()]);
          recycleBinStudents = studentResult.data || [];
          recycleBinDocuments = documentResult.data || [];
          renderRecycleBinList(recycleBinStudents, recycleBinDocuments);
        } catch (error) {
          recycleBinList.innerHTML = '<div class="year-empty-state">Unable to load the recycle bin.</div>';
        }
      };

      const openRecycleBin = () => {
        recycleBinModal.hidden = false;
        loadRecycleBin();
      };

      const closeRecycleBin = () => {
        recycleBinModal.hidden = true;
      };

      recycleBinButton?.addEventListener('click', openRecycleBin);
      closeRecycleBinModal?.addEventListener('click', closeRecycleBin);
      recycleBinModal?.addEventListener('click', (event) => {
        if (event.target === recycleBinModal) closeRecycleBin();
      });

      const restoreStudent = async (studentId) => {
        try {
          const response = await fetch('../api.php?resource=students', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ action: 'restore', student_id: studentId }),
          });
          const result = await response.json().catch(() => ({}));
          if (!response.ok) {
            showToast(result.error || 'Unable to restore this student.');
            return;
          }

          showToast(`${result.data?.first_name || ''} ${result.data?.last_name || ''}`.trim() + ' restored.');
          await loadRecycleBin();
          await loadYears();
          const year = selectedYearTitle.dataset.year;
          if (year) await selectYear(year);
        } catch (error) {
          showToast('Unable to reach the server. Check that Apache and MariaDB are running.');
        }
      };

      const restoreDocument = async (documentId) => {
        try {
          const response = await fetch('../api.php?resource=documents', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ action: 'restore', document_id: documentId }),
          });
          const result = await response.json().catch(() => ({}));
          if (!response.ok) {
            showToast(result.error || 'Unable to restore this document.');
            return;
          }

          showToast(`${result.data?.original_filename || 'Document'} restored.`);
          await loadRecycleBin();
          await loadYears();
          const year = selectedYearTitle.dataset.year;
          if (year) await selectYear(year);
        } catch (error) {
          showToast('Unable to reach the server. Check that Apache and MariaDB are running.');
        }
      };

      let documentPendingPurge = null;

      const openPurgeDocument = (document) => {
        documentPendingPurge = document;
        purgeDocumentName.textContent = document.original_filename || 'Uploaded file';
        purgeDocumentMeta.textContent = `${document.student_name || 'Unknown student'} · ${document.student_number || '—'} · Removed ${formatDeletedAt(document.deleted_at)}`;
        confirmPurgeDocumentButton.disabled = false;
        purgeDocumentModal.hidden = false;
        if (window.lucide) lucide.createIcons();
        cancelPurgeDocumentButton.focus();
      };

      const closePurgeDocument = () => {
        purgeDocumentModal.hidden = true;
        confirmPurgeDocumentButton.disabled = false;
        documentPendingPurge = null;
      };

      closePurgeDocumentModal?.addEventListener('click', closePurgeDocument);
      cancelPurgeDocumentButton?.addEventListener('click', closePurgeDocument);
      purgeDocumentModal?.addEventListener('click', (event) => {
        if (event.target === purgeDocumentModal) closePurgeDocument();
      });

      confirmPurgeDocumentButton?.addEventListener('click', async () => {
        if (!documentPendingPurge) return;
        const document = documentPendingPurge;
        confirmPurgeDocumentButton.disabled = true;

        try {
          const response = await fetch('../api.php?resource=documents', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ action: 'purge', document_id: document.id }),
          });
          const result = await response.json().catch(() => ({}));
          if (!response.ok) {
            showToast(result.error || 'Unable to permanently delete this document.');
            confirmPurgeDocumentButton.disabled = false;
            return;
          }

          closePurgeDocument();
          showToast(`${document.original_filename || 'Document'} deleted forever.`);
          await loadRecycleBin();
        } catch (error) {
          showToast('Unable to reach the server. Check that Apache and MariaDB are running.');
          confirmPurgeDocumentButton.disabled = false;
        }
      });

      let studentPendingPurge = null;

      const openPurgeStudent = (student) => {
        studentPendingPurge = student;
        const fullName = `${student.first_name || ''} ${student.last_name || ''}`.trim() || 'Unknown student';
        purgeStudentName.textContent = fullName;
        purgeStudentMeta.textContent = `${student.student_number || '—'} · Removed ${formatDeletedAt(student.deleted_at)}`;
        confirmPurgeStudentButton.disabled = false;
        purgeStudentModal.hidden = false;
        if (window.lucide) lucide.createIcons();
        cancelPurgeStudentButton.focus();
      };

      const closePurgeStudent = () => {
        purgeStudentModal.hidden = true;
        confirmPurgeStudentButton.disabled = false;
        studentPendingPurge = null;
      };

      closePurgeStudentModal?.addEventListener('click', closePurgeStudent);
      cancelPurgeStudentButton?.addEventListener('click', closePurgeStudent);
      purgeStudentModal?.addEventListener('click', (event) => {
        if (event.target === purgeStudentModal) closePurgeStudent();
      });

      confirmPurgeStudentButton?.addEventListener('click', async () => {
        if (!studentPendingPurge) return;
        const student = studentPendingPurge;
        confirmPurgeStudentButton.disabled = true;

        try {
          const response = await fetch('../api.php?resource=students', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ action: 'purge', student_id: student.id }),
          });
          const result = await response.json().catch(() => ({}));
          if (!response.ok) {
            showToast(result.error || 'Unable to permanently delete this student.');
            confirmPurgeStudentButton.disabled = false;
            return;
          }

          closePurgeStudent();
          showToast(`${student.first_name || ''} ${student.last_name || ''}`.trim() + ' deleted forever.');
          await loadRecycleBin();
        } catch (error) {
          showToast('Unable to reach the server. Check that Apache and MariaDB are running.');
          confirmPurgeStudentButton.disabled = false;
        }
      });

      const loadYears = async () => {
        try {
          const response = await fetch('../api.php?resource=enrollment-years', { credentials: 'same-origin' });
          if (!response.ok) throw new Error('Unable to load years');
          const result = await response.json();
          availableYears = (result.data || []).sort((a, b) => Number(b.year) - Number(a.year));
          const previousYear = selectedYearTitle.dataset.year;
          enrollmentYearFilter.replaceChildren(
            new Option('All enrollment years', 'ALL'),
            ...availableYears.map((entry) => new Option(String(entry.year), String(entry.year)))
          );
          const canKeepYear = previousYear === 'ALL' || availableYears.some((entry) => String(entry.year) === String(previousYear));
          const year = canKeepYear ? previousYear : previousYear ? String(availableYears[0]?.year || 'ALL') : 'ALL';
          await selectYear(year);
        } catch (error) {
          enrollmentYearFilter.replaceChildren(new Option('Unable to load enrollment years', ''));
          enrollmentYearFilter.disabled = true;
          deleteYearButton.disabled = true;
          renderEmptyState('Unable to load enrollment years.');
        }
      };

      const formatFileSize = (bytes) => {
        if (!Number.isFinite(Number(bytes)) || Number(bytes) <= 0) return '0 KB';
        const value = Number(bytes);
        const units = ['B', 'KB', 'MB', 'GB'];
        let size = value;
        let unitIndex = 0;
        while (size >= 1024 && unitIndex < units.length - 1) {
          size /= 1024;
          unitIndex += 1;
        }
        return `${size.toFixed(size >= 10 || unitIndex === 0 ? 0 : 1)} ${units[unitIndex]}`;
      };

      const formatViewLabel = (fileName = '') => {
        const value = String(fileName || '').trim();
        if (!value) return 'View';
        const cleaned = value.replace(/\.[a-z0-9]+$/i, '');
        return cleaned.length > 14 ? `View ${cleaned.slice(0, 12)}...` : `View ${cleaned}`;
      };

      const getFileTypeIcon = (mimeType = '', fileName = '') => {
        const lowerMime = String(mimeType || '').toLowerCase();
        const lowerName = String(fileName || '').toLowerCase();
        if (lowerMime.includes('pdf') || lowerName.endsWith('.pdf')) return 'file-text';
        if (lowerMime.includes('image') || /\.(png|jpg|jpeg|gif|webp|bmp|svg)$/.test(lowerName)) return 'image';
        if (lowerMime.includes('word') || /\.(doc|docx|odt)$/.test(lowerName)) return 'file-type';
        if (lowerMime.includes('excel') || /\.(xls|xlsx|csv)$/.test(lowerName)) return 'sheet';
        return 'file';
      };

      const downloadBlob = (blob, filename) => {
        const objectUrl = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = objectUrl;
        link.download = filename || 'archive.zip';
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
      };

      const downloadStudentZip = async (studentId, options = {}) => {
        const selectedIds = Array.isArray(options.documentIds) ? options.documentIds.filter((id) => Number(id) > 0).map((id) => Number(id)) : [];
        const payload = {
          student_id: Number(studentId),
          download_all: Boolean(options.downloadAll),
          document_ids: selectedIds,
        };

        try {
          const response = await fetch('../api.php?resource=document-download-bulk', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
          });

          if (!response.ok) {
            const message = await response.text();
            throw new Error(message || 'Unable to create the ZIP package.');
          }

          const blob = await response.blob();
          const contentDisposition = response.headers.get('Content-Disposition') || '';
          const filenameMatch = /filename\*=UTF-8''([^;]+)|filename="?([^";]+)"?/i.exec(contentDisposition);
          const filename = (filenameMatch ? decodeURIComponent((filenameMatch[1] || filenameMatch[2] || '').replace(/['"]/g, '')) : 'student_files.zip') || 'student_files.zip';
          downloadBlob(blob, filename);
        } catch (error) {
          showToast(error.message || 'Unable to download the selected files.');
        }
      };

      const syncBulkSelectionState = () => {
        const checkboxes = studentFilesList.querySelectorAll('.student-file-checkbox');
        const selectAll = studentFilesList.querySelector('#selectAllStudentFiles');
        if (!selectAll || !checkboxes.length) {
          return;
        }
        const selectedCount = [...checkboxes].filter((checkbox) => checkbox.checked).length;
        selectAll.checked = selectedCount === checkboxes.length;
        selectAll.indeterminate = selectedCount > 0 && selectedCount < checkboxes.length;
      };

      const bindBulkFileActions = (studentId) => {
        const selectAll = studentFilesList.querySelector('#selectAllStudentFiles');
        if (selectAll) {
          selectAll.addEventListener('change', () => {
            studentFilesList.querySelectorAll('.student-file-checkbox').forEach((checkbox) => {
              checkbox.checked = selectAll.checked;
            });
          });
        }

        studentFilesList.querySelectorAll('.student-file-checkbox').forEach((checkbox) => {
          checkbox.addEventListener('change', syncBulkSelectionState);
        });

        const selectedButton = studentFilesList.querySelector('[data-bulk-action="selected"]');
        if (selectedButton) {
          selectedButton.addEventListener('click', async () => {
            const selectedIds = [...studentFilesList.querySelectorAll('.student-file-checkbox:checked')].map((checkbox) => Number(checkbox.value)).filter((id) => Number.isFinite(id) && id > 0);
            if (!selectedIds.length) {
              showToast('Select at least one file to download.');
              return;
            }
            await downloadStudentZip(studentId, { documentIds: selectedIds, downloadAll: false });
          });
        }

        const allButton = studentFilesList.querySelector('[data-bulk-action="all"]');
        if (allButton) {
          allButton.addEventListener('click', async () => {
            await downloadStudentZip(studentId, { downloadAll: true });
          });
        }
      };

      let documentPendingDelete = null;

      const openDeleteDocument = (document) => {
        documentPendingDelete = document;
        deleteDocumentName.textContent = document.original_filename || 'Uploaded file';
        deleteDocumentMeta.textContent = `${document.studentName || 'Unknown student'} · ${document.academicYear || 'Unknown year'}`;
        confirmDeleteDocumentButton.disabled = false;
        deleteDocumentModal.hidden = false;
        if (window.lucide) lucide.createIcons();
        cancelDeleteDocumentButton.focus();
      };

      const closeDeleteDocument = () => {
        deleteDocumentModal.hidden = true;
        confirmDeleteDocumentButton.disabled = false;
        documentPendingDelete = null;
      };

      closeDeleteDocumentModal?.addEventListener('click', closeDeleteDocument);
      cancelDeleteDocumentButton?.addEventListener('click', closeDeleteDocument);
      deleteDocumentModal?.addEventListener('click', (event) => {
        if (event.target === deleteDocumentModal) closeDeleteDocument();
      });

      confirmDeleteDocumentButton?.addEventListener('click', async () => {
        if (!documentPendingDelete) return;
        const document = documentPendingDelete;
        confirmDeleteDocumentButton.disabled = true;

        try {
          const response = await fetch('../api.php?resource=documents', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ action: 'delete', document_id: document.id }),
          });
          const result = await response.json().catch(() => ({}));
          if (!response.ok) {
            showToast(result.error || 'Unable to move this document to the Recycle Bin.');
            confirmDeleteDocumentButton.disabled = false;
            return;
          }

          closeDeleteDocument();
          showToast(`${document.original_filename || 'Document'} moved to the Recycle Bin.`);
          await openStudentFilesModal(document.studentId, document.studentName, document.studentNumber, document.year);
        } catch (error) {
          showToast('Unable to reach the server. Check that Apache and MariaDB are running.');
          confirmDeleteDocumentButton.disabled = false;
        }
      });

      const openStudentFilesModal = async (studentId, studentName, studentNumber, year) => {
        if (!studentId) return;
        const isAllYears = String(year) === 'ALL';
        studentFilesTitle.textContent = 'Student files';
        studentFilesName.textContent = studentName || 'Unknown student';
        studentFilesId.textContent = studentNumber || '—';
        studentFilesYear.textContent = isAllYears ? 'All school years' : (year || '—');
        studentFilesList.innerHTML = '<div class="student-files-empty">Loading files...</div>';
        studentFilesModal.hidden = false;

        try {
          const response = await fetch(`../api.php?resource=documents&student_id=${encodeURIComponent(studentId)}`, { credentials: 'same-origin' });
          const result = await response.json().catch(() => ({}));
          if (!response.ok) {
            throw new Error(result.error || 'Unable to load student files.');
          }

          const files = (result.data || [])
            .filter((file) => isAllYears || String(file.academic_year || '').trim() === String(year))
            .sort((left, right) => {
              const leftGroup = (left.category_name || 'Uncategorized').toLowerCase();
              const rightGroup = (right.category_name || 'Uncategorized').toLowerCase();
              if (leftGroup < rightGroup) return -1;
              if (leftGroup > rightGroup) return 1;
              const leftName = (left.original_filename || '').toLowerCase();
              const rightName = (right.original_filename || '').toLowerCase();
              if (leftName < rightName) return -1;
              if (leftName > rightName) return 1;
              return 0;
            });

          if (!files.length) {
            studentFilesList.innerHTML = `<div class="student-files-empty">No files were uploaded for this student${isAllYears ? '' : ' in this enrollment year'}.</div>`;
            return;
          }

          const grouped = files.reduce((map, file) => {
            const categoryName = file.category_name || 'Uncategorized';
            if (!map[categoryName]) {
              map[categoryName] = [];
            }
            map[categoryName].push(file);
            return map;
          }, {});

          const groups = Object.entries(grouped).sort(([left], [right]) => left.localeCompare(right));
          const buildPreviewContent = (file) => {
            if (!file) {
              return `<div class="student-preview-placeholder"><i data-lucide="image"></i><span>Select a file to preview</span></div>`;
            }

            const fileName = file.original_filename || 'Uploaded file';
            const fileType = file.mime_type || 'Unknown';
            const previewUrl = file.id ? `../api.php?resource=document-view&student_id=${encodeURIComponent(studentId)}&document_id=${encodeURIComponent(file.id)}` : '';
            const lowerType = String(fileType || '').toLowerCase();
            const lowerName = String(fileName || '').toLowerCase();
            const isImage = lowerType.includes('image') || /\.(png|jpg|jpeg|gif|webp|bmp|svg)$/.test(lowerName);
            const isPdf = lowerType.includes('pdf') || lowerName.endsWith('.pdf');

            if (isImage && previewUrl) {
              return `<img src="${previewUrl}" alt="${escapeHtml(fileName)}" />`;
            }

            if (isPdf && previewUrl) {
              return `<iframe src="${previewUrl}" title="${escapeHtml(fileName)}"></iframe>`;
            }

            return `
              <div class="student-preview-placeholder">
                <i data-lucide="file-text"></i>
                <strong>${escapeHtml(fileName)}</strong>
                <span>Preview is not available for this file type. You can still download it.</span>
              </div>
            `;
          };

          const renderSelectedFileDetails = (file) => {
            const previewPanel = studentFilesList.querySelector('.student-file-preview');
            const detailPanel = studentFilesList.querySelector('.student-file-detail-panel');
            if (!previewPanel || !detailPanel) return;

            previewPanel.innerHTML = buildPreviewContent(file);
            const fileName = file.original_filename || 'Uploaded file';
            const fileType = file.mime_type || 'Unknown';
            const uploadDate = file.uploaded_at ? new Date(file.uploaded_at.replace(' ', 'T')).toLocaleString() : 'Unknown date';
            const fileSize = formatFileSize(file.file_size_bytes);
            const previewUrl = file.id ? `../api.php?resource=document-view&student_id=${encodeURIComponent(studentId)}&document_id=${encodeURIComponent(file.id)}` : '#';

            detailPanel.innerHTML = `
              <h3>Student details</h3>
              <div class="student-detail-grid">
                <div class="student-detail-item"><span>Student name</span><strong>${escapeHtml(studentName || 'Unknown student')}</strong></div>
                <div class="student-detail-item"><span>Student ID</span><strong>${escapeHtml(studentNumber || '—')}</strong></div>
                <div class="student-detail-item"><span>Year</span><strong>${file.academic_year || (isAllYears ? 'All school years' : '—')}</strong></div>
                <div class="student-detail-item"><span>Course</span><strong>${fileType || '—'}</strong></div>
              </div>
            `;
            if (window.lucide) lucide.createIcons();
          };

          studentFilesList.innerHTML = `
            <div class="student-file-toolbar">
              <label class="bulk-select-toggle"><input id="selectAllStudentFiles" class="student-file-checkbox" type="checkbox" /> Select All</label>
              <div class="student-file-actions">
                <button type="button" class="outline-button" data-bulk-action="selected">Download Selected</button>
                <button type="button" class="gold-button" data-bulk-action="all">Download All</button>
              </div>
            </div>
            ${groups.map(([categoryName, categoryFiles]) => `
              <div class="student-file-group">
                <h3>${escapeHtml(categoryName)}</h3>
                ${categoryFiles.map((file) => {
                  const fileName = file.original_filename || 'Uploaded file';
                  const fileType = file.mime_type || 'Unknown';
                  const uploadDate = file.uploaded_at ? new Date(file.uploaded_at.replace(' ', 'T')).toLocaleString() : 'Unknown date';
                  const secureViewUrl = file.id ? `student-record-view.php?student_id=${encodeURIComponent(studentId)}&document_id=${encodeURIComponent(file.id)}` : '';
                  const secureDownloadUrl = file.id ? `../api.php?resource=document-download&student_id=${encodeURIComponent(studentId)}&document_id=${encodeURIComponent(file.id)}` : '';
                  const hasFile = !!secureViewUrl;
                  const iconName = getFileTypeIcon(file.mime_type, file.original_filename);
                  return `
                    <div class="student-file-item" data-file-name="${escapeHtml(fileName)}" data-file-type="${escapeHtml(fileType)}" data-file-size="${formatFileSize(file.file_size_bytes)}" data-file-uploaded="${escapeHtml(uploadDate)}" data-file-year="${file.academic_year || ''}" data-preview-url="${secureViewUrl}">
                      <div class="student-file-select"><input class="student-file-checkbox" type="checkbox" value="${file.id || ''}" aria-label="Select ${escapeHtml(fileName)}" /></div>
                      <div class="student-file-icon"><i data-lucide="${iconName}"></i></div>
                      <div class="student-file-details">
                        <strong>${escapeHtml(fileName)}</strong>
                        <small>Type: ${escapeHtml(fileType)}<br />Size: ${formatFileSize(file.file_size_bytes)}<br />Uploaded: ${escapeHtml(uploadDate)}</small>
                      </div>
                      <div class="student-file-actions">
                        <a class="${hasFile ? 'primary' : 'disabled'}" href="${hasFile ? secureViewUrl : '#'}" data-preview-url="${hasFile ? secureViewUrl : '#'}" aria-disabled="${hasFile ? 'false' : 'true'}">View</a>
                        <a class="${hasFile ? '' : 'disabled'}" href="${hasFile ? secureDownloadUrl : '#'}" download="${hasFile ? escapeHtml(fileName) : ''}" aria-disabled="${hasFile ? 'false' : 'true'}">Download</a>
                        <button type="button" class="outline-button student-file-delete" data-delete-document="${file.id || ''}">Delete</button>
                      </div>
                    </div>
                  `;
                }).join('')}
              </div>
            `).join('')}
          `;

          const selectedFileItem = studentFilesList.querySelector('.student-file-item');
          if (selectedFileItem) {
            const firstFileData = {
              original_filename: selectedFileItem.dataset.fileName,
              mime_type: selectedFileItem.dataset.fileType,
              file_size_bytes: selectedFileItem.dataset.fileSize,
              uploaded_at: selectedFileItem.dataset.fileUploaded,
              id: selectedFileItem.querySelector('.student-file-checkbox')?.value || '',
            };
            renderSelectedFileDetails(firstFileData);
            selectedFileItem.classList.add('selected');
          }

          studentFilesList.querySelectorAll('.student-file-item').forEach((item) => {
            item.addEventListener('click', (event) => {
              if (event.target.closest('input') || event.target.closest('a') || event.target.closest('button')) return;
              const fileData = {
                original_filename: item.dataset.fileName,
                mime_type: item.dataset.fileType,
                file_size_bytes: item.dataset.fileSize,
                uploaded_at: item.dataset.fileUploaded,
                id: item.querySelector('.student-file-checkbox')?.value || '',
              };
              renderSelectedFileDetails(fileData);
              studentFilesList.querySelectorAll('.student-file-item').forEach((entry) => entry.classList.toggle('selected', entry === item));
            });
          });

          studentFilesList.querySelectorAll('.student-file-actions a.primary').forEach((link) => {
            link.addEventListener('click', (event) => {
              const previewUrl = link.dataset.previewUrl || link.getAttribute('href') || '';
              if (!previewUrl || previewUrl === '#') {
                event.preventDefault();
                return;
              }
              const item = link.closest('.student-file-item');
              if (item) {
                item.click();
              }
            });
          });

          studentFilesList.querySelectorAll('[data-delete-document]').forEach((button) => {
            button.addEventListener('click', () => {
              const item = button.closest('.student-file-item');
              if (!item) return;
              openDeleteDocument({
                id: Number(button.dataset.deleteDocument || 0),
                original_filename: item.dataset.fileName,
                academicYear: item.dataset.fileYear,
                studentId: Number(studentId),
                studentName,
                studentNumber,
                year,
              });
            });
          });

          bindBulkFileActions(Number(studentId));
          syncBulkSelectionState();
          if (window.lucide) lucide.createIcons();
        } catch (error) {
          studentFilesList.innerHTML = `<div class="student-files-empty">${error.message}</div>`;
        }
      };

      closeStudentFilesModal?.addEventListener('click', () => {
        studentFilesModal.hidden = true;
        studentFilesList.innerHTML = '';
      });
      studentFilesModal?.addEventListener('click', (event) => {
        if (event.target === studentFilesModal) {
          studentFilesModal.hidden = true;
          studentFilesList.innerHTML = '';
        }
      });

      const getYearStudentMatches = (students, query) => {
        const searchTerm = query.trim().toLowerCase();
        if (!searchTerm) return students;
        return students.filter((student) => {
          const lastName = String(student.last_name || '').trim().toLowerCase();
          const fullName = formatStudentName(student).toLowerCase();
          const studentId = String(student.student_number || '').toLowerCase();
          return lastName.startsWith(searchTerm) || fullName === searchTerm || studentId.includes(searchTerm);
        });
      };

      /* Search suggestions --------------------------------------------------
         A live dropdown under the search box, filtered from the students
         already loaded for the selected year. It never fetches on its own,
         so it stays in sync with whatever selectYear() last loaded. */
      const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
      const SUGGESTION_LIMIT = 30;

      const clearSearchSuggestions = () => {
        if (!yearStudentSuggestions) return;
        yearStudentSuggestions.hidden = true;
        yearStudentSuggestions.innerHTML = '';
        yearStudentSearch?.setAttribute('aria-expanded', 'false');
      };

      const selectSuggestion = (fullName) => {
        yearStudentSearch.value = fullName;
        clearSearchSuggestions();
        yearStudentSearch.dispatchEvent(new Event('input'));
        yearStudentSearch.focus();
      };

      const renderSearchSuggestions = (query) => {
        if (!yearStudentSuggestions) return;
        const term = query.trim();
        if (!term) {
          clearSearchSuggestions();
          return;
        }

        const matches = getYearStudentMatches(activeYearStudents, term).sort(compareLastNames).slice(0, SUGGESTION_LIMIT);

        if (!matches.length) {
          yearStudentSuggestions.innerHTML = `<div class="search-suggestion-empty">No students match “${escapeHtml(term)}”.</div>`;
          yearStudentSuggestions.hidden = false;
          yearStudentSearch.setAttribute('aria-expanded', 'true');
          return;
        }

        yearStudentSuggestions.innerHTML = matches.map((student) => {
          const fullName = formatStudentName(student);
          const initials = `${(student.first_name || 'U').charAt(0)}${(student.last_name || 'S').charAt(0)}`.toUpperCase();
          return `
            <button type="button" class="search-suggestion-item" role="option" data-suggest-name="${escapeHtml(fullName)}">
              <span class="student-mini-avatar">${escapeHtml(initials)}</span>
              <span>
                <strong>${escapeHtml(fullName)}</strong>
                <small>${escapeHtml(student.student_number || '—')}</small>
              </span>
            </button>
          `;
        }).join('');
        yearStudentSuggestions.hidden = false;
        yearStudentSearch.setAttribute('aria-expanded', 'true');

        yearStudentSuggestions.querySelectorAll('[data-suggest-name]').forEach((item) => {
          item.addEventListener('click', () => selectSuggestion(item.dataset.suggestName));
        });
      };

      yearStudentSearch?.addEventListener('input', () => renderSearchSuggestions(yearStudentSearch.value));
      yearStudentSearch?.addEventListener('focus', () => {
        if (yearStudentSearch.value.trim()) renderSearchSuggestions(yearStudentSearch.value);
      });
      yearStudentSearch?.addEventListener('keydown', (event) => {
        if (!yearStudentSuggestions || yearStudentSuggestions.hidden) return;
        const items = [...yearStudentSuggestions.querySelectorAll('.search-suggestion-item')];
        if (!items.length) return;
        const currentIndex = items.findIndex((item) => item.classList.contains('active'));

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
          event.preventDefault();
          const nextIndex = event.key === 'ArrowDown'
            ? (currentIndex + 1) % items.length
            : (currentIndex - 1 + items.length) % items.length;
          items.forEach((item, index) => item.classList.toggle('active', index === nextIndex));
          items[nextIndex].scrollIntoView({ block: 'nearest' });
        } else if (event.key === 'Enter' && currentIndex >= 0) {
          event.preventDefault();
          selectSuggestion(items[currentIndex].dataset.suggestName);
        } else if (event.key === 'Escape') {
          clearSearchSuggestions();
        }
      });
      document.addEventListener('click', (event) => {
        if (!yearStudentSuggestions || yearStudentSuggestions.hidden) return;
        if (event.target.closest('.search-box')) return;
        clearSearchSuggestions();
      });

      const selectYear = async (year) => {
        const isAllYears = String(year) === 'ALL';
        enrollmentYearFilter.value = String(year);
        deleteYearButton.disabled = isAllYears;
        selectedYearTitle.textContent = isAllYears ? 'All students · every school year' : `Enrollment year · ${year}`;
        selectedYearTitle.dataset.year = year;
        selectedYearTitle.dataset.page = '1';
        totalStudentCount.textContent = '--';
        if (yearStudentSearch) {
          yearStudentSearch.value = '';
        }
        clearSearchSuggestions();
        try {
          const response = await fetch('../api.php?resource=students', { credentials: 'same-origin' });
          if (!response.ok) throw new Error('Unable to load students');
          const result = await response.json();
          const students = (result.data || [])
            .filter((student) => isAllYears || String(student.academic_year || '').trim() === String(year));
          if (enrollmentYearFilter.value !== String(year)) return;

          activeYearStudents = students;
          totalStudentCount.textContent = students.length.toLocaleString();

          if (!students.length) {
            renderEmptyState(isAllYears ? 'No student records found.' : `No student records found for ${year}.`);
            return;
          }

          const pageSize = 10;
          const renderPage = (page) => {
            const searchTerm = yearStudentSearch ? yearStudentSearch.value.trim() : '';
            const filteredStudents = getYearStudentMatches(activeYearStudents, searchTerm);
            const sortedStudents = [...filteredStudents].sort(searchTerm ? compareLastNames : compareStudents);
            const totalPages = Math.max(1, Math.ceil(sortedStudents.length / pageSize));
            const currentPage = Math.min(Math.max(1, page), totalPages);
            selectedYearTitle.dataset.page = String(currentPage);

            if (!sortedStudents.length) {
              yearStudentList.innerHTML = `<div class="year-empty-state">No students found for “${(yearStudentSearch?.value || '').trim()}”${isAllYears ? '' : ` in ${year}`}.</div>`;
              return;
            }

            const start = (currentPage - 1) * pageSize;
            const pageStudents = sortedStudents.slice(start, start + pageSize);

            const rows = pageStudents.map((student) => {
              const fullName = formatStudentName(student);
              const initials = `${(student.first_name || 'U').charAt(0)}${(student.last_name || 'S').charAt(0)}`.toUpperCase();
              return `
                <tr class="student-file-row" data-student-id="${student.id || ''}" tabindex="0" aria-label="Open files for ${fullName || 'Unknown student'}">
                  <td>
                    <div class="student-name-cell">
                      <span class="student-mini-avatar">${initials}</span>
                      <div class="student-meta">
                        <strong>${fullName || 'Unknown student'}</strong>
                        <small>${student.status || 'Active'}</small>
                      </div>
                    </div>
                  </td>
                  <td>${student.student_number || '—'}</td>
                  <td>${student.academic_year || '—'}</td>
                  <td>
                    <div class="student-row-actions">
                      <button class="view-files-btn" type="button" data-student-name="${fullName}" data-student-id="${student.id || ''}">View Files</button>
                      <button class="student-row-delete" type="button" aria-label="Remove ${fullName || 'student'}" title="Remove student" data-student-id="${student.id || ''}"><i data-lucide="trash-2"></i></button>
                    </div>
                  </td>
                </tr>
              `;
            }).join('');

            yearStudentList.innerHTML = `
              <div class="student-year-table-wrap">
                <table class="student-year-table">
                  <thead>
                    <tr>
                      ${renderSortableHeader('name', 'Student')}
                      ${renderSortableHeader('student_id', 'Student ID')}
                      ${renderSortableHeader('academic_year', 'School Year')}
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>${rows}</tbody>
                </table>
              </div>
              <div class="year-pagination">
                <button type="button" data-page-action="prev" ${currentPage === 1 ? 'disabled' : ''}>Prev</button>
                ${Array.from({ length: totalPages }, (_, index) => `
                  <button type="button" class="${currentPage === index + 1 ? 'active' : ''}" data-page-number="${index + 1}">${index + 1}</button>
                `).join('')}
                <button type="button" data-page-action="next" ${currentPage === totalPages ? 'disabled' : ''}>Next</button>
              </div>
            `;

            yearStudentList.querySelectorAll('.sort-th').forEach((button) => {
              button.addEventListener('click', () => {
                const key = button.dataset.sortKey;
                if (studentSortColumn === key) {
                  studentSortDirection = studentSortDirection === 'asc' ? 'desc' : 'asc';
                } else {
                  studentSortColumn = key;
                  studentSortDirection = 'asc';
                }
                renderPage(1);
              });
            });

            yearStudentList.querySelectorAll('.view-files-btn').forEach((button) => {
              button.addEventListener('click', () => {
                const studentName = button.dataset.studentName || 'Student';
                const studentId = button.dataset.studentId || '';
                const selectedStudent = activeYearStudents.find((student) => String(student.id) === String(studentId));
                const studentNumber = selectedStudent?.student_number || '';
                openStudentFilesModal(studentId, studentName, studentNumber, year);
              });
            });

            yearStudentList.querySelectorAll('.student-file-row').forEach((row) => {
              const openFiles = () => {
                const studentId = row.dataset.studentId || '';
                const selectedStudent = activeYearStudents.find((student) => String(student.id) === String(studentId));
                if (!selectedStudent) return;
                const studentName = formatStudentName(selectedStudent);
                openStudentFilesModal(studentId, studentName, selectedStudent.student_number || '', year);
              };

              row.addEventListener('click', (event) => {
                if (event.target.closest('button, a, .student-row-actions')) return;
                openFiles();
              });
              row.addEventListener('keydown', (event) => {
                if (event.target !== row || (event.key !== 'Enter' && event.key !== ' ')) return;
                event.preventDefault();
                openFiles();
              });
            });

            yearStudentList.querySelectorAll('.student-row-delete').forEach((button) => {
              button.addEventListener('click', (event) => {
                event.stopPropagation();
                const studentId = button.dataset.studentId || '';
                const selectedStudent = activeYearStudents.find((student) => String(student.id) === String(studentId));
                if (selectedStudent) openDeleteStudent(selectedStudent);
              });
            });

            if (window.lucide) lucide.createIcons();

            yearStudentList.querySelectorAll('[data-page-number]').forEach((button) => {
              button.addEventListener('click', () => {
                const selectedPage = Number(button.dataset.pageNumber || 1);
                renderPage(selectedPage);
              });
            });

            yearStudentList.querySelectorAll('[data-page-action]').forEach((button) => {
              button.addEventListener('click', () => {
                const action = button.dataset.pageAction;
                const nextPage = action === 'next' ? currentPage + 1 : currentPage - 1;
                renderPage(nextPage);
              });
            });
          };

          if (yearStudentSearch) {
            yearStudentSearch.oninput = () => {
              renderPage(1);
            };
          }

          renderPage(1);
        } catch (error) {
          if (enrollmentYearFilter.value !== String(year)) return;
          totalStudentCount.textContent = '—';
          renderEmptyState('Unable to load student records for this year.');
        }
      };

      enrollmentYearFilter.addEventListener('change', () => selectYear(enrollmentYearFilter.value));
      await loadYears();
    });
  </script>
  <script src="../dashboard-tables.js?v=20260914"></script>
</body>
</html>
