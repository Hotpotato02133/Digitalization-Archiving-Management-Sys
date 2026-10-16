<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/gradesheet-parser.php';

$page = static fn (string $code, string $subject): string => implode("\n", [
    'Report of Rating',
    'S.Y. 2024-2025',
    '1st Semester',
    "Course No: {$code} Units 3",
    "Description: {$subject}",
    '@ROW|1|REYES, ANA M.|BSIT|1.0|PASSED',
]);
$ocr = $page('MATH 101', 'Algebra') . "\f" . $page('ENG 202', 'Composition') . "\f" . $page('MATH 101', 'Algebra');
$records = dats_extract_gradesheet_records($ocr);

assert(count($records) === 2, 'Expected one record per student and subject.');
$pagesByCode = [];
foreach ($records as $record) $pagesByCode[$record['subject_code']] = $record['source_pages'];
assert($pagesByCode['MATH 101'] === [1, 3], 'Repeated subject rows must retain every matching page.');
assert($pagesByCode['ENG 202'] === [2], 'Each subject must retain its own page number.');

$directoryStudent = ['first_name' => 'Ana', 'middle_initial' => 'M', 'last_name' => 'Reyes'];
assert(dats_gradesheet_name_matches_student('REYES, ANA M.', $directoryStudent), 'Directory names should match the printed surname-first form.');
assert(dats_gradesheet_name_matches_student('ANA M REYES', $directoryStudent), 'Directory names should match the printed given-name-first form.');
assert(!dats_gradesheet_name_matches_student('REYES, BEA M.', $directoryStudent), 'A different first name must not match.');
assert(dats_gradesheet_pages_matching_student("REYES, ANA M. MATH 101\fOther page\fANA M REYES ENG 202", $directoryStudent) === [1, 3], 'Stored OCR should return every page containing the student name.');
$matchCriteria = [
    'student_name' => 'REYES, ANA M.', 'year_course' => 'BSIT 1st Year',
    'semester' => '1st Semester', 'academic_year' => '2024', 'subject_code' => 'MATH 101',
    'subject_name' => 'Algebra', 'units' => '3.0', 'grade' => '1.0', 'remarks' => 'PASSED',
];
$matchRecord = [
    'student_name' => 'REYES, ANA M.', 'year_course' => 'BSIT 1st Year', 'semester' => '1st Semester',
    'academic_year' => '2024', 'subject_code' => 'MATH 101', 'subject_name' => 'Algebra',
    'units' => 3, 'grade' => '1.0', 'remarks' => 'PASSED',
];
assert(dats_gradesheet_record_matches_criteria($matchRecord, $matchCriteria), 'All entered Edit Grade Record values should match the extracted record.');
assert(!dats_gradesheet_record_matches_criteria(array_merge($matchRecord, ['subject_code' => 'ENG 202']), $matchCriteria), 'A different subject must not match the Add Student criteria.');
assert(!dats_gradesheet_record_matches_criteria(array_merge($matchRecord, ['subject_code' => 'MATH 102']), $matchCriteria), 'A different subject number must not match the Add Student criteria.');

// A short lowercase OCR speck glued onto the course code (leading or trailing) must be stripped,
// the same as a single-letter speck, without eating a real short code like "IS 101" or "PE 1".
$leading = dats_extract_gradesheet_records($page('oo Computer 121', 'Fundamentals of Computer'));
assert(($leading[0]['subject_code'] ?? null) === 'Computer 121', 'A leading 2-letter speck ("oo Computer 121") must be dropped: got ' . var_export($leading[0]['subject_code'] ?? null, true));
$trailing = dats_extract_gradesheet_records($page('Computer 121 oo', 'Fundamentals of Computer'));
assert(($trailing[0]['subject_code'] ?? null) === 'Computer 121', 'A trailing 2-letter speck must still be dropped: got ' . var_export($trailing[0]['subject_code'] ?? null, true));
$realCode = dats_extract_gradesheet_records($page('IS 101', 'Information Systems'));
assert(($realCode[0]['subject_code'] ?? null) === 'IS 101', 'A real 2-letter uppercase code ("IS 101") must not be stripped: got ' . var_export($realCode[0]['subject_code'] ?? null, true));
assert(!dats_gradesheet_record_matches_criteria(array_merge($matchRecord, ['academic_year' => '2023']), $matchCriteria), 'A different school year must not match the Add Student criteria.');
$matchingPageText = 'REYES ANA M BSIT 1st Year 1st Semester S.Y. 2024-2025 Course No MATH 101 Units 3 Description Algebra Final Grade 1.0 PASSED';
assert(dats_gradesheet_page_matches_criteria($matchingPageText, $matchCriteria), 'Page OCR should match the full manually entered grade-record criteria.');
assert(!dats_gradesheet_page_matches_criteria(str_replace('MATH 101', 'MATH 102', $matchingPageText), $matchCriteria), 'Page OCR must reject a different subject code.');

