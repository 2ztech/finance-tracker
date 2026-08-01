<?php

declare(strict_types=1);

final class QuickTemplate
{
    /** @return array<int, array{id:int, category_id:int|null, category_name:string|null, color_hex:string|null, type:string, description:string, amount:float}> */
    public static function getAll(): array
    {
        $db = Database::getConnection();
        $stmt = $db->query("
            SELECT qt.*, c.name AS category_name, c.color_hex
            FROM quick_templates qt
            LEFT JOIN categories c ON qt.category_id = c.id
            ORDER BY qt.id
        ");
        return $stmt->fetchAll();
    }

    public static function create(string $type, string $description, ?int $categoryId, float $amount): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO quick_templates (type, description, category_id, amount) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$type, $description, $categoryId, $amount]);
    }

    public static function delete(int $id): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM quick_templates WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
