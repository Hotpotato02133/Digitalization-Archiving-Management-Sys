<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/archive-workflow.php';

$target = dats_archive_target('uploads/example.pdf', 'example.pdf', '2026', 'abcdef');
assert($target['directory'] === 'archive/2026');
assert($target['relative_path'] === 'archive/2026/abcdef_example.pdf');
assert($target['filename'] === 'abcdef_example.pdf');

$unsorted = dats_archive_target('uploads/example.pdf', '../unsafe name.pdf', null, '123456');
assert($unsorted['directory'] === 'archive/Unsorted');
assert($unsorted['relative_path'] === 'archive/Unsorted/123456_unsafe_name.pdf');

$alreadyArchived = dats_archive_target('archive/2025/already.pdf', 'already.pdf', '2025', 'unused');
assert($alreadyArchived['relative_path'] === 'archive/2025/already.pdf');
assert($alreadyArchived['requires_move'] === false);

assert(dats_is_pending_archive_status('Processing'));
assert(dats_is_pending_archive_status('Needs review'));
assert(!dats_is_pending_archive_status('Archived'));
assert(!dats_is_pending_archive_status('Rejected'));

echo "archive_workflow_test: PASS\n";
