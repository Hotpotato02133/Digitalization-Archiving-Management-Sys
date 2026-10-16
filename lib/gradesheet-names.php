<?php
declare(strict_types=1);

/**
 * Student-name accuracy helpers for gradesheet uploads. Handwritten names are the weakest part of
 * the OCR pipeline, so names are (1) repaired of obvious OCR damage, (2) flagged by structural
 * checks instead of trusting OCR confidence, (3) reconciled across every page of the same upload
 * (the same student appears once per subject), and (4) matched against the Students directory.
 * Everything here is pure — no database — so it can be tested directly.
 */

/** Letters-only identity of a name: uppercased, accents on Ñ folded, everything else dropped. */
function dats_gn_key(string $name): string
{
    $upper = function_exists('mb_strtoupper') ? mb_strtoupper($name, 'UTF-8') : strtoupper($name);
    return preg_replace('/[^A-Z]/', '', str_replace('Ñ', 'N', $upper)) ?? '';
}

/** [surname key, given key] for a "SURNAME, Given" name, or null when it has no comma. */
function dats_gn_key_parts(string $name): ?array
{
    if (!str_contains($name, ',')) return null;
    [$surname, $given] = array_map('trim', explode(',', $name, 2));
    return [dats_gn_key($surname), dats_gn_key($given)];
}

function dats_gn_distance(string $a, string $b): int
{
    return levenshtein(substr($a, 0, 250), substr($b, 0, 250));
}

/** Review reasons stored on a record ("name,grade") as a list. */
function dats_gn_reasons(array $record): array
{
    $raw = $record['review_reasons'] ?? '';
    if (is_array($raw)) return array_values(array_filter(array_map('strval', $raw)));
    return array_values(array_filter(array_map('trim', explode(',', (string) $raw))));
}

function dats_gn_set_reasons(array $record, array $reasons): array
{
    $reasons = array_values(array_unique($reasons));
    $record['review_reasons'] = implode(',', $reasons);
    $record['needs_review'] = $reasons !== [];
    return $record;
}

/**
 * Cleans damage OCR leaves on a handwritten name without inventing letters: stray symbols, a
 * digit/initial prefix glued on from a row number or checkmark, a "." where the comma should be,
 * and run-together given names ("JuvieAnn" -> "Juvie Ann"). Returns [name, guessed]; guessed is
 * true whenever a repair changed what was read, so the caller keeps the record for review.
 */
function dats_gradesheet_repair_name(string $name): array
{
    $guessed = false;
    $before = $name;
    $name = preg_replace('/[^\p{L}\p{N} .,\'\-]/u', '', $name) ?? $name;
    if ($name !== $before) $guessed = true;

    if (preg_match('/^[A-Za-z]{0,2}\d+(?=[A-ZÑ]{3})/u', $name, $match)) {
        $name = substr($name, strlen($match[0]));
        $guessed = true;
    }
    // A one/two letter fragment ahead of an all-caps surname: "B HRAMIS, Estrella" / "ei TATILANO, Mary".
    if (preg_match('/^[A-Za-z]{1,2}\s+(?=[A-ZÑ]{3,}\s*,)/u', $name, $match)) {
        $name = substr($name, strlen($match[0]));
        $guessed = true;
    }
    // "HUSSIN.NURAISA": a period read where the comma was.
    if (!str_contains($name, ',') && preg_match('/^([A-ZÑ]{3,})\.([A-Za-zñÑ]{3,})/u', $name, $match)) {
        $name = $match[1] . ', ' . $match[2] . substr($name, strlen($match[0]));
        $guessed = true;
    }
    if (str_contains($name, ',')) {
        [$surname, $given] = array_map('trim', explode(',', $name, 2));
        $split = preg_replace('/([a-zñ])([A-ZÑ])(?=[a-zñ.]|$)/u', '$1 $2', $given) ?? $given;
        if ($split !== $given) {
            $given = $split;
            $guessed = true;
        }
        $name = $surname . ', ' . $given;
    }
    return [trim(preg_replace('/\s+/u', ' ', $name) ?? $name), $guessed];
}

/**
 * Structural reasons a name cannot be trusted, independent of OCR confidence (Tesseract is often
 * confident and wrong on handwriting). Empty list means the name has the expected shape.
 */
function dats_gradesheet_name_issues(string $name): array
{
    $name = trim($name);
    $issues = [];
    if (preg_match('/[^\p{L} .,\'\-]/u', $name)) $issues[] = 'bad_chars';
    if (!str_contains($name, ',')) {
        $issues[] = 'no_comma';
        return $issues;
    }
    [$surname, $given] = array_map('trim', explode(',', $name, 2));
    $letters = static fn (string $value): string => preg_replace('/[^\p{L}]/u', '', $value) ?? '';
    if (strlen($letters($surname)) < 3) $issues[] = 'short_surname';
    if (strlen($letters($given)) < 2) $issues[] = 'no_given';
    foreach (preg_split('/\s+/u', $given) ?: [] as $token) {
        $tokenLetters = $letters($token);
        $allCaps = $tokenLetters !== '' && preg_match('/\p{Ll}/u', $tokenLetters) !== 1;
        if (strlen($tokenLetters) >= 12 || ($allCaps && strlen($tokenLetters) >= 9) || preg_match('/\p{Ll}\p{Lu}/u', $tokenLetters)) {
            $issues[] = 'glued';
            break;
        }
    }
    return $issues;
}

