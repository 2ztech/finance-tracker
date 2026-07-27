<?php

declare(strict_types=1);

final class Expense
{
    private static array $startingBalanceCache = [];
    private static array $eomProjectionCache = [];
    private static array $onHandBalanceCache = [];

    // --- Transactions ---

    public static function getTransactions(string $month, string $year): array
    {
        $db = Database::getConnection();
        $startDate = "$year-$month-01";
        $endDate = date("Y-m-t", strtotime($startDate));

        $query = "
            SELECT t.*, c.name AS category_name, c.color_hex 
            FROM transactions t
            LEFT JOIN categories c ON t.category_id = c.id
            WHERE t.date >= ? AND t.date <= ?
            ORDER BY t.date DESC, t.id DESC
        ";
        $stmt = $db->prepare($query);
        $stmt->execute([$startDate, $endDate]);
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

    public static function getExpensesByCategory(string $month, string $year): array
    {
        $db = Database::getConnection();
        $startDate = "$year-$month-01";
        $endDate = date("Y-m-t", strtotime($startDate));

        $query = "
            SELECT c.name, c.color_hex, ROUND(SUM(t.amount), 2) AS total
            FROM transactions t
            JOIN categories c ON t.category_id = c.id
            WHERE t.type = 'expense' AND t.date >= ? AND t.date <= ?
            GROUP BY c.id
            ORDER BY total DESC
        ";
        $stmt = $db->prepare($query);
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll();
    }

    public static function addTransaction(int $categoryId, float $amount, string $type, string $description, string $date): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO transactions (category_id, amount, type, description, date) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([
            $categoryId > 0 ? $categoryId : null,
            $amount,
            $type,
            $description,
            $date,
        ]);
    }

    public static function updateTransaction(int $id, int $categoryId, float $amount, string $type, string $description, string $date): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE transactions SET category_id = ?, amount = ?, type = ?, description = ?, date = ? WHERE id = ?");
        return $stmt->execute([
            $categoryId > 0 ? $categoryId : null,
            $amount,
            $type,
            $description,
            $date,
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
        foreach ($commitments as $c) {
            $dueDateStr = sprintf("%04d-%02d-%02d", (int) $year, (int) $month, (int) $c['due_date_day']);

            $valid = true;
            if (!empty($c['start_date']) && $dueDateStr < $c['start_date']) {
                $valid = false;
            }
            if (!empty($c['end_date']) && $dueDateStr > $c['end_date']) {
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

    public static function getCommitments(): array
    {
        $db = Database::getConnection();
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

    public static function addCommitment(string $name, float $amount, string $type, int $due_date_day, ?int $category_id = null, ?string $start_date = null, ?string $end_date = null): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO commitments (name, amount, type, due_date_day, category_id, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $success = $stmt->execute([$name, $amount, $type, $due_date_day, $category_id, $start_date, $end_date]);
        if ($success && $category_id !== null) {
            self::syncTransactionsCategory($name, $category_id);
        }
        return $success;
    }

    public static function updateCommitment(int $id, string $name, float $amount, string $type, int $due_date_day, ?int $category_id = null, ?string $start_date = null, ?string $end_date = null): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE commitments SET name = ?, amount = ?, type = ?, due_date_day = ?, category_id = ?, start_date = ?, end_date = ? WHERE id = ?");
        $success = $stmt->execute([$name, $amount, $type, $due_date_day, $category_id, $start_date, $end_date, $id]);
        if ($success && $category_id !== null) {
            self::syncTransactionsCategory($name, $category_id);
        }
        return $success;
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
        $stmt = $db->prepare("DELETE FROM commitments WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public static function processDueCommitments(): void
    {
        $todayStr = date('Y-m-d');
        $lastProcessed = Settings::get('last_commitment_process', '');
        if ($lastProcessed === $todayStr) {
            return;
        }

        $db = Database::getConnection();

        $stmt = $db->query("SELECT * FROM commitments");
        $commitments = $stmt->fetchAll();

        $checkStmt = $db->prepare("SELECT COUNT(*) FROM transactions WHERE description = ? AND type = ? AND date = ?");
        $insertStmt = $db->prepare("INSERT INTO transactions (category_id, amount, type, description, date) VALUES (?, ?, ?, ?, ?)");

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
                    $iterYear = $currentIter->format('Y');
                    $iterMonth = $currentIter->format('m');
                    $dueDateStr = sprintf("%04d-%02d-%02d", (int) $iterYear, (int) $iterMonth, $dueDay);

                    if ($dueDateStr <= $todayStr) {
                        if ($dueDateStr >= $c['start_date'] && (empty($c['end_date']) || $dueDateStr <= $c['end_date'])) {
                            $checkStmt->execute([$desc, $type, $dueDateStr]);
                            if ($checkStmt->fetchColumn() == 0) {
                                $insertStmt->execute([$c['category_id'], $c['amount'], $type, $desc, $dueDateStr]);
                            }
                        }
                    }
                    $currentIter->modify('+1 month');
                }
            } else {
                $currentYear = date('Y');
                $currentMonth = date('m');
                $dueDateStr = sprintf("%04d-%02d-%02d", (int) $currentYear, (int) $currentMonth, $dueDay);

                if ($dueDateStr <= $todayStr) {
                    if (empty($c['end_date']) || $dueDateStr <= $c['end_date']) {
                        $checkStmt->execute([$desc, $type, $dueDateStr]);
                        if ($checkStmt->fetchColumn() == 0) {
                            $insertStmt->execute([$c['category_id'], $c['amount'], $type, $desc, $dueDateStr]);
                        }
                    }
                }
            }
        }

        Settings::set('last_commitment_process', $todayStr);
    }
}
