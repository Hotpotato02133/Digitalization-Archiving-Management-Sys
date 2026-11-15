<?php
require_once __DIR__ . '/config/database.php';

$pdo = dats_db();
$archiveStats = [
  'total_students' => 0,
  'total_documents' => 0,
  'total_colleges' => 0,
  'completed_documents' => 0,
];
$recentDocuments = [];

if ($pdo) {
  try {
    $row = $pdo->query('SELECT total_students, total_documents, total_colleges, completed_documents FROM vw_archive_analytics')->fetch();
    if (is_array($row)) {
      $archiveStats = array_merge($archiveStats, $row);
    }
    $recentDocuments = $pdo->query(
      "SELECT d.id, d.original_filename, d.academic_year, d.status, COALESCE(c.name, 'Uncategorized') AS category_name
       FROM documents d
       LEFT JOIN document_categories c ON c.id = d.category_id
       WHERE d.deleted_at IS NULL
       ORDER BY d.uploaded_at DESC
       LIMIT 5"
    )->fetchAll() ?: [];
  } catch (Throwable $e) {
    $recentDocuments = [];
  }
}

$totalDocuments = (int) ($archiveStats['total_documents'] ?? 0);
$completedDocuments = (int) ($archiveStats['completed_documents'] ?? 0);
$totalStudents = (int) ($archiveStats['total_students'] ?? 0);
$totalColleges = (int) ($archiveStats['total_colleges'] ?? 0);
$digitizedPercent = $totalDocuments > 0 ? round(($completedDocuments / $totalDocuments) * 100, 1) : 0;

$displayStatus = static fn (string $status): string => in_array($status, ['Uploaded', 'Archived'], true) ? 'Verified' : $status;
$statusClass = static fn (string $status): string => in_array($status, ['Uploaded', 'Archived'], true) ? 'status-verified' : ($status === 'Processing' ? 'status-processing' : 'status-review');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="Digitalization Archiving Management System (DATS) for the Zamboanga Peninsula Polytechnic State University Registrar's Office." />
  <title>ZPPSU Registrar Digital Archive | DATS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Source+Serif+4:ital,opsz,wght@0,8..60,400;0,8..60,600;0,8..60,700;1,8..60,400&family=Young+Serif&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="styles.css" />
  <script>document.documentElement.classList.add('js-motion');</script>
  <script defer src="https://unpkg.com/lucide@0.469.0/dist/umd/lucide.min.js"></script>