/**
 * Larger is better; compared lexicographically. After well-formedness, MORE LETTERS wins: handwriting
 * OCR drops letters far more often than it invents them ("Viky" for "Vicky"), so the longest
 * plausible read is the best evidence of the true spelling.
 */
function dats_gn_variant_score(string $name, int $count, int $clean): array
{
    return [
        dats_gradesheet_name_issues($name) === [] ? 1 : 0,
        strlen(dats_gn_key($name)),
        $clean > 0 ? 1 : 0,
        str_contains($name, ',') ? 1 : 0,
        preg_match('/\p{Ll}/u', $name) ? 1 : 0,
        $count,
        substr_count($name, ' '),
    ];
}

/** True when two spellings plausibly read the same handwritten name. */
function dats_gn_same_person(string $a, string $b): bool
{
    $partsA = dats_gn_key_parts($a);
    $partsB = dats_gn_key_parts($b);
    if ($partsA !== null && $partsB !== null) {
        [$sa, $ga] = $partsA;
        [$sb, $gb] = $partsB;
        if ($sa === '' || $sb === '' || $ga === '' || $gb === '') return false;
        $allowedSurname = min(strlen($sa), strlen($sb)) >= 9 ? 2 : (min(strlen($sa), strlen($sb)) >= 5 ? 1 : 0);
        if (dats_gn_distance($sa, $sb) > $allowedSurname) return false;
        if ($ga === $gb) return true;
        // A middle initial present in one read and dropped in the other ("ANA" vs "ANAM").
        [$short, $long] = strlen($ga) <= strlen($gb) ? [$ga, $gb] : [$gb, $ga];
        if (strlen($short) >= 4 && strlen($long) - strlen($short) <= 2 && str_starts_with($long, $short)) return true;
        $longest = max(strlen($ga), strlen($gb));
        $allowedGiven = $longest >= 9 ? 2 : ($longest >= 4 ? 1 : 0);
        return dats_gn_distance($ga, $gb) <= $allowedGiven;
    }
    $ka = dats_gn_key($a);
    $kb = dats_gn_key($b);
    if (min(strlen($ka), strlen($kb)) < 8) return false;
    return dats_gn_distance($ka, $kb) <= max(1, (int) floor(0.15 * max(strlen($ka), strlen($kb))));
}

/**
 * The same student appears on one page per subject, so the spellings read for them across an upload
 * are independent attempts at one name. Spellings that are close enough are grouped and rewritten
 * to the best-formed one. Agreement is also evidence: when a group has at least two reads and its
 * chosen spelling is well-formed and was read without doubt at least once, the name-check flag is
 * cleared for the group.
 */
function dats_gradesheet_consensus_names(array $records): array
{
    $variants = [];
    foreach ($records as $record) {
        $name = trim((string) ($record['student_name'] ?? ''));
        if ($name === '' || $name === 'Unidentified student') continue;
        $variants[$name] = $variants[$name] ?? ['count' => 0, 'clean' => 0];
        $variants[$name]['count']++;
        if (!in_array('name', dats_gn_reasons($record), true)) $variants[$name]['clean']++;
    }
    if (!$variants) return $records;

    $names = array_keys($variants);
    usort($names, static fn (string $a, string $b): int => dats_gn_variant_score($b, $variants[$b]['count'], $variants[$b]['clean'])
        <=> dats_gn_variant_score($a, $variants[$a]['count'], $variants[$a]['clean']));

    $clusters = [];
    $clusterOf = [];
    foreach ($names as $name) {
        $placed = false;
        foreach ($clusters as $index => $cluster) {
            if (dats_gn_same_person($cluster['canonical'], $name)) {
                $clusters[$index]['members'][] = $name;
                $clusterOf[$name] = $index;
                $placed = true;
                break;
            }
        }
        if (!$placed) {
            $clusters[] = ['canonical' => $name, 'members' => [$name]];
            $clusterOf[$name] = count($clusters) - 1;
        }
    }

    // Spellings that differ in letters are rewritten to one consistent spelling (so a reviewer fixes
    // a student once), but stay flagged: two reads that disagree prove nothing about which is right.
    // Only reads with IDENTICAL letters (differing in spacing, case or punctuation) are independent
    // agreement, and that clears the flag when the shared spelling is well-formed and was read
    // without doubt at least once.
    $verifiedKey = [];
    foreach ($clusters as $index => $cluster) {
        $canonicalKey = dats_gn_key($cluster['canonical']);
        $agreeing = 0;
        $clean = 0;
        foreach ($cluster['members'] as $member) {
            if (dats_gn_key($member) !== $canonicalKey) continue;
            $agreeing += $variants[$member]['count'];
            $clean += $variants[$member]['clean'];
        }
        $verifiedKey[$index] = $agreeing >= 2 && $clean > 0 && dats_gradesheet_name_issues($cluster['canonical']) === [] ? $canonicalKey : null;
    }

    foreach ($records as $position => $record) {
        $name = trim((string) ($record['student_name'] ?? ''));
        if (!isset($clusterOf[$name])) continue;
        $index = $clusterOf[$name];
        $canonical = $clusters[$index]['canonical'];
        // A cluster whose best spelling is itself malformed gains nothing from being rewritten.
        if (dats_gradesheet_name_issues($canonical) !== [] && dats_gn_key($canonical) !== dats_gn_key($name)) continue;
        // Never rewrite a read to a spelling with FEWER letters: that would throw away evidence.
        if (strlen(dats_gn_key($canonical)) < strlen(dats_gn_key($name))) continue;
        $record['student_name'] = $canonical;
        if ($verifiedKey[$index] !== null && dats_gn_key($name) === $verifiedKey[$index]) {
            $record = dats_gn_set_reasons($record, array_diff(dats_gn_reasons($record), ['name']));
        }
        $records[$position] = $record;
    }
    return $records;
}

