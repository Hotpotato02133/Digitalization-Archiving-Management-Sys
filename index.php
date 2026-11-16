<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$currentUser = dats_current_user();
$pdo = dats_db();

$stats = [
  'documents' => 0,
  'students' => 0,
  'colleges' => 0,
  'years' => 0,
];

if ($pdo) {
  try {
    $row = $pdo->query('SELECT total_students, total_documents, total_colleges FROM vw_archive_analytics')->fetch();
    if (is_array($row)) {
      $stats['documents'] = (int) ($row['total_documents'] ?? 0);
      $stats['students'] = (int) ($row['total_students'] ?? 0);
      $stats['colleges'] = (int) ($row['total_colleges'] ?? 0);
    }
    $stats['years'] = (int) $pdo->query(
      "SELECT COUNT(DISTINCT academic_year) FROM documents
       WHERE deleted_at IS NULL AND academic_year IS NOT NULL AND academic_year NOT IN ('', '0000')"
    )->fetchColumn();
  } catch (Throwable $e) {
    // The public page degrades to an "archive being set up" state when the view is unavailable.
  }
}

$archiveIsNew = $stats['documents'] < 10;
$firstName = $currentUser ? (explode(' ', trim((string) $currentUser['full_name']))[0] ?: 'there') : '';
$esc = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="DATS, the Digitalization Archiving Management System of the Zamboanga Peninsula Polytechnic State University Registrar's Office. Scan, index and preserve student records." />
  <meta name="theme-color" content="#6b1d2a" />
  <title>ZPPSU Registrar Digital Archive | DATS</title>
  <link rel="icon" href="img/ZPPSU-192.png" type="image/png" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Manrope:wght@400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="styles.css?v=<?= filemtime(__DIR__ . '/styles.css') ?>" />
  <script>document.documentElement.classList.add('js');</script>
  <script defer src="https://unpkg.com/lucide@0.469.0/dist/umd/lucide.min.js"></script>
