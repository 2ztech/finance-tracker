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

    /** Credit: group purchases through each statement cutoff into its due cycle. */
    private static function syncCredit(array $account): void
    {
        $db = Database::getConnection();
        $accountId = (int) $account['id'];
        $statementDay = (int) ($account['statement_day'] ?? 0);
        $dueDay = (int) ($account['due_day'] ?: 1);

        $stmt = $db->prepare("
            SELECT date, type, amount
            FROM transactions
            WHERE account_id = ?
            ORDER BY date, id
        ");
        $stmt->execute([$accountId]);
        $rows = $stmt->fetchAll();

        $upsert = $db->prepare("
            INSERT INTO bills (account_id, period_start, period_end, due_date, amount_due, paid_amount, status)
            VALUES (?, ?, ?, ?, ?, 0, 'open')
            ON CONFLICT(account_id, due_date) DO UPDATE SET
                period_start = excluded.period_start,
                period_end = excluded.period_end,
                amount_due = excluded.amount_due
        ");
        $cycles = [];
        $keep = [];
        foreach ($rows as $r) {
            $date = (string) $r['date'];
            $year = (int) substr($date, 0, 4);
            $month = (int) substr($date, 5, 2);
            $day = (int) substr($date, 8, 2);

            // Older credit accounts without a configured cutoff keep their prior
            // behavior: calendar month purchases are due in the following month.
            if ($statementDay < 1 || $statementDay > 31) {
                $cycleYear = $year;
                $cycleMonth = $month + 1;
                if ($cycleMonth > 12) { $cycleMonth = 1; $cycleYear++; }
                $periodEnd = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $year, $month)));
                $periodStart = sprintf('%04d-%02d-01', $year, $month);
            } else {
                // A purchase on the statement day belongs to the statement
                // closing that day; the next cycle starts on the following day.
                $cycleYear = $year;
                $cycleMonth = $month;
                if ($day > $statementDay) {
                    $cycleMonth++;
                    if ($cycleMonth > 12) { $cycleMonth = 1; $cycleYear++; }
                }
                $periodEnd = PaylaterPlan::clamped($cycleYear, $cycleMonth, $statementDay);
                $previousYear = $cycleYear;
                $previousMonth = $cycleMonth - 1;
                if ($previousMonth < 1) { $previousMonth = 12; $previousYear--; }
                $periodStart = date('Y-m-d', strtotime(PaylaterPlan::clamped($previousYear, $previousMonth, $statementDay) . ' +1 day'));
            }

            $dueDate = PaylaterPlan::clamped($cycleYear, $cycleMonth, $dueDay);
            if (!isset($cycles[$dueDate])) {
                $cycles[$dueDate] = ['start' => $periodStart, 'end' => $periodEnd, 'amount' => 0.0];
            }
            $cycles[$dueDate]['amount'] += ((string) $r['type'] === 'expense' ? 1 : -1) * (float) $r['amount'];
        }

        foreach ($cycles as $dueDate => $cycle) {
            $keep[] = $dueDate;
            $upsert->execute([
                $accountId, $cycle['start'], $cycle['end'], $dueDate,
                max(0.0, round((float) $cycle['amount'], 2)),
            ]);
        }

        if (!empty($keep)) {
            $placeholders = implode(',', array_fill(0, count($keep), '?'));
            $db->prepare("DELETE FROM bills WHERE account_id = ? AND paid_amount = 0 AND due_date NOT IN ($placeholders)")
                ->execute(array_merge([$accountId], $keep));
        }

        $db->prepare("UPDATE bills SET status = CASE WHEN paid_amount >= amount_due - 0.001 THEN 'paid' WHEN paid_amount > 0 THEN 'partial' ELSE 'open' END WHERE account_id = ?")
            ->execute([$accountId]);
    }

    /** Record a dated allocation so EOM can reconstruct unpaid bills historically. */
    public static function recordPaymentAllocation(int $accountId, string $dueDate, string $paidDate, float $amount, ?int $transferId = null): void
    {
        if ($amount <= 0) {
            return;
        }
        $db = Database::getConnection();
        $db->prepare("INSERT INTO bill_payment_allocations
            (account_id, due_date, paid_date, amount, transfer_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?)")->execute([
                $accountId, $dueDate, $paidDate, round($amount, 2), $transferId ?: null, date('Y-m-d H:i:s'),
            ]);
    }

    /** Remaining bills due on or before a date, as they stood on that date. */
    public static function outstandingAsOf(int $accountId, string $asOf): float
    {
        self::sync($accountId);
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id, due_date, amount_due, paid_amount FROM bills WHERE account_id = ? AND due_date <= ?");
        $stmt->execute([$accountId, $asOf]);
        $allocations = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM bill_payment_allocations WHERE account_id = ? AND due_date = ? AND paid_date <= ?");
        $allAllocations = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM bill_payment_allocations WHERE account_id = ? AND due_date = ?");
        $total = 0.0;
        foreach ($stmt->fetchAll() as $bill) {
            $allocations->execute([$accountId, $bill['due_date'], $asOf]);
            $paidByDate = (float) $allocations->fetchColumn();
            $allAllocations->execute([$accountId, $bill['due_date']]);
            $trackedPaid = (float) $allAllocations->fetchColumn();

            // Preserve legacy paid state that has no corresponding dated
            // transfer record; its original payment date cannot be recovered.
            $legacyPaid = max(0.0, (float) $bill['paid_amount'] - $trackedPaid);
            $outstanding = max(0.0, (float) $bill['amount_due'] - $legacyPaid - $paidByDate);
            $total += $outstanding;
        }
        return round($total, 2);
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
            $transferId = 0;
            if ($fromAccountId > 0) {
                $transferId = Transfer::create($fromAccountId, $accountId, $amount, $date, 'Card payment (' . $dueDate . ')', 'bill_payment');
            }
            $db->prepare("UPDATE bills SET paid_amount = ROUND(paid_amount + ?, 2) WHERE account_id = ? AND due_date = ?")->execute([$amount, $accountId, $dueDate]);
            self::recordPaymentAllocation($accountId, $dueDate, $date, $amount, $transferId);
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

        $transferId = 0;
        if ($applied > 0 && $fromAccountId > 0) {
            $transferId = Transfer::create($fromAccountId, $accountId, $applied, $date, 'Bill payment (' . $dueDate . ')', 'bill_payment');
        }
        if ($applied > 0) {
            self::recordPaymentAllocation($accountId, $dueDate, $date, $applied, $transferId);
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
