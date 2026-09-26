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

    public static function create(string $name, string $type, string $color_hex): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO categories (name, type, color_hex) VALUES (?, ?, ?)");
        return $stmt->execute([$name, $type, $color_hex]);
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
