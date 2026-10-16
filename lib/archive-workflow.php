<?php
declare(strict_types=1);

function dats_is_pending_archive_status(string $status): bool
{
    return in_array($status, ['Processing', 'Needs review'], true);
}

/**
 * Build a safe, year-based archive destination. Existing archive paths are
 * returned unchanged so migrations can be retried without moving files twice.
 */
function dats_archive_target(?string $currentPath, ?string $storedFilename, ?string $academicYear, ?string $token = null): array
{
    $normalizedPath = str_replace('\\', '/', trim((string) $currentPath));
    if (str_starts_with($normalizedPath, 'archive/')) {
        return [
            'directory' => dirname($normalizedPath),
            'filename' => basename($normalizedPath),
            'relative_path' => $normalizedPath,
            'requires_move' => false,
        ];
    }

    $year = preg_match('/^(20\d{2}|2100)$/', trim((string) $academicYear)) === 1
        ? trim((string) $academicYear)
        : 'Unsorted';
    $base = basename(str_replace('\\', '/', (string) ($storedFilename ?: 'document')));
    $safeBase = preg_replace('/[^A-Za-z0-9._-]/', '_', $base) ?: 'document';
    $prefix = $token ?: bin2hex(random_bytes(12));
    $filename = $prefix . '_' . $safeBase;
    $directory = 'archive/' . $year;

    return [
        'directory' => $directory,
        'filename' => $filename,
        'relative_path' => $directory . '/' . $filename,
        'requires_move' => true,
    ];
}
