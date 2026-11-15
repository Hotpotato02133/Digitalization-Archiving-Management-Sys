<?php
require_once __DIR__ . '/../auth.php';
dats_require_page_access(basename(__FILE__));

$currentUser = dats_current_user();
$studentId = isset($_GET['student_id']) ? (int) $_GET['student_id'] : 0;
$documentId = isset($_GET['document_id']) ? (int) $_GET['document_id'] : 0;

if ($studentId < 1 || $documentId < 1) {
    header('Location: files.php');
    exit;
}

$pdo = dats_db();
if (!$pdo) {
    header('Location: files.php');
    exit;
}

$studentStmt = $pdo->prepare('SELECT s.*, c.name AS college_name, co.name AS course_name FROM students s LEFT JOIN colleges c ON c.id = s.college_id LEFT JOIN courses co ON co.id = s.course_id WHERE s.id = :id LIMIT 1');
$studentStmt->execute([':id' => $studentId]);
$student = $studentStmt->fetch();

$documentStmt = $pdo->prepare('SELECT d.*, dc.name AS category_name FROM documents d LEFT JOIN document_categories dc ON dc.id = d.category_id WHERE d.id = :document_id AND d.student_id = :student_id LIMIT 1');
$documentStmt->execute([':document_id' => $documentId, ':student_id' => $studentId]);
$document = $documentStmt->fetch();

if (!$student || !$document) {
    header('Location: files.php');
    exit;
}

$previewUrl = '../api.php?resource=document-view&student_id=' . urlencode((string) $studentId) . '&document_id=' . urlencode((string) $documentId);
$fileName = trim((string) ($document['original_filename'] ?? 'Uploaded file'));
$fileType = trim((string) ($document['mime_type'] ?? 'Unknown'));
$lowerName = strtolower($fileName);
$lowerType = strtolower($fileType);
$isImage = $lowerType !== '' && (str_contains($lowerType, 'image') || preg_match('/\.(png|jpg|jpeg|gif|webp|bmp|svg)$/i', $lowerName) === 1);
$isPdf = $lowerType !== '' && (str_contains($lowerType, 'pdf') || str_ends_with($lowerName, '.pdf'));
$displayName = trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? ''));
$studentNumber = trim((string) ($student['student_number'] ?? ''));
$academicYear = trim((string) ($student['academic_year'] ?? ''));
$courseName = trim((string) ($student['course_name'] ?? 'N/A'));
$collegeName = trim((string) ($student['college_name'] ?? 'N/A'));
$uploadedAt = $document['uploaded_at'] ?? null;
$formattedUploaded = $uploadedAt ? date('F j, Y, g:i A', strtotime((string) $uploadedAt)) : 'Unknown';
$fileSize = isset($document['file_size_bytes']) ? (int) $document['file_size_bytes'] : 0;
$formattedSize = $fileSize > 0 ? number_format($fileSize / 1024, 2) . ' KB' : '—';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Student Record View | ZPPSU DATS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <style>
    :root {
      --maroon: #6b1d2a;
      --maroon-dark: #4a111b;
      --maroon-soft: #f6eaec;
      --maroon-border: #e4c6cb;
      --gold: #d6ad3a;
      --gold-pale: #fbf4df;
      --gold-ink: #7a5909;
      --ground: #f4f3f1;
      --surface: #ffffff;
      --raised: #f8f7f5;
      --text: #1c1917;
      --muted: #57534e;
      --border: #e5e2de;
      --rule: #efece9;
      --sans: 'Manrope', system-ui, sans-serif;
      --mono: 'DM Mono', ui-monospace, monospace;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      min-height: 100dvh;
      font-family: var(--sans);
      font-size: 14px;
      line-height: 1.5;
      background: var(--ground);
      color: var(--text);
    }
    .view-shell {
      max-width: 1240px;
      margin: 24px auto;
      padding: 0 20px 40px;
    }
    .topbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 20px;
      gap: 12px;
      flex-wrap: wrap;
    }
    .back-link {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      height: 36px;
      padding: 0 15px;
      border-radius: 8px;
      background: var(--gold);
      border: 1px solid #c59b2c;
      color: var(--maroon-dark);
      text-decoration: none;
      font-weight: 700;
      font-size: 13px;
      transition: background-color 0.15s ease, border-color 0.15s ease;
    }
    .back-link:hover {
      background: #dfba4e;
      border-color: #b78e24;
    }
    .page-title {
      margin: 0;
      font-size: clamp(20px, 2.2vw, 24px);
      font-weight: 700;
      letter-spacing: -0.02em;
      color: var(--text);
    }
    .record-layout {
      display: grid;
      grid-template-columns: minmax(0, 1.65fr) minmax(300px, 1fr);
      gap: 20px;
      align-items: start;
    }
    .panel {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 14px;
      overflow: hidden;
    }
    .preview-panel {
      padding: 16px;
      min-height: 700px;
    }
    .preview-box {
      width: 100%;
      min-height: 668px;
      background: #18181b;
      border: 1px solid var(--border);
      border-radius: 10px;
      overflow: hidden;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .preview-box img,
    .preview-box iframe {
      width: 100%;
      height: 668px;
      border: 0;
      object-fit: contain;
      background: var(--raised);
    }
    .preview-placeholder {
      display: grid;
      place-items: center;
      gap: 10px;
      text-align: center;
      color: #a1a1aa;
      padding: 24px;
    }
    .preview-placeholder strong {
      font-size: 16px;
      color: #ffffff;
    }
    .info-panel {
      padding: 24px;
      background: var(--surface);
    }
    .info-kicker {
      display: block;
      margin-bottom: 8px;
      color: var(--maroon);
      font: 600 11px/1.2 var(--mono);
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }
    .student-name {
      margin: 0 0 18px;
      font-size: clamp(20px, 2vw, 24px);
      font-weight: 700;
      letter-spacing: -0.02em;
      color: var(--text);
    }
    .info-grid {
      display: grid;
      gap: 14px;
    }
    .info-item {
      display: grid;
      gap: 4px;
      padding-bottom: 12px;
      border-bottom: 1px solid var(--rule);
    }
    .info-item:last-child {
      border-bottom: 0;
      padding-bottom: 0;
    }
    .info-item span {
      color: var(--muted);
      font: 600 11px/1.2 var(--mono);
      letter-spacing: 0.06em;
      text-transform: uppercase;
    }
    .info-item strong {
      font-size: 14px;
      font-weight: 600;
      line-height: 1.45;
      color: var(--text);
      word-break: break-word;
    }
    .meta-box {
      margin-top: 20px;
      padding: 16px;
      background: var(--gold-pale);
      border: 1px solid #ead89c;
      border-radius: 10px;
    }
    .meta-box h3 {
      margin: 0 0 6px;
      font: 600 11px/1.2 var(--mono);
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--gold-ink);
    }
    .meta-box p {
      margin: 0;
      color: var(--text);
      line-height: 1.55;
      font-size: 13px;
    }
    @media (max-width: 900px) {
      .view-shell { margin: 16px auto; padding: 0 16px 32px; }
      .record-layout { grid-template-columns: minmax(0, 1fr); }
      .preview-panel { min-height: unset; }
      .preview-box,
      .preview-box img,
      .preview-box iframe {
        min-height: 400px;
        height: 400px;
      }
    }
  </style>
