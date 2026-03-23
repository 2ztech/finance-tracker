<?php
class Category {
    public static function getAll(): array {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT * FROM categories ORDER BY type, name");
        return $stmt->fetchAll();
    }

    public static function create(string $name, string $type, string $color_hex): bool {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO categories (name, type, color_hex) VALUES (?, ?, ?)");
        return $stmt->execute([$name, $type, $color_hex]);
    }

    public static function delete(int $id): bool {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
