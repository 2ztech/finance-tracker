<?php

declare(strict_types=1);

/**
 * Lightweight JSON-lines application log for debugging user-reported issues.
 * Kept under the (git-ignored) data/ directory so it never ships or leaks.
 */
final class AppLog
{
    private const KEEP_DAYS = 14;

    public static function info(string $event, array $ctx = []): void
    {
        self::write('info', $event, $ctx);
    }

    public static function warn(string $event, array $ctx = []): void
    {
        self::write('warn', $event, $ctx);
    }

    public static function error(string $event, array $ctx = []): void
    {
        self::write('error', $event, $ctx);
    }

    public static function dir(): string
    {
        $env = getenv('FINANCE_LOG_DIR');
        if ($env !== false && $env !== '') {
            return $env;
        }
        return dirname(__DIR__) . '/data/logs';
    }

    public static function currentFile(): string
    {
        return self::dir() . '/app-' . date('Y-m-d') . '.log';
    }

    private static function write(string $level, string $event, array $ctx): void
    {
        try {
            $dir = self::dir();
            if (!is_dir($dir)) {
                @mkdir($dir, 0o777, true);
            }
            $line = json_encode([
                'ts'    => date('Y-m-d H:i:s'),
                'level' => $level,
                'event' => $event,
                'ctx'   => $ctx,
            ], JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
            @file_put_contents(self::currentFile(), $line . PHP_EOL, FILE_APPEND | LOCK_EX);
            if (random_int(1, 50) === 1) {
                self::prune();
            }
        } catch (Throwable) {
            // Never let logging break the app.
        }
    }

    public static function prune(): void
    {
        $cutoff = time() - self::KEEP_DAYS * 86400;
        foreach (glob(self::dir() . '/app-*.log') ?: [] as $file) {
            if (@filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
    }

    public static function tail(int $lines = 200): string
    {
        $file = self::currentFile();
        if (!is_file($file)) {
            return '';
        }
        $size = filesize($file);
        $fh = fopen($file, 'rb');
        if ($fh === false) {
            return '';
        }
        $chunk = 32768;
        $offset = max(0, $size - $chunk);
        fseek($fh, $offset);
        $data = stream_get_contents($fh);
        fclose($fh);
        $all = explode("\n", (string) $data);
        return implode("\n", array_slice($all, -$lines));
    }
}
