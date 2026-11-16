<?php
require_once __DIR__ . '/../auth.php';
dats_require_page_access(basename(__FILE__));

$studentId = isset($_GET['student_id']) ? (int) $_GET['student_id'] : 0;

if ($studentId < 1) {
    header('Location: students.php');
    exit;
}

$pdo = dats_db();
if (!$pdo) {
    header('Location: students.php');
    exit;
}

$studentStmt = $pdo->prepare('SELECT s.*, c.name AS college_name, co.name AS course_name FROM students s LEFT JOIN colleges c ON c.id = s.college_id LEFT JOIN courses co ON co.id = s.course_id WHERE s.id = :id AND s.deleted_at IS NULL LIMIT 1');
$studentStmt->execute([':id' => $studentId]);
$student = $studentStmt->fetch();

if (!$student) {
    header('Location: students.php');
    exit;
}

$displayName = trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? ''));
$studentNumber = trim((string) ($student['student_number'] ?? ''));
$courseName = trim((string) ($student['course_name'] ?? 'N/A'));
$collegeName = trim((string) ($student['college_name'] ?? 'N/A'));

$matchingNames = array_values(array_unique(array_filter([
    trim((string) ($student['last_name'] ?? '') . ', ' . (string) ($student['first_name'] ?? '') . (!empty($student['middle_initial']) ? ' ' . $student['middle_initial'] : '')),
    trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? '')),
    trim((string) ($student['last_name'] ?? '')),
    $displayName,
], static fn ($name) => $name !== '')));

$params = [':student_id' => $studentId];
$conditions = ['student_id = :student_id'];
foreach ($matchingNames as $index => $name) {
    $placeholder = ':name_' . $index;
    $conditions[] = '(student_id IS NULL AND student_name = ' . $placeholder . ')';
    $params[$placeholder] = $name;
}
if ($studentNumber !== '') {
    $conditions[] = '(student_id IS NULL AND student_number = :student_number)';
    $params[':student_number'] = $studentNumber;
}

$entriesStmt = $pdo->prepare(
    'SELECT * FROM gradesheet_entries WHERE confirmed = 1 AND (' . implode(' OR ', $conditions) . ')' .
    " ORDER BY academic_year, FIELD(semester, '1st Semester', '2nd Semester', 'Summer', 'Midyear'), subject_code"
);
$entriesStmt->execute($params);
$entries = $entriesStmt->fetchAll();

