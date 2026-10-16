# Scan Student Selection and Registration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add explicit existing-student selection and atomic new-student registration to File Scanning while preserving OCR categorization and the current review/archive lifecycle.

**Architecture:** A small dependency-free scan-flow module will hold searchable-student and request-payload rules, leaving DOM behavior in `admin/script.js`. A PHP intake library will centralize student validation and transaction/cleanup helpers; `api.php` will use it to resolve an existing student or insert a new one in the same transaction as the document and review rows.

**Tech Stack:** PHP 8.2, PDO/MariaDB, vanilla JavaScript, server-rendered PHP, hand-written CSS, Node.js 24 built-in test runner, dependency-free PHP test scripts.

**Spec:** `docs/superpowers/specs/2026-09-22-scan-student-registration-design.md`

## Global Constraints

- Do not introduce Composer, npm, a frontend framework, or a build step.
- Preserve the current Pending Review → approval/rejection → year-folder archive workflow.
- Existing-student submissions derive academic year from the server-side student row.
- New-student identity, document, enrollment-year, and review writes share one database transaction.
- Uploaded-file cleanup compensates for any failure after a new file has moved into `uploads/`.
- Document Type remains editable and falls back to the active-category dropdown when OCR has no supported match.
- Keep the existing `/DATS/` deployment assumption unchanged.

## Review Focus

- Duplicate names: selecting one of two identically named students must submit the chosen numeric ID and year.
- Stale/deleted students: a formerly selected ID must fail server-side instead of filing under typed client metadata.
- Incomplete profiles: an existing student without a valid academic year must be rejected with corrective guidance.
- Partial failure: neither a new student row nor an orphan upload may remain when document creation fails.
- OCR mismatch: an unknown or inactive category must leave the category dropdown usable and must never submit a fabricated code.

---

### Task 1: Pure scan-flow rules

**Files:**
- Create: `admin/scan-flow.js`
- Create: `tests/js/scan-flow.test.cjs`

**Interfaces:**
- Produces: `DatsScanFlow.normalizeSearchText(value)`, `filterStudents(students, query, limit)`, `getExistingStudentSelection(student)`, `resolveDetectedCategory(categories, detected)`, and `buildStudentPayload(mode, values)`.
- Consumed by: Task 4's scan-page controller.

- [ ] **Step 1: Write failing tests for student search and selection**

```js
const test = require('node:test');
const assert = require('node:assert/strict');
const flow = require('../../admin/scan-flow.js');

const students = [{
  id: 14,
  student_number: '2026-0014',
  first_name: 'Ana',
  middle_initial: 'M',
  last_name: 'Reyes',
  academic_year: '2026',
  college_name: 'College of Computing',
  course_name: 'BS Information Technology',
}];

test('filters students by number, name, school metadata, and year', () => {
  for (const query of ['0014', 'ana reyes', 'computing', 'information technology', '2026']) {
    assert.deepEqual(flow.filterStudents(students, query, 8).map((student) => student.id), [14]);
  }
});

test('selection carries the immutable student id and stored year', () => {
  assert.deepEqual(flow.getExistingStudentSelection(students[0]), { studentId: 14, academicYear: '2026' });
});
```

- [ ] **Step 2: Run the test and verify RED**

Run: `node --test tests/js/scan-flow.test.cjs`

Expected: FAIL because `admin/scan-flow.js` does not exist.

- [ ] **Step 3: Implement the UMD-style pure module**

Create functions that normalize case/whitespace/diacritics, build a searchable string from student number/name/year/college/course/major, cap suggestions to `limit`, and return only `{studentId, academicYear}` for a selection. Export with `module.exports` under Node and `window.DatsScanFlow` in the browser.

- [ ] **Step 4: Add failing tests for category resolution and mode-specific payloads**

