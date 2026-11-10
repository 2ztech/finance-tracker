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

        $account = Account::find($accountId);
        $cycle = $account !== null && ($account['bnpl_mode'] ?? '') === 'cycle';
        $dueDay = (int) (($account['due_day'] ?? 0) ?: 10);

        // Re-bucket cycle BNPL instalments onto the monthly statement day (the
        // account's due day within the installment's own month). Personal
        // financing keeps its borrow-day date. Derived on the fly — installment
        // rows are never rewritten.
        $map = [];
        $stmt = $db->prepare("
            SELECT i.due_date, i.amount, i.paid_amount, p.kind, p.description
            FROM paylater_installments i
            JOIN paylater_plans p ON p.id = i.plan_id
            WHERE p.account_id = ?
        ");
        $stmt->execute([$accountId]);
        foreach ($stmt->fetchAll() as $i) {
            $billDue = self::billDateForInstallment((string) $i['due_date'], (string) $i['kind'], (string) $i['description'], $account);
            if (!isset($map[$billDue])) {
                $map[$billDue] = self::emptyBillBucket();
            }
            $map[$billDue]['due'] += (float) $i['amount'];
            $map[$billDue]['paid'] += (float) $i['paid_amount'];
            if (self::isFinancingPlan((string) $i['kind'], (string) $i['description'])) {
                $map[$billDue]['financing'] = true;
            } else {
                $map[$billDue]['purchase'] = true;
            }
        }

        // Cycle BNPL: one-off charges (not converted to an instalment plan) are
        // part of the monthly statement, due on the account's due day next month.
        if ($cycle) {
            $o = $db->prepare("SELECT date, type, amount FROM transactions WHERE account_id = ? AND plan_id IS NULL");
            $o->execute([$accountId]);
            foreach ($o->fetchAll() as $t) {
                $y = (int) substr((string) $t['date'], 0, 4);
                $m = (int) substr((string) $t['date'], 5, 2);
                $nm = $m + 1;
                $ny = $y;
                if ($nm > 12) { $nm = 1; $ny++; }
                $billDue = PaylaterPlan::clamped($ny, $nm, $dueDay);
                if (!isset($map[$billDue])) {
                    $map[$billDue] = self::emptyBillBucket();
                }
                $map[$billDue]['due'] += ((string) $t['type'] === 'expense' ? 1 : -1) * (float) $t['amount'];
                $map[$billDue]['oneoff'] = true;
            }
        }

        ksort($map);
        $db->prepare("DELETE FROM bills WHERE account_id = ?")->execute([$accountId]);
        $insert = $db->prepare("INSERT INTO bills (account_id, period_start, period_end, due_date, amount_due, paid_amount, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($map as $due => $v) {
            $amount = max(0.0, round((float) $v['due'], 2));
            $paid = max(0.0, round((float) $v['paid'], 2));
            $status = $paid >= $amount - 0.001 ? 'paid' : ($paid > 0 ? 'partial' : 'open');
            // Monthly BNPL statements cover the previous calendar month; pure
            // personal-financing bills keep no period.
            $isFinancingOnly = $v['financing'] && !$v['purchase'] && !$v['oneoff'];
            [$ps, $pe] = ($cycle && !$isFinancingOnly) ? self::statementPeriod((string) $due) : [null, null];
            $insert->execute([$accountId, $ps, $pe, $due, $amount, $paid, $status]);
        }

        // Keep plan statuses in sync with their instalments.
        PaylaterPlan::refreshStatusesForAccount($accountId);
    }

    /** @return array{due:float,paid:float,purchase:bool,financing:bool,oneoff:bool} */
    private static function emptyBillBucket(): array
    {
        return ['due' => 0.0, 'paid' => 0.0, 'purchase' => false, 'financing' => false, 'oneoff' => false];
    }

    private static function isFinancingPlan(string $kind, string $description): bool
    {
        // Classification is explicit so plans can be moved between types.
        return $kind === 'financing';
    }

    /**
     * Bill date for an installment. Cycle BNPL is bundled onto the account's
     * due day within the installment's month; financing and per-purchase keep
     * their own date.
     */
    private static function billDateForInstallment(string $dueDate, string $kind, string $description, ?array $account): string
    {
        if (self::isFinancingPlan($kind, $description)) {
            return $dueDate;
        }
        if ($account !== null && ($account['bnpl_mode'] ?? '') === 'cycle') {
            $dueDay = (int) (($account['due_day'] ?? 0) ?: 10);
            $y = (int) substr($dueDate, 0, 4);
            $m = (int) substr($dueDate, 5, 2);
            return PaylaterPlan::clamped($y, $m, $dueDay);
        }
        return $dueDate;
    }

    /** Calendar month covered by a monthly statement due on $dueDate. */
    private static function statementPeriod(string $dueDate): array
    {
        $y = (int) substr($dueDate, 0, 4);
        $m = (int) substr($dueDate, 5, 2);
        $pm = $m - 1;
        $py = $y;
        if ($pm < 1) { $pm = 12; $py--; }
        $start = sprintf('%04d-%02d-01', $py, $pm);
        return [$start, date('Y-m-t', strtotime($start))];
    }

    /** Classify a paylater bill: 'financing' when it holds only financing plans. */
    public static function kindOf(int $accountId, string $dueDate): string
    {
        $db = Database::getConnection();
        $account = Account::find($accountId);
        $stmt = $db->prepare("
            SELECT i.due_date, p.kind, p.description
            FROM paylater_installments i
            JOIN paylater_plans p ON p.id = i.plan_id
            WHERE p.account_id = ?
        ");
        $stmt->execute([$accountId]);
        $purchase = false;
        $financing = false;
        foreach ($stmt->fetchAll() as $r) {
            if (self::billDateForInstallment((string) $r['due_date'], (string) $r['kind'], (string) $r['description'], $account) !== $dueDate) {
                continue;
            }
            if (self::isFinancingPlan((string) $r['kind'], (string) $r['description'])) {
                $financing = true;
            } else {
                $purchase = true;
            }
        }
        return ($financing && !$purchase) ? 'financing' : 'bnpl';
    }

    /** Line items that make up a bill (paylater statements or credit cycles). */
    public static function details(int $accountId, array $bill): array
    {
        $db = Database::getConnection();
        $account = Account::find($accountId);
        if ($account === null) {
            return [];
        }
        $rows = [];

        if ($account['kind'] === 'credit') {
            $ps = $bill['period_start'] ?? null;
            $pe = $bill['period_end'] ?? null;
            if ($ps && $pe) {
                $stmt = $db->prepare("SELECT date, description, type, amount FROM transactions WHERE account_id = ? AND date >= ? AND date <= ? ORDER BY date, id");
                $stmt->execute([$accountId, $ps, $pe]);
                foreach ($stmt->fetchAll() as $t) {
                    $credit = (string) $t['type'] === 'income';
                    $rows[] = [
                        'title' => (string) ($t['description'] ?: 'Transaction'),
                        'meta' => date('j M', strtotime((string) $t['date'])) . ($credit ? ' · credit' : ''),
                        'amount' => round(($credit ? -1 : 1) * (float) $t['amount'], 2),
                    ];
                }
            }
            return $rows;
        }

        // Paylater: instalments that bucket into this bill date.
        $stmt = $db->prepare("
            SELECT i.seq, i.due_date, i.amount, i.paid_amount, p.months, p.kind, p.description, t.description AS purchase_desc
            FROM paylater_installments i
            JOIN paylater_plans p ON p.id = i.plan_id
            LEFT JOIN transactions t ON t.id = p.purchase_txn_id
            WHERE p.account_id = ?
            ORDER BY p.id, i.seq
        ");
        $stmt->execute([$accountId]);
        foreach ($stmt->fetchAll() as $i) {
            if (self::billDateForInstallment((string) $i['due_date'], (string) $i['kind'], (string) $i['description'], $account) !== (string) $bill['due_date']) {
                continue;
            }
            $isFinancing = self::isFinancingPlan((string) $i['kind'], (string) $i['description']);
            $title = trim((string) ($i['description'] ?? ''));
            if ($title === '') {
                $title = (string) ($i['purchase_desc'] ?? 'Paylater plan');
            }
            $rows[] = [
                'title' => $title,
                'meta' => ($isFinancing ? 'Personal Financing' : 'BNPL') . ' · ' . (int) $i['seq'] . '/' . (int) $i['months'],
                'amount' => round((float) $i['amount'], 2),
            ];
        }

        // Cycle BNPL statements: one-off purchases in the covered month.
        if (($account['bnpl_mode'] ?? '') === 'cycle' && !empty($bill['period_start']) && !empty($bill['period_end'])) {
            $stmt = $db->prepare("SELECT date, description, type, amount FROM transactions WHERE account_id = ? AND plan_id IS NULL AND date >= ? AND date <= ? ORDER BY date, id");
            $stmt->execute([$accountId, $bill['period_start'], $bill['period_end']]);
            foreach ($stmt->fetchAll() as $t) {
                $credit = (string) $t['type'] === 'income';
                $rows[] = [
                    'title' => (string) ($t['description'] ?: 'Purchase'),
                    'meta' => 'BNPL · one-off · ' . date('j M', strtotime((string) $t['date'])),
                    'amount' => round(($credit ? -1 : 1) * (float) $t['amount'], 2),
                ];
            }
        }

        return $rows;
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
        $account = Account::find($accountId);
        $stmt = $db->prepare("
            SELECT i.*, p.kind AS plan_kind, p.description AS plan_description
            FROM paylater_installments i
            JOIN paylater_plans p ON p.id = i.plan_id
            WHERE p.account_id = ? AND i.status IN ('open', 'partial')
            ORDER BY i.due_date, i.id
        ");
        $stmt->execute([$accountId]);
        $rows = [];
        foreach ($stmt->fetchAll() as $r) {
            if (self::billDateForInstallment((string) $r['due_date'], (string) $r['plan_kind'], (string) $r['plan_description'], $account) === $dueDate) {
                $rows[] = $r;
            }
        }
        return $rows;
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
