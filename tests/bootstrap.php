<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap.
 *
 * Registers a PSR-0 style autoloader for the flat src/ classes (the app has no
 * Composer autoloader) and points logging away from the repository. The test
 * database path is set per-test by TestDb::reset().
 */

spl_autoload_register(function (string $class): void {
    $file = __DIR__ . '/../src/' . $class . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'Tests\\')) {
        $relative = str_replace('\\', '/', substr($class, strlen('Tests\\')));
        $file = __DIR__ . '/' . $relative . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
});

// Keep test runs from writing into the repository's data/logs directory.
putenv('FINANCE_LOG_DIR=' . sys_get_temp_dir() . '/expenzz-test-logs');

require_once __DIR__ . '/Support/TestDb.php';