$blockOcr = "Name: REYES, ANA M.\nSchool year: 2024\n1st Semester\nMATH 101 Algebra 3 1.00\fMATH 102 Geometry 3 1.50\fMATH 101 Algebra 3 1.00";
$blockRecords = dats_gradesheet_records_from_blocks(parse_batch_gradesheet_text($blockOcr));
assert(count($blockRecords) === 2, 'Expected both subject rows from the multi-page block layout.');
assert($blockRecords[0]['source_pages'] === [1, 3], 'Repeated block-layout subject should retain both pages.');
assert($blockRecords[1]['source_pages'] === [2], 'Second block-layout subject should point to page two.');

assert(dats_normalize_year_course('1st Year', 'BSIT') === 'BSIT 1st Year', 'Year and course must be combined without creating a duplicate row.');
assert(dats_normalize_year_course('BSIT 1st Year', '') === 'BSIT 1st Year', 'Existing combined values should remain stable when only one side is edited.');
assert(dats_normalize_year_course('', 'BSIT') === 'BSIT', 'Course-only edits should still save to the existing record.');

// A trailing "?" on the structured row's name field (added client-side when a low-confidence,
// letterless word — typically a pen checkmark — was stripped from the Name cell) must still flag
// the record for review even though the remaining text is a perfectly plausible name.
$cleanRow = dats_gradesheet_structured_row('@ROW|1|Dela Cruz, Juan|BSIT|1.0|PASSED');
assert($cleanRow !== null && $cleanRow['student_name'] === 'Dela Cruz, Juan' && $cleanRow['uncertain_name'] === false, 'A clean structured name must not be flagged uncertain.');
$noisyRow = dats_gradesheet_structured_row('@ROW|1|Dela Cruz, Juan?|BSIT|1.0|PASSED');
assert($noisyRow !== null, 'A name-noise marker must not prevent the row from being read.');
assert($noisyRow['student_name'] === 'Dela Cruz, Juan', 'The "?" noise marker must not leak into the saved student name.');
assert($noisyRow['uncertain_name'] === true, 'A name-noise marker must flag the record for review even when the remaining name looks plausible.');

// A fully glued name (OCR lost both the comma and the space) must still be split into FAMILY
// NAME, FIRST NAME order and sent for review, never left as one unparsed blob.
assert(dats_gradesheet_split_glued_name('DelaCruzJuan') === ['Dela Cruz', 'Juan'], 'A particle ("Dela") must stay attached to the surname word after it.');
assert(dats_gradesheet_split_glued_name('CruzJuan') === ['Cruz', 'Juan'], 'A simple two-part glued name must split surname/given correctly.');
assert(dats_gradesheet_split_glued_name('SantosMariaCristina') === ['Santos', 'Maria Cristina'], 'Extra given names after the surname must all be kept.');
assert(dats_gradesheet_split_glued_name('CRUZJUAN') === null, 'An all-caps blob has no case boundary to split on and must not be guessed.');
assert(dats_gradesheet_split_glued_name('Reyes') === null, 'A single name-shaped word must not be treated as glued.');
$gluedRow = dats_gradesheet_structured_row('@ROW|1|DelaCruzJuan|BSIT|1.0|PASSED');
assert($gluedRow !== null && $gluedRow['student_name'] === 'Dela Cruz, Juan', 'A glued structured-row name must be split into FAMILY NAME, FIRST NAME order.');
assert($gluedRow['uncertain_name'] === true, 'A glued-name split is a guess and must always be flagged for review.');

// The "Name:" labeled block-layout line must also normalize into FAMILY NAME, FIRST NAME order,
// not just pass through whatever order was printed.
[$normalized, $uncertain] = dats_gradesheet_normalize_block_name('Juan Dela Cruz');
assert($normalized === 'Dela Cruz, Juan', 'A "First Last" block name with no comma must be reordered to FAMILY NAME, FIRST NAME.');
assert($uncertain === true, 'Reordering a name with no comma to anchor on is a guess and must be flagged.');
[$commaNormalized, $commaUncertain] = dats_gradesheet_normalize_block_name('REYES, ANA M.');
assert($commaNormalized === 'REYES, ANA M.' && $commaUncertain === false, 'An already-comma-ordered name must pass through unflagged.');

echo "gradesheet_page_index_test: PASS\n";
