<?php

class Database {
    private static ?PDO $instance = null;
    private static string $dbPath = __DIR__ . '/../data/finance.db';

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $dsn = 'sqlite:' . self::$dbPath;
            
            try {
                // Ensure data directory exists
                $dir = dirname(self::$dbPath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }

                self::$instance = new PDO($dsn);
                self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                
                self::initSchema();
            } catch (PDOException $e) {
                die("Database connection failed: " . $e->getMessage());
            }
        }
        return self::$instance;
    }

    private static function initSchema() {
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
            )"
        ];

        foreach ($queries as $query) {
            $db->exec($query);
        }
        
        try {
            $db->exec("ALTER TABLE commitments ADD COLUMN category_id INTEGER REFERENCES categories(id) ON DELETE SET NULL");
        } catch (PDOException $e) {
            // Ignored, column already exists
        }
        
        try {
            $db->exec("ALTER TABLE commitments ADD COLUMN type TEXT NOT NULL DEFAULT 'expense'");
        } catch (PDOException $e) {
            // Ignored, column already exists
        }

        // Setup default categories
        $stmt = $db->query("SELECT COUNT(*) FROM categories");
        if ($stmt->fetchColumn() == 0) {
            $defaultCategories = [
                ['name' => 'Food & Dining', 'type' => 'expense', 'color_hex' => '#ef4444'],
                ['name' => 'Fuel & Transport', 'type' => 'expense', 'color_hex' => '#f97316'],
                ['name' => 'Utilities', 'type' => 'expense', 'color_hex' => '#eab308'],
                ['name' => 'Groceries', 'type' => 'expense', 'color_hex' => '#84cc16'],
                ['name' => 'Entertainment', 'type' => 'expense', 'color_hex' => '#06b6d4'],
                ['name' => 'Healthcare', 'type' => 'expense', 'color_hex' => '#ec4899'],
                ['name' => 'Motorcycle Maintenance', 'type' => 'expense', 'color_hex' => '#64748b'],
                ['name' => 'Subscriptions', 'type' => 'expense', 'color_hex' => '#8b5cf6'],
                ['name' => 'Salary', 'type' => 'income', 'color_hex' => '#10b981'],
                ['name' => 'Side Hustle', 'type' => 'income', 'color_hex' => '#3b82f6'],
                ['name' => 'Miscellaneous', 'type' => 'income', 'color_hex' => '#14b8a6']
            ];
            
            $insertCat = $db->prepare("INSERT INTO categories (name, type, color_hex) VALUES (?, ?, ?)");
            foreach ($defaultCategories as $cat) {
                $insertCat->execute([$cat['name'], $cat['type'], $cat['color_hex']]);
            }
        }
    }
}
