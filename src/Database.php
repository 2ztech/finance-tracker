<?php

declare(strict_types=1);

final class Database
{
    private static ?PDO $instance = null;
    private static ?string $dbPath = null;

    /**
     * Resolve the SQLite path. Production keeps using data/finance.db; the
     * FINANCE_DB_PATH env var lets the automated test suite point at an
     * isolated database without touching real data.
     */
    public static function path(): string
    {
        if (self::$dbPath === null) {
            $env = getenv('FINANCE_DB_PATH');
            self::$dbPath = ($env !== false && $env !== '')
                ? $env
                : dirname(__DIR__) . '/data/finance.db';
        }
        return self::$dbPath;
    }

    /** Test helper: drop the cached connection/path so the next call reconnects. */
    public static function reset(): void
    {
        self::$instance = null;
        self::$dbPath = null;
    }

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $dsn = 'sqlite:' . self::path();

            try {
                $dir = dirname(self::path());
                if (!is_dir($dir)) {
                    mkdir($dir, 0o777, true);
                }

                self::$instance = new PDO($dsn);
                self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                self::$instance->exec('PRAGMA foreign_keys = ON');

                self::initSchema();
            } catch (PDOException $e) {
                error_log('Database connection failed: ' . $e->getMessage());
                http_response_code(500);
                die('A database error occurred. Please try again later.');
            }
        }
        return self::$instance;
    }

    private static function initSchema(): void
    {
        $db = self::$instance;

        $queries = [
            "CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS settings (
                key TEXT PRIMARY KEY,
                value TEXT NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                type TEXT NOT NULL CHECK(type IN ('income', 'expense')),
                color_hex TEXT NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS commitments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                amount REAL NOT NULL,
                due_date_day INTEGER NOT NULL CHECK(due_date_day >= 1 AND due_date_day <= 31),
                category_id INTEGER,
                FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
            )",
            "CREATE TABLE IF NOT EXISTS transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category_id INTEGER,
                amount REAL NOT NULL,
                type TEXT NOT NULL CHECK(type IN ('income', 'expense')),
                description TEXT,
                date TEXT NOT NULL,
                FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
            )",
            "CREATE TABLE IF NOT EXISTS budgets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category_id INTEGER NOT NULL,
                amount REAL NOT NULL,
                FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
            )",
            "CREATE TABLE IF NOT EXISTS quick_templates (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category_id INTEGER,
                type TEXT NOT NULL CHECK(type IN ('income', 'expense')),
                description TEXT NOT NULL,
                amount REAL DEFAULT 0,
                FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
            )",
            "CREATE TABLE IF NOT EXISTS login_attempts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ip TEXT NOT NULL,
                attempted_at TEXT NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS accounts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                kind TEXT NOT NULL CHECK(kind IN ('savings', 'credit', 'paylater')),
                color_hex TEXT NOT NULL DEFAULT '#4f6ef7',
                bnpl_mode TEXT CHECK(bnpl_mode IN ('cycle', 'per_purchase')),
                statement_day INTEGER,
                due_day INTEGER,
                first_due_offset INTEGER NOT NULL DEFAULT 1,
                allow_partial INTEGER NOT NULL DEFAULT 0,
                no_interest_months INTEGER,
                credit_limit REAL,
                opening_balance REAL NOT NULL DEFAULT 0,
                start_month TEXT,
                archived INTEGER NOT NULL DEFAULT 0,
                sort_order INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS transfers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                date TEXT NOT NULL,
                from_account_id INTEGER NOT NULL,
                to_account_id INTEGER NOT NULL,
                amount REAL NOT NULL,
                description TEXT,
                kind TEXT NOT NULL DEFAULT 'internal',
                created_at TEXT NOT NULL,
                FOREIGN KEY (from_account_id) REFERENCES accounts(id) ON DELETE CASCADE,
                FOREIGN KEY (to_account_id) REFERENCES accounts(id) ON DELETE CASCADE
            )",
            "CREATE TABLE IF NOT EXISTS paylater_plans (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                account_id INTEGER NOT NULL,
                purchase_txn_id INTEGER,
                total_payable REAL NOT NULL,
                cash_price REAL,
                interest REAL NOT NULL DEFAULT 0,
                months INTEGER NOT NULL,
                installment_amount REAL NOT NULL,
                first_due_date TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'active',
                created_at TEXT NOT NULL,
                FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
            )",
            "CREATE TABLE IF NOT EXISTS paylater_installments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                plan_id INTEGER NOT NULL,
                seq INTEGER NOT NULL,
                due_date TEXT NOT NULL,
                amount REAL NOT NULL,
                paid_amount REAL NOT NULL DEFAULT 0,
                status TEXT NOT NULL DEFAULT 'open',
                bill_id INTEGER,
                FOREIGN KEY (plan_id) REFERENCES paylater_plans(id) ON DELETE CASCADE
            )",
            "CREATE TABLE IF NOT EXISTS bills (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                account_id INTEGER NOT NULL,
                period_start TEXT,
                period_end TEXT,
                due_date TEXT NOT NULL,
                amount_due REAL NOT NULL DEFAULT 0,
                paid_amount REAL NOT NULL DEFAULT 0,
                status TEXT NOT NULL DEFAULT 'open',
                FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
            )",
            "CREATE INDEX IF NOT EXISTS idx_transactions_date ON transactions(date)",
            "CREATE INDEX IF NOT EXISTS idx_transactions_category ON transactions(category_id)",
            "CREATE INDEX IF NOT EXISTS idx_login_attempts_ip ON login_attempts(ip, attempted_at)",
            "CREATE INDEX IF NOT EXISTS idx_transfers_date ON transfers(date)",
            "CREATE INDEX IF NOT EXISTS idx_plans_account ON paylater_plans(account_id)",
            "CREATE INDEX IF NOT EXISTS idx_installments_plan ON paylater_installments(plan_id)",
            "CREATE INDEX IF NOT EXISTS idx_installments_due ON paylater_installments(due_date)",
            "CREATE UNIQUE INDEX IF NOT EXISTS idx_bills_account_due ON bills(account_id, due_date)",
        ];

        foreach ($queries as $query) {
            $db->exec($query);
        }

        $alterations = [
            "ALTER TABLE commitments ADD COLUMN category_id INTEGER REFERENCES categories(id) ON DELETE SET NULL",
            "ALTER TABLE commitments ADD COLUMN type TEXT NOT NULL DEFAULT 'expense'",
            "ALTER TABLE commitments ADD COLUMN start_date TEXT",
            "ALTER TABLE commitments ADD COLUMN end_date TEXT",
            "ALTER TABLE commitments ADD COLUMN account_id INTEGER REFERENCES accounts(id) ON DELETE SET NULL",
            "ALTER TABLE transactions ADD COLUMN account_id INTEGER REFERENCES accounts(id) ON DELETE SET NULL",
            "ALTER TABLE transactions ADD COLUMN plan_id INTEGER",
            "ALTER TABLE transactions ADD COLUMN refund_of_id INTEGER",
            "ALTER TABLE quick_templates ADD COLUMN account_id INTEGER REFERENCES accounts(id) ON DELETE SET NULL",
        ];

        foreach ($alterations as $alter) {
            try {
                $db->exec($alter);
            } catch (PDOException) {
                // Column already exists
            }
        }

        // Indexes that depend on columns added above must run after the ALTERs.
        foreach ([
            "CREATE INDEX IF NOT EXISTS idx_transactions_account ON transactions(account_id)",
            "CREATE INDEX IF NOT EXISTS idx_commitments_account ON commitments(account_id)",
        ] as $index) {
            try {
                $db->exec($index);
            } catch (PDOException) {
                // ignore
            }
        }

        $stmt = $db->query("SELECT COUNT(*) FROM categories");
        if ($stmt->fetchColumn() == 0) {
            $defaultCategories = [
                ['name' => 'Food & Dining',          'type' => 'expense', 'color_hex' => '#ef4444'],
                ['name' => 'Fuel & Transport',       'type' => 'expense', 'color_hex' => '#f97316'],
                ['name' => 'Utilities',              'type' => 'expense', 'color_hex' => '#eab308'],
                ['name' => 'Groceries',              'type' => 'expense', 'color_hex' => '#84cc16'],
                ['name' => 'Entertainment',          'type' => 'expense', 'color_hex' => '#06b6d4'],
                ['name' => 'Healthcare',             'type' => 'expense', 'color_hex' => '#ec4899'],
                ['name' => 'Motorcycle Maintenance', 'type' => 'expense', 'color_hex' => '#64748b'],
                ['name' => 'Subscriptions',          'type' => 'expense', 'color_hex' => '#8b5cf6'],
                ['name' => 'Salary',                 'type' => 'income',  'color_hex' => '#10b981'],
                ['name' => 'Side Hustle',            'type' => 'income',  'color_hex' => '#3b82f6'],
                ['name' => 'Miscellaneous',          'type' => 'income',  'color_hex' => '#14b8a6'],
            ];

            $insertCat = $db->prepare("INSERT INTO categories (name, type, color_hex) VALUES (?, ?, ?)");
            foreach ($defaultCategories as $cat) {
                $insertCat->execute([$cat['name'], $cat['type'], $cat['color_hex']]);
            }
        }

        Maintenance::run();
    }
}
