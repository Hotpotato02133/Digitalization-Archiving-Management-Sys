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
      --maroon-soft: #f6eaec;
      --maroon-border: #e4c6cb;
      --gold: #d6ad3a;
      --gold-pale: #fbf4df;
      --ground: #f4f3f1;
      --surface: #ffffff;
      --raised: #f8f7f5;
      --text: #1c1917;
      --muted: #57534e;
      --border: #e5e2de;
      --rule: #efece9;
      --control: #d2ceca;
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
    .view-shell { max-width: 1140px; margin: 24px auto; padding: 0 20px 44px; }
    .topbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; gap: 12px; flex-wrap: wrap; }
    .back-link {
      display: inline-flex; align-items: center; justify-content: center; gap: 6px;
      height: 36px; padding: 0 15px; border-radius: 8px; background: var(--gold);
      border: 1px solid #c59b2c; color: var(--maroon-dark);
      text-decoration: none; font-weight: 700; font-size: 13px;
      transition: background-color 0.15s ease, border-color 0.15s ease;
    }
    .back-link:hover { background: #dfba4e; border-color: #b78e24; }
    .page-title { margin: 0; font-size: clamp(20px, 2.2vw, 24px); font-weight: 700; letter-spacing: -0.02em; color: var(--text); }
    .panel {
      background: var(--surface); border: 1px solid var(--border); border-radius: 14px;
      padding: 22px; margin-bottom: 18px; overflow-x: auto;
    }
    .student-summary { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
    .student-name { margin: 0 0 6px; font-size: clamp(20px, 2.2vw, 24px); font-weight: 700; letter-spacing: -0.02em; color: var(--text); }
    .student-meta { display: flex; flex-wrap: wrap; gap: 10px 20px; color: var(--muted); font-size: 13px; }
    .student-meta strong { color: var(--text); font-weight: 600; }
    .semester-heading { display: flex; align-items: baseline; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin: 0 0 14px; }
    .semester-heading h3 { margin: 0; font-size: 16px; font-weight: 700; color: var(--maroon-dark); }
    .semester-heading span { font: 600 11px/1.2 var(--mono); letter-spacing: 0.06em; text-transform: uppercase; color: var(--muted); }
    table.gradesheet-table { width: 100%; min-width: 620px; border-collapse: collapse; font-size: 13px; font-variant-numeric: tabular-nums; }
    table.gradesheet-table th {
      text-align: left; padding: 11px 12px; color: var(--muted); background: var(--raised);
      font: 600 12px/1.25 var(--sans); border-bottom: 1px solid var(--border); white-space: nowrap;
    }
    table.gradesheet-table td { padding: 11px 12px; border-bottom: 1px solid var(--rule); color: var(--text); }
    table.gradesheet-table tbody tr:hover td { background: var(--raised); }
    table.gradesheet-table tr:last-child td { border-bottom: 0; }
    .row-actions { display: flex; gap: 6px; justify-content: flex-end; }
    .row-actions button {
      display: grid; place-items: center; width: 30px; height: 30px; padding: 0;
      color: var(--muted); background: var(--surface); border: 1px solid var(--control); border-radius: 6px; cursor: pointer;
    }
    .row-actions button:hover { color: var(--maroon); border-color: var(--maroon-border); background: var(--maroon-soft); }
    .row-actions button svg { width: 14px; height: 14px; }
    .semester-average { margin-top: 12px; text-align: right; font-size: 13px; color: var(--muted); font-variant-numeric: tabular-nums; }
    .semester-average strong { color: var(--maroon-dark); font-size: 14px; }
    .empty-state { text-align: center; padding: 48px 20px; color: var(--muted); font-size: 14px; }
    .empty-state strong { display: block; margin-bottom: 8px; color: var(--text); font-size: 16px; }
    .edit-field { width: 100%; min-height: 32px; padding: 6px 8px; border: 1px solid var(--control); border-radius: 6px; font: 13px var(--sans); color: var(--text); background: var(--surface); }
    .edit-field:focus { outline: 0; border-color: var(--maroon); }
    .admin-toast {
      position: fixed; left: 50%; bottom: 24px; transform: translate(-50%, 16px);
      padding: 12px 18px; background: var(--text); color: #ffffff; border-left: 3px solid var(--gold); border-radius: 8px;
      font-size: 13px; font-weight: 500; opacity: 0; pointer-events: none; transition: opacity 0.2s ease, transform 0.2s ease;
      z-index: 30; box-shadow: 0 12px 28px rgba(28, 25, 23, 0.22);
    }
    .admin-toast.show { opacity: 1; transform: translate(-50%, 0); }
    @media (max-width: 640px) {
      .view-shell { margin: 16px auto; padding: 0 14px 36px; }
      .panel { padding: 16px; }
    }
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
