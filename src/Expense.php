<?php
class Expense {
    // --- Transactions Logic ---
    public static function getTransactions(string $month, string $year): array {
        $db = Database::getConnection();
        $startDate = "$year-$month-01";
        $endDate = date("Y-m-t", strtotime($startDate));
        
        $query = "
            SELECT t.*, c.name as category_name, c.color_hex 
            FROM transactions t
            LEFT JOIN categories c ON t.category_id = c.id
            WHERE t.date >= ? AND t.date <= ?
            ORDER BY t.date DESC, t.id DESC
        ";
        $stmt = $db->prepare($query);
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll();
    }

    public static function getTotalIncomeBetween(string $startDate, string $endDate): float {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT ROUND(SUM(amount), 2) FROM transactions WHERE type = 'income' AND date >= ? AND date <= ?");
        $stmt->execute([$startDate, $endDate]);
        return round((float) $stmt->fetchColumn(), 2);
    }

    public static function getTotalExpenseBetween(string $startDate, string $endDate): float {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT ROUND(SUM(amount), 2) FROM transactions WHERE type = 'expense' AND date >= ? AND date <= ?");
        $stmt->execute([$startDate, $endDate]);
        return round((float) $stmt->fetchColumn(), 2);
    }

    public static function getTotalIncome(string $month, string $year): float {
        $startDate = "$year-$month-01";
        $endDate = date("Y-m-t", strtotime($startDate));
        return self::getTotalIncomeBetween($startDate, $endDate);
    }

    public static function getTotalIncomeAllTime(): float {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT ROUND(SUM(amount), 2) FROM transactions WHERE type = 'income'");
        return round((float) $stmt->fetchColumn(), 2);
    }

    public static function getTotalExpense(string $month, string $year): float {
        $startDate = "$year-$month-01";
        $endDate = date("Y-m-t", strtotime($startDate));
        return self::getTotalExpenseBetween($startDate, $endDate);
    }

    public static function getTotalExpenseAllTime(): float {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT ROUND(SUM(amount), 2) FROM transactions WHERE type = 'expense'");
        return round((float) $stmt->fetchColumn(), 2);
    }

