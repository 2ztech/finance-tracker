<?php

declare(strict_types=1);

final class PaylaterPlan
{
    public static function find(int $id): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM paylater_plans WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function forAccount(int $accountId): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM paylater_plans WHERE account_id = ? ORDER BY id DESC");
        $stmt->execute([$accountId]);
        return $stmt->fetchAll();
    }

    public static function installments(int $planId): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM paylater_installments WHERE plan_id = ? ORDER BY seq");
        $stmt->execute([$planId]);
        return $stmt->fetchAll();
    }

    public static function clamped(int $year, int $month, int $day): string
    {
        $last = (int) date('t', strtotime(sprintf('%04d-%02d-01', $year, $month)));
        return sprintf('%04d-%02d-%02d', $year, $month, min($day, $last));
    }

    /** First due date for a purchase on an account. */
    public static function firstDueDate(array $account, string $purchaseDate, ?int $offsetOverride = null): string
    {
        $p = explode('-', $purchaseDate);
        $y = (int) $p[0];
        $m = (int) $p[1];
        $d = (int) $p[2];

        $mode = $account['bnpl_mode'] ?? 'cycle';
        $offset = $offsetOverride ?? (int) $account['first_due_offset'];

        if ($mode === 'cycle') {
            $dueDay = (int) ($account['due_day'] ?: $d);
            $nm = $m + 1;
            $ny = $y;
            if ($nm > 12) { $nm = 1; $ny++; }
            return self::clamped($ny, $nm, $dueDay);
        }

        // per_purchase: due day is the purchase day
        if ($offset === 0) {
            return self::clamped($y, $m, $d);
        }
        $nm = $m + 1;
        $ny = $y;
        if ($nm > 12) { $nm = 1; $ny++; }
        return self::clamped($ny, $nm, $d);
    }

    /** @return string[] Monthly dates starting at $firstDue. */
    public static function buildSchedule(string $firstDue, int $months): array
    {
        $p = explode('-', $firstDue);
        $y = (int) $p[0];
        $m = (int) $p[1];
        $day = (int) $p[2];
        $dates = [];
        for ($i = 0; $i < $months; $i++) {
            $idx = $m - 1 + $i;
            $yy = $y + intdiv($idx, 12);
            $mm = ($idx % 12) + 1;
            $dates[] = self::clamped($yy, $mm, $day);
        }
        return $dates;
    }

    /**
     * Record a paylater purchase and its repayment plan.
     *
     * @return int plan id
     */
    public static function createPurchase(int $accountId, int $categoryId, string $description, string $purchaseDate, float $totalPayable, int $months, ?float $cashPrice = null, ?int $offsetOverride = null): int
    {
        $db = Database::getConnection();
        $account = Account::find($accountId);
        if ($account === null) {
            return 0;
        }
        $months = max(1, $months);
        $totalPayable = round($totalPayable, 2);
        $installment = round($totalPayable / $months, 2);
        $firstDue = self::firstDueDate($account, $purchaseDate, $offsetOverride);

        // Purchase expense = cash price if provided, otherwise the full repayable.
        $purchaseAmount = $cashPrice !== null && $cashPrice > 0 ? round($cashPrice, 2) : $totalPayable;
        $interest = round($totalPayable - $purchaseAmount, 2);

        $insertTxn = $db->prepare("INSERT INTO transactions (category_id, amount, type, description, date, account_id) VALUES (?, ?, 'expense', ?, ?, ?)");
        $insertTxn->execute([$categoryId > 0 ? $categoryId : null, $purchaseAmount, $description, $purchaseDate, $accountId]);
        $purchaseTxnId = (int) $db->lastInsertId();

        $financingTxnId = null;
        if ($interest > 0) {
            $finCat = self::financingCategoryId();
            $insertTxn->execute([$finCat, $interest, $description . ' (financing)', $purchaseDate, $accountId]);
            $financingTxnId = (int) $db->lastInsertId();
        }

        $planStmt = $db->prepare("INSERT INTO paylater_plans (account_id, purchase_txn_id, total_payable, cash_price, interest, months, installment_amount, first_due_date, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)");
        $planStmt->execute([$accountId, $purchaseTxnId, $totalPayable, $cashPrice, $interest, $months, $installment, $firstDue, date('Y-m-d H:i:s')]);
        $planId = (int) $db->lastInsertId();

        $linkTxn = $db->prepare("UPDATE transactions SET plan_id = ? WHERE id = ?");
        $linkTxn->execute([$planId, $purchaseTxnId]);
        if ($financingTxnId !== null) {
            $linkTxn->execute([$planId, $financingTxnId]);
        }

        $instStmt = $db->prepare("INSERT INTO paylater_installments (plan_id, seq, due_date, amount) VALUES (?, ?, ?, ?)");
        $dates = self::buildSchedule($firstDue, $months);
        $allocated = 0.0;
        foreach ($dates as $i => $due) {
            $amt = ($i === $months - 1) ? round($totalPayable - $allocated, 2) : $installment;
            $allocated += $amt;
            $instStmt->execute([$planId, $i + 1, $due, $amt]);
        }

        Bill::sync($accountId);
        return $planId;
    }

    public static function financingCategoryId(): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id FROM categories WHERE type = 'expense' AND LOWER(name) = 'financing cost' LIMIT 1");
        $stmt->execute();
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int) $id;
        }
        $ins = $db->prepare("INSERT INTO categories (name, type, color_hex) VALUES ('Financing Cost', 'expense', '#a855f7')");
        $ins->execute();
        return (int) $db->lastInsertId();
    }

    /** Outstanding amount still to be paid across a plan's open instalments. */
    public static function remaining(int $planId): float
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount - paid_amount), 0) FROM paylater_installments WHERE plan_id = ? AND status IN ('open','partial')");
        $stmt->execute([$planId]);
        return round((float) $stmt->fetchColumn(), 2);
    }

    /** Recompute active/completed status for a plan based on its instalments. */
    public static function refreshStatus(int $planId): void
    {
        $db = Database::getConnection();
        $remaining = self::remaining($planId);
        $stmt = $db->prepare("SELECT status FROM paylater_plans WHERE id = ?");
        $stmt->execute([$planId]);
        $status = $stmt->fetchColumn();
        if ($status === 'cancelled') {
            return;
        }
        $newStatus = $remaining <= 0.001 ? 'completed' : 'active';
        if ($status !== $newStatus) {
            $db->prepare("UPDATE paylater_plans SET status = ? WHERE id = ?")->execute([$newStatus, $planId]);
            AppLog::info('plan_status_changed', ['plan_id' => $planId, 'from' => $status, 'to' => $newStatus]);
        }
    }

    public static function refreshStatusesForAccount(int $accountId): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id FROM paylater_plans WHERE account_id = ? AND status = 'active'");
        $stmt->execute([$accountId]);
        foreach ($stmt->fetchAll() as $row) {
            self::refreshStatus((int) $row['id']);
        }
    }

    /**
     * Reflect an edited plan-linked transaction back onto the plan and its
     * instalments. Regenerates the schedule when nothing has been paid yet.
     */
    public static function syncFromTransaction(int $planId, float $newTotal, string $description, string $date): void
    {
        $plan = self::find($planId);
        if ($plan === null) {
            return;
        }
        $db = Database::getConnection();
        $total = round($newTotal, 2);

        $paidStmt = $db->prepare("SELECT COALESCE(SUM(paid_amount), 0) FROM paylater_installments WHERE plan_id = ?");
        $paidStmt->execute([$planId]);
        $paid = (float) $paidStmt->fetchColumn();

        if ($paid <= 0.001) {
            $months = max(1, (int) $plan['months']);
            $installment = round($total / $months, 2);
            $db->prepare("DELETE FROM paylater_installments WHERE plan_id = ?")->execute([$planId]);
            $ins = $db->prepare("INSERT INTO paylater_installments (plan_id, seq, due_date, amount) VALUES (?, ?, ?, ?)");
            $allocated = 0.0;
            foreach (self::buildSchedule((string) $plan['first_due_date'], $months) as $i => $due) {
                $amt = ($i === $months - 1) ? round($total - $allocated, 2) : $installment;
                $allocated += $amt;
                $ins->execute([$planId, $i + 1, $due, $amt]);
            }
            // Cash-price split cannot be expressed in the edit modal, so drop it.
            $db->prepare("DELETE FROM transactions WHERE plan_id = ? AND description LIKE '%(financing)'")->execute([$planId]);
            $db->prepare("UPDATE paylater_plans SET total_payable = ?, installment_amount = ?, cash_price = NULL, interest = 0, status = 'active' WHERE id = ?")
               ->execute([$total, $installment, $planId]);
            AppLog::info('plan_edited', ['plan_id' => $planId, 'total' => $total, 'months' => $months]);
        } else {
            AppLog::warn('plan_edit_skipped_paid', ['plan_id' => $planId, 'paid' => $paid]);
        }

        $upd = $db->prepare("UPDATE transactions SET description = ? WHERE plan_id = ? AND description LIKE '%(financing)'");
        $upd->execute([$description . ' (financing)', $planId]);

        Bill::sync((int) $plan['account_id']);
    }

    /** Remove a plan's instalments and any transactions tied to it. */
    private static function deletePlanData(int $planId): void
    {
        $db = Database::getConnection();
        $db->prepare("DELETE FROM paylater_installments WHERE plan_id = ?")->execute([$planId]);
        $db->prepare("DELETE FROM transactions WHERE plan_id = ?")->execute([$planId]);
        $db->prepare("DELETE FROM paylater_plans WHERE id = ?")->execute([$planId]);
    }

    /**
     * Drop plans whose purchase transaction no longer exists (e.g. deleted
     * before plan-binding existed). Does not call Bill::sync, so it is safe to
     * call from within Bill::sync.
     */
    public static function reconcileAccount(int $accountId): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id, purchase_txn_id FROM paylater_plans WHERE account_id = ?");
        $stmt->execute([$accountId]);
        $plans = $stmt->fetchAll();

        $check = $db->prepare("SELECT COUNT(*) FROM transactions WHERE id = ?");
        $removed = 0;
        foreach ($plans as $p) {
            $txnId = (int) ($p['purchase_txn_id'] ?? 0);
            $exists = 0;
            if ($txnId > 0) {
                $check->execute([$txnId]);
                $exists = (int) $check->fetchColumn();
            }
            if ($exists === 0) {
                self::deletePlanData((int) $p['id']);
                $removed++;
                AppLog::info('orphan_plan_removed', ['plan_id' => (int) $p['id'], 'account_id' => $accountId]);
            }
        }
        return $removed;
    }

    /** Remove a plan and its instalments (used when its purchase is deleted). */
    public static function cancel(int $planId): void
    {
        $plan = self::find($planId);
        if ($plan === null) {
            return;
        }
        self::deletePlanData($planId);
        Bill::sync((int) $plan['account_id']);
        AppLog::info('plan_cancelled', ['plan_id' => $planId, 'account_id' => (int) $plan['account_id']]);
    }

    /**
     * Settle all remaining instalments of a plan in one payment.
     */
    public static function settle(int $planId, int $fromAccountId, string $date): float
    {
        $plan = self::find($planId);
        if ($plan === null) {
            return 0.0;
        }
        $due = self::remaining($planId);
        if ($due <= 0) {
            return 0.0;
        }
        $db = Database::getConnection();
        $db->prepare("UPDATE paylater_installments SET paid_amount = amount, status = 'paid' WHERE plan_id = ? AND status IN ('open','partial')")->execute([$planId]);
        $db->prepare("UPDATE paylater_plans SET status = 'completed' WHERE id = ?")->execute([$planId]);
        if ($fromAccountId > 0) {
            Transfer::create($fromAccountId, (int) $plan['account_id'], $due, $date, 'Settle ' . ($plan['total_payable'] ? '' : '') . 'paylater plan', 'bill_payment');
        }
        Bill::sync((int) $plan['account_id']);
        return $due;
    }

    /**
     * Refund against a plan: records an income on the account and reduces the
     * remaining instalments (oldest first). Cancels covered instalments.
     */
    public static function refund(int $planId, float $amount, string $date): float
    {
        $plan = self::find($planId);
        if ($plan === null) {
            return 0.0;
        }
        $amount = round(abs($amount), 2);
        if ($amount <= 0) {
            return 0.0;
        }
        $db = Database::getConnection();

        // Record the refund as income (reduces the liability).
        $ins = $db->prepare("INSERT INTO transactions (category_id, amount, type, description, date, account_id, refund_of_id) VALUES (NULL, ?, 'income', ?, ?, ?, ?)");
        $ins->execute([$amount, 'Refund', $date, (int) $plan['account_id'], (int) ($plan['purchase_txn_id'] ?? 0)]);

        // Reduce remaining instalments oldest-first.
        $remaining = $amount;
        $inst = $db->prepare("SELECT id, amount, paid_amount FROM paylater_installments WHERE plan_id = ? AND status IN ('open','partial') ORDER BY seq");
        $inst->execute([$planId]);
        $upd = $db->prepare("UPDATE paylater_installments SET paid_amount = ?, status = ? WHERE id = ?");
        foreach ($inst->fetchAll() as $row) {
            if ($remaining <= 0) break;
            $outstanding = round((float) $row['amount'] - (float) $row['paid_amount'], 2);
            if ($outstanding <= 0) continue;
            $reduce = min($remaining, $outstanding);
            $newPaid = round((float) $row['paid_amount'] + $reduce, 2);
            $status = $newPaid >= (float) $row['amount'] - 0.001 ? 'cancelled' : 'partial';
            $upd->execute([$newPaid, $status, $row['id']]);
            $remaining = round($remaining - $reduce, 2);
        }

        if (self::remaining($planId) <= 0) {
            $db->prepare("UPDATE paylater_plans SET status = 'cancelled' WHERE id = ?")->execute([$planId]);
        }
        Bill::sync((int) $plan['account_id']);
        return $amount;
    }
}
