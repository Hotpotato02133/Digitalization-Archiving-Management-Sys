<?php
declare(strict_types=1);

// Run: php -d zend.assertions=1 -d assert.exception=1 tests/php/gradesheet_names_test.php
require_once __DIR__ . '/../../lib/gradesheet-parser.php';

// --- Repair: damage with one obvious reading (all taken from a real scanned upload) -------------
[$name, $guessed] = dats_gradesheet_repair_name('J3BAYOT, Joshly Kjeldahl D.');
assert($name === 'BAYOT, Joshly Kjeldahl D.' && $guessed, 'A digit prefix glued onto the surname must be stripped.');
[$name] = dats_gradesheet_repair_name('ei TATILANO, MARY');
assert($name === 'TATILANO, MARY', 'A short fragment ahead of an all-caps surname must be dropped.');
[$name] = dats_gradesheet_repair_name('HUSSIN.NURAISA');
assert($name === 'HUSSIN, NURAISA', 'A period where the comma belongs must become a comma.');
[$name] = dats_gradesheet_repair_name('REBOLLOS, JuvieAnn M.');
assert($name === 'REBOLLOS, Juvie Ann M.', 'Run-together given names split on the case boundary.');
[$name, $guessed] = dats_gradesheet_repair_name('PB[LAEAD, Maricrisl');
assert($name === 'PBLAEAD, Maricrisl' && $guessed, 'Stray symbols are removed and the record stays a guess.');
[$name, $guessed] = dats_gradesheet_repair_name('DELOS REYES, Princess May');
assert($name === 'DELOS REYES, Princess May' && !$guessed, 'A clean name must not be touched.');

// --- Structural flags: OCR confidence is not trusted ---------------------------------------------
assert(dats_gradesheet_name_issues('REYES, Ana M.') === [], 'A well-formed name has no issues.');
assert(in_array('no_comma', dats_gradesheet_name_issues('HUSSINNURAISA'), true), 'No comma is flagged.');
assert(in_array('glued', dats_gradesheet_name_issues('BALOYO, MARKRHODIEP.'), true), 'A long all-caps given-name blob is flagged as glued.');
assert(in_array('glued', dats_gradesheet_name_issues('GONZALES, TONIEZELDR.'), true), 'A glued given name must be flagged even with a comma.');
assert(in_array('bad_chars', dats_gradesheet_name_issues('SMITH, J0hn'), true), 'Digits inside a name are flagged.');
assert(in_array('short_surname', dats_gradesheet_name_issues('AB, Maria'), true), 'A 2-letter surname is flagged.');
assert(dats_gradesheet_name_issues('DELA CRUZ, Maria Angelica M.') === [], 'Long multi-word names are fine.');

// --- Structured rows now carry review reasons ----------------------------------------------------
$page = "Report of Rating\nS.Y. 2014-2015\n1st Semester\nCourse No: MATH 101 Units 3\nDescription: Algebra\n"
    . "@ROW|1|BAYOT, Joshly Kjeldahl D.|BEEDII-A|1.75|PASSED\n"
    . "@ROW|2|J3BAYOT, Joshly Kjeldahl D.|BEEDII-A|1.75|PASSED\n"
    . "@ROW|3|GONZALES, TONIEZELDR.|BEEDII-A|2.25|PASSED\n";
$records = parse_report_of_rating_text($page);
assert(count($records) === 2, 'The repaired duplicate must collapse into one record: ' . count($records));
$byName = [];
foreach ($records as $record) $byName[$record['student_name']] = $record;
assert(isset($byName['BAYOT, Joshly Kjeldahl D.']), 'The repaired name should be used.');
assert($byName['GONZALES, TONIEZELDR.']['needs_review'] === true, 'A glued name is flagged.');
assert(in_array('name', dats_gn_reasons($byName['GONZALES, TONIEZELDR.']), true), 'The reason names the name.');
assert(!in_array('grade', dats_gn_reasons($byName['GONZALES, TONIEZELDR.']), true), 'A good grade is not a reason.');

