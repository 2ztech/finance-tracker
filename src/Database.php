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
                due_date_day INTEGER NOT NULL CHECK(due_date_day >= 1 AND due_date_day <= 31)
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
        
        // Setup initial default user if not exists
        $stmt = $db->query("SELECT COUNT(*) FROM users");
        if ($stmt->fetchColumn() == 0) {
            $defaultPassword = password_hash('admin', PASSWORD_DEFAULT);
            $db->exec("INSERT INTO users (username, password_hash) VALUES ('admin', '$defaultPassword')");
        }
    }
}
