<?php

declare(strict_types=1);

final class Settings
{
    public static function get(string $key, mixed $default = null): mixed
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT value FROM settings WHERE key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['value'] : $default;
    }

    public static function set(string $key, string $value): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)");
        $stmt->execute([$key, $value]);
    }
}
