<?php

declare(strict_types=1);

final class Transfer
{
    public static function create(int $from, int $to, float $amount, string $date, string $description, string $kind = 'internal'): int
    {
        // Reject invalid transfers at the model layer as well as in the route.
        if ($from <= 0 || $to <= 0 || $from === $to || $amount <= 0) {
            return 0;
        }
        if (Account::find($from) === null || Account::find($to) === null) {
            return 0;
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO transfers (date, from_account_id, to_account_id, amount, description, kind, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$date, $from, $to, $amount, $description, $kind, date('Y-m-d H:i:s')]);
        return (int) $db->lastInsertId();
    }

    public static function find(int $id): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT t.*, fa.name AS from_name, ta.name AS to_name
            FROM transfers t
            LEFT JOIN accounts fa ON fa.id = t.from_account_id
            LEFT JOIN accounts ta ON ta.id = t.to_account_id
            WHERE t.id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function update(int $id, int $from, int $to, float $amount, string $date, string $description, string $kind = 'internal'): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            UPDATE transfers SET date = ?, from_account_id = ?, to_account_id = ?, amount = ?, description = ?, kind = ?
            WHERE id = ?
        ");
        return $stmt->execute([$date, $from, $to, $amount, $description, $kind, $id]);
    }

    public static function delete(int $id): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM transfers WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /** Transfers touching the account within a date range. */
    public static function forAccountBetween(int $accountId, string $from, string $to): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT t.*, fa.name AS from_name, ta.name AS to_name
            FROM transfers t
            LEFT JOIN accounts fa ON fa.id = t.from_account_id
            LEFT JOIN accounts ta ON ta.id = t.to_account_id
            WHERE t.date >= ? AND t.date <= ?
              AND (t.from_account_id = ? OR t.to_account_id = ?)
            ORDER BY t.date DESC, t.id DESC
        ");
        $stmt->execute([$from, $to, $accountId, $accountId]);
        return $stmt->fetchAll();
    }
}
