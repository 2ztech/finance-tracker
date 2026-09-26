<?php

declare(strict_types=1);

final class Account
{
    public static function all(bool $includeArchived = false): array
    {
        $db = Database::getConnection();
        $sql = "SELECT * FROM accounts";
        if (!$includeArchived) {
            $sql .= " WHERE archived = 0";
        }
        $sql .= " ORDER BY sort_order, id";
        return $db->query($sql)->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM accounts WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $d): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO accounts
                (name, kind, color_hex, bnpl_mode, statement_day, due_day, first_due_offset,
                 allow_partial, no_interest_months, credit_limit, opening_balance, start_month,
                 archived, sort_order, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)
        ");
        $stmt->execute([
            $d['name'],
            $d['kind'],
            $d['color_hex'] ?? '#4f6ef7',
            $d['bnpl_mode'] ?? null,
            $d['statement_day'] ?? null,
            $d['due_day'] ?? null,
            (int) ($d['first_due_offset'] ?? 1),
            (int) ($d['allow_partial'] ?? 0),
            $d['no_interest_months'] ?? null,
            $d['credit_limit'] ?? null,
            (float) ($d['opening_balance'] ?? 0),
            $d['start_month'] ?? null,
            (int) ($d['sort_order'] ?? 0),
            date('Y-m-d H:i:s'),
        ]);
        return (int) $db->lastInsertId();
    }

    public static function update(int $id, array $d): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            UPDATE accounts SET
                name = ?, kind = ?, color_hex = ?, bnpl_mode = ?, statement_day = ?, due_day = ?,
                first_due_offset = ?, allow_partial = ?, no_interest_months = ?, credit_limit = ?,
                opening_balance = ?, start_month = ?, archived = ?, sort_order = ?
            WHERE id = ?
        ");
        return $stmt->execute([
            $d['name'],
            $d['kind'],
            $d['color_hex'] ?? '#4f6ef7',
            $d['bnpl_mode'] ?? null,
            $d['statement_day'] ?? null,
            $d['due_day'] ?? null,
            (int) ($d['first_due_offset'] ?? 1),
            (int) ($d['allow_partial'] ?? 0),
            $d['no_interest_months'] ?? null,
            $d['credit_limit'] ?? null,
            (float) ($d['opening_balance'] ?? 0),
            $d['start_month'] ?? null,
            (int) ($d['archived'] ?? 0),
            (int) ($d['sort_order'] ?? 0),
            $id,
        ]);
    }

    public static function delete(int $id): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM accounts WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // --- Active account (session) ---

    public static function activeId(): ?int
    {
        $id = isset($_SESSION['active_account_id']) ? (int) $_SESSION['active_account_id'] : 0;
        if ($id > 0 && self::find($id) !== null) {
            return $id;
        }
        return self::defaultId();
    }

    public static function active(): ?array
    {
        $id = self::activeId();
        return $id ? self::find($id) : null;
    }

    public static function setActive(int $id): void
    {
        if (self::find($id) !== null) {
            $_SESSION['active_account_id'] = $id;
        }
    }

    public static function defaultId(): ?int
    {
        $db = Database::getConnection();
        $row = $db->query("SELECT id FROM accounts WHERE archived = 0 AND kind = 'savings' ORDER BY sort_order, id LIMIT 1")->fetchColumn();
        if ($row) {
            return (int) $row;
        }
        $row = $db->query("SELECT id FROM accounts WHERE archived = 0 ORDER BY sort_order, id LIMIT 1")->fetchColumn();
        return $row ? (int) $row : null;
    }

    public static function isLiability(array $account): bool
    {
        return $account['kind'] === 'credit' || $account['kind'] === 'paylater';
    }

    // --- Balances ---

    /** Raw movement sums within an optional date range. */
    private static function sums(int $id, ?string $from, ?string $to): array
    {
        $db = Database::getConnection();
        $txWhere = "account_id = ?";
        $txParams = [$id];
        if ($from !== null) { $txWhere .= " AND date >= ?"; $txParams[] = $from; }
        if ($to !== null)   { $txWhere .= " AND date <= ?"; $txParams[] = $to; }

        $income = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE type = 'income' AND $txWhere");
        $income->execute($txParams);
        $expense = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE type = 'expense' AND $txWhere");
        $expense->execute($txParams);

        $tWhere = "";
        $tParams = [$id];
        if ($from !== null) { $tWhere .= " AND date >= ?"; $tParams[] = $from; }
        if ($to !== null)   { $tWhere .= " AND date <= ?"; $tParams[] = $to; }

        $tin = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM transfers WHERE to_account_id = ?$tWhere");
        $tin->execute($tParams);
        $tout = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM transfers WHERE from_account_id = ?$tWhere");
        $tout->execute($tParams);

        return [
            'income'  => round((float) $income->fetchColumn(), 2),
            'expense' => round((float) $expense->fetchColumn(), 2),
            'in'      => round((float) $tin->fetchColumn(), 2),
            'out'     => round((float) $tout->fetchColumn(), 2),
        ];
    }

    /**
     * Natural balance: money you have (savings) or amount owed (credit/paylater).
     */
    public static function balance(int $id, ?string $asOf = null): float
    {
        $account = self::find($id);
        if ($account === null) {
            return 0.0;
        }

        $from = null;
        if ($account['kind'] === 'savings' && !empty($account['start_month'])) {
            $from = $account['start_month'] . '-01';
        }

        $s = self::sums($id, $from, $asOf);
        $opening = (float) $account['opening_balance'];

        // Natural ledger position: positive = cash you hold.
        $net = $opening + $s['income'] - $s['expense'] + $s['in'] - $s['out'];

        if (self::isLiability($account)) {
            // Outstanding = amount owed (positive when you owe).
            return round(-$net, 2);
        }
        return round($net, 2);
    }

    public static function movements(int $id, string $from, string $to): array
    {
        return self::sums($id, $from, $to);
    }

    /** Money spent on this account in a month (expenses; purchases for liabilities). */
    public static function monthExpense(int $id, string $month, string $year): float
    {
        $from = "$year-$month-01";
        $to = date('Y-m-t', strtotime($from));
        return self::sums($id, $from, $to)['expense'];
    }

    public static function netWorth(): float
    {
        $total = 0.0;
        foreach (self::all() as $a) {
            $bal = self::balance((int) $a['id']);
            $total += self::isLiability($a) ? -$bal : $bal;
        }
        return round($total, 2);
    }
}
