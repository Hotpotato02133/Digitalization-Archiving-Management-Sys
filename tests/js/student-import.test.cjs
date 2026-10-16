const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '../..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');

test('the downloadable template matches the server template, so what a registrar downloads always imports', () => {
  const php = read('lib/student-import.php');
  const js = read('admin/student-import.js');
  const header = /Student Number,[A-Za-z ,]*Year Level/;
  assert.ok(php.match(header) && js.match(header), 'both templates must have a header row');
  assert.equal(js.match(header)[0], php.match(header)[0]);
  // The sample row; PHP escapes its quotes inside a double-quoted string, JS does not.
  const row = /2026-000123,.*?,1st Year/;
  assert.ok(php.match(row) && js.match(row), 'both templates must have a sample row');
  assert.equal(js.match(row)[0], php.match(row)[0].replace(/\\"/g, '"'));
});

test('the students page offers Import students and loads its dialog, styles and script', () => {
  const page = read('admin/students.php');
  assert.match(page, /id="importStudentsButton"/);
  assert.match(page, /id="studentImportModal"/);
  for (const id of ['siFile', 'siYear', 'siTemplate', 'siResults', 'siConfirm', 'siCancel', 'siClose', 'siStatus']) {
    assert.match(page, new RegExp(`id="${id}"`), id);
  }
  assert.match(page, /student-import\.css/);
  assert.match(page, /student-import\.js/);
  // Every element the script looks up must exist in the page.
  const js = read('admin/student-import.js');
  for (const [, id] of js.matchAll(/\$\('#(si[A-Za-z]+)'\)/g)) assert.match(page, new RegExp(`id="${id}"`), `script uses #${id}`);
});

test('import is a two-step flow: a preview that changes nothing, then a confirmed import', () => {
  const js = read('admin/student-import.js');
  const api = read('api.php');
  assert.match(api, /'import-preview' => preview_student_import\(\$pdo, \$_FILES\['file'\] \?\? null, \$input\)/);
  assert.match(api, /'import' => import_students\(\$pdo, \$currentUser, \$_FILES\['file'\] \?\? null, \$input\)/);
  assert.match(js, /post\('import-preview'\)/);
  assert.match(js, /post\('import'\)/);
  // The confirm button stays disabled until a preview found something to import.
  assert.match(js, /confirmButton\.dataset\.ready = summary\.ready > 0 \? '1' : '0'/);
  assert.match(js, /if \(busy \|\| confirmButton\.dataset\.ready !== '1'\) return;/);
  // Server messages are shown as text, never as HTML.
  assert.doesNotMatch(js, /innerHTML/);
});

test('the import reuses the Add Student rules instead of writing students its own way', () => {
  const lib = read('lib/student-import.php');
  assert.match(lib, /dats_validate_new_student_payload\(/);
  assert.match(lib, /dats_insert_intake_student\(/);
  assert.match(lib, /dats_resolve_intake_academic_hierarchy\(/);
  assert.match(lib, /dats_validate_student_hierarchy\(/);
  // Rows are inserted in a transaction with a savepoint per row, so one bad row cannot undo the rest.
  assert.match(lib, /SAVEPOINT import_row/);
  assert.match(lib, /ROLLBACK TO SAVEPOINT import_row/);
});