// --- Consensus across pages -----------------------------------------------------------------------
$read = static fn (string $name, array $reasons = []): array => dats_gn_set_reasons(['student_name' => $name, 'grade' => '1.5'], $reasons);
$records = [
    $read('PAMAONG, VICKY M.'), $read('PAMAONG, Viky M.', ['name']), $read('PAMAONG, Vicky M.'),
    $read('REBOLLOS, JUVIEANNM.', ['name']), $read('REBOLLOS, Ana D.'),
];
$merged = dats_gradesheet_consensus_names($records);
$names = array_column($merged, 'student_name');
assert(count(array_unique(array_slice($names, 0, 3))) === 1, 'Three reads of one student must settle on one spelling: ' . implode(' | ', $names));
assert($names[0] === 'PAMAONG, Vicky M.', 'The read with the most letters wins over a dropped-letter read ("Viky"): ' . $names[0]);
assert($merged[0]['needs_review'] === false && $merged[2]['needs_review'] === false, 'Two reads with identical letters clear the name check.');
assert($merged[1]['needs_review'] === true, 'A read whose letters disagreed is unified but stays flagged for a person to confirm.');

// Same letters, different spacing/case: independent agreement, so the glued read is corrected AND cleared.
$agree = dats_gradesheet_consensus_names([$read('GONZALES, TONIEZELDR.', ['name']), $read('GONZALES, Toniezel DR.')]);
assert($agree[0]['student_name'] === 'GONZALES, Toniezel DR.' && $agree[0]['needs_review'] === false, 'Identical letters with different spacing must agree and clear.');

// Letters disagree and the longer read is malformed: the shorter well-formed one is not "verified".
$disagree = dats_gradesheet_consensus_names([$read('SABILLANO, RIZZAMAER.', ['name']), $read('SABLLANO, Riza Mae R')]);
assert($disagree[0]['needs_review'] === true && $disagree[1]['needs_review'] === false || $disagree[0]['needs_review'] === true, 'Disagreeing reads must never clear a flag on the disputed spelling.');
assert($names[3] === 'REBOLLOS, JUVIEANNM.' && $names[4] === 'REBOLLOS, Ana D.', 'Two different students sharing a surname must NOT be merged.');
assert($merged[3]['needs_review'] === true, 'An unconfirmed single read stays flagged.');

$single = dats_gradesheet_consensus_names([$read('LONE, Reader A.', ['name'])]);
assert($single[0]['needs_review'] === true, 'One read alone is no evidence; the flag stays.');

// --- Roster matching ------------------------------------------------------------------------------
$roster = dats_gradesheet_prepare_roster([
    ['id' => 11, 'last_name' => 'Sanchez', 'first_name' => 'Richie', 'middle_initial' => 'A', 'display' => 'SANCHEZ, Richie A.'],
    ['id' => 12, 'last_name' => 'Santos', 'first_name' => 'Angela', 'middle_initial' => 'L', 'display' => 'SANTOS, Angela L.'],
    ['id' => 13, 'last_name' => 'Baloyo', 'first_name' => 'Mark Rhodie', 'middle_initial' => 'P', 'display' => 'BALOYO, Mark Rhodie P.'],
]);
$matched = dats_gradesheet_apply_roster([
    $read('SPNCHEZ, Richio A', ['name']),
    $read('BALOYO, MARKRHODIEP.', ['name']),
    $read('SANTOZ, Angel', ['name']),
    $read('NOBODY, Unknown Person', ['name']),
], $roster);
assert($matched[0]['student_name'] === 'SANCHEZ, Richie A.' && $matched[0]['student_id_hint'] === 11, 'A one-letter-off surname resolves to the directory student.');
assert($matched[0]['needs_review'] === false, 'A confident directory match clears the name check.');
assert($matched[1]['student_name'] === 'BALOYO, Mark Rhodie P.', 'A glued name resolves through the directory.');
assert(!isset($matched[3]['student_id_hint']) && $matched[3]['student_name'] === 'NOBODY, Unknown Person' && $matched[3]['needs_review'] === true, 'A name with no close student is left alone.');
assert(($matched[2]['suggested_name'] ?? '') === 'SANTOS, Angela L.' || ($matched[2]['student_name'] ?? '') === 'SANTOS, Angela L.', 'A near match is at least suggested.');

// Ambiguity: two directory students equally close must never be auto-picked.
$ambiguous = dats_gradesheet_prepare_roster([
    ['id' => 1, 'last_name' => 'Reyes', 'first_name' => 'Anna', 'middle_initial' => '', 'display' => 'REYES, Anna'],
    ['id' => 2, 'last_name' => 'Reyes', 'first_name' => 'Anne', 'middle_initial' => '', 'display' => 'REYES, Anne'],
]);
$result = dats_gradesheet_apply_roster([$read('REYES, Anny', ['name'])], $ambiguous);
assert(!isset($result[0]['student_id_hint']), 'An ambiguous near-match must not be auto-linked.');

echo "gradesheet names: all assertions passed\n";