```js
test('resolves only active categories returned by the API', () => {
  const categories = [{ code: 'TOR', name: 'Transcript of Records' }];
  assert.equal(flow.resolveDetectedCategory(categories, { code: 'TOR', name: 'Transcript of Records' }), 'TOR');
  assert.equal(flow.resolveDetectedCategory(categories, { code: 'UNKNOWN', name: 'Unknown' }), '');
});

test('builds an existing-student payload without editable identity fields', () => {
  assert.deepEqual(flow.buildStudentPayload('existing', { studentId: 14 }), {
    student_mode: 'existing', student_id: '14',
  });
});

test('builds a trimmed new-student payload with all supported profile fields', () => {
  const payload = flow.buildStudentPayload('new', {
    studentNumber: ' 2026-0042 ', firstName: ' Ada ', lastName: ' Cruz ',
    middleInitial: ' Q ', nationality: ' Filipino ', academicYear: '2026',
    gender: 'Undisclosed', birthdate: '', address: ' City ', civilStatus: 'Undisclosed',
    contactNumber: '', collegeId: '2', courseId: '5', majorId: '', yearLevel: '1st Year',
  });
  assert.equal(payload.student_number, '2026-0042');
  assert.equal(payload.nationality, 'Filipino');
  assert.equal(payload.college_id, '2');
  assert.equal(payload.student_id, undefined);
});
```

- [ ] **Step 5: Run RED, implement minimal category/payload behavior, then run GREEN**

Run: `node --test tests/js/scan-flow.test.cjs`

Expected before implementation: FAIL for missing functions. Expected after implementation: all Task 1 tests PASS.

- [ ] **Step 6: Commit Task 1 if version control becomes available**

```powershell
git add admin/scan-flow.js tests/js/scan-flow.test.cjs
git commit -m "test: define scan student flow rules"
```

Skip the commit without failing the task while this directory is not a Git repository.

### Task 2: Student intake validation and nationality schema

**Files:**
- Create: `lib/scan-intake.php`
- Create: `tests/php/scan_intake_test.php`
- Modify: `api.php` near startup schema guards and student create/update functions
- Modify: `dats.sql` in the `students` table and sample insert column list

**Interfaces:**
- Produces: `dats_validate_new_student_payload(array $input): array`, `dats_validate_student_hierarchy(PDO $pdo, array $student): array`, `dats_find_existing_student_for_intake(PDO $pdo, int $id): array`, and `dats_insert_intake_student(PDO $pdo, array $student): int`.
- Consumed by: Task 3's `create_document()` transaction.

- [ ] **Step 1: Write the failing dependency-free PHP validation tests**

Use a tiny `expect_exception()` helper and require only `lib/scan-intake.php`. Cover required student number/first/last/year, year range, supported enum values, trimmed strings, nationality's 80-character maximum, and optional blank values becoming `null`.

```php
$valid = dats_validate_new_student_payload([
    'student_number' => ' 2026-0042 ', 'first_name' => ' Ada ', 'last_name' => ' Cruz ',
    'academic_year' => '2026', 'nationality' => ' Filipino ',
]);
assert($valid['student_number'] === '2026-0042');
assert($valid['nationality'] === 'Filipino');
assert($valid['gender'] === 'Undisclosed');
expect_exception(fn () => dats_validate_new_student_payload(['student_number' => '', 'first_name' => 'A', 'last_name' => 'C', 'academic_year' => '2026']), 'Student number');
expect_exception(fn () => dats_validate_new_student_payload(['student_number' => '1', 'first_name' => 'A', 'last_name' => 'C', 'academic_year' => '1999']), 'academic year');
```

- [ ] **Step 2: Run the test and verify RED**

Run: `& 'C:\xampp\php\php.exe' -d zend.assertions=1 -d assert.exception=1 tests\php\scan_intake_test.php`

Expected: FAIL because the intake library/functions do not exist.

- [ ] **Step 3: Implement pure validation and rerun GREEN**

Implement explicit length limits matching `students`: number 30, first/last 80, middle initial 10, nationality 80, address 255, contact 30. Normalize academic year with the same 2000–2100 rule. Allow only the database enum values for gender, civil status, and year level.

