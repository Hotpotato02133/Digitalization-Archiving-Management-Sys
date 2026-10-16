const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const script = fs.readFileSync(path.resolve(__dirname, '../../admin/script.js'), 'utf8');
const studentsPage = fs.readFileSync(path.resolve(__dirname, '../../admin/students.php'), 'utf8');
const dashboardPage = fs.readFileSync(path.resolve(__dirname, '../../admin/index.php'), 'utf8');
const settingsPage = fs.readFileSync(path.resolve(__dirname, '../../admin/settings.php'), 'utf8');
const filesPage = fs.readFileSync(path.resolve(__dirname, '../../admin/files.php'), 'utf8');
const dataSync = fs.readFileSync(path.resolve(__dirname, '../../data-sync.js'), 'utf8');
const api = fs.readFileSync(path.resolve(__dirname, '../../api.php'), 'utf8');

test('student directory script registers its DOM-ready handlers without runtime errors', () => {
  let registered = false;
  const document = {
    addEventListener(eventName) {
      if (eventName === 'DOMContentLoaded') registered = true;
    },
  };

  assert.doesNotThrow(() => vm.runInNewContext(script, { document }));
  assert.equal(registered, true);
});

test('student row actions stay bound to the clicked student ID', () => {
  assert.match(script, /const selectedStudentRow = actionButton\.closest\('tr'\)/);
  assert.match(script, /const studentId = Number\(student\?\.id \?\? selectedStudentRow\?\.dataset\.studentId\)/);
  assert.match(script, /openDocumentsDialog\(studentId, studentName\)/);
  assert.match(script, /studentId,\s*studentName,\s*student: student \|\| null/);
  assert.match(script, /editingStudentId = studentId/);
});

test('profile dropdown role label reflects the authenticated profile role', () => {
  assert.match(dataSync, /const roleLabels = \{ Admin: 'System Administrator', Staff: 'Registrar Staff' \}/);
  assert.match(dataSync, /const roleLabel = roleLabels\[profile\.role\] \|\| 'User'/);
  assert.match(dataSync, /profile-tip-role'\)\.textContent = roleLabel/);
  assert.match(api, /'profile' => \$currentUser/);
});

test('Staff dashboard and settings load the current shared profile script', () => {
  assert.doesNotMatch(dashboardPage, /<head>\s*System Administrator/);
  for (const page of [dashboardPage, settingsPage, filesPage]) {
    assert.match(page, /data-sync\.js\?v=<\?= filemtime\(__DIR__ \. '\/\.\.\/data-sync\.js'\) \?>/);
  }
});

test('student suggestions show every match in last-name order and reveal the selected row', () => {
  assert.match(script, /label\.textContent = `\$\{student\.student_number \|\| '—'\} — \$\{getStudentName\(student\)\}`/);
  assert.match(script, /\.sort\(compareStudentLastNames\);/);
  assert.doesNotMatch(script, /\.slice\(0, 8\)/);
  assert.match(script, /selectedStudentId = String\(student\.id\)/);
  assert.match(script, /selectedRow\.scrollIntoView\(\{ behavior: 'smooth', block: 'center', inline: 'nearest' \}\)/);
  assert.match(script, /selectedStudentId\s*\?\s*String\(student\?\.id\) === selectedStudentId/);
});

test('student directory has one live search control connected to registered student data', () => {
  assert.match(studentsPage, /^\uFEFF?<\?php/);
  assert.equal([...studentsPage.matchAll(/id="studentSearch"/g)].length, 1);
  assert.equal([...studentsPage.matchAll(/id="studentSearchSuggestions"/g)].length, 1);
  assert.match(dataSync, /const students = await request\('students'\)/);
  assert.match(api, /'students' => \$pdo->query\([\s\S]*?FROM students s[\s\S]*?WHERE s\.deleted_at IS NULL[\s\S]*?ORDER BY s\.last_name, s\.first_name/);
});

test('Add Student uses scanner academic inputs, shared validation, and intake year levels', () => {
  assert.match(script, /id="studentCollege" list="studentCollegeOptions"/);
  assert.match(script, /id="studentCourse" type="text" list="studentCourseOptions"/);
  assert.match(script, /id="studentMajor" type="text" list="studentMajorOptions"/);
  assert.match(script, /resolveAcademicSelection\(\[\.\.\.collegeData\.values\(\)\], profile\)/);
  assert.match(script, /validateNewStudentProfile\(profile\)/);
  assert.match(studentsPage, /dats_intake_year_levels\(\)/);
  assert.match(api, /dats_resolve_intake_academic_hierarchy\(\$pdo, \$student\)/);
  assert.match(api, /dats_validate_new_student_payload\(\$input\)/);
});