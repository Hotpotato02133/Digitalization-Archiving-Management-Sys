<?php
declare(strict_types=1);

function dats_metric_rate(int $numerator, int $denominator): float
{
    return $denominator > 0 ? round(($numerator / $denominator) * 100, 1) : 0.0;
}

function dats_get_dashboard_analytics(PDO $pdo, ?DateTimeImmutable $now = null): array
{
    try {
        $pdo->exec('ALTER TABLE documents ADD COLUMN deleted_at DATETIME NULL');
    } catch (Throwable $ignored) {
    }

    $now ??= new DateTimeImmutable('now');
    $weekStart = $now->modify('monday this week')->setTime(0, 0, 0);
    $lastWeekStart = $weekStart->modify('-7 days');

    $documentSummary = $pdo->query(
        "SELECT
            COUNT(*) AS archived_documents,
            COALESCE(SUM(CASE WHEN ocr_status = 'Completed' THEN 1 ELSE 0 END), 0) AS ocr_completed_archived,
            COALESCE(SUM(CASE WHEN ocr_status = 'Pending' THEN 1 ELSE 0 END), 0) AS ocr_pending_archived,
            COALESCE(SUM(CASE WHEN ocr_status = 'Failed' THEN 1 ELSE 0 END), 0) AS ocr_failed_archived,
            COALESCE(SUM(COALESCE(file_size_bytes, 0)), 0) AS archived_storage_bytes,
            COALESCE(SUM(CASE WHEN file_path IS NULL OR file_path = '' THEN 1 ELSE 0 END), 0) AS missing_file_path_count,
            COALESCE(SUM(CASE WHEN file_size_bytes IS NULL THEN 1 ELSE 0 END), 0) AS missing_file_size_count
         FROM documents
         WHERE status = 'Archived' AND deleted_at IS NULL"
    )->fetch() ?: [];

    $eligibleStudents = (int) $pdo->query(
        "SELECT COUNT(*) FROM students WHERE deleted_at IS NULL AND status <> 'Inactive'"
    )->fetchColumn();
    $studentsWithArchive = (int) $pdo->query(
        "SELECT COUNT(DISTINCT s.id)
         FROM students s
         JOIN documents d ON d.student_id = s.id AND d.status = 'Archived' AND d.deleted_at IS NULL
         WHERE s.deleted_at IS NULL AND s.status <> 'Inactive'"
    )->fetchColumn();

    $weekCounts = $pdo->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN archived_at >= :week_start AND archived_at <= :now_at THEN 1 ELSE 0 END), 0) AS archived_this_week,
            COALESCE(SUM(CASE WHEN archived_at >= :last_week_start AND archived_at < :week_start_copy THEN 1 ELSE 0 END), 0) AS archived_last_week
         FROM documents
         WHERE status = 'Archived' AND deleted_at IS NULL"
    );
    $weekCounts->execute([
        ':week_start' => $weekStart->format('Y-m-d H:i:s'),
        ':now_at' => $now->format('Y-m-d H:i:s'),
        ':last_week_start' => $lastWeekStart->format('Y-m-d H:i:s'),
        ':week_start_copy' => $weekStart->format('Y-m-d H:i:s'),
    ]);
    $weekSummary = $weekCounts->fetch() ?: [];

    $collegeRows = $pdo->query(
        "SELECT
            c.id AS college_id,
            c.name AS college_name,
            COUNT(DISTINCT s.id) AS eligible_students,
            COUNT(DISTINCT CASE WHEN d.id IS NOT NULL THEN s.id END) AS covered_students
         FROM colleges c
         LEFT JOIN students s
           ON s.college_id = c.id
          AND s.deleted_at IS NULL
          AND s.status <> 'Inactive'
         LEFT JOIN documents d
           ON d.student_id = s.id
          AND d.status = 'Archived'
          AND d.deleted_at IS NULL
         WHERE c.status = 'Active'
         GROUP BY c.id, c.name
         ORDER BY c.name"
    )->fetchAll();
    $coverageByCollege = array_map(static function (array $row): array {
        $eligible = (int) $row['eligible_students'];
        $covered = (int) $row['covered_students'];
        return [
            'college_id' => (int) $row['college_id'],
            'college_name' => (string) $row['college_name'],
            'eligible_students' => $eligible,
            'covered_students' => $covered,
            'coverage_rate' => dats_metric_rate($covered, $eligible),
            'coverage_label' => $eligible > 0 ? null : 'No registered students',
        ];
    }, $collegeRows);

    $categoryRows = $pdo->query(
        "SELECT
            c.id AS category_id,
            c.name AS category_name,
            COUNT(d.id) AS document_count
         FROM document_categories c
         LEFT JOIN documents d
           ON d.category_id = c.id
          AND d.status = 'Archived'
          AND d.deleted_at IS NULL
         WHERE c.status = 'Active'
         GROUP BY c.id, c.name
         ORDER BY document_count DESC, c.name"
    )->fetchAll();
    $archiveByCategory = array_map(static fn (array $row): array => [
        'category_id' => (int) $row['category_id'],
        'category_name' => (string) $row['category_name'],
        'document_count' => (int) $row['document_count'],
    ], $categoryRows);

    $oldestWeekStart = $weekStart->modify('-49 days');
    $weekBuckets = [];
    for ($offset = 0; $offset < 8; $offset++) {
        $start = $oldestWeekStart->modify('+' . ($offset * 7) . ' days');
        $key = $start->format('Y-m-d');
        $weekBuckets[$key] = [
            'week_start' => $key,
            'week_label' => $start->format('M j'),
            'document_count' => 0,
        ];
    }
    $trendStatement = $pdo->prepare(
        "SELECT archived_at FROM documents
         WHERE status = 'Archived' AND deleted_at IS NULL AND archived_at >= :oldest AND archived_at <= :now_at
         ORDER BY archived_at"
    );
    $trendStatement->execute([
        ':oldest' => $oldestWeekStart->format('Y-m-d H:i:s'),
        ':now_at' => $now->format('Y-m-d H:i:s'),
    ]);
    foreach ($trendStatement->fetchAll() as $row) {
        $archivedAt = new DateTimeImmutable((string) $row['archived_at'], $now->getTimezone());
        $key = $archivedAt->modify('monday this week')->setTime(0, 0, 0)->format('Y-m-d');
        if (isset($weekBuckets[$key])) {
            $weekBuckets[$key]['document_count']++;
        }
    }

    $archivedDocuments = (int) ($documentSummary['archived_documents'] ?? 0);
    $ocrCompleted = (int) ($documentSummary['ocr_completed_archived'] ?? 0);
    $archivedThisWeek = (int) ($weekSummary['archived_this_week'] ?? 0);
    $archivedLastWeek = (int) ($weekSummary['archived_last_week'] ?? 0);

    return [
        'archived_documents' => $archivedDocuments,
        'eligible_students' => $eligibleStudents,
        'students_with_archived_documents' => $studentsWithArchive,
        'student_archive_coverage_rate' => dats_metric_rate($studentsWithArchive, $eligibleStudents),
        'archived_this_week' => $archivedThisWeek,
        'archived_last_week' => $archivedLastWeek,
        'archived_week_change' => $archivedThisWeek - $archivedLastWeek,
        'ocr_completed_archived' => $ocrCompleted,
        'ocr_pending_archived' => (int) ($documentSummary['ocr_pending_archived'] ?? 0),
        'ocr_failed_archived' => (int) ($documentSummary['ocr_failed_archived'] ?? 0),
        'search_ready_rate' => dats_metric_rate($ocrCompleted, $archivedDocuments),
        'archived_storage_bytes' => (int) ($documentSummary['archived_storage_bytes'] ?? 0),
        'missing_file_path_count' => (int) ($documentSummary['missing_file_path_count'] ?? 0),
        'missing_file_size_count' => (int) ($documentSummary['missing_file_size_count'] ?? 0),
        'coverage_by_college' => $coverageByCollege,
        'archive_by_week' => array_values($weekBuckets),
        'archive_by_category' => $archiveByCategory,
    ];
}

function dats_get_dashboard_activity(PDO $pdo, int $limit = 12): array
{
    $limit = max(1, min(50, $limit));
    $rows = $pdo->query(
        "SELECT
            a.id,
            a.action,
            a.entity_type,
            a.entity_id,
            a.description,
            a.created_at,
            u.full_name AS user_name,
            d.original_filename AS document_name,
            d.status AS document_status,
            c.name AS category_name,
            s.first_name AS student_first_name,
            s.last_name AS student_last_name
         FROM activity_logs a
         LEFT JOIN users u ON u.id = a.user_id
         LEFT JOIN documents d ON a.entity_type = 'document' AND d.id = a.entity_id
         LEFT JOIN students s ON s.id = d.student_id
         LEFT JOIN document_categories c ON c.id = d.category_id
         ORDER BY a.created_at DESC, a.id DESC
         LIMIT {$limit}"
    )->fetchAll();

    return array_map(static function (array $row): array {
        $studentName = trim((string) ($row['student_first_name'] ?? '') . ' ' . (string) ($row['student_last_name'] ?? ''));
        unset($row['student_first_name'], $row['student_last_name']);
        $row['student_name'] = $studentName !== '' ? $studentName : null;
        return $row;
    }, $rows);
}
