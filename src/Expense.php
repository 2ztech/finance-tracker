<?php

declare(strict_types=1);

final class Expense
{
    private static array $startingBalanceCache = [];
    private static array $onHandBalanceCache = [];

    // --- Transactions ---

    public static function getTransactions(string $month, string $year, ?int $accountId = null): array
    {
        $db = Database::getConnection();
        $startDate = "$year-$month-01";
        $endDate = date("Y-m-t", strtotime($startDate));

        $sql = "
            SELECT t.*, c.name AS category_name, c.color_hex, c.icon_key, c.icon_data, c.icon_mime, a.name AS account_name
            FROM transactions t
            LEFT JOIN categories c ON t.category_id = c.id
            LEFT JOIN accounts a ON t.account_id = a.id
            WHERE t.date >= ? AND t.date <= ?";
        $params = [$startDate, $endDate];
        if ($accountId !== null) {
            $sql .= " AND t.account_id = ?";
            $params[] = $accountId;
        }
        $sql .= " ORDER BY t.date DESC, t.id DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function getTotalIncomeBetween(string $startDate, string $endDate): float
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT ROUND(SUM(amount), 2) FROM transactions WHERE type = 'income' AND date >= ? AND date <= ?");
        $stmt->execute([$startDate, $endDate]);
        return round((float) $stmt->fetchColumn(), 2);
    }