</head>
<body>
  <div class="view-shell">
    <div class="topbar">
      <a class="back-link" href="files.php">← Back to Files</a>
      <h1 class="page-title">Student record</h1>
    </div>

    <div class="record-layout">
      <section class="panel preview-panel" aria-label="Document preview">
        <div class="preview-box">
          <?php if ($isImage): ?>
            <img src="<?= htmlspecialchars($previewUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($fileName, ENT_QUOTES, 'UTF-8') ?>" />
          <?php elseif ($isPdf): ?>
            <iframe src="<?= htmlspecialchars($previewUrl, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($fileName, ENT_QUOTES, 'UTF-8') ?>"></iframe>
          <?php else: ?>
            <div class="preview-placeholder">
              <div style="width: 64px; height: 64px; border: 2px solid var(--maroon); border-radius: 14px; display:grid; place-items:center; font-size: 28px;">📄</div>
              <strong><?= htmlspecialchars($fileName, ENT_QUOTES, 'UTF-8') ?></strong>
              <span>Preview is not available for this file type.</span>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <aside class="panel info-panel" aria-label="Student details">
        <span class="info-kicker">Student information</span>
        <h2 class="student-name"><?= htmlspecialchars($displayName ?: 'Unknown student', ENT_QUOTES, 'UTF-8') ?></h2>

        <div class="info-grid">
          <div class="info-item">
            <span>Student ID</span>
            <strong><?= htmlspecialchars($studentNumber ?: '—', ENT_QUOTES, 'UTF-8') ?></strong>
          </div>
          <div class="info-item">
            <span>Academic year</span>
            <strong><?= htmlspecialchars($academicYear ?: '—', ENT_QUOTES, 'UTF-8') ?></strong>
          </div>
          <div class="info-item">
            <span>Course</span>
            <strong><?= htmlspecialchars($courseName ?: '—', ENT_QUOTES, 'UTF-8') ?></strong>
          </div>
          <div class="info-item">
            <span>College</span>
            <strong><?= htmlspecialchars($collegeName ?: '—', ENT_QUOTES, 'UTF-8') ?></strong>
          </div>
          <div class="info-item">
            <span>Document</span>
            <strong><?= htmlspecialchars($fileName ?: 'Uploaded file', ENT_QUOTES, 'UTF-8') ?></strong>
          </div>
          <div class="info-item">
            <span>Category</span>
            <strong><?= htmlspecialchars((string) ($document['category_name'] ?? 'Uncategorized'), ENT_QUOTES, 'UTF-8') ?></strong>
          </div>
          <div class="info-item">
            <span>File type</span>
            <strong><?= htmlspecialchars($fileType ?: 'Unknown', ENT_QUOTES, 'UTF-8') ?></strong>
          </div>
          <div class="info-item">
            <span>Uploaded</span>
            <strong><?= htmlspecialchars($formattedUploaded, ENT_QUOTES, 'UTF-8') ?></strong>
          </div>
          <div class="info-item">
            <span>File size</span>
            <strong><?= htmlspecialchars($formattedSize, ENT_QUOTES, 'UTF-8') ?></strong>
          </div>
        </div>

      </aside>
    </div>
  </div>
  <script src="../data-sync.js?v=<?= filemtime(__DIR__ . '/../data-sync.js') ?>"></script>
</body>
</html>