- [ ] **Step 4: Add failing SQLite tests for identity and hierarchy checks**

In `tests/php/scan_intake_test.php`, create an in-memory SQLite schema for `students`, `colleges`, `courses`, and `course_majors`, then add assertions in this shape:

```php
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE colleges (id INTEGER PRIMARY KEY, status TEXT)');
$pdo->exec('CREATE TABLE courses (id INTEGER PRIMARY KEY, college_id INTEGER, status TEXT)');
$pdo->exec('CREATE TABLE course_majors (id INTEGER PRIMARY KEY, course_id INTEGER, status TEXT)');
$pdo->exec('CREATE TABLE students (id INTEGER PRIMARY KEY AUTOINCREMENT, student_number TEXT UNIQUE, first_name TEXT, middle_initial TEXT, last_name TEXT, gender TEXT, birthdate TEXT, address TEXT, nationality TEXT, civil_status TEXT, contact_number TEXT, college_id INTEGER, course_id INTEGER, major_id INTEGER, academic_year TEXT, year_level TEXT, status TEXT DEFAULT "Active", deleted_at TEXT)');
$pdo->exec("INSERT INTO students (student_number, first_name, last_name, academic_year) VALUES ('2026-0001', 'Existing', 'Student', '2026')");
$student = dats_find_existing_student_for_intake($pdo, 1);
assert($student['academic_year'] === '2026');
expect_exception(fn () => dats_find_existing_student_for_intake($pdo, 999), 'not found');
expect_exception(fn () => dats_insert_intake_student($pdo, dats_validate_new_student_payload([
    'student_number' => '2026-0001', 'first_name' => 'Duplicate', 'last_name' => 'Number', 'academic_year' => '2026',
])), 'already exists');
```

Extend the fixture with active college/course/major rows and verify:

- a selected existing ID returns its stored academic year;
- a missing/deleted/inactive student is rejected;
- duplicate student number is rejected;
- a course outside the selected college is rejected;
- a major outside the selected course is rejected;
- a valid new profile inserts and returns an ID.

- [ ] **Step 5: Run RED, implement PDO-backed helpers, and rerun GREEN**

Run the same PHP test command. Expected after implementation: `scan_intake_test: PASS` and exit code 0.

- [ ] **Step 6: Add nationality to the canonical and runtime schemas**

In `dats.sql`, add:

```sql
`nationality` varchar(80) DEFAULT NULL,
```

after `address`. Update the seed insert's columns and rows with `NULL` nationality values. In `api.php`, add and call:

```php
function ensure_student_nationality_column(PDO $pdo): void
{
    $pdo->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS nationality VARCHAR(80) NULL AFTER address");
}
```

Require `lib/scan-intake.php` after `auth.php`, and update normal student create/update SQL and parameters to persist validated nationality.

- [ ] **Step 7: Run PHP tests and syntax checks**

Run:

```powershell
& 'C:\xampp\php\php.exe' -d zend.assertions=1 -d assert.exception=1 tests\php\scan_intake_test.php
& 'C:\xampp\php\php.exe' -l lib\scan-intake.php
& 'C:\xampp\php\php.exe' -l api.php
```

Expected: test PASS and both files report no syntax errors.

### Task 3: Atomic document and new-student creation

**Files:**
- Modify: `lib/scan-intake.php`
- Modify: `tests/php/scan_intake_test.php`
- Modify: `api.php` function `create_document()`

**Interfaces:**
- Consumes: Task 2's validation, lookup, hierarchy, and insertion helpers.
- Produces: `dats_run_transaction(PDO $pdo, callable $operation): mixed` and `dats_remove_new_upload(?string $absolutePath): void`; explicit `student_mode=existing|new` behavior in `create_document()`.

- [ ] **Step 1: Write failing transaction and cleanup tests**