    public static function getExpensesByCategory(string $month, string $year): array {
        $db = Database::getConnection();
        $startDate = "$year-$month-01";
        $endDate = date("Y-m-t", strtotime($startDate));
        
        $query = "
            SELECT c.name, c.color_hex, ROUND(SUM(t.amount), 2) as total
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

    public static function addTransaction(int $categoryId, float $amount, string $type, string $description, string $date): bool {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO transactions (category_id, amount, type, description, date) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([
            $categoryId ?: null,
            $amount,
            $type,
            $description,
            $date
        ]);
    }

    public static function deleteTransaction(int $id): bool {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM transactions WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // --- Core Balance Calculations Phase 6 ---

    public static function getStartingBalanceForMonth(string $month, string $year): float {
        $requested = "$year-$month";
        $trackingStart = Settings::get('tracking_start_month', date('Y-m'));

        if ($requested <= $trackingStart) {
            return round((float) Settings::get('starting_bank_balance', 0), 2);
        }

        // Requested is greater, calculate previous month recursively
        $prevDate = date('Y-m', strtotime("$requested-01 -1 month"));
        $prevParts = explode('-', $prevDate);
        $prevYear = $prevParts[0];
        $prevMonth = $prevParts[1];

        $prevStart = self::getStartingBalanceForMonth($prevMonth, $prevYear);
        $prevInc = self::getTotalIncome($prevMonth, $prevYear);
        $prevExp = self::getTotalExpense($prevMonth, $prevYear);

        return round($prevStart + $prevInc - $prevExp, 2);
    }

    public static function getOnHandBalance(string $month, string $year): float {
        $sysDate = date('Y-m-d');
        $sysMonth = date('m');
        $sysYear = date('Y');
        
        $viewing = "$year-$month";
        $current = "$sysYear-$sysMonth";
        $startDate = "$year-$month-01";
        
        // 1. Viewing the CURRENT Month
        if ($viewing === $current) {
            $startBal = self::getStartingBalanceForMonth($month, $year);
            $inc = self::getTotalIncomeBetween($startDate, $sysDate);
            $exp = self::getTotalExpenseBetween($startDate, $sysDate);
            return round($startBal + $inc - $exp, 2);
        } 
        // 2. Viewing a PAST Month
        elseif ($viewing < $current) {
            $endDate = date("Y-m-t", strtotime($startDate));
            $startBal = self::getStartingBalanceForMonth($month, $year);
            $inc = self::getTotalIncomeBetween($startDate, $endDate);
            $exp = self::getTotalExpenseBetween($startDate, $endDate);
            return round($startBal + $inc - $exp, 2);
        } 
        // 3. Viewing a FUTURE Month (Your Ledger Fix)
        else {
            // Grab the On-Hand balance from the PREVIOUS month
            $prevDate = date('Y-m', strtotime("$startDate -1 month"));
            $prevParts = explode('-', $prevDate);
            
            $prevOnHand = self::getOnHandBalance($prevParts[1], $prevParts[0]);
            
            // Add any new transactions already inserted into this future month
            $endDate = date("Y-m-t", strtotime($startDate));
            $inc = self::getTotalIncomeBetween($startDate, $endDate);
            $exp = self::getTotalExpenseBetween($startDate, $endDate);
            
            return round($prevOnHand + $inc - $exp, 2);
        }
    }

    public static function getUnpaidCommitments(string $month, string $year): float {
        $db = Database::getConnection();
        
        // Total monthly commitments
        $stmt1 = $db->query("SELECT ROUND(SUM(amount), 2) FROM commitments");
        $totalComm = round((float) $stmt1->fetchColumn(), 2);
        
        // Paid commitments (auto-inserted) in this month
        $startDate = "$year-$month-01";
        $endDate = date("Y-m-t", strtotime($startDate));
        
        $stmt2 = $db->prepare("SELECT ROUND(SUM(amount), 2) FROM transactions WHERE type = 'expense' AND description LIKE '[Auto] %' AND date >= ? AND date <= ?");
        $stmt2->execute([$startDate, $endDate]);
        $paidComm = round((float) $stmt2->fetchColumn(), 2);
        
        $unpaid = round($totalComm - $paidComm, 2);
        return $unpaid > 0 ? $unpaid : 0.0;
    }

    public static function getEOMProjection(string $month, string $year): float {
        $requested = "$year-$month";
        $trackingStart = Settings::get('tracking_start_month', date('Y-m'));

        // 1. For your Start Month (March), calculate normally from Starting Balance
        if ($requested <= $trackingStart) {
            $startBal = self::getStartingBalanceForMonth($month, $year);
            $allInc = self::getTotalIncome($month, $year);
            $allExp = self::getTotalExpense($month, $year);
            $unpaid = self::getUnpaidCommitments($month, $year);
            
            return round($startBal + $allInc - $allExp - $unpaid, 2);
        } 
        // 2. For Future Months (April onwards), carry over the PREVIOUS month's EOM
        else {
            $prevDate = date('Y-m', strtotime("$requested-01 -1 month"));
            $prevParts = explode('-', $prevDate);
            
            // Grab the 274.47 from March
            $prevEOM = self::getEOMProjection($prevParts[1], $prevParts[0]);
            
            $allInc = self::getTotalIncome($month, $year);
            $allExp = self::getTotalExpense($month, $year);
            $unpaid = self::getUnpaidCommitments($month, $year);
            
            // 274.47 + 0 (Inc) - 0 (Exp) - 501.25 (Unpaid) = -226.78
            return round($prevEOM + $allInc - $allExp - $unpaid, 2);
        }
    }

    // --- Commitments Logic ---
    public static function getCommitments(): array {
        $db = Database::getConnection();
        $stmt = $db->query("
            SELECT c.*, cat.name as category_name, cat.color_hex 
            FROM commitments c
            LEFT JOIN categories cat ON c.category_id = cat.id
            ORDER BY c.due_date_day
        ");
        return $stmt->fetchAll();
    }

    public static function getCommitmentsRemaining(): float {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT ROUND(SUM(amount), 2) FROM commitments");
        return round((float) $stmt->fetchColumn(), 2);
    }

    public static function addCommitment(string $name, float $amount, int $due_date_day, ?int $category_id = null): bool {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO commitments (name, amount, due_date_day, category_id) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$name, $amount, $due_date_day, $category_id]);
    }

    public static function updateCommitment(int $id, string $name, float $amount, int $due_date_day, ?int $category_id = null): bool {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE commitments SET name = ?, amount = ?, due_date_day = ?, category_id = ? WHERE id = ?");
        return $stmt->execute([$name, $amount, $due_date_day, $category_id, $id]);
    }

    public static function deleteCommitment(int $id): bool {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM commitments WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public static function processDueCommitments(): void {
        $db = Database::getConnection();
        $currentDay = (int)date('j');
        $currentMonth = date('m');
        $currentYear = date('Y');
        
        $stmt = $db->query("SELECT * FROM commitments WHERE due_date_day <= $currentDay");
        $dueCommitments = $stmt->fetchAll();
        
        $checkStmt = $db->prepare("SELECT COUNT(*) FROM transactions WHERE description = ? AND type = 'expense' AND date = ?");
        $insertStmt = $db->prepare("INSERT INTO transactions (category_id, amount, type, description, date) VALUES (?, ?, 'expense', ?, ?)");
        
        foreach ($dueCommitments as $c) {
            $desc = "[Auto] " . $c['name'];
            $dueDateStr = sprintf("%04d-%02d-%02d", $currentYear, $currentMonth, $c['due_date_day']);
            
            $checkStmt->execute([$desc, $dueDateStr]);
            
            if ($checkStmt->fetchColumn() == 0) {
                $insertStmt->execute([$c['category_id'], $c['amount'], $desc, $dueDateStr]);
            }
        }
    }
}