</head>
<body>
  <a class="skip-link" href="#main-content">Skip to main content</a>

  <header class="topbar" id="top">
    <div class="container topbar-inner">
      <a class="brand" href="#top" aria-label="ZPPSU Registrar Digital Archive home">
        <img class="brand-mark" src="img/ZPPSU.png" width="44" height="44" alt="Zamboanga Peninsula Polytechnic State University seal" />
        <span class="brand-text">
          <strong>ZPPSU Registrar</strong>
          <small>Digital Archiving System</small>
        </span>
      </a>

      <button
        class="menu-button"
        type="button"
        id="menuButton"
        aria-label="Toggle navigation menu"
        aria-expanded="false"
        aria-controls="siteMenu"
      >
        <i data-lucide="menu" aria-hidden="true"></i>
        <span>Menu</span>
      </button>

      <nav class="site-menu" id="siteMenu" aria-label="Primary navigation">
        <ul class="nav-list">
          <li><a class="nav-link active" href="#overview" aria-current="page">Overview</a></li>
          <li><a class="nav-link" href="#capabilities">Capabilities</a></li>
          <li><a class="nav-link" href="#workspace">Workspace</a></li>
          <li><a class="nav-link" href="#records">Records</a></li>
        </ul>
        <div class="nav-actions">
          <button class="button-primary nav-signin" type="button" id="signInButton" data-open-login>
            <i data-lucide="log-in" aria-hidden="true"></i>
            <span>Sign in</span>
          </button>
        </div>
      </nav>
    </div>
  </header>

  <main id="main-content">
    <section class="hero" id="overview" aria-labelledby="hero-title">
      <div class="hero-media" aria-hidden="true">
        <img
          class="hero-photo is-active"
          src="assets/wroclaw-university-library-digitizing-rare-archival-texts.jpg"
          width="1920"
          height="1278"
          fetchpriority="high"
          decoding="async"
          alt="Aisles of bound historical university ledgers in tall wooden archive shelving"
        />
        <img
          class="hero-photo"
          src="assets/beinecke-rare-book-manuscript-library-interior-34254026911.jpg"
          width="1700"
          height="1408"
          decoding="async"
          alt="Illuminated multi-tier university archive stacks seen from the gallery walkway"
        />
        <div class="hero-scrim"></div>
      </div>

      <div class="container hero-grid">
        <div class="hero-copy" data-reveal>
          <h1 id="hero-title">ZPPSU records,<br />digitally archived.</h1>
          <p class="hero-lead">
            The Zamboanga Peninsula Polytechnic State University Registrar's Office portal for scanning, OCR indexing, gradesheet parsing, and year-folder record preservation.
          </p>
          <div class="hero-actions">
            <button class="button-accent" type="button" data-open-login>
              <span>Sign in to Registrar portal</span>
              <i data-lucide="arrow-up-right" aria-hidden="true"></i>
            </button>
            <a class="button-ghost" href="#records">
              <span>Inspect live records</span>
              <i data-lucide="arrow-down" aria-hidden="true"></i>
            </a>
          </div>
        </div>

        <aside class="hero-cards" aria-label="Live database archive figures">
          <article class="hero-stat-card is-maroon" data-reveal style="--reveal-delay: 80ms">
            <div class="stat-top">
              <strong><?= number_format($totalDocuments) ?></strong>
              <span class="stat-pill">SQL synced</span>
            </div>
            <p class="stat-label">Archived academic documents</p>
            <p class="stat-detail"><?= number_format($completedDocuments) ?> verified and filed into permanent year directories (<?= $digitizedPercent ?>% completion).</p>
          </article>

          <article class="hero-stat-card is-paper" data-reveal style="--reveal-delay: 160ms">
            <div class="stat-top">
              <strong><?= number_format($totalStudents) ?></strong>
              <span class="stat-pill is-gold"><?= number_format($totalColleges) ?> colleges</span>
            </div>
            <p class="stat-label">Registered student profiles</p>
            <p class="stat-detail">Linked across colleges, degree programs, and indexed subject gradesheet batches.</p>
          </article>

          <p class="hero-meta" data-reveal style="--reveal-delay: 200ms">
            <i data-lucide="shield-check" aria-hidden="true"></i>
            <span>Protected session access · <span data-current-datetime>Synchronizing system clock</span></span>
          </p>
        </aside>
      </div>
    </section>

    <section class="section-editorial" id="capabilities" aria-labelledby="capabilities-title">
      <div class="container axis-grid">
        <div class="axis-aside" data-reveal>
          <h2 id="capabilities-title">Paper ledgers to digital truth</h2>
          <p class="section-standfirst">
            Built directly around the Registrar's daily workflow—from camera capture and optical character recognition to duplicate-safe storage.
          </p>
          <figure class="editorial-figure figure-stacks" data-reveal style="--reveal-delay: 90ms">
            <img
              src="assets/first-icrc-records-icrc-archives-romandeckert09062020.jpg"
              width="1920"
              height="1261"
              loading="lazy"
              decoding="async"
              alt="Numbered archival document boxes stacked neatly on metal shelves"
            />
            <figcaption>Physical dossier boxes replaced by indexed year folders and cryptographic duplicate checks.</figcaption>
          </figure>
        </div>

        <div class="axis-main">
          <figure class="editorial-figure figure-scanner" data-reveal>
            <img
              src="assets/book-scanner-digitization-national-library-of-the.jpg"
              width="1920"
              height="1280"
              loading="lazy"
              decoding="async"
              alt="Archivist operating an overhead page digitization scanner on an open historical ledger"
            />
            <figcaption>Direct camera or flatbed intake paired with automated document type inference.</figcaption>
          </figure>

          <div class="capability-columns">
            <article class="capability-item" data-reveal style="--reveal-delay: 60ms">
              <h3>Smart scan and OCR intake</h3>
              <p>Capture documents from live camera feeds or PDF/image uploads. DATS extracts searchable text via server Tesseract or browser PDF.js and suggests the document category and academic year.</p>
            </article>

            <article class="capability-item" data-reveal style="--reveal-delay: 120ms">
              <h3>Automated gradesheet indexing</h3>
              <p>Parse scanned college gradesheets into student roster rows, repair fragmented names, and map page numbers directly to each enrolled student profile.</p>
            </article>

            <article class="capability-item" data-reveal style="--reveal-delay: 180ms">
              <h3>Year-folder archive and bulk export</h3>
              <p>Documents are validated by MIME signature, hashed to prevent duplicates, stored inside structured academic-year directories, and bundled cleanly as student ZIP records.</p>
            </article>

            <article class="capability-item" data-reveal style="--reveal-delay: 240ms">
              <h3>Full database backup and restore</h3>
              <p>Administrators can export verifiable SQL snapshots of students, gradesheets, colleges, and document tables or restore recovered archives directly through Setup.</p>
            </article>
          </div>
        </div>
      </div>
    </section>

    <section class="section-workspace" id="workspace" aria-labelledby="workspace-title">
      <div class="container">
        <div class="workspace-header" data-reveal>
          <h2 id="workspace-title">Inside the Registrar workspace</h2>
          <p class="workspace-intro">
            Three core stations unify intake, verification, and college-wide reporting inside one authenticated portal.
          </p>
        </div>

        <div class="fragment-grid">
          <article class="fragment-card" data-ui data-reveal style="--reveal-delay: 40ms">
            <header class="ui-head">
              <span class="ui-title">Scan &amp; OCR Intake</span>
              <span class="ui-badge ui-badge-success">OCR Ready</span>
            </header>
            <div class="ui-body">
              <div class="ui-file-row">
                <i data-lucide="file-text" aria-hidden="true"></i>
                <div>
                  <strong>TOR_AY2025_StudentRecord.pdf</strong>
                  <small>MIME verified · SHA-256 unique check passed</small>
                </div>
              </div>
              <div class="ui-kv-grid">
                <div><span>Detected category</span><strong>Transcript of Records</strong></div>
                <div><span>Academic year</span><strong>2025</strong></div>
              </div>
              <p class="ui-excerpt">Extracted text indexed into FULLTEXT archive search with instant student-folder filing.</p>
            </div>
          </article>

          <article class="fragment-card" data-ui data-reveal style="--reveal-delay: 120ms">
            <header class="ui-head">
              <span class="ui-title">Gradesheet Batch Indexer</span>
              <span class="ui-badge ui-badge-gold">Page Mapping</span>
            </header>
            <div class="ui-body">
              <div class="ui-row-list">
                <div class="ui-list-item"><span>Page 01 · BSIT Subject Ledger</span><strong>38 matched</strong></div>
                <div class="ui-list-item"><span>Page 02 · Continuation Sheet</span><strong>24 matched</strong></div>
                <div class="ui-list-item"><span>Name &amp; ID Cross-Check</span><strong>Verified</strong></div>
              </div>
              <p class="ui-excerpt">Links individual student entries back to exact gradesheet pages inside student profiles.</p>
            </div>
          </article>

          <article class="fragment-card" data-ui data-reveal style="--reveal-delay: 200ms">
            <header class="ui-head">
              <span class="ui-title">Archive Health Pulse</span>
              <span class="ui-badge ui-badge-maroon">Live MySQL</span>
            </header>
            <div class="ui-body">
              <div class="ui-metrics-pair">
                <div>
                  <strong><?= number_format($totalDocuments) ?></strong>
                  <span>Archived files</span>
                </div>
                <div>
                  <strong><?= $digitizedPercent ?>%</strong>
                  <span>Completed OCR &amp; filing</span>
                </div>
              </div>
              <div class="ui-meter" role="presentation"><span style="width: <?= max(6, min(100, (int) round($digitizedPercent))) ?>%"></span></div>
              <p class="ui-excerpt">Powered by vw_archive_analytics across <?= number_format($totalColleges) ?> active colleges.</p>
            </div>
          </article>
        </div>
      </div>
    </section>

    <section class="section-permanence" aria-labelledby="permanence-title">
      <div class="container axis-grid permanence-grid">
        <div class="axis-aside" data-reveal>
          <h2 id="permanence-title">Built for lasting custody</h2>
          <p class="section-standfirst">
            Student transcripts outlast hardware cycles. Every file upload passes strict MIME sniffing, path-traversal confinement, and isolated directory execution lockouts before reaching disk.
          </p>
          <ul class="assurance-list">
            <li>Server-side PDO prepared statements with non-emulated bindings</li>
            <li>Hardened session cookies with regeneration on sign-in</li>
            <li>Upload and archive directories locked against script execution</li>
          </ul>
        </div>
        <div class="axis-main">
          <figure class="editorial-figure figure-vault" data-reveal style="--reveal-delay: 100ms">
            <img
              src="assets/beinecke-rare-book-manuscript-library-interior-34254026911.jpg"
              width="1700"
              height="1408"
              loading="lazy"
              decoding="async"
              alt="Multi-story archive vault housing protected collections behind glass and warm lighting"
            />
            <figcaption>Long-term academic custody designed around ZPPSU Registrar retention standards.</figcaption>
          </figure>
        </div>
      </div>
    </section>

    <section class="section-records" id="records" aria-labelledby="records-title">
      <div class="container">
        <div class="records-header" data-reveal>
          <div>
            <h2 id="records-title">Recently indexed records</h2>
            <p class="records-subtitle">Real-time ledger snapshot pulled directly from the Registrar archive database.</p>
          </div>
          <button class="button-outline" type="button" data-open-login>
            <span>Open full archive</span>
            <i data-lucide="arrow-up-right" aria-hidden="true"></i>
          </button>
        </div>

        <div class="records-layout">
          <div class="records-table-panel" data-reveal>
            <?php if ($recentDocuments): ?>
              <table class="records-table">
                <thead>
                  <tr>
                    <th scope="col">Record ID</th>
                    <th scope="col">Document Category</th>
                    <th scope="col">Academic Year</th>
                    <th scope="col">Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($recentDocuments as $document): ?>
                    <tr>
                      <td data-label="Record ID">ZP-<?= str_pad((string) $document['id'], 6, '0', STR_PAD_LEFT) ?></td>
                      <td data-label="Document Category"><?= htmlspecialchars((string) ($document['category_name'] ?: $document['original_filename']), ENT_QUOTES, 'UTF-8') ?></td>
                      <td data-label="Academic Year"><?= htmlspecialchars((string) ($document['academic_year'] ?: date('Y')), ENT_QUOTES, 'UTF-8') ?></td>
                      <td data-label="Status">
                        <span class="status-pill <?= $statusClass((string) $document['status']) ?>">
                          <?= htmlspecialchars($displayStatus((string) $document['status']), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php else: ?>
              <div class="records-empty">
                <p>No public archive entries are visible yet, or the archive database is awaiting initial sync. Sign in to the Registrar portal to scan, classify, and index student documents.</p>
                <button class="button-primary" type="button" data-open-login>Sign in to upload records</button>
              </div>
            <?php endif; ?>
          </div>

          <aside class="activity-panel" aria-label="Recent archive activity" data-reveal style="--reveal-delay: 100ms">
            <h3>Latest indexing feed</h3>
            <?php if ($recentDocuments): ?>
              <ul class="activity-list">
                <?php foreach (array_slice($recentDocuments, 0, 3) as $index => $document): ?>
                  <li class="activity-item">
                    <span class="activity-badge <?= $index === 1 ? 'is-gold' : ($index === 2 ? 'is-stone' : 'is-maroon') ?>" aria-hidden="true">
                      <i data-lucide="<?= $index === 1 ? 'scan-line' : ($index === 2 ? 'file-text' : 'file-check-2') ?>"></i>
                    </span>
                    <div>
                      <strong><?= htmlspecialchars($displayStatus((string) $document['status']), ENT_QUOTES, 'UTF-8') ?></strong>
                      <p><?= htmlspecialchars((string) ($document['category_name'] ?: $document['original_filename']), ENT_QUOTES, 'UTF-8') ?></p>
                      <small>Academic Year <?= htmlspecialchars((string) ($document['academic_year'] ?: date('Y')), ENT_QUOTES, 'UTF-8') ?></small>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <p class="activity-empty">Recent scan, OCR verification, and gradesheet batch events appear here automatically once records are archived.</p>
            <?php endif; ?>
          </aside>
        </div>
      </div>
    </section>
  </main>

  <footer class="site-footer">
    <div class="container footer-grid">
      <div class="footer-brand">
        <strong>Zamboanga Peninsula Polytechnic State University</strong>
        <p>Office of the University Registrar · Digitalization Archiving Management System (DATS)</p>
      </div>
      <div class="footer-actions">
        <a href="#overview">Overview</a>
        <a href="#capabilities">Capabilities</a>
        <a href="#records">Records</a>
        <button class="footer-signin-link" type="button" data-open-login>Staff Sign in</button>
      </div>
    </div>
    <div class="container footer-meta">
      <p class="credits">Pictures: <a href="https://commons.wikimedia.org/wiki/File:Wroclaw_University_Library_digitizing_rare_archival_texts.jpg" target="_blank" rel="noopener">“Wroclaw University Library digitizing rare archival texts”</a> by j_cadmus, <a href="https://creativecommons.org/licenses/by/2.0" target="_blank" rel="noopener">CC BY 2.0</a>; <a href="https://commons.wikimedia.org/wiki/File:First-ICRC-records_ICRC-Archives_RomanDeckert09062020.jpg" target="_blank" rel="noopener">“First-ICRC-records ICRC-Archives”</a> by RomanDeckert, <a href="https://creativecommons.org/licenses/by-sa/4.0" target="_blank" rel="noopener">CC BY-SA 4.0</a>; <a href="https://commons.wikimedia.org/wiki/File:Beinecke_Rare_Book_%26_Manuscript_Library_Interior_(34254026911).jpg" target="_blank" rel="noopener">“Beinecke Rare Book &amp; Manuscript Library Interior”</a> by Gunnar Klack, <a href="https://creativecommons.org/licenses/by-sa/2.0" target="_blank" rel="noopener">CC BY-SA 2.0</a>; <a href="https://commons.wikimedia.org/wiki/File:Book_scanner_digitization_National_library_of_the_Czech_republic.jpg" target="_blank" rel="noopener">“Book scanner digitization National library of the Czech republic”</a> by Skot, <a href="https://creativecommons.org/licenses/by-sa/4.0" target="_blank" rel="noopener">CC BY-SA 4.0</a>, via Wikimedia Commons.</p>
      <p class="copyright">© 2026 ZPPSU Registrar's Office · Records Division</p>
    </div>
  </footer>

  <div class="login-backdrop" id="loginModal" role="dialog" aria-modal="true" aria-labelledby="loginTitle" hidden>
    <section class="login-modal">
      <button class="icon-button login-close" id="closeLoginButton" type="button" aria-label="Close login modal">
        <i data-lucide="x" aria-hidden="true"></i>
      </button>
      <div class="login-seal">
        <img src="img/ZPPSU.png" width="48" height="48" alt="ZPPSU seal" />
      </div>
      <h2 id="loginTitle">Welcome back.</h2>
      <p class="login-copy">Sign in with your Registrar account to continue to the ZPPSU records archive.</p>
      <form id="loginForm">
        <label for="emailInput">Work email</label>
        <div class="input-wrap">
          <i data-lucide="mail" aria-hidden="true"></i>
          <input id="emailInput" name="email" type="email" autocomplete="email" placeholder="name@zppsu.edu.ph" required />
        </div>

        <label for="passwordInput">Password</label>
        <div class="input-wrap">
          <i data-lucide="lock-keyhole" aria-hidden="true"></i>
          <input id="passwordInput" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" required />
          <button type="button" class="password-toggle" id="passwordToggle" aria-label="Show password">
            <i data-lucide="eye" aria-hidden="true"></i>
          </button>
        </div>

        <div class="form-options">
          <label class="check-label">
            <input type="checkbox" name="remember" />
            <span>Remember me</span>
          </label>
          <a href="mailto:registrar@zppsu.edu.ph?subject=DATS%20Password%20Reset">Forgot password?</a>
        </div>

        <button class="login-submit" type="submit">
          <span>Continue to workspace</span>
          <i data-lucide="arrow-right" aria-hidden="true"></i>
        </button>
      </form>
      <p class="login-help">Need account access? <a href="mailto:registrar@zppsu.edu.ph">Contact the Registrar's Office</a></p>
    </section>
  </div>

  <div class="toast" id="toast" role="status" aria-live="polite"></div>
  <script src="script.js"></script>
</body>
</html>
