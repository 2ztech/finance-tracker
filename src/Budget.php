<?php

declare(strict_types=1);

final class Budget
{
    /** @return array<int, array{category_id:int, category_name:string, color_hex:string, amount:float, type:string}> */
    public static function getAll(): array
    {
        $db = Database::getConnection();
        $stmt = $db->query("
            SELECT b.category_id, c.name AS category_name, c.color_hex, b.amount, c.type
            FROM budgets b
            JOIN categories c ON b.category_id = c.id
            WHERE c.type = 'expense'
            ORDER BY c.name
        ");
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[(int) $row['category_id']] = $row;
        }
        return $result;
    }

    public static function set(int $categoryId, float $amount): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT OR REPLACE INTO budgets (id, category_id, amount) VALUES ((SELECT id FROM budgets WHERE category_id = ?), ?, ?)");
        $stmt->execute([$categoryId, $categoryId, $amount]);
    }

    public static function delete(int $categoryId): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM budgets WHERE category_id = ?");
        $stmt->execute([$categoryId]);
    }

    /** @return array{category_id:int, amount:float}|null */
    public static function get(int $categoryId): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT category_id, amount FROM budgets WHERE category_id = ?");
        $stmt->execute([$categoryId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
