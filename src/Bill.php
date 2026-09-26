<?php

declare(strict_types=1);

final class Bill
{
    /** Rebuild the bill rows for a liability account. */
    public static function sync(int $accountId): void
    {
        $account = Account::find($accountId);
        if ($account === null) {
            return;
        }
        if ($account['kind'] === 'paylater') {
            self::syncPaylater($accountId);
        } elseif ($account['kind'] === 'credit') {
            self::syncCredit($account);
        }
    }

    /** Paylater: bills are derived from instalments (authoritative paid state). */
    private static function syncPaylater(int $accountId): void
    {
        $db = Database::getConnection();

        // Drop plans whose purchase transaction no longer exists.
        PaylaterPlan::reconcileAccount($accountId);

        $stmt = $db->prepare("
            SELECT i.due_date AS due_date,
                   ROUND(SUM(i.amount), 2) AS amount_due,
                   ROUND(SUM(i.paid_amount), 2) AS paid_amount
            FROM paylater_installments i
            JOIN paylater_plans p ON p.id = i.plan_id
            WHERE p.account_id = ?
            GROUP BY i.due_date
            ORDER BY i.due_date
        ");
        $stmt->execute([$accountId]);
        $groups = $stmt->fetchAll();

        $db->prepare("DELETE FROM bills WHERE account_id = ?")->execute([$accountId]);

        $insert = $db->prepare("INSERT INTO bills (account_id, due_date, amount_due, paid_amount, status) VALUES (?, ?, ?, ?, ?)");
        foreach ($groups as $g) {
            $due = (float) $g['amount_due'];
            $paid = (float) $g['paid_amount'];
            $status = $paid >= $due - 0.001 ? 'paid' : ($paid > 0 ? 'partial' : 'open');
            $insert->execute([$accountId, $g['due_date'], $due, $paid, $status]);
        }

        // Keep plan statuses in sync with their instalments.
        PaylaterPlan::refreshStatusesForAccount($accountId);
    }

    /**
     * Credit: monthly statement = net purchases in a calendar month, due on the
     * account's due day the following month. Paid amounts are preserved.
     */
    private static function syncCredit(array $account): void
    {
        $db = Database::getConnection();
        $accountId = (int) $account['id'];
        $dueDay = (int) ($account['due_day'] ?: 1);

        $stmt = $db->prepare("
            SELECT strftime('%Y-%m', date) AS ym,
                   ROUND(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 2) AS spend,
                   ROUND(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 2) AS credit
            FROM transactions
            WHERE account_id = ?
            GROUP BY ym
        ");
        $stmt->execute([$accountId]);
        $rows = $stmt->fetchAll();

        $upsert = $db->prepare("
            INSERT INTO bills (account_id, due_date, amount_due, paid_amount, status)
            VALUES (?, ?, ?, 0, 'open')
            ON CONFLICT(account_id, due_date) DO UPDATE SET amount_due = excluded.amount_due
        ");
        $keep = [];
        foreach ($rows as $r) {
            $p = explode('-', (string) $r['ym']);
            $y = (int) $p[0];
            $m = (int) $p[1] + 1;
            if ($m > 12) { $m = 1; $y++; }
            $dueDate = PaylaterPlan::clamped($y, $m, $dueDay);
            $keep[] = $dueDate;
            $amount = round((float) $r['spend'] - (float) $r['credit'], 2);
            $upsert->execute([$accountId, $dueDate, max(0, $amount)]);
        }

        if (!empty($keep)) {
            $placeholders = implode(',', array_fill(0, count($keep), '?'));
            $db->prepare("DELETE FROM bills WHERE account_id = ? AND paid_amount = 0 AND due_date NOT IN ($placeholders)")
                ->execute(array_merge([$accountId], $keep));
        }

        $db->prepare("UPDATE bills SET status = CASE WHEN paid_amount >= amount_due - 0.001 THEN 'paid' WHEN paid_amount > 0 THEN 'partial' ELSE 'open' END WHERE account_id = ?")
            ->execute([$accountId]);
    }

    public static function forAccount(int $accountId): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM bills WHERE account_id = ? ORDER BY due_date DESC");
        $stmt->execute([$accountId]);
        return $stmt->fetchAll();
    }

    /** Open (unpaid/partial) instalments for a bill date, oldest first. */
    public static function openInstallments(int $accountId, string $dueDate): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT i.*
            FROM paylater_installments i
            JOIN paylater_plans p ON p.id = i.plan_id
            WHERE p.account_id = ? AND i.due_date = ? AND i.status IN ('open', 'partial')
            ORDER BY i.due_date, i.id
        ");
        $stmt->execute([$accountId, $dueDate]);
        return $stmt->fetchAll();
    }

    /**
     * Pay an amount toward a bill (FIFO across its instalments) and record the
     * transfer from the funding account. Returns the amount applied.
     */
    public static function payDue(int $accountId, string $dueDate, float $amount, int $fromAccountId, string $date): float
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            return 0.0;
        }

        $account = Account::find($accountId);
        $db = Database::getConnection();

        if ($account !== null && $account['kind'] === 'credit') {
            if ($fromAccountId > 0) {
                Transfer::create($fromAccountId, $accountId, $amount, $date, 'Card payment (' . $dueDate . ')', 'bill_payment');
            }
            $db->prepare("UPDATE bills SET paid_amount = ROUND(paid_amount + ?, 2) WHERE account_id = ? AND due_date = ?")->execute([$amount, $accountId, $dueDate]);
            self::syncCredit($account);
            return $amount;
        }

        $rows = self::openInstallments($accountId, $dueDate);
        $remaining = $amount;
        $applied = 0.0;
        $update = $db->prepare("UPDATE paylater_installments SET paid_amount = ?, status = ? WHERE id = ?");

        foreach ($rows as $row) {
            if ($remaining <= 0) {
                break;
            }
            $outstanding = round((float) $row['amount'] - (float) $row['paid_amount'], 2);
            if ($outstanding <= 0) {
                continue;
            }
            $reduce = min($remaining, $outstanding);
            $newPaid = round((float) $row['paid_amount'] + $reduce, 2);
            $status = $newPaid >= (float) $row['amount'] - 0.001 ? 'paid' : 'partial';
            $update->execute([$newPaid, $status, $row['id']]);
            $remaining = round($remaining - $reduce, 2);
            $applied = round($applied + $reduce, 2);
        }

        if ($applied > 0 && $fromAccountId > 0) {
            Transfer::create($fromAccountId, $accountId, $applied, $date, 'Bill payment (' . $dueDate . ')', 'bill_payment');
        }

        self::sync($accountId);
        return $applied;
    }

    /** Outstanding across all bills for an account. */
    public static function outstanding(int $accountId): float
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount_due - paid_amount), 0) FROM bills WHERE account_id = ?");
        $stmt->execute([$accountId]);
        return round((float) $stmt->fetchColumn(), 2);
    }
}
