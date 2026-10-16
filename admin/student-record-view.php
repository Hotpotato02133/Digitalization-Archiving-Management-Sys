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
      --gold: #d6ad3a;
      --gold-pale: #f5ebd1;
      --paper: #f8f5f2;
      --ink: #2a171a;
      --muted: #6a5f5d;
      --line: #e8dedb;
      --white: #ffffff;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      min-height: 100vh;
      font-family: 'Manrope', sans-serif;
      background: linear-gradient(180deg, #f8f3ee 0%, #f2e9e4 100%);
      color: var(--ink);
    }
    .view-shell {
      max-width: 1280px;
      margin: 32px auto;
      padding: 0 20px 48px;
    }
    .topbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 24px;
      gap: 12px;
      flex-wrap: wrap;
    }
    .back-link {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      padding: 8px 12px;
      border-radius: 8px;
      background: var(--gold);
      border: 1px solid rgba(214,173,58,0.9);
      color: var(--maroon-dark);
      text-decoration: none;
      font-weight: 800;
      font-size: 11px;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      box-shadow: 0 8px 18px rgba(214,173,58,0.2);
      transition: transform 0.18s ease, box-shadow 0.18s ease, filter 0.18s ease;
    }
    .back-link:hover {
      transform: translateY(-1px);
      box-shadow: 0 10px 22px rgba(214,173,58,0.28);
      filter: brightness(0.98);
    }
    .page-title {
      margin: 0;
      font-size: clamp(24px, 3vw, 36px);
      letter-spacing: -0.05em;
      color: var(--maroon-dark);
    }
    .record-layout {
      display: grid;
      grid-template-columns: minmax(0, 1.7fr) minmax(300px, 0.9fr);
      gap: 24px;
      align-items: start;
    }
    .panel {
      background: rgba(255,255,255,0.7);
      border: 1px solid var(--line);
      border-radius: 22px;
      box-shadow: 0 18px 42px rgba(59, 38, 26, 0.07);
      overflow: hidden;
      backdrop-filter: blur(4px);
    }
    .preview-panel {
      padding: 18px;
      min-height: 760px;
    }
    .preview-box {
      width: 100%;
      min-height: 710px;
      background: linear-gradient(180deg, #1e2a31 0%, #11171c 100%);
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 16px;
      overflow: hidden;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: inset 0 0 0 1px rgba(255,255,255,0.04);
    }
    .preview-box img,
    .preview-box iframe {
      width: 100%;
      height: 710px;
      border: 0;
      background: #f6f4f3;
    }
    .preview-placeholder {
      display: grid;
      place-items: center;
      gap: 12px;
      text-align: center;
      color: var(--muted);
      padding: 24px;
    }
    .preview-placeholder strong {
      font-size: 18px;
      color: var(--ink);
    }
    .info-panel {
      padding: 26px 24px 24px;
      background: rgba(249, 245, 240, 0.96);
    }
    .info-kicker {
      display: block;
      margin-bottom: 12px;
      color: var(--maroon);
      font-size: 10px;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      font-weight: 800;
    }
    .student-name {
      margin: 0 0 20px;
      font-size: clamp(24px, 2vw, 30px);
      letter-spacing: -0.04em;
      color: var(--ink);
    }
    .info-grid {
      display: grid;
      gap: 18px;
    }
    .info-item {
      display: grid;
      gap: 8px;
      padding-bottom: 14px;
      border-bottom: 1px solid var(--line);
    }
    .info-item:last-child {
      border-bottom: 0;
      padding-bottom: 0;
    }
    .info-item span {
      color: var(--muted);
      font-size: 10px;
      font-family: 'DM Mono', monospace;
      letter-spacing: 0.12em;
      text-transform: uppercase;
    }
    .info-item strong {
      font-size: 16px;
      line-height: 1.5;
      word-break: break-word;
    }
    .meta-box {
      margin-top: 22px;
      padding: 16px 18px;
      background: var(--gold-pale);
      border: 1px solid rgba(214,173,58,0.6);
      border-radius: 14px;
    }
    .meta-box h3 {
      margin: 0 0 10px;
      font-size: 11px;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      color: var(--maroon);
    }
    .meta-box p {
      margin: 0;
      color: var(--ink);
      line-height: 1.6;
      font-size: 15px;
    }
    @media (max-width: 900px) {
      .record-layout {
        grid-template-columns: 1fr;
      }
      .preview-panel {
        min-height: unset;
      }
      .preview-box,
      .preview-box img,
      .preview-box iframe {
        min-height: 420px;
        height: 420px;
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