Use SQLite to assert that `dats_run_transaction()` commits a successful insert and rolls back an insert when the callback throws. Create a uniquely named file under `sys_get_temp_dir()`, call `dats_remove_new_upload()`, and assert it no longer exists; also assert a null/missing path is harmless.

```php
$pdo->exec('CREATE TABLE tx_probe (value TEXT)');
dats_run_transaction($pdo, function (PDO $pdo): void {
    $pdo->exec("INSERT INTO tx_probe VALUES ('committed')");
});
assert((int) $pdo->query('SELECT COUNT(*) FROM tx_probe')->fetchColumn() === 1);
expect_exception(function () use ($pdo): void {
    dats_run_transaction($pdo, function (PDO $pdo): void {
        $pdo->exec("INSERT INTO tx_probe VALUES ('rolled-back')");
        throw new RuntimeException('forced failure');
    });
}, 'forced failure');
assert((int) $pdo->query('SELECT COUNT(*) FROM tx_probe')->fetchColumn() === 1);
$temporaryUpload = tempnam(sys_get_temp_dir(), 'dats-intake-');
dats_remove_new_upload($temporaryUpload);
assert(!is_file($temporaryUpload));
```

- [ ] **Step 2: Run RED and implement the minimal helpers**

Run the PHP test command. Verify failure is missing helpers, implement begin/commit/rollback with rethrow, plus guarded `is_file()`/`unlink()`, then rerun to PASS.

- [ ] **Step 3: Write failing SQLite tests for mode resolution**

Add `dats_resolve_intake_student(PDO $pdo, array $input): array` expectations:

```php
assert(dats_resolve_intake_student($pdo, ['student_mode' => 'existing', 'student_id' => $existingId]) === [
    'student_id' => $existingId, 'academic_year' => '2026', 'created' => false,
]);
$new = dats_resolve_intake_student($pdo, [
    'student_mode' => 'new', 'student_number' => '2026-0099',
    'first_name' => 'New', 'last_name' => 'Student', 'academic_year' => '2026',
]);
assert($new['created'] === true);
```

Also test that `student_mode=existing` ignores a conflicting client `academic_year`, and legacy requests without `student_mode` retain the old lookup behavior.

- [ ] **Step 4: Run RED, implement mode resolution, and rerun GREEN**

Keep new-student insertion inside the caller's active transaction; do not begin a nested transaction inside `dats_resolve_intake_student()`.

- [ ] **Step 5: Refactor `create_document()` around one transaction**

After file validation/move and OCR, execute these steps inside `dats_run_transaction()`:

1. Resolve/insert the student from explicit mode.
2. Resolve the active category.
3. Insert the document using the resolved student ID and academic year.
4. Upsert the enrollment year.
5. Insert the pending review.

Wrap the transaction call in `try/catch`; when the current request moved a new upload and any later step fails, call `dats_remove_new_upload($absolutePath)` before rethrowing. Do not delete a path supplied through legacy input.

- [ ] **Step 6: Run backend regression checks**

Run the PHP intake tests and `php -l` commands from Task 2. Also lint `auth.php` and `config/database.php` to ensure the include chain remains valid.

### Task 4: File Scanning interface and controller

**Files:**
- Modify: `admin/scan.php`
- Modify: `data-sync.js`
- Modify: `admin/script.js`
- Modify: `tests/js/scan-flow.test.cjs`

**Interfaces:**
- Consumes: Task 1's `window.DatsScanFlow` and API `students`, `categories`, and `colleges` data.
- Produces: explicit existing/new mode controls and multipart fields accepted by Task 3.

- [ ] **Step 1: Extend failing JS tests for state reset and validation**

Add tests that mode switching clears `studentId` only when leaving existing mode, clears new-profile values only when leaving new mode, preserves `categoryCode`, and reports these validation failures:

- existing mode without a selected ID;
- existing student without a valid stored year;
- new mode without student number, first name, last name, or valid year;
- missing category or file.