/**
 * Builds the lookup structure used by dats_gradesheet_apply_roster() from directory rows
 * (id, last_name, first_name, middle_initial, display).
 */
function dats_gradesheet_prepare_roster(array $students): array
{
    $roster = [];
    foreach ($students as $student) {
        $surname = dats_gn_key((string) ($student['last_name'] ?? ''));
        $given = dats_gn_key((string) ($student['first_name'] ?? ''));
        if ($surname === '' || $given === '') continue;
        $middle = dats_gn_key((string) ($student['middle_initial'] ?? ''));
        $roster[] = [
            'id' => (int) ($student['id'] ?? 0),
            'display' => (string) ($student['display'] ?? ''),
            'surname' => $surname,
            'keys' => array_values(array_unique([$surname . $given, $surname . $given . $middle, $given . $surname, $given . $middle . $surname])),
        ];
    }
    return $roster;
}

/** Best and runner-up roster candidates for one OCR name, by letters-only edit distance. */
function dats_gradesheet_roster_candidates(string $name, array $roster): array
{
    $key = dats_gn_key($name);
    if (strlen($key) < 6) return [null, PHP_INT_MAX];
    $best = null;
    $second = PHP_INT_MAX;
    foreach ($roster as $candidate) {
        $distance = PHP_INT_MAX;
        foreach ($candidate['keys'] as $rosterKey) {
            $distance = min($distance, dats_gn_distance($key, $rosterKey));
        }
        if ($best === null || $distance < $best['distance']) {
            if ($best !== null) $second = min($second, $best['distance']);
            $best = $candidate + ['distance' => $distance];
        } else {
            $second = min($second, $distance);
        }
    }
    return [$best, $second];
}

/**
 * Corrects names against the Students directory. A name is rewritten (and its name-check flag
 * cleared) only when ONE student is clearly the closest — within a small edit distance and at
 * least 2 edits closer than the runner-up. Weaker matches never change the record; the closest
 * student is attached as `suggested_name` so the reviewer can accept it in one click.
 */
function dats_gradesheet_apply_roster(array $records, array $roster): array
{
    if (!$roster) return $records;
    $cache = [];
    foreach ($records as $position => $record) {
        $name = trim((string) ($record['student_name'] ?? ''));
        if ($name === '' || $name === 'Unidentified student') continue;
        if (!isset($cache[$name])) {
            [$best, $second] = dats_gradesheet_roster_candidates($name, $roster);
            $verdict = ['match' => null, 'suggest' => null];
            if ($best !== null) {
                $length = strlen(dats_gn_key($name));
                $parts = dats_gn_key_parts($name);
                $surnameOk = $parts === null || dats_gn_distance($parts[0], $best['surname']) <= 2;
                $confident = $length >= 8 && $best['distance'] <= 3 && $best['distance'] <= 0.2 * $length
                    && ($second - $best['distance']) >= 2 && $surnameOk;
                if ($confident) {
                    $verdict['match'] = $best;
                } elseif ($length >= 6 && $best['distance'] <= max(4, (int) floor(0.35 * $length)) && $second > $best['distance']) {
                    $verdict['suggest'] = $best['display'];
                }
            }
            $cache[$name] = $verdict;
        }
        $verdict = $cache[$name];
        if ($verdict['match'] !== null) {
            $record['student_name'] = $verdict['match']['display'];
            $record['student_id_hint'] = $verdict['match']['id'];
            $record = dats_gn_set_reasons($record, array_diff(dats_gn_reasons($record), ['name']));
            unset($record['suggested_name']);
        } elseif ($verdict['suggest'] !== null && $verdict['suggest'] !== $name) {
            $record['suggested_name'] = $verdict['suggest'];
            $record = dats_gn_set_reasons($record, array_merge(dats_gn_reasons($record), ['name']));
        }
        $records[$position] = $record;
    }
    return $records;
}