</head>
<body>
  <a class="skip-link" href="#main-content">Skip to main content</a>

  <header class="topbar" id="top">
    <div class="container topbar-inner">
      <a class="brand" href="#top" aria-label="ZPPSU Registrar Digital Archive, back to top">
        <img class="brand-mark" src="img/ZPPSU-192.png" width="40" height="40" alt="" />
        <span class="brand-text">
          <strong>ZPPSU Registrar</strong>
          <small>Digital Archiving System</small>
        </span>
      </a>

      <nav class="site-nav" id="siteNav" aria-label="Primary">
        <ul class="nav-list">
          <li><a class="nav-link" href="#overview" data-spy>Overview</a></li>
          <li><a class="nav-link" href="#how-it-works" data-spy>How it works</a></li>
          <li><a class="nav-link" href="#capabilities" data-spy>Capabilities</a></li>
          <li><a class="nav-link" href="#security" data-spy>Security</a></li>
          <li><a class="nav-link" href="#help" data-spy>Help</a></li>
        </ul>
        <?php if ($currentUser): ?>
          <a class="button button-primary nav-cta" href="admin/index.php">
            <span>Open dashboard</span>
            <i data-lucide="arrow-right" aria-hidden="true"></i>
          </a>
        <?php else: ?>
          <button class="button button-primary nav-cta" type="button" data-open-login>
            <i data-lucide="log-in" aria-hidden="true"></i>
            <span>Sign in</span>
          </button>
        <?php endif; ?>
      </nav>

      <button class="menu-button" type="button" id="menuButton" aria-expanded="false" aria-controls="siteNav">
        <i data-lucide="menu" aria-hidden="true"></i>
        <span>Menu</span>
      </button>
    </div>
  </header>

  <main id="main-content">
    <section class="hero" id="overview" aria-labelledby="hero-title">
      <div class="container hero-grid">
        <div class="hero-copy" data-reveal>
          <p class="eyebrow">Office of the University Registrar</p>
          <h1 id="hero-title">Every student record, preserved and searchable.</h1>
          <p class="hero-lead">
            The Zamboanga Peninsula Polytechnic State University's digital archive. Scan paper documents, extract their text automatically, and file them into permanent year folders that staff can search in seconds.
          </p>
          <div class="hero-actions">
            <?php if ($currentUser): ?>
              <a class="button button-primary button-lg" href="admin/index.php">
                <span>Continue to dashboard</span>
                <i data-lucide="arrow-right" aria-hidden="true"></i>
              </a>
            <?php else: ?>
              <button class="button button-primary button-lg" type="button" data-open-login>
                <i data-lucide="log-in" aria-hidden="true"></i>
                <span>Sign in to the archive</span>
              </button>
            <?php endif; ?>
            <a class="button button-secondary button-lg" href="#how-it-works">
              <span>See how it works</span>
              <i data-lucide="arrow-down" aria-hidden="true"></i>
            </a>
          </div>
          <?php if ($currentUser): ?>
            <p class="hero-session">Signed in as <strong><?= $esc((string) $currentUser['full_name']) ?></strong> · <a href="logout.php">Sign out</a></p>
          <?php endif; ?>
          <ul class="hero-points" aria-label="What the system does">
            <li><i data-lucide="check" aria-hidden="true"></i><span>Camera or upload intake with automatic text recognition</span></li>
            <li><i data-lucide="check" aria-hidden="true"></i><span>Gradesheets indexed page by page to each student profile</span></li>
            <li><i data-lucide="check" aria-hidden="true"></i><span>Duplicate-free, tamper-resistant year-folder storage</span></li>
          </ul>
        </div>

        <div class="hero-visual" data-reveal style="--reveal-delay: 120ms">
          <figure class="hero-photo">
            <img
              src="assets/wroclaw-university-library-digitizing-rare-archival-texts.jpg"
              width="1920"
              height="1278"
              fetchpriority="high"
              decoding="async"
              alt="Aisles of bound historical university ledgers on tall wooden archive shelving"
            />
          </figure>

          <div class="hero-float hero-float-record" aria-hidden="true">
            <span class="hero-float-icon"><i data-lucide="file-check-2"></i></span>
            <div>
              <strong>Transcript of Records</strong>
              <small>Filed to 2025 · CICT · duplicate check passed</small>
            </div>
          </div>

          <div class="hero-float hero-float-search" aria-hidden="true">
            <span class="hero-float-icon is-gold"><i data-lucide="search"></i></span>
            <div>
              <strong>“Dela Cruz, Juan”</strong>
              <small>3 records found in 0.2 s</small>
            </div>
          </div>

          <div class="hero-float hero-float-ocr" aria-hidden="true">
            <span class="hero-float-dot"></span>
            <span>Text recognised · year detected 2025</span>
          </div>
        </div>
      </div>
    </section>

    <section class="stats-band" aria-label="Archive at a glance">
      <div class="container">
        <div class="stats-grid">
          <div class="stat" data-reveal>
            <strong><?= number_format($stats['documents']) ?></strong>
            <span>Documents archived</span>
          </div>
          <div class="stat" data-reveal style="--reveal-delay: 60ms">
            <strong><?= number_format($stats['students']) ?></strong>
            <span>Student profiles</span>
          </div>
          <div class="stat" data-reveal style="--reveal-delay: 120ms">
            <strong><?= number_format($stats['colleges']) ?></strong>
            <span>Colleges on record</span>
          </div>
          <div class="stat" data-reveal style="--reveal-delay: 180ms">
            <strong><?= number_format($stats['years']) ?></strong>
            <span>Academic years covered</span>
          </div>
        </div>
        <p class="stats-note">
          <?php if ($archiveIsNew): ?>
            Figures update automatically as the Registrar's Office digitizes its paper holdings.
          <?php else: ?>
            Live figures from the Registrar archive database.
          <?php endif; ?>
        </p>
      </div>
    </section>

    <section class="section section-how" id="how-it-works" aria-labelledby="how-title">
      <div class="container how-grid">
        <div class="how-intro" data-reveal>
          <p class="eyebrow">How it works</p>
          <h2 id="how-title">From a paper folder to a searchable record in three steps.</h2>
          <p class="section-lead">Designed around the Registrar's daily routine, so staff spend their time verifying records instead of retyping them.</p>
          <figure class="photo-card">
            <img
              src="assets/book-scanner-digitization-national-library-of-the.jpg"
              width="1920"
              height="1280"
              loading="lazy"
              decoding="async"
              alt="An archivist digitizing an open ledger with an overhead book scanner"
            />
            <figcaption>Overhead or flatbed scanning, phone cameras and PDF uploads all feed the same intake.</figcaption>
          </figure>
        </div>

        <ol class="steps">
          <li class="step" data-reveal>
            <span class="step-number">01</span>
            <div class="step-body">
              <span class="step-icon"><i data-lucide="camera" aria-hidden="true"></i></span>
              <h3>Capture</h3>
              <p>Scan with a camera or upload PDFs and images. Each file is checked by its real content type and compared against the archive so the same document is never stored twice.</p>
            </div>
          </li>
          <li class="step" data-reveal style="--reveal-delay: 80ms">
            <span class="step-number">02</span>
            <div class="step-body">
              <span class="step-icon"><i data-lucide="file-search" aria-hidden="true"></i></span>
              <h3>Recognize</h3>
              <p>Text is extracted automatically and the system proposes the document type and academic year. Staff confirm or correct the suggestion before anything is filed.</p>
            </div>
          </li>
          <li class="step" data-reveal style="--reveal-delay: 160ms">
            <span class="step-number">03</span>
            <div class="step-body">
              <span class="step-icon"><i data-lucide="folder-archive" aria-hidden="true"></i></span>
              <h3>File and find</h3>
              <p>Documents land in structured year folders linked to student profiles, ready for full-text search, in-browser preview and bulk download as a student record bundle.</p>
            </div>
          </li>
        </ol>
      </div>
    </section>

    <section class="section section-capabilities" id="capabilities" aria-labelledby="capabilities-title">
      <div class="container">
        <div class="section-head" data-reveal>
          <p class="eyebrow">Capabilities</p>
          <h2 id="capabilities-title">One portal for intake, indexing and custody.</h2>
          <p class="section-lead">Everything the Registrar's Office needs to digitize and manage student records, with no separate tools to juggle.</p>
        </div>

        <div class="capability-grid">
          <article class="capability" data-reveal>
            <span class="capability-icon"><i data-lucide="scan-line" aria-hidden="true"></i></span>
            <h3>Scan and upload intake</h3>
            <p>Use the device camera, drag and drop files, or browse for PDFs and images. Large batches are queued and processed in the background.</p>
          </article>
          <article class="capability" data-reveal style="--reveal-delay: 60ms">
            <span class="capability-icon"><i data-lucide="text-search" aria-hidden="true"></i></span>
            <h3>Automatic text recognition</h3>
            <p>Every page is read by OCR so its contents become searchable. Document type and academic year are suggested and stay editable.</p>
          </article>
          <article class="capability" data-reveal style="--reveal-delay: 120ms">
            <span class="capability-icon"><i data-lucide="table-2" aria-hidden="true"></i></span>
            <h3>Gradesheet indexing</h3>
            <p>Scanned college gradesheets are parsed into student rows, fragmented names are repaired, and each page is linked to the matching student profile.</p>
          </article>
          <article class="capability" data-reveal style="--reveal-delay: 180ms">
            <span class="capability-icon"><i data-lucide="folder-tree" aria-hidden="true"></i></span>
            <h3>Year-folder archive</h3>
            <p>Records are organised by academic year and college, with previews, category filters and a recycle bin that holds deletions before any permanent removal.</p>
          </article>
          <article class="capability" data-reveal style="--reveal-delay: 240ms">
            <span class="capability-icon"><i data-lucide="users" aria-hidden="true"></i></span>
            <h3>Student profiles and exports</h3>
            <p>Each student's documents and gradesheet pages sit in one profile. Export a complete record as a ZIP bundle or print reports to PDF.</p>
          </article>
          <article class="capability" data-reveal style="--reveal-delay: 300ms">
            <span class="capability-icon"><i data-lucide="database-backup" aria-hidden="true"></i></span>
            <h3>Backup and restore</h3>
            <p>Administrators can download a verified snapshot of the whole database and restore it from Setup when a server is rebuilt.</p>
          </article>
        </div>
      </div>
    </section>

    <section class="section section-workspace" id="workspace" aria-labelledby="workspace-title">
      <div class="container workspace-grid">
        <div class="workspace-copy" data-reveal>
          <p class="eyebrow">Inside the workspace</p>
          <h2 id="workspace-title">Verify in one screen, then let the archive do the filing.</h2>
          <p class="section-lead">The intake screen shows the recognised text, the suggested category and year, and the duplicate check together, so a record is confirmed in one glance.</p>
          <ul class="checklist">
            <li><i data-lucide="check" aria-hidden="true"></i><span>Suggested fields are pre-filled and stay editable</span></li>
            <li><i data-lucide="check" aria-hidden="true"></i><span>Duplicate uploads are caught before they reach disk</span></li>
            <li><i data-lucide="check" aria-hidden="true"></i><span>Dashboards refresh the moment a document is archived</span></li>
          </ul>
        </div>

        <div class="mock-stack" aria-label="Illustrative preview of the intake and gradesheet screens">
          <article class="mock mock-intake" data-reveal style="--reveal-delay: 80ms">
            <header class="mock-head">
              <span class="mock-title">Scan &amp; OCR intake</span>
              <span class="mock-badge is-success">Text recognised</span>
            </header>
            <div class="mock-body">
              <div class="mock-file">
                <i data-lucide="file-text" aria-hidden="true"></i>
                <div>
                  <strong>TOR_2025_Dela-Cruz.pdf</strong>
                  <small>3 pages · type verified · no duplicate found</small>
                </div>
              </div>
              <dl class="mock-fields">
                <div><dt>Document type</dt><dd>Transcript of Records</dd></div>
                <div><dt>Academic year</dt><dd>2025</dd></div>
                <div><dt>Student</dt><dd>Dela Cruz, Juan M.</dd></div>
                <div><dt>College</dt><dd>CICT</dd></div>
              </dl>
              <div class="mock-actions">
                <span class="mock-button is-primary">Add to archive</span>
                <span class="mock-button">Edit details</span>
              </div>
            </div>
          </article>

          <article class="mock mock-batch" data-reveal style="--reveal-delay: 160ms">
            <header class="mock-head">
              <span class="mock-title">Gradesheet batch · BSIT 2024</span>
              <span class="mock-badge is-gold">Page mapping</span>
            </header>
            <ul class="mock-rows">
              <li><span>Page 01 · Programming 1</span><strong>38 students</strong></li>
              <li><span>Page 02 · Continuation</span><strong>24 students</strong></li>
              <li><span>Name and ID cross-check</span><strong class="is-success">Verified</strong></li>
            </ul>
          </article>
          <p class="mock-note">Sample data for illustration.</p>
        </div>
      </div>
    </section>

    <section class="section section-security" id="security" aria-labelledby="security-title">
      <div class="container security-grid">
        <div class="security-copy" data-reveal>
          <p class="eyebrow is-gold">Security and custody</p>
          <h2 id="security-title">Built to keep student records safe for decades.</h2>
          <p class="section-lead">Transcripts outlast the hardware they are stored on. DATS treats every file as a permanent record from the moment it is uploaded.</p>
          <ul class="assurance-list">
            <li>
              <i data-lucide="shield-check" aria-hidden="true"></i>
              <div><strong>Account-only access.</strong> Only active Registrar accounts can sign in, and every session is renewed on login and cleared on sign out.</div>
            </li>
            <li>
              <i data-lucide="file-check-2" aria-hidden="true"></i>
              <div><strong>Content-verified uploads.</strong> Files are checked by what they contain, not what they are named, and rejected if they do not match.</div>
            </li>
            <li>
              <i data-lucide="lock" aria-hidden="true"></i>
              <div><strong>Locked storage.</strong> Archive and upload folders cannot run scripts, and nothing stored there is reachable without signing in.</div>
            </li>
            <li>
              <i data-lucide="history" aria-hidden="true"></i>
              <div><strong>Recoverable by design.</strong> Deleted records wait in a recycle bin, and administrators can back up and restore the full database.</div>
            </li>
          </ul>
        </div>
        <figure class="security-photo" data-reveal style="--reveal-delay: 100ms">
          <img
            src="assets/beinecke-rare-book-manuscript-library-interior-34254026911.jpg"
            width="1700"
            height="1408"
            loading="lazy"
            decoding="async"
            alt="Multi-storey archive vault with protected collections behind glass"
          />
          <figcaption>Paper holdings stay where they are. The archive gives them a searchable digital twin.</figcaption>
        </figure>
      </div>
    </section>

    <section class="section section-help" id="help" aria-labelledby="help-title">
      <div class="container help-grid">
        <div class="help-intro" data-reveal>
          <p class="eyebrow">Help and access</p>
          <h2 id="help-title">Questions before you sign in?</h2>
          <p class="section-lead">The archive is an internal tool for the Registrar's Office. Here is how access works.</p>
          <div class="contact-card">
            <p class="contact-label">Office of the University Registrar</p>
            <a class="contact-line" href="mailto:registrar@zppsu.edu.ph">
              <i data-lucide="mail" aria-hidden="true"></i>
              <span>registrar@zppsu.edu.ph</span>
            </a>
            <p class="contact-line">
              <i data-lucide="map-pin" aria-hidden="true"></i>
              <span>ZPPSU Main Campus, R.T. Lim Boulevard, Zamboanga City</span>
            </p>
            <p class="contact-line">
              <i data-lucide="clock" aria-hidden="true"></i>
              <span>Monday to Friday, 8:00 AM to 5:00 PM</span>
            </p>
          </div>
        </div>

        <div class="faq" data-reveal style="--reveal-delay: 80ms">
          <details class="faq-item" open>
            <summary>Who can use this system?</summary>
            <p>Registrar staff and administrators with an account issued by the system administrator. Every account signs in with a work email and password.</p>
          </details>
          <details class="faq-item">
            <summary>How do I get an account?</summary>
            <p>Ask the Registrar's Office. An administrator creates your account under Users in the dashboard and shares a temporary password, which you should change in Settings on first sign-in.</p>
          </details>
          <details class="faq-item">
            <summary>I forgot my password.</summary>
            <p>Email the Registrar's Office from your work address. An administrator can reset the password on your account; there is no self-service reset.</p>
          </details>
          <details class="faq-item">
            <summary>Can students look up their own records here?</summary>
            <p>No. Student records are only visible to signed-in Registrar staff. Students and alumni request transcripts and certificates through the Registrar's Office as usual.</p>
          </details>
          <details class="faq-item">
            <summary>Does scanning need special equipment?</summary>
            <p>No. A phone or laptop camera works for single pages, and any flatbed or overhead scanner that produces PDF or image files can be used for batches.</p>
          </details>
        </div>
      </div>
    </section>
  </main>

  <footer class="site-footer">
    <div class="container footer-grid">
      <div class="footer-brand">
        <img src="img/ZPPSU-192.png" width="48" height="48" alt="" />
        <div>
          <strong>Zamboanga Peninsula Polytechnic State University</strong>
          <p>Office of the University Registrar · Digitalization Archiving Management System (DATS)</p>
        </div>
      </div>
      <nav class="footer-nav" aria-label="Footer">
        <a href="#overview">Overview</a>
        <a href="#how-it-works">How it works</a>
        <a href="#capabilities">Capabilities</a>
        <a href="#security">Security</a>
        <a href="#help">Help</a>
        <?php if ($currentUser): ?>
          <a href="admin/index.php">Dashboard</a>
        <?php else: ?>
          <button class="footer-link-button" type="button" data-open-login>Staff sign in</button>
        <?php endif; ?>
      </nav>
    </div>
    <div class="container footer-meta">
      <p class="credits">
        Photos: <a href="https://commons.wikimedia.org/wiki/File:Book_scanner_digitization_National_library_of_the_Czech_republic.jpg" target="_blank" rel="noopener">“Book scanner digitization National library of the Czech republic”</a> by Skot, <a href="https://creativecommons.org/licenses/by-sa/4.0" target="_blank" rel="noopener">CC BY-SA 4.0</a>; <a href="https://commons.wikimedia.org/wiki/File:Wroclaw_University_Library_digitizing_rare_archival_texts.jpg" target="_blank" rel="noopener">“Wroclaw University Library digitizing rare archival texts”</a> by j_cadmus, <a href="https://creativecommons.org/licenses/by/2.0" target="_blank" rel="noopener">CC BY 2.0</a>; <a href="https://commons.wikimedia.org/wiki/File:Beinecke_Rare_Book_%26_Manuscript_Library_Interior_(34254026911).jpg" target="_blank" rel="noopener">“Beinecke Rare Book &amp; Manuscript Library Interior”</a> by Gunnar Klack, <a href="https://creativecommons.org/licenses/by-sa/2.0" target="_blank" rel="noopener">CC BY-SA 2.0</a>, via Wikimedia Commons.
      </p>
      <p class="copyright">© <?= date('Y') ?> ZPPSU Office of the University Registrar</p>
    </div>
  </footer>

  <button class="scroll-top-button" id="scrollTopButton" type="button" aria-label="Scroll to top" title="Scroll to top">
    <i data-lucide="arrow-up" aria-hidden="true"></i>
  </button>

  <?php if (!$currentUser): ?>
    <dialog class="login-dialog" id="loginDialog" aria-labelledby="signin-title">
      <div class="login-card">
        <button class="dialog-close" type="button" id="closeLoginButton" aria-label="Close sign-in">
          <i data-lucide="x" aria-hidden="true"></i>
        </button>
        <div class="signin-head">
          <img src="img/ZPPSU-192.png" width="44" height="44" alt="" />
          <div>
            <p class="signin-eyebrow">Registrar staff</p>
            <h2 id="signin-title">Sign in to the archive</h2>
          </div>
        </div>
        <form id="loginForm" class="signin-form" method="dialog" novalidate>
          <div class="field">
            <label for="emailInput">Work email</label>
            <div class="input-wrap">
              <i data-lucide="mail" aria-hidden="true"></i>
              <input id="emailInput" name="email" type="email" inputmode="email" autocomplete="username" placeholder="name@zppsu.edu.ph" required />
            </div>
          </div>

          <div class="field">
            <div class="label-row">
              <label for="passwordInput">Password</label>
              <a class="field-link" href="mailto:registrar@zppsu.edu.ph?subject=DATS%20password%20reset">Forgot password?</a>
            </div>
            <div class="input-wrap">
              <i data-lucide="lock-keyhole" aria-hidden="true"></i>
              <input id="passwordInput" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" required />
              <button type="button" class="password-toggle" id="passwordToggle" aria-label="Show password" aria-pressed="false">
                <i data-lucide="eye" aria-hidden="true"></i>
              </button>
            </div>
          </div>

          <p class="form-error" id="loginError" role="alert" hidden></p>

          <button class="button button-primary button-block" id="loginSubmit" type="submit">
            <span class="button-label">Continue to workspace</span>
            <i data-lucide="arrow-right" aria-hidden="true"></i>
          </button>
        </form>
        <p class="signin-help">
          <i data-lucide="shield-check" aria-hidden="true"></i>
          <span>Accounts are issued by the Registrar's Office. <a href="#help" data-close-login>How to get access</a></span>
        </p>
      </div>
    </dialog>
  <?php endif; ?>

  <script src="script.js?v=<?= filemtime(__DIR__ . '/script.js') ?>"></script>
</body>
</html>