$groups = [];
foreach ($entries as $entry) {
    $key = (string) ($entry['academic_year'] ?? '') . '|' . (string) ($entry['semester'] ?? '');
    if (!isset($groups[$key])) {
        $groups[$key] = ['academic_year' => $entry['academic_year'], 'semester' => $entry['semester'], 'rows' => []];
    }
    $groups[$key]['rows'][] = $entry;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Digitized Gradesheet | ZPPSU DATS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <script src="https://unpkg.com/lucide@latest"></script>
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
    .view-shell { max-width: 1100px; margin: 32px auto; padding: 0 20px 48px; }
    .topbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; gap: 12px; flex-wrap: wrap; }
    .back-link {
      display: inline-flex; align-items: center; justify-content: center; gap: 6px;
      padding: 8px 12px; border-radius: 8px; background: var(--gold);
      border: 1px solid rgba(214,173,58,0.9); color: var(--maroon-dark);
      text-decoration: none; font-weight: 800; font-size: 11px; letter-spacing: 0.04em;
      text-transform: uppercase; box-shadow: 0 8px 18px rgba(214,173,58,0.2);
    }
    .page-title { margin: 0; font-size: clamp(22px, 3vw, 32px); letter-spacing: -0.05em; color: var(--maroon-dark); }
    .panel {
      background: rgba(255,255,255,0.7); border: 1px solid var(--line); border-radius: 22px;
      box-shadow: 0 18px 42px rgba(59, 38, 26, 0.07); backdrop-filter: blur(4px);
      padding: 24px; margin-bottom: 20px;
    }
    .student-summary { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
    .student-name { margin: 0 0 6px; font-size: clamp(22px, 2.4vw, 28px); letter-spacing: -0.04em; }
    .student-meta { display: flex; flex-wrap: wrap; gap: 16px; color: var(--muted); font-size: 12px; }
    .student-meta strong { color: var(--ink); }
    .semester-heading { display: flex; align-items: baseline; justify-content: space-between; gap: 10px; margin: 0 0 14px; }
    .semester-heading h3 { margin: 0; font-size: 16px; color: var(--maroon-dark); }
    .semester-heading span { font: 700 10px var(--mono); letter-spacing: .08em; text-transform: uppercase; color: var(--muted); }
    table.gradesheet-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    table.gradesheet-table th {
      text-align: left; padding: 10px 8px; color: var(--muted); font: 700 10px var(--mono);
      letter-spacing: .06em; text-transform: uppercase; border-bottom: 2px solid var(--line);
    }
    table.gradesheet-table td { padding: 9px 8px; border-bottom: 1px solid var(--line); }
    table.gradesheet-table tr:last-child td { border-bottom: 0; }
    .row-actions { display: flex; gap: 6px; }
    .row-actions button {
      display: grid; place-items: center; width: 26px; height: 26px; padding: 0;
      color: var(--maroon); background: transparent; border: 1px solid var(--line); cursor: pointer;
    }
    .row-actions button svg { width: 13px; height: 13px; }
    .semester-average { margin-top: 10px; text-align: right; font-size: 12px; color: var(--muted); }
    .semester-average strong { color: var(--ink); }
    .empty-state { text-align: center; padding: 48px 20px; color: var(--muted); }
    .empty-state strong { display: block; margin-bottom: 8px; color: var(--ink); font-size: 16px; }
    .edit-field { width: 100%; padding: 6px; border: 1px solid var(--line); font: 12px var(--sans); }
    .admin-toast {
      position: fixed; left: 50%; bottom: 24px; transform: translate(-50%, 20px);
      padding: 12px 18px; background: var(--maroon-dark); color: #fff; border-radius: 8px;
      font-size: 12px; opacity: 0; pointer-events: none; transition: opacity .2s ease, transform .2s ease;
      z-index: 30;
    }
    .admin-toast.show { opacity: 1; transform: translate(-50%, 0); }
  </style>
</head>
<body>
  <div class="view-shell">
    <div class="topbar">
      <a class="back-link" href="students.php">← Back to Students</a>
      <h1 class="page-title">Digitized gradesheet</h1>
    </div>

    <section class="panel student-summary">
      <div>
        <h2 class="student-name"><?= htmlspecialchars($displayName ?: 'Unknown student', ENT_QUOTES, 'UTF-8') ?></h2>
        <div class="student-meta">
          <span>Student ID <strong><?= htmlspecialchars($studentNumber ?: '—', ENT_QUOTES, 'UTF-8') ?></strong></span>
          <span>Course <strong><?= htmlspecialchars($courseName ?: '—', ENT_QUOTES, 'UTF-8') ?></strong></span>
          <span>College <strong><?= htmlspecialchars($collegeName ?: '—', ENT_QUOTES, 'UTF-8') ?></strong></span>
        </div>
      </div>
    </section>

    <?php if (!$groups): ?>
      <section class="panel empty-state">
        <strong>No digitized grades yet</strong>
        <p>Upload a gradesheet or Report of Rating that lists this student on the Gradesheets page to build their transcript.</p>
      </section>
    <?php else: foreach ($groups as $group): ?>
      <section class="panel" data-semester-panel>
        <div class="semester-heading">
          <h3><?= htmlspecialchars($group['semester'], ENT_QUOTES, 'UTF-8') ?></h3>
          <span>A.Y. <?= htmlspecialchars((string) $group['academic_year'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <table class="gradesheet-table">
          <thead><tr><th>Code</th><th>Subject title</th><th>Units</th><th>Grade</th><th>Remarks</th><th></th></tr></thead>
          <tbody>
            <?php $unitSum = 0.0; $weightedSum = 0.0; ?>
            <?php foreach ($group['rows'] as $row): ?>
              <?php
                $gradeValue = (string) ($row['grade'] ?? '');
                $units = $row['units'] !== null ? (float) $row['units'] : null;
                if ($units !== null && is_numeric($gradeValue)) {
                    $unitSum += $units;
                    $weightedSum += $units * (float) $gradeValue;
                }
              ?>
              <tr data-entry-id="<?= (int) $row['id'] ?>" data-academic-year="<?= htmlspecialchars((string) $row['academic_year'], ENT_QUOTES, 'UTF-8') ?>" data-semester="<?= htmlspecialchars((string) $row['semester'], ENT_QUOTES, 'UTF-8') ?>">
                <td><?= htmlspecialchars((string) $row['subject_code'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) $row['subject_name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($units !== null ? rtrim(rtrim(number_format($units, 2), '0'), '.') : '—', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($gradeValue ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($row['remarks'] ?? '') ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                <td class="row-actions">
                  <button type="button" data-edit-entry aria-label="Edit row"><i data-lucide="pencil"></i></button>
                  <button type="button" data-delete-entry aria-label="Delete row"><i data-lucide="trash-2"></i></button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php if ($unitSum > 0): ?>
          <div class="semester-average">Semester average (numeric grades only): <strong><?= htmlspecialchars(number_format($weightedSum / $unitSum, 2), ENT_QUOTES, 'UTF-8') ?></strong></div>
        <?php endif; ?>
      </section>
    <?php endforeach; endif; ?>
  </div>
  <div class="admin-toast" id="gradesheetToast" role="status"></div>
  <script src="../data-sync.js?v=<?= filemtime(__DIR__ . '/../data-sync.js') ?>"></script>
  <script>
    const toastEl = document.querySelector('#gradesheetToast');
    const showToast = (message) => {
      if (!toastEl) return;
      toastEl.textContent = message;
      toastEl.classList.add('show');
      window.setTimeout(() => toastEl.classList.remove('show'), 3000);
    };

    const postGradesheetAction = async (payload) => {
      try {
        const response = await fetch('../api.php?resource=gradesheets', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify(payload),
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok) return { ok: false, error: result.error || 'The server rejected this change.' };
        return { ok: true, data: result.data };
      } catch (error) {
        return { ok: false, error: 'Unable to reach the server. Check your connection and try again.' };
      }
    };

    document.querySelectorAll('[data-delete-entry]').forEach((button) => {
      button.addEventListener('click', async () => {
        const row = button.closest('tr');
        const entryId = row?.dataset.entryId;
        if (!entryId || !window.confirm('Remove this grade row?')) return;
        button.disabled = true;
        const result = await postGradesheetAction({ action: 'delete-entry', id: entryId });
        button.disabled = false;
        if (!result.ok) { showToast(result.error); return; }
        row.remove();
        showToast('Grade row removed.');
      });
    });

    document.querySelectorAll('[data-edit-entry]').forEach((button) => {
      button.addEventListener('click', async () => {
        const row = button.closest('tr');
        const entryId = row?.dataset.entryId;
        // Read the row's own stored academic year/semester rather than scraping the panel
        // heading text, so this can't be thrown off by heading wording changes.
        const academicYear = row.dataset.academicYear || '';
        const semester = row.dataset.semester || '';
        const cells = row.querySelectorAll('td');
        const cellText = (cell) => (cell.textContent.trim() === '—' ? '' : cell.textContent.trim());

        const subjectCode = window.prompt('Subject code', cellText(cells[0]));
        if (subjectCode === null) return;
        if (!subjectCode.trim()) { showToast('Subject code is required.'); return; }

        const subjectName = window.prompt('Subject title', cellText(cells[1]));
        if (subjectName === null) return;
        if (!subjectName.trim()) { showToast('Subject title is required.'); return; }

        const unitsInput = window.prompt('Units (blank allowed)', cellText(cells[2]));
        if (unitsInput === null) return;
        if (unitsInput.trim() && (Number.isNaN(Number(unitsInput)) || Number(unitsInput) < 0 || Number(unitsInput) > 99.99)) {
          showToast('Units must be a number between 0 and 99.99.');
          return;
        }

        const grade = window.prompt('Grade', cellText(cells[3]));
        if (grade === null) return;

        const remarks = window.prompt('Remarks', cellText(cells[4]));
        if (remarks === null) return;

        button.disabled = true;
        const result = await postGradesheetAction({
          action: 'update-entry', id: entryId, academic_year: academicYear, semester,
          subject_code: subjectCode, subject_name: subjectName, units: unitsInput, grade, remarks,
        });
        button.disabled = false;
        if (!result.ok) { showToast(result.error); return; }
        window.location.reload();
      });
    });

    if (window.lucide) lucide.createIcons();
  </script>
</body>
</html>