    public static function getTotalExpenseBetween(string $startDate, string $endDate): float
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT ROUND(SUM(amount), 2) FROM transactions WHERE type = 'expense' AND date >= ? AND date <= ?");
        $stmt->execute([$startDate, $endDate]);
        return round((float) $stmt->fetchColumn(), 2);
    }

    public static function getTotalIncome(string $month, string $year): float
    {
        $startDate = "$year-$month-01";
        $endDate = date("Y-m-t", strtotime($startDate));
        return self::getTotalIncomeBetween($startDate, $endDate);
    }

    public static function getTotalIncomeAllTime(): float
    {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT ROUND(SUM(amount), 2) FROM transactions WHERE type = 'income'");
        return round((float) $stmt->fetchColumn(), 2);
    }

    public static function getTotalExpense(string $month, string $year): float
    {
        $startDate = "$year-$month-01";
        $endDate = date("Y-m-t", strtotime($startDate));
        return self::getTotalExpenseBetween($startDate, $endDate);
    }

    public static function getTotalExpenseAllTime(): float
    {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT ROUND(SUM(amount), 2) FROM transactions WHERE type = 'expense'");
        return round((float) $stmt->fetchColumn(), 2);
    }

    public static function getExpensesByCategory(string $month, string $year, ?int $accountId = null): array
    {
        $db = Database::getConnection();
        $startDate = "$year-$month-01";
        $endDate = date("Y-m-t", strtotime($startDate));

        $sql = "
            SELECT c.id AS category_id, c.name, c.color_hex, c.icon_key, c.icon_data, c.icon_mime, ROUND(SUM(t.amount), 2) AS total
            FROM transactions t
            JOIN categories c ON t.category_id = c.id
            WHERE t.type = 'expense' AND t.date >= ? AND t.date <= ?";
        $params = [$startDate, $endDate];
        if ($accountId !== null) {
            $sql .= " AND t.account_id = ?";
            $params[] = $accountId;
        }
        $sql .= " GROUP BY c.id ORDER BY total DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function addTransaction(int $categoryId, float $amount, string $type, string $description, string $date, ?int $accountId = null): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO transactions (category_id, amount, type, description, date, account_id) VALUES (?, ?, ?, ?, ?, ?)");
        return $stmt->execute([
            $categoryId > 0 ? $categoryId : null,
            $amount,
            $type,
            $description,
            $date,
            $accountId,
        ]);
    }

    public static function updateTransaction(int $id, int $categoryId, float $amount, string $type, string $description, string $date, ?int $accountId = null): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE transactions SET category_id = ?, amount = ?, type = ?, description = ?, date = ?, account_id = ? WHERE id = ?");
        return $stmt->execute([
            $categoryId > 0 ? $categoryId : null,
            $amount,
            $type,
            $description,
            $date,
            $accountId,
            $id,
        ]);
    }

    public static function deleteTransaction(int $id): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM transactions WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // --- Balance Calculations ---

    public static function getStartingBalanceForMonth(string $month, string $year): float
    {
        $cacheKey = "$year-$month";
        if (isset(self::$startingBalanceCache[$cacheKey])) {
            return self::$startingBalanceCache[$cacheKey];
        }

        $requested = $cacheKey;
        $trackingStart = Settings::get('tracking_start_month', date('Y-m'));

        if ($requested <= $trackingStart) {
            $result = round((float) Settings::get('starting_bank_balance', 0), 2);
            self::$startingBalanceCache[$cacheKey] = $result;
            return $result;
        }

        $prevDate = date('Y-m', strtotime("$requested-01 -1 month"));
        [$prevYear, $prevMonth] = explode('-', $prevDate);

        $prevStart = self::getStartingBalanceForMonth($prevMonth, $prevYear);
        $prevInc = self::getTotalIncome($prevMonth, $prevYear);
        $prevExp = self::getTotalExpense($prevMonth, $prevYear);

        $result = round($prevStart + $prevInc - $prevExp, 2);
        self::$startingBalanceCache[$cacheKey] = $result;
        return $result;
    }

    public static function getOnHandBalance(string $month, string $year): float
    {
        $cacheKey = "$year-$month";
        $sysDate = date('Y-m-d');
        if ($sysDate === date('Y-m-d') && isset(self::$onHandBalanceCache[$cacheKey])) {
            return self::$onHandBalanceCache[$cacheKey];
        }

        $sysMonth = date('m');
        $sysYear = date('Y');
        $current = "$sysYear-$sysMonth";
        $startDate = "$year-$month-01";

        if ($cacheKey === $current) {
            $startBal = self::getStartingBalanceForMonth($month, $year);
            $inc = self::getTotalIncomeBetween($startDate, $sysDate);
            $exp = self::getTotalExpenseBetween($startDate, $sysDate);
            $result = round($startBal + $inc - $exp, 2);
        } elseif ($cacheKey < $current) {
            $endDate = date("Y-m-t", strtotime($startDate));
            $startBal = self::getStartingBalanceForMonth($month, $year);
            $inc = self::getTotalIncomeBetween($startDate, $endDate);
            $exp = self::getTotalExpenseBetween($startDate, $endDate);
            $result = round($startBal + $inc - $exp, 2);
        } else {
            $prevDate = date('Y-m', strtotime("$startDate -1 month"));
            [$prevYear, $prevMonth] = explode('-', $prevDate);

            $prevOnHand = self::getOnHandBalance($prevMonth, $prevYear);

            $endDate = date("Y-m-t", strtotime($startDate));
            $inc = self::getTotalIncomeBetween($startDate, $endDate);
            $exp = self::getTotalExpenseBetween($startDate, $endDate);

            $result = round($prevOnHand + $inc - $exp, 2);
        }

        if ($cacheKey !== $current) {
            self::$onHandBalanceCache[$cacheKey] = $result;
        }
        return $result;
    }

    /**
     * Recurring amount still expected for a month: active schedules for the
     * type minus anything already posted against them for that period.
     */
    public static function getUnpaidRecurring(string $type, string $month, string $year, ?int $accountId = null): float
    {
        $db = Database::getConnection();
        $monthKey = sprintf('%04d-%02d', (int) $year, (int) $month);

        $sql = "SELECT id, amount, start_date, end_date FROM commitments WHERE type = ? AND archived = 0";
        $params = [$type];
        if ($accountId !== null) {
            $sql .= " AND account_id = ?";
            $params[] = $accountId;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        // Start/end define which MONTHS the item is active; the day-of-month
        // in those dates is intentionally ignored (the due day governs that).
        $ids = [];
        $total = 0.0;
        foreach ($stmt->fetchAll() as $c) {
            if (!empty($c['start_date']) && $monthKey < substr((string) $c['start_date'], 0, 7)) continue;
            if (!empty($c['end_date']) && $monthKey > substr((string) $c['end_date'], 0, 7)) continue;
            $total += (float) $c['amount'];
            $ids[] = (int) $c['id'];
        }
        $total = round($total, 2);

        $posted = 0.0;
        if ($ids !== []) {
            $place = implode(',', array_fill(0, count($ids), '?'));
            $p = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE commitment_id IN ($place) AND commitment_period = ?");
            $p->execute([...$ids, $monthKey]);
            $posted = round((float) $p->fetchColumn(), 2);
        }

        return max(round($total - $posted, 2), 0.0);
    }

    /**
     * Projected cash at the end of a selected month, including unpaid recurring
     * items and liability bills still outstanding by that date, with the full
     * breakdown so the UI can show how the figure was reached.
     *
     * @return array{account_id:?int,month:string,year:string,month_end:?string,
     *   balance:float,recurring_income:float,recurring_expense:float,
     *   recurring_net:float,bills:float,bills_by_account:array<string,float>,eom:float}
     */
    public static function projectedEomBreakdown(?int $accountId, ?string $month = null, ?string $year = null): array
    {
        if ($accountId === null) {
            return [
                'account_id' => null, 'month' => (string) ($month ?? date('m')), 'year' => (string) ($year ?? date('Y')),
                'month_end' => null, 'balance' => 0.0, 'recurring_income' => 0.0, 'recurring_expense' => 0.0,
                'recurring_net' => 0.0, 'bills' => 0.0, 'bills_by_account' => [], 'eom' => 0.0,
            ];
        }

        $month = $month ?? date('m');
        $year = $year ?? date('Y');
        $monthEnd = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', (int) $year, (int) $month)));
        // Account::balance with an as-of date includes manually entered future
        // activity up to this month's end, and excludes activity dated later.
        $balance = Account::balance($accountId, $monthEnd);

        $requestedMonth = sprintf('%04d-%02d', (int) $year, (int) $month);
        $trackingStart = Settings::get('tracking_start_month', date('Y-m'));
        $firstForecastMonth = $requestedMonth < $trackingStart ? $requestedMonth : $trackingStart;
        $account = Account::find($accountId);
        if (!empty($account['start_month']) && $account['start_month'] > $firstForecastMonth && $account['start_month'] <= $requestedMonth) {
            $firstForecastMonth = (string) $account['start_month'];
        }

        // The ledger balance contains posted transactions but not recurring
        // forecasts. Carry each month's still-unposted schedules forward so a
        // missed September posting remains reflected in an October projection.
        $unpaidInc = 0.0;
        $unpaidExp = 0.0;
        $cursor = new DateTimeImmutable($firstForecastMonth . '-01');
        $lastMonth = new DateTimeImmutable($requestedMonth . '-01');
        while ($cursor <= $lastMonth) {
            $unpaidInc += self::getUnpaidRecurring('income', $cursor->format('m'), $cursor->format('Y'), $accountId);
            $unpaidExp += self::getUnpaidRecurring('expense', $cursor->format('m'), $cursor->format('Y'), $accountId);
            $cursor = $cursor->modify('+1 month');
        }

        // Every liability account (credit / paylater) whose bill is still
        // unpaid on or before the projected month-end reduces cash.
        $billsByAccount = [];
        $outstandingBills = 0.0;
        foreach (Account::all(true) as $liability) {
            if (!Account::isLiability($liability)) {
                continue;
            }
            $amount = Bill::outstandingAsOf((int) $liability['id'], $monthEnd);
            if ($amount > 0.0) {
                $billsByAccount[(string) $liability['name']] = round($amount, 2);
                $outstandingBills += $amount;
            }
        }

        $balance = round($balance, 2);
        $unpaidInc = round($unpaidInc, 2);
        $unpaidExp = round($unpaidExp, 2);
        $outstandingBills = round($outstandingBills, 2);

        return [
            'account_id' => $accountId,
            'month' => (string) $month,
            'year' => (string) $year,
            'month_end' => $monthEnd,
            'balance' => $balance,
            'recurring_income' => $unpaidInc,
            'recurring_expense' => $unpaidExp,
            'recurring_net' => round($unpaidInc - $unpaidExp, 2),
            'bills' => $outstandingBills,
            'bills_by_account' => $billsByAccount,
            'eom' => round($balance + $unpaidInc - $unpaidExp - $outstandingBills, 2),
        ];
    }

    /** Projected cash at the end of a selected month (see projectedEomBreakdown). */
    public static function projectedEndOfMonth(?int $accountId, ?string $month = null, ?string $year = null): float
    {
        return self::projectedEomBreakdown($accountId, $month, $year)['eom'];
    }

    public static function getEOMProjection(string $month, string $year, ?int $accountId = null): float
    {
        $accountId ??= Account::primaryId();
        return self::projectedEndOfMonth($accountId, $month, $year);
    }

    // --- Commitments ---

    public static function getCommitments(?int $accountId = null): array
    {
        return self::commitmentsQuery($accountId, 0);
    }

    public static function getArchivedCommitments(?int $accountId = null): array
    {
        return self::commitmentsQuery($accountId, 1);
    }

    private static function commitmentsQuery(?int $accountId, int $archived): array
    {
        $db = Database::getConnection();
        $sql = "SELECT c.*, cat.name AS category_name, cat.color_hex, cat.icon_key, cat.icon_data, cat.icon_mime
                FROM commitments c
                LEFT JOIN categories cat ON c.category_id = cat.id
                WHERE c.archived = ?";
        $params = [$archived];
        if ($accountId !== null) {
            $sql .= " AND c.account_id = ?";
            $params[] = $accountId;
        }
        $sql .= " ORDER BY c.due_date_day, c.id";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function getCommitmentsRemaining(): float
    {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT ROUND(SUM(amount), 2) FROM commitments WHERE type = 'expense' AND archived = 0");
        return round((float) $stmt->fetchColumn(), 2);
    }

    public static function addCommitment(string $name, float $amount, string $type, int $due_date_day, ?int $category_id = null, ?string $start_date = null, ?string $end_date = null, ?int $accountId = null): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO commitments (name, amount, type, due_date_day, category_id, start_date, end_date, account_id, archived) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)");
        return $stmt->execute([$name, $amount, $type, $due_date_day, $category_id, $start_date, $end_date, $accountId]);
    }

    public static function updateCommitment(int $id, string $name, float $amount, string $type, int $due_date_day, ?int $category_id = null, ?string $start_date = null, ?string $end_date = null, ?int $accountId = null): bool
    {
        $db = Database::getConnection();

        $stmt = $db->prepare("UPDATE commitments SET name = ?, amount = ?, type = ?, due_date_day = ?, category_id = ?, start_date = ?, end_date = ?, account_id = ? WHERE id = ?");
        $success = $stmt->execute([$name, $amount, $type, $due_date_day, $category_id, $start_date, $end_date, $accountId, $id]);

        if ($success) {
            // Propagate definitions to every auto-generated transaction by id.
            // Dates of already-posted rows are intentionally left untouched so
            // the ledger keeps the real posting date.
            self::propagateCommitmentToTransactions($id, $name, $type, $amount, $category_id, $accountId);
        }
        return $success;
    }

    /**
     * Propagate a schedule's definitions (name, amount, type, category and
     * account) onto every transaction it generated, matched by commitment_id.
     * This is why category edits now reflect in the ledger. Posted dates are
     * left untouched so the ledger keeps the real posting date.
     */
    private static function propagateCommitmentToTransactions(int $id, string $name, string $type, float $amount, ?int $categoryId, ?int $accountId): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE transactions SET description = ?, amount = ?, type = ?, category_id = ?, account_id = ? WHERE commitment_id = ?");
        $stmt->execute(['[Auto] ' . $name, $amount, $type, $categoryId, $accountId, $id]);
    }

    /** Stop future occurrences but keep the schedule and all history. */
    public static function archiveCommitment(int $id): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE commitments SET archived = 1 WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public static function restoreCommitment(int $id): bool
    {
        $db = Database::getConnection();

        // An auto-archived (expired) schedule must also lose its past end date,
        // otherwise the next run would immediately re-archive it.
        $end = $db->prepare("SELECT end_date FROM commitments WHERE id = ?");
        $end->execute([$id]);
        $endDate = $end->fetchColumn();
        if (is_string($endDate) && $endDate !== '' && substr($endDate, 0, 7) < date('Y-m')) {
            $stmt = $db->prepare("UPDATE commitments SET archived = 0, end_date = NULL WHERE id = ?");
            return $stmt->execute([$id]);
        }

        $stmt = $db->prepare("UPDATE commitments SET archived = 0 WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Hard-delete the schedule. Generated transactions are preserved; their
     * commitment_id is cleared by the foreign key (ON DELETE SET NULL).
     */
    public static function deleteCommitment(int $id): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM commitments WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /** Whether the current month has already been posted for this schedule. */
    public static function currentPeriodPosted(int $id): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) FROM transactions WHERE commitment_id = ? AND commitment_period = ?");
        $stmt->execute([$id, date('Y-m')]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Post this month's occurrence now (early). Uses today as the actual date;
     * the period tag stops the scheduler from double-posting later.
     */
    public static function postCommitmentNow(int $id): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM commitments WHERE id = ? AND archived = 0");
        $stmt->execute([$id]);
        $c = $stmt->fetch();
        if (!$c) {
            return false;
        }

        $period = date('Y-m');
        $check = $db->prepare("SELECT COUNT(*) FROM transactions WHERE commitment_id = ? AND commitment_period = ?");
        $check->execute([$id, $period]);
        if ((int) $check->fetchColumn() > 0) {
            return false;
        }

        $ins = $db->prepare("INSERT INTO transactions (category_id, amount, type, description, date, account_id, commitment_id, commitment_period) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        return $ins->execute([
            $c['category_id'],
            $c['amount'],
            $c['type'] ?? 'expense',
            '[Auto] ' . $c['name'],
            date('Y-m-d'),
            $c['account_id'],
            $id,
            $period,
        ]);
    }

    /**
     * Build a valid Y-m-d date for a recurring item, clamping the due day
     * to the number of days in the target month (e.g. day 31 in Feb -> 28/29).
     */
    private static function clampedDueDate(int $year, int $month, int $dueDay): string
    {
        $lastDay = (int) date('t', strtotime(sprintf('%04d-%02d-01', $year, $month)));
        $day = min($dueDay, $lastDay);
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /**
     * Post occurrences that have become due. By default this is FORWARD-ONLY:
     * only the current month can post, so normal runs and edits never inject
     * backdated transactions. Pass $backfillPast = true (only when a schedule is
     * first created with a past start date) to build history from the start.
     */
    public static function processDueCommitments(bool $force = false, bool $backfillPast = false): void
    {
        $todayStr = date('Y-m-d');
        $db = Database::getConnection();
        $currentMonth = date('Y-m');

        $lastProcessed = Settings::get('last_commitment_process', '');
        if (!$force && $lastProcessed === $todayStr) {
            // Posting is gated to once a day, but expiry must still be applied
            // so ended schedules move to the archive on the next page load.
            self::archiveExpiredCommitments($db, $currentMonth);
            return;
        }

        // Archived schedules never post; everything else is month-driven.
        $commitments = $db->query("SELECT * FROM commitments WHERE archived = 0")->fetchAll();

        // A month is "covered" when a transaction exists for (commitment, period).
        // This is the source of truth: delete a posted transaction and the month
        // becomes uncovered again, so the next run re-posts it.
        $postedStmt = $db->prepare("SELECT COUNT(*) FROM transactions WHERE commitment_id = ? AND commitment_period = ?");
        $legacyStmt = $db->prepare("SELECT id FROM transactions WHERE description = ? AND type = ? AND date = ?");
        $linkStmt = $db->prepare("UPDATE transactions SET commitment_id = ?, commitment_period = ? WHERE id = ?");
        $insertStmt = $db->prepare("INSERT INTO transactions (category_id, amount, type, description, date, account_id, commitment_id, commitment_period) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($commitments as $c) {
            $desc = '[Auto] ' . $c['name'];
            $type = $c['type'] ?? 'expense';
            $dueDay = (int) $c['due_date_day'];
            $cid = (int) $c['id'];

            $start = !empty($c['start_date']) ? new DateTime($c['start_date']) : new DateTime($todayStr);
            $start->modify('first day of this month');
            if (!$backfillPast) {
                // Forward-only: never reach back before the current month.
                $curMonthStart = new DateTime($todayStr);
                $curMonthStart->modify('first day of this month');
                if ($start < $curMonthStart) {
                    $start = $curMonthStart;
                }
            }
            $endLimitStr = (!empty($c['end_date']) && $c['end_date'] < $todayStr) ? $c['end_date'] : $todayStr;
            $end = new DateTime($endLimitStr);
            $end->modify('last day of this month');

            $currentIter = clone $start;
            while ($currentIter <= $end) {
                $iterYear = (int) $currentIter->format('Y');
                $iterMonth = (int) $currentIter->format('m');
                $dueDateStr = self::clampedDueDate($iterYear, $iterMonth, $dueDay);
                $period = substr($dueDateStr, 0, 7);

                if ($dueDateStr <= $todayStr) {
                    $afterStart = empty($c['start_date']) || $period >= substr((string) $c['start_date'], 0, 7);
                    $beforeEnd = empty($c['end_date']) || $period <= substr((string) $c['end_date'], 0, 7);
                    if ($afterStart && $beforeEnd) {
                        $postedStmt->execute([$cid, $period]);
                        if ((int) $postedStmt->fetchColumn() === 0) {
                            // Adopt a legacy "[Auto]" row for this month instead of
                            // inserting a duplicate that predates the id link.
                            $legacyStmt->execute([$desc, $type, $dueDateStr]);
                            $existingId = $legacyStmt->fetchColumn();
                            if ($existingId) {
                                $linkStmt->execute([$cid, $period, (int) $existingId]);
                            } else {
                                $insertStmt->execute([$c['category_id'], $c['amount'], $type, $desc, $dueDateStr, $c['account_id'], $cid, $period]);
                            }
                        }
                    }
                }
                $currentIter->modify('+1 month');
            }
        }

        // Retire schedules whose end month has passed. Runs after posting so a
        // deliberately backdated/ended schedule can still build its history first.
        self::archiveExpiredCommitments($db, $currentMonth);

        Settings::set('last_commitment_process', $todayStr);
    }

    private static function archiveExpiredCommitments(\PDO $db, string $currentMonth): void
    {
        $stmt = $db->prepare("UPDATE commitments SET archived = 1 WHERE archived = 0 AND end_date IS NOT NULL AND substr(end_date, 1, 7) < ?");
        $stmt->execute([$currentMonth]);
    }
}