```js
test('switching student modes clears hidden identity but preserves document state', () => {
  const state = flow.switchStudentMode({
    mode: 'existing', studentId: 14, academicYear: '2026',
    newStudent: { studentNumber: 'draft' }, categoryCode: 'TOR', file: { name: 'tor.pdf' },
  }, 'new');
  assert.equal(state.studentId, null);
  assert.equal(state.categoryCode, 'TOR');
  assert.equal(state.file.name, 'tor.pdf');
});

test('validates the identity required by the active mode', () => {
  assert.match(flow.validateScanSubmission({ mode: 'existing', studentId: null, categoryCode: 'TOR', file: {} }).join(' '), /select an existing student/i);
  assert.match(flow.validateScanSubmission({ mode: 'new', newStudent: { studentNumber: '', firstName: '', lastName: '', academicYear: '' }, categoryCode: 'TOR', file: {} }).join(' '), /student number/i);
});
```

- [ ] **Step 2: Run RED, add pure state/validation helpers, and rerun GREEN**

Add `switchStudentMode(state, nextMode)` and `validateScanSubmission(state)` to `admin/scan-flow.js`. Keep returned errors as concise user-facing strings consumed directly by the controller.

- [ ] **Step 3: Replace the Record Details markup**

In `admin/scan.php` add:

- a labeled radio group (`scanStudentExisting`, `scanStudentNew`);
- an ARIA combobox input, hidden numeric `scanStudentId`, and listbox `scanStudentSuggestions`;
- read-only `scanAcademicYear` in existing mode;
- `scanNewStudentFields` containing all required/optional fields from the spec;
- a `scanCategorySource` help/status element;
- `admin/scan-flow.js` before `admin/script.js`.

Add focused CSS for selected radio cards, the anchored suggestion list, active suggestion, registration grid, read-only fields, help text, invalid state, and one-column mobile layout. Reuse existing CSS variables and focus styling.

- [ ] **Step 4: Load normalized scan reference data**

Replace the old datalist block in `data-sync.js` with parallel requests for students, categories, and colleges. Populate the category select, then dispatch:

```js
window.dispatchEvent(new CustomEvent('dats:scan-data-ready', {
  detail: { students, categories, colleges },
}));
```

Do not render suggestion HTML from API strings with `innerHTML`; construct nodes and assign `textContent`.

- [ ] **Step 5: Implement accessible existing-student suggestions**

In `admin/script.js`, consume the scan-data event, render up to eight matches, maintain `aria-expanded`/`aria-activedescendant`, support Arrow Up/Down, Enter, Escape, Tab, pointer selection, and outside-click close. On selection set `scanStudentId`, visible label, and read-only academic year. Editing after selection clears the hidden ID and year so stale identity cannot submit.

- [ ] **Step 6: Implement new-registration dependent fields**

Populate college options using numeric IDs. On college change, filter courses; on course change, filter majors. Reset invalid downstream selections. Use the same enum options as the database, including `Undisclosed`, `Graduate`, and `5th Year` where applicable.

- [ ] **Step 7: Integrate OCR category state**

Update `applyOcrResultToScanUI()` to call `resolveDetectedCategory()`. When it returns a code, select it and set `scanCategorySource` to “Detected from uploaded document — verify before archiving.” When it returns blank, retain/restore manual dropdown behavior and explain that no supported type was detected. Clear file-derived status when the active file is removed or replaced.

- [ ] **Step 8: Submit explicit mode payloads and reset safely**

Replace free-text `student_name` submission with `buildStudentPayload()`. Existing mode sends only `student_mode` and `student_id`; new mode sends the registration fields. Continue sending the file/category/OCR fields. On success reset the active identity form, selected file, OCR state, category state, and radio mode to Existing student. On failure retain user inputs.

- [ ] **Step 9: Run JS tests and PHP syntax check for the page**

Run:

```powershell
node --test tests\js\scan-flow.test.cjs
& 'C:\xampp\php\php.exe' -l admin\scan.php
```

Expected: all JavaScript tests PASS and scan.php has no syntax errors.

### Task 5: Maintain nationality in Students Information

**Files:**
- Modify: `admin/students.php`
- Modify: `admin/script.js`
- Modify: `tests/js/scan-flow.test.cjs` only if a shared profile serializer is used

**Interfaces:**
- Consumes: `students.nationality` returned by the existing students resource.
- Produces: add/edit student requests that preserve nationality.

- [ ] **Step 1: Add a failing serializer test before changing form submission**

Extract or reuse a pure `buildStudentProfilePayload(values)` helper and test that nationality is trimmed and included without changing the established college/course/major IDs.

```js
test('student profile serialization preserves hierarchy ids and nationality', () => {
  assert.deepEqual(flow.buildStudentProfilePayload({
    studentNumber: '2026-0010', firstName: 'Lin', lastName: 'Santos',
    nationality: ' Filipino ', collegeId: '2', courseId: '5', majorId: '7',
  }), {
    student_number: '2026-0010', first_name: 'Lin', last_name: 'Santos',
    nationality: 'Filipino', college_id: '2', course_id: '5', major_id: '7',
  });
});
```

- [ ] **Step 2: Run RED, implement the helper, and rerun GREEN**

Run `node --test tests\js\scan-flow.test.cjs` before and after the minimal implementation.

- [ ] **Step 3: Add the Nationality field with a stable ID**

Add `<input id="studentNationality" maxlength="80" autocomplete="country-name">` after Address in the student modal. Give the existing base fields stable IDs while touching this markup, so submission no longer depends on array position.

- [ ] **Step 4: Update add, edit, and save behavior**

Read form values by ID, include `nationality`, populate it from `activeStudentRow.studentData.nationality`, and clear it when opening Add student. Preserve all existing academic-field dependencies.

- [ ] **Step 5: Run JS tests and lint the modified PHP page**

Run the Task 4 verification commands, substituting `admin\students.php` for the PHP lint.

### Task 6: Full verification and workflow walkthrough

**Files:**
- Verify all files modified in Tasks 1–5

**Interfaces:**
- Verifies the complete browser → API → database → review → archive flow.

- [ ] **Step 1: Run the complete automated suite**

```powershell
node --test tests\js\scan-flow.test.cjs
& 'C:\xampp\php\php.exe' -d zend.assertions=1 -d assert.exception=1 tests\php\scan_intake_test.php
Get-ChildItem -Recurse -Filter '*.php' | ForEach-Object { & 'C:\xampp\php\php.exe' -l $_.FullName; if ($LASTEXITCODE -ne 0) { throw "PHP lint failed: $($_.FullName)" } }
```

Expected: all Node tests pass, PHP test prints PASS, and every PHP file reports no syntax errors.

- [ ] **Step 2: Verify existing-student flow in the browser**

Start from File Scanning, upload a supported document, search by student number, select an existing student, confirm academic year auto-fills read-only, verify/correct detected category, submit, approve in Pending Reviews, and confirm the document appears in that student's View Files list and increments the correct year folder.

- [ ] **Step 3: Verify new-student flow in the browser**

Register a unique test student with required fields plus nationality/address and academic hierarchy, submit a document, approve it, verify the student appears in Students Information with maintainable nationality, and confirm the approved document appears in the correct year folder.

- [ ] **Step 4: Verify failure paths**

Check duplicate student number, typed-but-unselected existing student, existing student without year, unsupported OCR match, invalid course/major combination, and a forced database failure. Confirm inputs remain for correction and no orphan student/file remains after the forced failure.

- [ ] **Step 5: Inspect final changes**

Because Git is currently unavailable, list the modified/new paths and run targeted searches for legacy `scanStudentNames` and scan-page `student_name` submission. Confirm the old ambiguous path is absent from the new UI while backend legacy compatibility remains intentional.
