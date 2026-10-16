const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const flow = require('../../admin/gradesheet-flow.js');

test('page matcher finds exact page numbers despite case, spacing, punctuation, and name order', () => {
  const ocr = 'Subject list\nREYES, ANA M.\nIT 101\fOther page\nCRUZ, BEA';
  assert.deepEqual(flow.findStudentPageNumbers(ocr, ['Reyes, Ana M']), [1]);
  assert.deepEqual(flow.findStudentPageNumbers(ocr, ['ana   m reyes']), [1]);
  assert.deepEqual(flow.findStudentPageNumbers('REYES, ANA\fOther', ['Reyes, Ana M']), [1]);
  assert.deepEqual(flow.findStudentPageNumbers(ocr, ['Reyes, Bea M']), []);
});

test('page matcher handles multiple matching pages and rejects short ambiguous names', () => {
  assert.deepEqual(flow.findStudentPageNumbers('LEE, JO\fLEE, JO', ['Lee, Jo']), []);
  assert.deepEqual(flow.findStudentPageNumbers('REYES, ANA M\fOther\fANA M REYES', ['Reyes, Ana M']), [1, 3]);
});

test('record matcher selects only pages with the student and selected subject', () => {
  const text = 'REYES, ANA M. MATH 101 ALGEBRA\fREYES, ANA M. ENG 202 COMPOSITION\fREYES, ANA M. MATH 101 ALGEBRA';
  assert.deepEqual(flow.findStudentRecordPageNumbers(text, ['Reyes, Ana M'], {
    subject_code: 'MATH 101', subject_name: 'Algebra',
  }), [1, 3]);
  assert.deepEqual(flow.findStudentRecordPageNumbers(text, ['Reyes, Ana M'], {
    subject_code: 'ENG 202', subject_name: 'Composition',
  }), [2]);
});

test('View passes the selected student and record source IDs to verified API routes', () => {
  const root = path.resolve(__dirname, '../..');
  const client = fs.readFileSync(path.join(root, 'admin/gradesheets.js'), 'utf8');
  const api = fs.readFileSync(path.join(root, 'api.php'), 'utf8');
  const parser = fs.readFileSync(path.join(root, 'lib/gradesheet-parser.php'), 'utf8');
  assert.match(client, /entry_id: String\(entry\.id \|\| 0\)/);
  assert.match(client, /student_id: String\(student\?\.student_id \|\| 0\)/);
  assert.match(client, /batch_id: String\(entry\.batch_id \|\| 0\)/);
  assert.match(client, /document_id: String\(entry\.document_id \|\| 0\)/);
  assert.match(client, /student_id: entry\.student_id \|\| null/);
  assert.match(client, /openSourcePages\(entry, batchStudent, entry\.student_name\)/);
  assert.doesNotMatch(client, /data\.student\?\.display_name/);
  assert.match(api, /This grade record does not belong to the selected student/);
  assert.match(api, /\$batchId !== \$expectedBatchId \|\| \$documentId !== \$expectedDocumentId/);
  assert.match(api, /status = 'Archived' AND deleted_at IS NULL/);
  assert.match(client, /Array\.isArray\(source\.source_pages\)/);
  assert.match(client, /const needsOcrIndex = pageNumbers === null/);
  assert.match(api, /'source_pages' => 'TEXT NULL AFTER subject_name'/);
  assert.match(api, /subject_name, source_pages, units, grade/);
  assert.match(parser, /'source_pages' => \[\$pageIndex \+ 1\]/);
});

