<?php

declare(strict_types=1);

/**
 * Isolated test database helper.
 *
 * Every test starts from a fresh SQLite file in the system temp directory
 * (never the repository's data/finance.db). The schema is created by the app's
 * own Database::getConnection() so tests exercise the real migrations.
 */
final class TestDb
{
    public static string $path = '';

    public static function reset(): void
    {
        $path = sys_get_temp_dir() . '/expenzz_test_' . getmypid() . '.db';
        @unlink($path);
        @unlink($path . '-journal');
        @unlink($path . '-wal');
        self::$path = $path;

        putenv('FINANCE_DB_PATH=' . $path);
        Database::reset();

        // Triggers schema creation + the one-time maintenance seed.
        Database::getConnection();
        self::wipe();
    }

    /** Remove all user data but keep the default categories. */
    private static function wipe(): void
    {
        $db = Database::getConnection();
        foreach ([
            'paylater_installments', 'paylater_plans', 'bills', 'transfers',
            'transactions', 'commitments', 'budgets', 'quick_templates',
            'accounts', 'login_attempts',
        ] as $table) {
            $db->exec("DELETE FROM {$table}");
        }
    }

    public static function pdo(): PDO
    {
        return Database::getConnection();
    }

    public static function makeSavings(string $name, float $opening = 0.0, ?string $start = '2026-01'): int
    {
        return Account::create([
            'name' => $name,
            'kind' => 'savings',
            'opening_balance' => $opening,
            'start_month' => $start,
        ]);
    }

    public static function makeCredit(string $name, ?int $dueDay = 20, ?float $limit = null): int
    {
        return Account::create([
            'name' => $name,
            'kind' => 'credit',
            'due_day' => $dueDay,
            'credit_limit' => $limit,
        ]);
    }

    public static function makePaylater(string $name, string $mode = 'per_purchase', ?int $dueDay = null, int $offset = 0, int $allowPartial = 1): int
    {
        return Account::create([
            'name' => $name,
            'kind' => 'paylater',
            'bnpl_mode' => $mode,
            'due_day' => $dueDay,
            'first_due_offset' => $offset,
            'allow_partial' => $allowPartial,
        ]);
    }

    public static function categoryId(string $name = 'Food & Dining'): int
    {
        $db = self::pdo();
        $stmt = $db->prepare("SELECT id FROM categories WHERE name = ? LIMIT 1");
        $stmt->execute([$name]);
        return (int) $stmt->fetchColumn();
    }

    public static function accountId(string $name): int
    {
        $db = self::pdo();
        $stmt = $db->prepare("SELECT id FROM accounts WHERE name = ? LIMIT 1");
        $stmt->execute([$name]);
        return (int) $stmt->fetchColumn();
    }

    public static function count(string $table, string $where = '', array $params = []): int
    {
        $db = self::pdo();
        $sql = "SELECT COUNT(*) FROM {$table}" . ($where !== '' ? " WHERE {$where}" : '');
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public static function scalar(string $sql, array $params = [])
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    public static function cleanup(): void
    {
        if (self::$path !== '') {
            @unlink(self::$path);
        }
    }
}
