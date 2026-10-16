<?php
declare(strict_types=1);

// Run: php -d zend.assertions=1 -d assert.exception=1 tests/php/student_import_test.php
require_once __DIR__ . '/../../lib/student-import.php';

$write = static function (string $content): string {
    $path = tempnam(sys_get_temp_dir(), 'dats_import_');
    file_put_contents($path, $content);
    return $path;
};

// --- Headers: registrar spellings map to fields --------------------------------------------------
assert(dats_student_import_field_for_header('Student No.') === 'student_number');
assert(dats_student_import_field_for_header('SURNAME') === 'last_name');
assert(dats_student_import_field_for_header('Given Name') === 'first_name');
assert(dats_student_import_field_for_header('Program') === 'course_name');
assert(dats_student_import_field_for_header('Sex') === 'gender');
assert(dats_student_import_field_for_header('Date of Birth') === 'birthdate');
assert(dats_student_import_field_for_header('Favourite colour') === null);

// --- Reading: BOM, semicolons, title rows above the headers, quoted newline, blank lines ---------
$csv = "\xEF\xBB\xBF" . "ZPPSU Enrollment List\r\n\r\nStudent No;Surname;Given Name;M.I.;Sex;Remarks\r\n"
    . "23-0001;DELA CRUZ;Juan;M;M;transferee\r\n"
    . ";;;;;\r\n"
    . "23-0002;\"Santos\";\"Ana\nMaria\";;F;\r\n";
$read = dats_student_import_read($write($csv));
assert(count($read['rows']) === 2, 'Blank rows are skipped and the title row is not mistaken for data: ' . count($read['rows']));
assert($read['rows'][0]['cells']['last_name'] === 'DELA CRUZ' && $read['rows'][0]['cells']['student_number'] === '23-0001');
assert($read['rows'][1]['cells']['first_name'] === "Ana\nMaria", 'A quoted multi-line cell stays in one cell.');
assert($read['unmapped'] === ['Remarks'], 'Unrecognized columns are reported, not silently dropped.');

// Windows-1252 (Excel "CSV" without UTF-8): "Niño" must survive.
$ansi = "Student Number,Last Name,First Name\r\n24-0001,Pe\xF1a,Ni\xF1o\r\n";
$read = dats_student_import_read($write($ansi));
assert($read['rows'][0]['cells']['last_name'] === 'Peña' && $read['rows'][0]['cells']['first_name'] === 'Niño', 'Windows-1252 text must be converted to UTF-8.');

// A single "Last, First M." Name column.
$read = dats_student_import_read($write("ID Number,Name\r\n25-0001,\"REYES, Maria Luisa C.\"\r\n"));
$row = dats_student_import_normalize_row($read['rows'][0]['cells'], '2026');
assert($row['errors'] === [] && $row['student']['last_name'] === 'Reyes' && $row['student']['first_name'] === 'Maria Luisa' && $row['student']['middle_initial'] === 'C.', 'Combined names split on the comma: ' . json_encode($row['student']));
$row = dats_student_import_normalize_row(['student_number' => 'X', 'full_name' => 'Maria Reyes'], '2026');
assert($row['errors'] !== [], 'A name with no comma is ambiguous and must not be guessed.');

// Files that are not usable fail with a message a registrar can act on.
foreach ([['', 'empty'], ["Foo,Bar\r\n1,2\r\n", 'column headings'], ["a\0b", 'CSV']] as [$content, $needle]) {
    try {
        dats_student_import_read($write($content));
        assert(false, 'Expected a failure for: ' . $needle);
    } catch (InvalidArgumentException $error) {
        assert(stripos($error->getMessage(), $needle) !== false, 'Message should mention "' . $needle . '": ' . $error->getMessage());
    }
}

// --- Value normalization ----------------------------------------------------------------------------
assert(dats_student_import_gender('F') === 'Female' && dats_student_import_gender('male') === 'Male' && dats_student_import_gender('') === 'Undisclosed' && dats_student_import_gender('x') === null);
assert(dats_student_import_civil_status('S') === 'Single' && dats_student_import_civil_status('widow') === 'Widowed' && dats_student_import_civil_status('?') === null);
foreach (['1' => '1st Year', 'First Year' => '1st Year', 'II' => '2nd Year', '3rd' => '3rd Year', 'Year 4' => '4th Year', '4th Year' => '4th Year', 'Graduate' => 'Graduate', 'SHS' => 'Senior High School', '' => 'Undisclosed'] as $in => $out) {
    assert(dats_student_import_year_level((string) $in) === $out, "Year level '$in' should be '$out'");
}
assert(dats_student_import_year_level('Sophomore') === null);
assert(dats_student_import_birthdate('2005-03-14') === '2005-03-14');
assert(dats_student_import_birthdate('03/14/2005') === '2005-03-14', 'Slash dates are month first.');
assert(dats_student_import_birthdate('March 14, 2005') === '2005-03-14');
assert(dats_student_import_birthdate('14-Mar-2005') === '2005-03-14');
assert(dats_student_import_birthdate('02/30/2005') === null && dats_student_import_birthdate('garbage') === null && dats_student_import_birthdate('2999-01-01') === null, 'Impossible or future dates are rejected, not guessed.');
assert(dats_student_import_academic_year('AY 2026-2027') === '2026' && dats_student_import_academic_year('nope') === null);
assert(dats_student_import_smart_case('DELA CRUZ') === 'Dela Cruz' && dats_student_import_smart_case('juan') === 'Juan' && dats_student_import_smart_case('McDonald') === 'McDonald', 'Mixed-case names are left alone.');

// --- A full row: lenient on soft fields, strict on identity ------------------------------------------
$row = dats_student_import_normalize_row(['student_number' => ' 23-0003 ', 'last_name' => 'REYES', 'first_name' => 'ana', 'middle_initial' => 'Cruz', 'gender' => 'Femail', 'birthdate' => 'soon', 'year_level' => '2', 'academic_year' => ''], '2026');
assert($row['errors'] === [], 'Bad soft fields must not block a student.');
assert($row['student']['student_number'] === '23-0003' && $row['student']['last_name'] === 'Reyes' && $row['student']['first_name'] === 'Ana' && $row['student']['middle_initial'] === 'C.');
assert($row['student']['gender'] === 'Undisclosed' && $row['student']['birthdate'] === '' && $row['student']['year_level'] === '2nd Year' && $row['student']['academic_year'] === '2026');
assert(count($row['warnings']) === 2, 'Each dropped value is reported: ' . json_encode($row['warnings']));
assert(dats_student_import_normalize_row(['last_name' => 'A', 'first_name' => 'B'], '2026')['errors'] === ['Student number is required.']);

// The downloadable template must itself import cleanly.
$read = dats_student_import_read($write(dats_student_import_template()));
assert(count($read['rows']) === 1 && dats_student_import_normalize_row($read['rows'][0]['cells'], '2026')['errors'] === [], 'The template must be a valid import file.');

echo "student import: all assertions passed\n";
