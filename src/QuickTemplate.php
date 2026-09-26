<?php

declare(strict_types=1);

final class QuickTemplate
{
    /** @return array<int, array<string,mixed>> */
    public static function getAll(?int $accountId = null): array
    {
        $db = Database::getConnection();
        $sql = "
            SELECT qt.*, c.name AS category_name, c.color_hex
            FROM quick_templates qt
            LEFT JOIN categories c ON qt.category_id = c.id";
        $params = [];
        if ($accountId !== null) {
            $sql .= " WHERE qt.account_id = ?";
            $params[] = $accountId;
        }
        $sql .= " ORDER BY qt.id";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function create(string $type, string $description, ?int $categoryId, float $amount, ?int $accountId = null): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO quick_templates (type, description, category_id, amount, account_id) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$type, $description, $categoryId, $amount, $accountId]);
    }

    public static function delete(int $id): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM quick_templates WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
