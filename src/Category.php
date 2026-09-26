<?php

declare(strict_types=1);

final class Category
{
    public static function getAll(): array
    {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT * FROM categories ORDER BY type, name");
        return $stmt->fetchAll();
    }

    public static function create(string $name, string $type, string $color_hex, string $iconKey = 'other', ?string $iconData = null, string $iconMime = 'image/png'): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO categories (name, type, color_hex, icon_key, icon_data, icon_mime) VALUES (?, ?, ?, ?, ?, ?)");
        return $stmt->execute([$name, $type, $color_hex, $iconKey, $iconData, $iconMime]);
    }

    public static function update(int $id, string $name, string $type, string $colorHex, string $iconKey, ?string $iconData = null, bool $clearIconData = false, string $iconMime = 'image/png'): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE categories SET name = ?, type = ?, color_hex = ?, icon_key = ?, icon_data = CASE WHEN ? = '1' THEN NULL WHEN ? IS NOT NULL THEN ? ELSE icon_data END, icon_mime = CASE WHEN ? = '1' THEN 'image/png' WHEN ? IS NOT NULL THEN ? ELSE icon_mime END WHERE id = ?");
        return $stmt->execute([$name, $type, $colorHex, $iconKey, (int)$clearIconData, $iconData, $iconData, (int)$clearIconData, $iconData, $iconMime, $id]);
    }

    public static function typeOf(int $id): ?string
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT type FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $type = $stmt->fetchColumn();
        return $type === false ? null : (string) $type;
    }

    /**
     * A category is valid for a transaction type when it matches, or when the
     * transaction is uncategorized (id <= 0). Enforced server-side so a crafted
     * POST cannot pair an income transaction with an expense category.
     */
    public static function isValidForType(int $id, string $type): bool
    {
        if ($id <= 0) {
            return true;
        }
        return self::typeOf($id) === $type;
    }

    public static function delete(int $id): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
