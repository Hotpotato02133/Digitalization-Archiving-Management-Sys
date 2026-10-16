<?php
declare(strict_types=1);

function dats_database_backup_file(PDO $pdo): string
{
    $path = tempnam(sys_get_temp_dir(), 'dats-db-backup-');
    if ($path === false) {
        throw new RuntimeException('A temporary backup file could not be created.');
    }

    $transactionStarted = false;
    try {
        $stream = fopen($path, 'wb');
        if ($stream === false) {
            throw new RuntimeException('The temporary backup file could not be opened.');
        }

        try {
            if (!$pdo->inTransaction()) {
                $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
                $pdo->beginTransaction();
                $transactionStarted = true;
            }

            $write = static function (string $content) use ($stream): void {
                if (fwrite($stream, $content) !== strlen($content)) {
                    throw new RuntimeException('The database backup could not be written completely.');
                }
            };

            $objects = $pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM);
            $tables = [];
            $views = [];
            foreach ($objects as [$name, $type]) {
                if ($type === 'BASE TABLE') {
                    $tables[] = (string) $name;
                } elseif ($type === 'VIEW') {
                    $views[] = (string) $name;
                }
            }
            sort($tables, SORT_STRING);
            sort($views, SORT_STRING);

            $write("-- DATS_DATABASE_BACKUP_V1\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\nSET time_zone='+00:00';\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
            foreach ($views as $view) {
                $write('DROP VIEW IF EXISTS ' . dats_database_quote_identifier($view) . ";\n");
            }
            foreach ($tables as $table) {
                $write('DROP TABLE IF EXISTS ' . dats_database_quote_identifier($table) . ";\n");
            }

            foreach ($tables as $table) {
                $create = $pdo->query('SHOW CREATE TABLE ' . dats_database_quote_identifier($table))->fetch(PDO::FETCH_NUM);
                if (!$create || !isset($create[1])) {
                    throw new RuntimeException('The structure for table ' . $table . ' could not be read.');
                }
                $write(rtrim((string) $create[1], ";\r\n") . ";\n");

                $columns = $pdo->query('SHOW COLUMNS FROM ' . dats_database_quote_identifier($table))->fetchAll(PDO::FETCH_COLUMN);
                $quotedColumns = array_map('dats_database_quote_identifier', array_map('strval', $columns));
                $select = $pdo->query('SELECT * FROM ' . dats_database_quote_identifier($table));
                while ($row = $select->fetch(PDO::FETCH_NUM)) {
                    $values = array_map(static function (mixed $value) use ($pdo): string {
                        if ($value === null) {
                            return 'NULL';
                        }
                        $quoted = $pdo->quote((string) $value);
                        if ($quoted === false) {
                            throw new RuntimeException('A database value could not be encoded in the backup.');
                        }
                        return $quoted;
                    }, $row);
                    $write('INSERT INTO ' . dats_database_quote_identifier($table)
                        . ' (' . implode(', ', $quotedColumns) . ') VALUES (' . implode(', ', $values) . ");\n");
                }
            }

            foreach ($views as $view) {
                $create = $pdo->query('SHOW CREATE VIEW ' . dats_database_quote_identifier($view))->fetch(PDO::FETCH_NUM);
                if (!$create || !isset($create[1])) {
                    throw new RuntimeException('The definition for view ' . $view . ' could not be read.');
                }
                $definition = preg_replace('/\sDEFINER=`[^`]*`@`[^`]*`/i', '', (string) $create[1]);
                if (!is_string($definition)) {
                    throw new RuntimeException('The definition for view ' . $view . ' could not be prepared.');
                }
                $write(rtrim($definition, ";\r\n") . ";\n");
            }
            $write("SET FOREIGN_KEY_CHECKS=1;\n");
            if ($transactionStarted) {
                $pdo->commit();
                $transactionStarted = false;
            }
        } finally {
            fclose($stream);
        }

        return $path;
    } catch (Throwable $error) {
        if ($transactionStarted && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        @unlink($path);
        throw $error;
    }
}

function dats_database_quote_identifier(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function dats_database_split_sql(string $sql): array
{
    $statements = [];
    $statement = '';
    $quote = null;
    $length = strlen($sql);

    for ($index = 0; $index < $length; $index++) {
        $character = $sql[$index];
        if ($quote !== null) {
            $statement .= $character;
            if ($character === '\\' && $quote !== '`' && $index + 1 < $length) {
                $statement .= $sql[++$index];
            } elseif ($character === $quote) {
                if ($index + 1 < $length && $sql[$index + 1] === $quote) {
                    $statement .= $sql[++$index];
                } else {
                    $quote = null;
                }
            }
            continue;
        }

        if ($character === "'" || $character === '"' || $character === '`') {
            $quote = $character;
            $statement .= $character;
        } elseif ($character === ';') {
            if (trim($statement) !== '') {
                $statements[] = trim($statement);
            }
            $statement = '';
        } else {
            $statement .= $character;
        }
    }

    if ($quote !== null) {
        throw new InvalidArgumentException('The SQL backup contains an unterminated quoted value.');
    }
    if (trim($statement) !== '') {
        $statements[] = trim($statement);
    }

    return $statements;
}

function dats_restore_database_backup(PDO $pdo, string $path): int
{
    $stream = fopen($path, 'rb');
    if ($stream === false) {
        throw new InvalidArgumentException('The uploaded SQL file could not be read.');
    }
    $header = fgets($stream);
    fclose($stream);
    if (trim((string) $header) !== '-- DATS_DATABASE_BACKUP_V1') {
        throw new InvalidArgumentException('This is not a supported DATS database backup file.');
    }

    $contents = file_get_contents($path);
    if (!is_string($contents) || strlen($contents) > 268435456) {
        throw new InvalidArgumentException('The SQL file is unreadable or exceeds the 256 MB restore limit.');
    }
    $contents = substr($contents, strlen((string) $header));
    $statements = dats_database_split_sql($contents);
    $createCount = 0;
    $foreignKeyChecksDisabled = false;
    $foreignKeyChecksRestored = false;

    foreach ($statements as $statement) {
        if (preg_match('/^SET FOREIGN_KEY_CHECKS\s*=\s*0$/i', $statement)) {
            $foreignKeyChecksDisabled = true;
        } elseif (preg_match('/^SET FOREIGN_KEY_CHECKS\s*=\s*1$/i', $statement)) {
            $foreignKeyChecksRestored = true;
        } elseif (preg_match("/^SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO'$/i", $statement)
            || preg_match("/^SET time_zone='\\+00:00'$/i", $statement)
            || preg_match('/^SET NAMES utf8mb4$/i', $statement)) {
            // Fixed connection settings emitted by DATS backups.
        } elseif (preg_match('/^DROP (?:TABLE|VIEW) IF EXISTS\s+`[^`]+`$/i', $statement)) {
            // Expected database object removal from a DATS-generated backup.
        } elseif (preg_match('/^CREATE TABLE\s+`[^`]+`\s*\(/i', $statement)) {
            $createCount++;
        } elseif (preg_match('/^INSERT INTO\s+`[^`]+`\s*\(/i', $statement)) {
            // Expected row data from a DATS-generated backup.
        } elseif (preg_match('/^CREATE\b.*\bVIEW\s+`[^`]+`\s+AS\s+/is', $statement)) {
            $createCount++;
        } else {
            throw new InvalidArgumentException('The SQL file contains an unsupported statement and was not restored.');
        }
    }

    if (!$foreignKeyChecksDisabled || !$foreignKeyChecksRestored || $createCount === 0) {
        throw new InvalidArgumentException('The SQL file is incomplete or is not a supported DATS backup.');
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    try {
        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }
    } catch (Throwable $error) {
        throw new RuntimeException('Restore stopped partway through. The database may be partially restored; contact your database administrator before using DATS.', 0, $error);
    } finally {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    return $createCount;
}