<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/database-backup.php';

final class DatabaseBackupTestPdo extends PDO
{
    public array $executed = [];

    public function __construct()
    {
    }

    public function exec(string $statement): int|false
    {
        $this->executed[] = $statement;
        return 0;
    }
}

$statements = dats_database_split_sql("INSERT INTO `records` (`value`) VALUES ('has; semicolon');\nINSERT INTO `records` (`value`) VALUES ('it''s doubled');\n");
if (count($statements) !== 2 || !str_contains($statements[0], 'has; semicolon') || !str_contains($statements[1], "it''s doubled")) {
    throw new RuntimeException('SQL splitting must preserve semicolons and doubled quote characters inside string values.');
}

$invalidBackup = tempnam(sys_get_temp_dir(), 'dats-invalid-backup-');
if ($invalidBackup === false) {
    throw new RuntimeException('Could not create SQL validation fixture.');
}
file_put_contents($invalidBackup, "-- DATS_DATABASE_BACKUP_V1\nSET FOREIGN_KEY_CHECKS=0;\nDROP DATABASE `dats`;\n");
try {
    dats_restore_database_backup(new PDO('sqlite::memory:'), $invalidBackup);
    throw new RuntimeException('Unsupported SQL statements must be rejected before execution.');
} catch (InvalidArgumentException $error) {
    if (!str_contains($error->getMessage(), 'unsupported statement')) {
        throw $error;
    }
} finally {
    unlink($invalidBackup);
}

$supportedBackup = tempnam(sys_get_temp_dir(), 'dats-supported-backup-');
if ($supportedBackup === false) {
    throw new RuntimeException('Could not create valid SQL fixture.');
}
file_put_contents($supportedBackup, "-- DATS_DATABASE_BACKUP_V1\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\nSET time_zone='+00:00';\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nDROP TABLE IF EXISTS `records`;\nCREATE TABLE `records` (`id` int NOT NULL);\nINSERT INTO `records` (`id`) VALUES (1);\nCREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `recent_records` AS SELECT `id` FROM `records`;\nSET FOREIGN_KEY_CHECKS=1;\n");
try {
    $pdo = new DatabaseBackupTestPdo();
    $objectCount = dats_restore_database_backup($pdo, $supportedBackup);
    if ($objectCount !== 2 || count($pdo->executed) !== 11) {
        throw new RuntimeException('A supported backup should execute its schema, rows, and connection settings.');
    }
} finally {
    unlink($supportedBackup);
}

echo "database_backup_test: PASS\n";