<?php

declare(strict_types=1);

final class Backup
{
    /** Tables that must exist for an uploaded DB to be accepted as a restore. */
    private const REQUIRED_TABLES = ['users', 'settings', 'categories', 'commitments', 'transactions'];

    /** Write a consistent snapshot of the live DB to $destPath. */
    public static function createConsistentCopy(string $destPath): bool
    {
        if (file_exists($destPath)) {
            @unlink($destPath);
        }

        $db = Database::getConnection();
        try {
            // SQLite 3.27+: produces a clean, transactionally-consistent copy.
            $db->exec("VACUUM INTO " . $db->quote($destPath));
            return file_exists($destPath);
        } catch (Throwable) {
            // Fallback for older SQLite builds: plain file copy.
            return @copy(self::dbPath(), $destPath);
        }
    }

    /** @return array{ok:bool,error:string} */
    public static function validateSqliteFile(string $path): array
    {
        if (!is_readable($path)) {
            return ['ok' => false, 'error' => 'File could not be read.'];
        }

        $fh = fopen($path, 'rb');
        $magic = $fh ? fread($fh, 16) : '';
        if ($fh) {
            fclose($fh);
        }
        if ($magic !== "SQLite format 3\0") {
            return ['ok' => false, 'error' => 'Not a valid SQLite database file.'];
        }

        try {
            $pdo = new PDO('sqlite:' . $path);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $integrity = $pdo->query('PRAGMA integrity_check')->fetchColumn();
            if ($integrity !== 'ok') {
                return ['ok' => false, 'error' => 'Database integrity check failed.'];
            }

            $tables = [];
            foreach ($pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'") as $row) {
                $tables[] = $row['name'];
            }
            $missing = array_diff(self::REQUIRED_TABLES, $tables);
            if (!empty($missing)) {
                return ['ok' => false, 'error' => 'Database is missing required tables: ' . implode(', ', $missing) . '.'];
            }
        } catch (Throwable $e) {
            error_log('Restore validation failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Database could not be opened or is corrupt.'];
        }

        return ['ok' => true, 'error' => ''];
    }

    /** Snapshot the current DB into data/backups/ and prune to the newest $keep files. */
    public static function backupCurrent(int $keep = 3): ?string
    {
        $dir = self::backupDir();
        if (!is_dir($dir) && !mkdir($dir, 0o777, true)) {
            return null;
        }

        $dest = $dir . '/finance_pre-restore_' . date('Y-m-d_Hi') . '.db';
        if (!self::createConsistentCopy($dest)) {
            return null;
        }

        self::prune($dir, $keep);
        return $dest;
    }

    private static function prune(string $dir, int $keep): void
    {
        $files = glob($dir . '/finance_pre-restore_*.db') ?: [];
        if (count($files) <= $keep) {
            return;
        }
        sort($files); // lexicographic == chronological for the timestamp format
        $excess = array_slice($files, 0, count($files) - $keep);
        foreach ($excess as $old) {
            @unlink($old);
        }
    }

    public static function backupDir(): string
    {
        return dirname(self::dbPath()) . '/backups';
    }

    public static function dbPath(): string
    {
        return dirname(__DIR__) . '/data/finance.db';
    }
}
