<?php

declare(strict_types=1);

/**
 * Reset the isolated E2E database. Never touches data/finance.db: the target
 * comes from FINANCE_DB_PATH (set by playwright.config.js).
 */
$root = dirname(__DIR__, 2);
spl_autoload_register(function (string $class) use ($root): void {
    $file = $root . '/src/' . $class . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

$path = getenv('FINANCE_DB_PATH');
if ($path === false || $path === '') {
    fwrite(STDERR, "FINANCE_DB_PATH not set\n");
    exit(1);
}

@unlink($path);
@unlink($path . '-journal');
@unlink($path . '-wal');

putenv('FINANCE_DB_PATH=' . $path);
Database::reset();
$db = Database::getConnection();

foreach ([
    'paylater_installments', 'paylater_plans', 'bills', 'transfers',
    'transactions', 'commitments', 'budgets', 'quick_templates',
    'accounts', 'login_attempts', 'users',
] as $table) {
    $db->exec("DELETE FROM {$table}");
}

Auth::setupFirstUser('admin', 'admin');
echo "E2E database reset at {$path}\n";