test('Upload History Add Student uses stored OCR and the existing batch file only as fallback', () => {
  const root = path.resolve(__dirname, '../..');
  const client = fs.readFileSync(path.join(root, 'admin/gradesheets.js'), 'utf8');
  const page = fs.readFileSync(path.join(root, 'admin/gradesheets.php'), 'utf8');
  const api = fs.readFileSync(path.join(root, 'api.php'), 'utf8');
  assert.match(page, /id="gsAddStudentOpen"/);
  assert.match(page, /id="gsAddStudentForm"/);
  ['gsAddStudentName', 'gsAddStudentYear', 'gsAddStudentCourse', 'gsAddSemester', 'gsAddYear', 'gsAddCode', 'gsAddUnits', 'gsAddTitle', 'gsAddGrade', 'gsAddRemarks'].forEach((field) => {
    assert.match(page, new RegExp(`id="${field}"`));
  });
  assert.doesNotMatch(page, /id="gsAddGender"|id="gsAddCollege"|id="gsAddYearLevel"|id="gsAddStudentNumber"/);
  assert.match(client, /action: 'add-student'/);
  assert.match(client, /const problem = flow\.validateEntry\(criteria\)/);
  assert.match(client, /action: 'add-student',\s+\.\.\.criteria/);
  assert.doesNotMatch(client, /student number is required to create the directory record/i);
  assert.match(client, /resource=gradesheet-batch-file/);
  assert.match(client, /action: 'associate-student', batch_id: batch\.id, \.\.\.criteria, draft_entry_id: draftEntryId, ocr_text: ocr\.ocrText/);
  const addStudentClient = client.slice(client.indexOf('const addStudentForm'), client.indexOf('const editForm'));
  assert.doesNotMatch(addStudentClient, /student_id|student_number/);
  assert.ok(addStudentClient.indexOf('selectAddedStudent()') < addStudentClient.indexOf('completeGradesheetStudentAssociation('));
  assert.match(client, /viewButton\.disabled = !Number\(entry\.batch_id \|\| entry\.document_id\)/);
  assert.match(client, /heading\.textContent = source\.original_filename/);
  assert.match(api, /function associate_gradesheet_records\(/);
  assert.match(api, /VALUES \(NULL, NULL, NULL, :student_name, NULL/);
  assert.match(api, /dats_gradesheet_page_matches_criteria\(/);
  const addStudentApi = api.slice(api.indexOf('function add_gradesheet_student'), api.indexOf('function dats_validate_add_gradesheet_criteria'));
  assert.doesNotMatch(addStudentApi, /dats_insert_intake_student\(\$pdo, \$student\)/);
  assert.match(api, /dats_gradesheet_record_matches_criteria\(\$record, \$criteria\)/);
  assert.match(api, /dats_extract_gradesheet_records\(\$text\)/);
  assert.match(api, /':source_pages' => json_encode\(\$pages/);
});

test('student full-page link uses the directory id or the gradesheet student id and includes all related semester records', () => {
  const root = path.resolve(__dirname, '../..');
  const client = fs.readFileSync(path.join(root, 'admin/gradesheets.js'), 'utf8');
  const page = fs.readFileSync(path.join(root, 'admin/student-gradesheet-view.php'), 'utf8');

  assert.match(client, /const studentId = student\?\.student_id \?\? student\?\.id \?\? null/);
  assert.match(client, /student-gradesheet-view\.php\?student_id=\$\{encodeURIComponent\(String\(studentId\)\)\}/);
  assert.match(page, /student_id = :student_id/);
  assert.match(page, /student_id IS NULL AND student_name/);
  assert.match(page, /foreach \(\$groups as \$group\)/);
});

test('student semester results scroll within the existing term list', () => {
  const root = path.resolve(__dirname, '../..');
  const styles = fs.readFileSync(path.join(root, 'admin/gradesheets.css'), 'utf8');
  const client = fs.readFileSync(path.join(root, 'admin/gradesheets.js'), 'utf8');

  assert.match(styles, /\.gs-term-list\s*\{[^}]*max-height:\s*340px;[^}]*overflow-y:\s*auto;/);
  assert.match(client, /el\('div', 'gs-term-list'\)/);
});

test('a pen checkmark read as a low-confidence, letterless word is dropped from the Name cell and flags the record', () => {
  const word = (text, confidence, x0) => ({ text, confidence, bbox: { x0, x1: x0 + 10, y0: 0, y1: 10 } });
  const band = [0, 10];

  // Clean name, no stray ink: nothing dropped, no uncertainty marker.
  const cleanWords = [word('Dela', 92, 0), word('Cruz,', 90, 20), word('Juan', 91, 40)];
  assert.equal(flow.internals.nameTextIn(cleanWords, [0, 200], band), 'Dela Cruz, Juan');

  // A checkmark stroke OCRs as a short, letterless, low-confidence token — dropped, and the
  // cleaned name is flagged with a trailing "?" so the server still sends it for review.
  const checkmarkWords = [word('Dela', 92, 0), word('Cruz,', 90, 20), word('Juan', 91, 40), word('\\', 22, 70)];
  assert.equal(flow.internals.nameTextIn(checkmarkWords, [0, 200], band), 'Dela Cruz, Juan?');

  // A real trailing initial — short, but still letters and read with reasonable confidence — must
  // not be mistaken for ink noise.
  const initialWords = [word('Dela', 92, 0), word('Cruz,', 90, 20), word('Juan', 91, 40), word('M.', 65, 70)];
  assert.equal(flow.internals.nameTextIn(initialWords, [0, 200], band), 'Dela Cruz, Juan M.');
});

test('a surname Tesseract itself is unsure of is kept but still flags the record for review', () => {
  const word = (text, confidence, x0) => ({ text, confidence, bbox: { x0, x1: x0 + 10, y0: 0, y1: 10 } });
  const band = [0, 10];

  // Poor handwriting/scan quality: the surname has letters (so it is kept, not dropped like ink
  // noise) but Tesseract's own confidence in the reading is quite low — exactly how a surname
  // ends up spelled wrong. The low-confidence word must still mark the name uncertain.
  const shakySurname = [word('Dala', 38, 0), word('Cruz,', 90, 20), word('Juan', 91, 40)];
  assert.equal(flow.internals.nameTextIn(shakySurname, [0, 200], band), 'Dala Cruz, Juan?');
  assert.equal(flow.internals.isNameUnsureWord({ confidence: 38 }), true);
  assert.equal(flow.internals.isNameUnsureWord({ confidence: 65 }), false);
});

test('the batch review modal has a search box to filter transcribed records while verifying them', () => {
  const root = path.resolve(__dirname, '../..');
  const page = fs.readFileSync(path.join(root, 'admin/gradesheets.php'), 'utf8');
  const client = fs.readFileSync(path.join(root, 'admin/gradesheets.js'), 'utf8');
  const styles = fs.readFileSync(path.join(root, 'admin/gradesheets.css'), 'utf8');

  assert.match(page, /id="gsBatchSearch"/);
  const batchModalMarkup = page.slice(page.indexOf('id="gsBatchModal"'), page.indexOf('id="gsAddStudentModal"'));
  assert.match(batchModalMarkup, /id="gsBatchSearch"/);
  assert.match(client, /const batchSearch = \$\('#gsBatchSearch'\)/);
  assert.match(client, /batchSearch\.addEventListener\('input', renderBatchEntries\)/);
  assert.match(client, /entries\.filter\(\(entry\) => matchesBatchSearch\(entry, term\)\)/);
  assert.match(styles, /\.gs-batch-search/);
});

test('gradesheet records are not searchable until a reviewer explicitly saves the batch', () => {
  const root = path.resolve(__dirname, '../..');
  const api = fs.readFileSync(path.join(root, 'api.php'), 'utf8');
  const client = fs.readFileSync(path.join(root, 'admin/gradesheets.js'), 'utf8');
  const page = fs.readFileSync(path.join(root, 'admin/gradesheets.php'), 'utf8');

  assert.match(api, /status ENUM\('Pending Review','Needs Review','Completed','Failed'\)/);
  assert.match(api, /'save' => save_gradesheet_batch\(\$pdo, \$input\)/);
  assert.match(api, /function save_gradesheet_batch\(PDO \$pdo, array \$input\): array/);
  assert.match(api, /UPDATE gradesheet_entries SET confirmed = 1 WHERE batch_id = :id/);
  assert.match(api, /FROM gradesheet_entries g\s+JOIN students s ON s\.id = g\.student_id AND s\.deleted_at IS NULL\s+WHERE g\.confirmed = 1/);
  assert.match(api, /WHERE student_id IS NULL AND student_name IS NOT NULL AND student_name <> '' AND confirmed = 1/);
  assert.match(api, /SELECT \* FROM gradesheet_entries WHERE confirmed = 1 AND \(/);
  assert.match(page, /id="gsBatchSave"/);
  assert.match(page, /id="gsBatchPendingNote"/);
  assert.match(client, /action: 'save', batch_id: currentBatch\.id/);
  assert.match(client, /status === 'Pending Review'/);
});
test('two independent reads of a Name cell that agree confirm it; reads that disagree flag it', () => {
  const merge = flow.mergeNameReads;
  // Same letters, second read has no punctuation: keep the shaped original, no doubt.
  assert.equal(merge({ text: 'BAYOT, Joshly D.', confidence: 80 }, { text: 'BAYOT Joshly D', confidence: 85 }), 'BAYOT, Joshly D.');
  // An earlier doubt ("?") is resolved only when the re-read is confident too.
  assert.equal(merge({ text: 'BAYOT, Joshly D.?', confidence: 80 }, { text: 'BAYOT, Joshly D.', confidence: 90 }), 'BAYOT, Joshly D.');
  assert.equal(merge({ text: 'BAYOT, Joshly D.?', confidence: 80 }, { text: 'BAYOT, Joshly D.', confidence: 40 }), 'BAYOT, Joshly D.?');
  // Disagreement: the more confident read is kept but the record is flagged.
  assert.equal(merge({ text: 'SPNCHEZ, Richio A', confidence: 55 }, { text: 'SANCHEZ, Richie A', confidence: 78 }), 'SANCHEZ, Richie A?');
  assert.equal(merge({ text: 'SANCHEZ, Richie A', confidence: 90 }, { text: 'SAUCHEI Pichio', confidence: 30 }), 'SANCHEZ, Richie A?');
  // An unusable second read never degrades the first.
  assert.equal(merge({ text: 'SANCHEZ, Richie A', confidence: 90 }, { text: '|', confidence: 10 }), 'SANCHEZ, Richie A');
  assert.equal(merge({ text: '?', confidence: 0 }, { text: 'SANCHEZ, Richie A', confidence: 70 }), 'SANCHEZ, Richie A?');
});

test('re-read names are written back into the rebuilt page text row by row', () => {
  const composed = {
    structured: true,
    header: ['Report of Rating'],
    text: 'unused',
    fields: [
      { number: '1', name: 'SPNCHEZ, Richio A', course: 'BEEDI-A', grade: '1.75', remarks: 'PASSED', nameConfidence: 55 },
      { number: '2', name: 'REYES, Ana M.', course: 'BEEDI-A', grade: '1.5', remarks: 'PASSED', nameConfidence: 90 },
    ],
  };
  const text = flow.applyNameReads(composed, { 0: { text: 'SANCHEZ, Richie A', confidence: 80 } });
  assert.match(text, /@ROW\|1\|SANCHEZ, Richie A\?\|BEEDI-A\|1\.75\|PASSED/);
  assert.match(text, /@ROW\|2\|REYES, Ana M\.\|BEEDI-A\|1\.5\|PASSED/);
  assert.equal(flow.applyNameReads({ ...composed, structured: false, text: 'plain' }, {}), 'plain');
  assert.equal(flow.applyNameReads(composed, null), 'unused');
});

test('the Name cell is cleaned of table-rule remnants but keeps handwriting for the second read', () => {
  const width = 60; const height = 20;
  const data = new Uint8ClampedArray(width * height * 4).fill(255);
  const paint = (x, y, value) => { const i = (y * width + x) * 4; data[i] = value; data[i + 1] = value; data[i + 2] = value; };
  for (let x = 0; x < width; x += 1) paint(x, 0, 0); // a ruled line along the top edge
  for (let y = 5; y < 15; y += 1) { paint(20, y, 40); paint(21, y, 40); } // a handwritten stroke
  const cleaned = flow.prepareNameCellImage({ width, height, data });
  const at = (x, y) => cleaned.data[(y * width + x) * 4];
  assert.equal(at(30, 0), 255, 'the ruled line is erased');
  assert.ok(at(20, 8) < 128, 'the handwriting stroke is kept');
  assert.equal(cleaned.data[3], 255, 'the output is fully opaque');
  assert.match(flow.NAME_WHITELIST, /^[A-Za-z]+ñÑ \.,'-$/);
});

test('table rows keep their structured fields and name box so Name cells can be re-read', () => {
  const root = path.resolve(__dirname, '../..');
  const flowSource = fs.readFileSync(path.join(root, 'admin/gradesheet-flow.js'), 'utf8');
  const client = fs.readFileSync(path.join(root, 'admin/gradesheets.js'), 'utf8');
  assert.match(flowSource, /nameBox: \[table\.columns\.name\[0\]/);
  assert.match(flowSource, /return \{ rows, cells, fields, top:/);
  assert.match(client, /tessedit_pageseg_mode: '7', tessedit_char_whitelist: flow\.NAME_WHITELIST/);
  // Both the upload path and the per-page re-scan path must use the two-read composition.
  assert.equal((client.match(/await composePageWithNames\(/g) || []).length, 2); // upload + per-page re-scan
  assert.doesNotMatch(client, /flow\.composePageText\([^)]*\)\.text/);
});

test('the review screen shows why a row is flagged, offers the directory suggestion, and can correct every same-spelling record', () => {
  const root = path.resolve(__dirname, '../..');
  const client = fs.readFileSync(path.join(root, 'admin/gradesheets.js'), 'utf8');
  const page = fs.readFileSync(path.join(root, 'admin/gradesheets.php'), 'utf8');
  const api = fs.readFileSync(path.join(root, 'api.php'), 'utf8');
  assert.match(client, /Check \$\{reasons\.join\(', '\)\}/);
  assert.match(client, /Did you mean \$\{entry\.suggested_name\}\?/);
  assert.match(client, /payload\.apply_to_batch = true/);
  assert.match(page, /id="gsEditApplyAll"/);
  assert.match(page, /id="gsEditNameSuggest"/);
  assert.match(api, /'review_reasons' => 'VARCHAR\(60\) NULL AFTER needs_review'/);
  assert.match(api, /dats_gradesheet_apply_roster\(\$records, dats_gradesheet_roster\(\$pdo\)\)/);
  assert.match(api, /function dats_apply_gradesheet_name_to_batch/);
});
