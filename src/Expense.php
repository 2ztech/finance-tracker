<?php

declare(strict_types=1);

final class Expense
{
    private static array $startingBalanceCache = [];
    private static array $eomProjectionCache = [];
    private static array $onHandBalanceCache = [];

    // --- Transactions ---

    public static function getTransactions(string $month, string $year, ?int $accountId = null): array
    {
        $db = Database::getConnection();
        $startDate = "$year-$month-01";
        $endDate = date("Y-m-t", strtotime($startDate));

        $sql = "
            SELECT t.*, c.name AS category_name, c.color_hex 
            FROM transactions t
            LEFT JOIN categories c ON t.category_id = c.id
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
            SELECT c.id AS category_id, c.name, c.color_hex, ROUND(SUM(t.amount), 2) AS total
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

    public static function getUnpaidRecurring(string $type, string $month, string $year): float
    {
        $db = Database::getConnection();

        $stmt1 = $db->prepare("SELECT * FROM commitments WHERE type = ?");
        $stmt1->execute([$type]);
        $commitments = $stmt1->fetchAll();

        $totalComm = 0.0;
        $monthKey = sprintf('%04d-%02d', (int) $year, (int) $month);
        foreach ($commitments as $c) {
            $dueDateStr = self::clampedDueDate((int) $year, (int) $month, (int) $c['due_date_day']);

            // Start/end define which MONTHS the item is active; the day-of-month
            // in those dates is intentionally ignored (the due day governs that).
            $valid = true;
            if (!empty($c['start_date']) && $monthKey < substr($c['start_date'], 0, 7)) {
                $valid = false;
            }
            if (!empty($c['end_date']) && $monthKey > substr($c['end_date'], 0, 7)) {
                $valid = false;
            }

            if ($valid) {
                $totalComm += (float) $c['amount'];
            }
        }
        $totalComm = round($totalComm, 2);

        $startDate = "$year-$month-01";
        $endDate = date("Y-m-t", strtotime($startDate));

        $stmt2 = $db->prepare("SELECT ROUND(SUM(amount), 2) FROM transactions WHERE type = ? AND description LIKE '[Auto] %' AND date >= ? AND date <= ?");
        $stmt2->execute([$type, $startDate, $endDate]);
        $paidComm = round((float) $stmt2->fetchColumn(), 2);

        $unpaid = round($totalComm - $paidComm, 2);
        return max($unpaid, 0.0);
    }

    public static function getEOMProjection(string $month, string $year): float
    {
        $cacheKey = "$year-$month";
        if (isset(self::$eomProjectionCache[$cacheKey])) {
            return self::$eomProjectionCache[$cacheKey];
        }

        $requested = $cacheKey;
        $trackingStart = Settings::get('tracking_start_month', date('Y-m'));

        if ($requested <= $trackingStart) {
            $startBal = self::getStartingBalanceForMonth($month, $year);
            $allInc = self::getTotalIncome($month, $year);
            $allExp = self::getTotalExpense($month, $year);
            $unpaidInc = self::getUnpaidRecurring('income', $month, $year);
            $unpaidExp = self::getUnpaidRecurring('expense', $month, $year);

            $result = round($startBal + $allInc - $allExp + $unpaidInc - $unpaidExp, 2);
            self::$eomProjectionCache[$cacheKey] = $result;
            return $result;
        }

        $prevDate = date('Y-m', strtotime("$requested-01 -1 month"));
        [$prevYear, $prevMonth] = explode('-', $prevDate);

        $prevEOM = self::getEOMProjection($prevMonth, $prevYear);

        $allInc = self::getTotalIncome($month, $year);
        $allExp = self::getTotalExpense($month, $year);
        $unpaidInc = self::getUnpaidRecurring('income', $month, $year);
        $unpaidExp = self::getUnpaidRecurring('expense', $month, $year);

        $result = round($prevEOM + $allInc - $allExp + $unpaidInc - $unpaidExp, 2);
        self::$eomProjectionCache[$cacheKey] = $result;
        return $result;
    }

    // --- Commitments ---

    public static function getCommitments(?int $accountId = null): array
    {
        $db = Database::getConnection();
        if ($accountId !== null) {
            $stmt = $db->prepare("
                SELECT c.*, cat.name AS category_name, cat.color_hex 
                FROM commitments c
                LEFT JOIN categories cat ON c.category_id = cat.id
                WHERE c.account_id = ?
                ORDER BY c.due_date_day
            ");
            $stmt->execute([$accountId]);
            return $stmt->fetchAll();
        }
        $stmt = $db->query("
            SELECT c.*, cat.name AS category_name, cat.color_hex 
            FROM commitments c
            LEFT JOIN categories cat ON c.category_id = cat.id
            ORDER BY c.due_date_day
        ");
        return $stmt->fetchAll();
    }

    public static function getCommitmentsRemaining(): float
    {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT ROUND(SUM(amount), 2) FROM commitments WHERE type = 'expense'");
        return round((float) $stmt->fetchColumn(), 2);
    }

    public static function addCommitment(string $name, float $amount, string $type, int $due_date_day, ?int $category_id = null, ?string $start_date = null, ?string $end_date = null, ?int $accountId = null): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO commitments (name, amount, type, due_date_day, category_id, start_date, end_date, account_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $success = $stmt->execute([$name, $amount, $type, $due_date_day, $category_id, $start_date, $end_date, $accountId]);
        if ($success) {
            self::syncAutoTransactions(null, $name, $type, $amount, $category_id, $due_date_day, $start_date, $end_date, $accountId);
            if ($category_id !== null) {
                self::syncTransactionsCategory($name, $category_id);
            }
        }
        return $success;
    }

    public static function updateCommitment(int $id, string $name, float $amount, string $type, int $due_date_day, ?int $category_id = null, ?string $start_date = null, ?string $end_date = null, ?int $accountId = null): bool
    {
        $db = Database::getConnection();

        $prev = $db->prepare("SELECT name FROM commitments WHERE id = ?");
        $prev->execute([$id]);
        $oldName = $prev->fetchColumn();

        $stmt = $db->prepare("UPDATE commitments SET name = ?, amount = ?, type = ?, due_date_day = ?, category_id = ?, start_date = ?, end_date = ?, account_id = ? WHERE id = ?");
        $success = $stmt->execute([$name, $amount, $type, $due_date_day, $category_id, $start_date, $end_date, $accountId, $id]);

        if ($success) {
            self::syncAutoTransactions(is_string($oldName) ? $oldName : null, $name, $type, $amount, $category_id, $due_date_day, $start_date, $end_date, $accountId);
            if ($category_id !== null) {
                self::syncTransactionsCategory($name, $category_id);
            }
        }
        return $success;
    }

    /**
     * Propagate a commitment's definitions (amount, type, category, due day,
     * rename, and active month range) onto the auto-transactions it has already
     * generated. Rows that fall outside the new start/end months are removed.
     * Start/end are treated as month boundaries; the due day governs the day.
     */
    private static function syncAutoTransactions(?string $oldName, string $newName, string $type, float $amount, ?int $categoryId, int $dueDay, ?string $startDate = null, ?string $endDate = null, ?int $accountId = null): void
    {
        $db = Database::getConnection();

        if ($oldName !== null && $oldName !== '' && $oldName !== $newName) {
            $rename = $db->prepare("UPDATE transactions SET description = ? WHERE description = ?");
            $rename->execute(['[Auto] ' . $newName, '[Auto] ' . $oldName]);
        }

        $select = $db->prepare("SELECT id, date FROM transactions WHERE description = ?");
        $select->execute(['[Auto] ' . $newName]);
        $rows = $select->fetchAll();

        $update = $db->prepare("UPDATE transactions SET amount = ?, type = ?, category_id = ?, date = ?, account_id = ? WHERE id = ?");
        $delete = $db->prepare("DELETE FROM transactions WHERE id = ?");

        $startMonth = ($startDate !== null && $startDate !== '') ? substr($startDate, 0, 7) : null;
        $endMonth = ($endDate !== null && $endDate !== '') ? substr($endDate, 0, 7) : null;

        foreach ($rows as $row) {
            $parts = explode('-', (string) $row['date']);
            if (count($parts) !== 3) {
                continue;
            }
            $y = (int) $parts[0];
            $m = (int) $parts[1];
            $rowMonth = sprintf('%04d-%02d', $y, $m);

            if (($startMonth !== null && $rowMonth < $startMonth) || ($endMonth !== null && $rowMonth > $endMonth)) {
                $delete->execute([$row['id']]);
                continue;
            }

            $update->execute([$amount, $type, $categoryId, self::clampedDueDate($y, $m, $dueDay), $accountId, $row['id']]);
        }
    }

    private static function syncTransactionsCategory(string $name, int $categoryId): void
    {
        $db = Database::getConnection();
        $exactName = $name;
        $autoName = "[Auto] " . $name;

        $stmt = $db->prepare("UPDATE transactions SET category_id = ? WHERE (description = ? OR description = ?) AND (category_id IS NULL OR category_id = 0 OR category_id = '')");
        $stmt->execute([$categoryId, $exactName, $autoName]);
    }

    public static function deleteCommitment(int $id): bool
    {
        $db = Database::getConnection();

        $prev = $db->prepare("SELECT name FROM commitments WHERE id = ?");
        $prev->execute([$id]);
        $name = $prev->fetchColumn();

        if (is_string($name) && $name !== '') {
            // Remove the auto-transactions this item generated so the ledger
            // no longer reflects a recurring item that has been deleted.
            $del = $db->prepare("DELETE FROM transactions WHERE description = ?");
            $del->execute(['[Auto] ' . $name]);
        }

        $stmt = $db->prepare("DELETE FROM commitments WHERE id = ?");
        return $stmt->execute([$id]);
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

    public static function processDueCommitments(bool $force = false): void
    {
        $todayStr = date('Y-m-d');
        $lastProcessed = Settings::get('last_commitment_process', '');
        if (!$force && $lastProcessed === $todayStr) {
            return;
        }

        $db = Database::getConnection();

        $stmt = $db->query("SELECT * FROM commitments");
        $commitments = $stmt->fetchAll();

        $checkStmt = $db->prepare("SELECT COUNT(*) FROM transactions WHERE description = ? AND type = ? AND date = ?");
        $insertStmt = $db->prepare("INSERT INTO transactions (category_id, amount, type, description, date, account_id) VALUES (?, ?, ?, ?, ?, ?)");

        foreach ($commitments as $c) {
            $desc = "[Auto] " . $c['name'];
            $type = $c['type'] ?? 'expense';
            $dueDay = (int) $c['due_date_day'];

            if (!empty($c['start_date'])) {
                $startMonth = new DateTime($c['start_date']);
                $startMonth->modify('first day of this month');

                $endLimitStr = (!empty($c['end_date']) && $c['end_date'] < $todayStr) ? $c['end_date'] : $todayStr;
                $endMonth = new DateTime($endLimitStr);
                $endMonth->modify('last day of this month');

                $currentIter = clone $startMonth;
                while ($currentIter <= $endMonth) {
                    $iterYear = (int) $currentIter->format('Y');
                    $iterMonth = (int) $currentIter->format('m');
                    $dueDateStr = self::clampedDueDate($iterYear, $iterMonth, $dueDay);

                    if ($dueDateStr <= $todayStr) {
                        $dueMonth = substr($dueDateStr, 0, 7);
                        $afterStart = empty($c['start_date']) || $dueMonth >= substr($c['start_date'], 0, 7);
                        $beforeEnd = empty($c['end_date']) || $dueMonth <= substr($c['end_date'], 0, 7);
                        if ($afterStart && $beforeEnd) {
                            $checkStmt->execute([$desc, $type, $dueDateStr]);
                            if ($checkStmt->fetchColumn() == 0) {
                                $insertStmt->execute([$c["category_id"], $c["amount"], $type, $desc, $dueDateStr, $c["account_id"]]);
                            }
                        }
                    }
                    $currentIter->modify('+1 month');
                }
            } else {
                $dueDateStr = self::clampedDueDate((int) date('Y'), (int) date('m'), $dueDay);

                if ($dueDateStr <= $todayStr) {
                    $dueMonth = substr($dueDateStr, 0, 7);
                    $beforeEnd = empty($c['end_date']) || $dueMonth <= substr($c['end_date'], 0, 7);
                    if ($beforeEnd) {
                        $checkStmt->execute([$desc, $type, $dueDateStr]);
                        if ($checkStmt->fetchColumn() == 0) {
                            $insertStmt->execute([$c["category_id"], $c["amount"], $type, $desc, $dueDateStr, $c["account_id"]]);
                        }
                    }
                }
            }
        }

        Settings::set('last_commitment_process', $todayStr);
    }
}
