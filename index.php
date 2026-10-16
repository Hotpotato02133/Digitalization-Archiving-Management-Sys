<?php
require_once __DIR__ . '/config/database.php';
$pdo = dats_db();
$archiveStats = ['total_documents' => 0, 'completed_documents' => 0];
$recentDocuments = [];
if ($pdo) {
  $archiveStats = $pdo->query('SELECT total_documents, completed_documents FROM vw_archive_analytics')->fetch() ?: $archiveStats;
  $recentDocuments = $pdo->query("SELECT d.id, d.original_filename, d.academic_year, d.status, COALESCE(c.name, 'Uncategorized') AS category_name FROM documents d LEFT JOIN document_categories c ON c.id = d.category_id WHERE d.deleted_at IS NULL ORDER BY d.uploaded_at DESC LIMIT 4")->fetchAll();
}
$totalDocuments = (int) ($archiveStats['total_documents'] ?? 0);
$completedDocuments = (int) ($archiveStats['completed_documents'] ?? 0);
$digitizedPercent = $totalDocuments > 0 ? round(($completedDocuments / $totalDocuments) * 100, 1) : 0;
$displayStatus = static fn (string $status): string => in_array($status, ['Uploaded', 'Archived'], true) ? 'Verified' : $status;
$statusClass = static fn (string $status): string => in_array($status, ['Uploaded', 'Archived'], true) ? 'success' : ($status === 'Processing' ? 'pending' : 'review');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="Digitalization Archiving Management System for the ZPPSU Registrar's Office." />
  <title>Digitalization Archiving Management System | ZPPSU Registrar</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="styles.css" />
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
  <div class="page-shell">
    <header class="topbar">
      <a class="brand" href="#top" aria-label="ZPPSU Registrar home">
        <img class="brand-mark" src="img/ZPPSU.png" alt="Zamboanga Peninsula Polytechnic State University seal" />
        <span>
          <strong>ZPPSU</strong>
          <small>Registrar's Office</small>
        </span>
      </a>
      <nav class="nav-links" aria-label="Main navigation">
        <a class="active" href="#overview">Overview</a>
        <a href="#services">Services</a>
        <a href="#records">Records</a>
      </nav>
      <button class="icon-button menu-button" aria-label="Open navigation menu" aria-expanded="false"><i data-lucide="menu"></i></button>
      <button class="outline-button" id="signInButton"><i data-lucide="log-in"></i> Login</button>
    </header>

    <main id="top">
      <section class="hero" id="overview">
        <div class="hero-copy">
          <div class="eyebrow"><span class="status-dot"></span> Registrar's digital command center</div>
          <h1>Records that move<br /><em>with you.</em></h1>
          <p class="hero-intro">A secure, searchable home for every academic record at Zamboanga Peninsula Polytechnic State University.</p>
          <div class="hero-actions">
            <a class="primary-button" href="#records">Explore the archive <i data-lucide="arrow-up-right"></i></a>
            <a class="text-button" href="#services">View services <i data-lucide="arrow-down"></i></a>
          </div>
          <div class="hero-note"><i data-lucide="shield-check"></i><span>Protected by ZPPSU data privacy standards</span></div>
        </div>

        <div class="dashboard-card" aria-label="Archive dashboard preview">
          <div class="dashboard-heading">
            <div>
              <span class="label">Archive overview</span>
              <h2>Good morning, Registrar.</h2>
            </div>
            <button class="icon-button" aria-label="More dashboard options"><i data-lucide="more-horizontal"></i></button>
          </div>
          <div class="metric-grid">
            <div class="metric-card metric-primary"><span>Total records</span><strong><?= number_format($totalDocuments) ?></strong><small><b>SQL synced</b> in the archive</small><div class="sparkline"><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span></div></div>
            <div class="metric-card"><span>Digitized</span><strong><?= $digitizedPercent ?>%</strong><small>of total archive</small><div class="progress-ring"><span><?= round($digitizedPercent) ?>%</span></div></div>
          </div>
          <div class="dashboard-footer"><span><i data-lucide="check-circle-2"></i> System operational</span><span class="mono" data-current-datetime>Loading date and time...</span></div>
        </div>
      </section>

      <section class="service-strip" id="services">
        <div class="section-intro"><span class="label">What you can do</span><h2>One place for<br />every record.</h2></div>
        <div class="service-list">
          <a class="service-item" href="#records"><span class="service-number">01</span><span><strong>Find a record</strong><small>Search the university archive</small></span><i data-lucide="arrow-up-right"></i></a>
          <a class="service-item" href="#records"><span class="service-number">02</span><span><strong>Request documents</strong><small>Transcripts, certifications, more</small></span><i data-lucide="arrow-up-right"></i></a>
          <a class="service-item" href="#records"><span class="service-number">03</span><span><strong>Track a request</strong><small>Follow progress in real time</small></span><i data-lucide="arrow-up-right"></i></a>
        </div>
      </section>

      <section class="records-section" id="records">
        <div class="records-heading"><div><span class="label">Live archive pulse</span><h2>Recently indexed</h2></div><a class="text-button" href="#records">View all records <i data-lucide="arrow-right"></i></a></div>
        <div class="records-layout">
          <div class="records-table-wrap">
            <table>
              <thead><tr><th>Record ID</th><th>Document Category</th><th>Year</th><th>Status</th></tr></thead>
              <tbody><?php foreach ($recentDocuments as $document): ?><tr><td class="mono">ZP-<?= str_pad((string) $document['id'], 6, '0', STR_PAD_LEFT) ?></td><td><?= htmlspecialchars((string) ($document['category_name'] ?: $document['original_filename']), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string) ($document['academic_year'] ?: date('Y')), ENT_QUOTES, 'UTF-8') ?></td><td><span class="pill <?= $statusClass((string) $document['status']) ?>"><?= htmlspecialchars($displayStatus((string) $document['status']), ENT_QUOTES, 'UTF-8') ?></span></td></tr><?php endforeach; ?><?php if (!$recentDocuments): ?><tr><td colspan="4">No archive records available.</td></tr><?php endif; ?></tbody>
            </table>
          </div>
          <aside class="activity-card"><span class="label">Latest activity</span><?php foreach (array_slice($recentDocuments, 0, 3) as $index => $document): ?><div class="activity-item"><span class="activity-icon<?= $index === 1 ? ' amber' : ($index === 2 ? ' blue' : '') ?>"><i data-lucide="<?= $index === 1 ? 'scan-line' : ($index === 2 ? 'file-text' : 'file-check-2') ?>"></i></span><p><strong><?= htmlspecialchars($displayStatus((string) $document['status']), ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars((string) ($document['category_name'] ?: $document['original_filename']), ENT_QUOTES, 'UTF-8') ?><br /><span>Recently indexed</span></small></p></div><?php endforeach; ?><?php if (!$recentDocuments): ?><p>No recent activity.</p><?php endif; ?></aside>
        </div>
      </section>
    </main>
    <footer><span>© 2026 Zamboanga Peninsula Polytechnic State University</span><span>Registrar's Office <b>·</b> Records Division</span></footer>
  </div>
  <div class="login-backdrop" id="loginModal" role="dialog" aria-modal="true" aria-labelledby="loginTitle" hidden>
    <section class="login-modal">
      <button class="icon-button login-close" id="closeLoginButton" aria-label="Close login"><i data-lucide="x"></i></button>
      <div class="login-seal"><img src="img/ZPPSU.png" alt="" /></div>
      <span class="label">Digital archive access</span>
      <h2 id="loginTitle">Welcome back.</h2>
      <p class="login-copy">Sign in to continue to the ZPPSU records system.</p>
      <form id="loginForm">
        <label for="emailInput">Work email</label>
        <div class="input-wrap"><i data-lucide="mail"></i><input id="emailInput" type="email" placeholder="name@zppsu.edu.ph" required /></div>
        <label for="passwordInput">Password</label>
        <div class="input-wrap"><i data-lucide="lock-keyhole"></i><input id="passwordInput" type="password" placeholder="Enter your password" required /><button type="button" class="password-toggle" id="passwordToggle" aria-label="Show password"><i data-lucide="eye"></i></button></div>
        <div class="form-options"><label class="check-label"><input type="checkbox" /> <span>Remember me</span></label><a href="#loginModal">Forgot password?</a></div>
        <button class="login-submit" type="submit">Continue <i data-lucide="arrow-right"></i></button>
      </form>
      <p class="login-help">Need access help? <a href="mailto:registrar@zppsu.edu.ph">Contact the Registrar's Office</a></p>
    </section>
  </div>
  <div class="toast" id="toast" role="status"></div>
  <script src="script.js"></script>
</body>
</html>

