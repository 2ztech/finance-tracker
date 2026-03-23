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

    public static function getTotalIncome(string $month, string $year): float {
        $db = Database::getConnection();
        $startDate = "$year-$month-01";
        $endDate = date("Y-m-t", strtotime($startDate));
        
        $stmt = $db->prepare("SELECT SUM(amount) FROM transactions WHERE type = 'income' AND date >= ? AND date <= ?");
        $stmt->execute([$startDate, $endDate]);
        return (float) $stmt->fetchColumn();
    }

    public static function getTotalIncomeAllTime(): float {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT SUM(amount) FROM transactions WHERE type = 'income'");
        return (float) $stmt->fetchColumn();
    }

    public static function getTotalExpense(string $month, string $year): float {
        $db = Database::getConnection();
        $startDate = "$year-$month-01";
        $endDate = date("Y-m-t", strtotime($startDate));
        
        $stmt = $db->prepare("SELECT SUM(amount) FROM transactions WHERE type = 'expense' AND date >= ? AND date <= ?");
        $stmt->execute([$startDate, $endDate]);
        return (float) $stmt->fetchColumn();
    }

    public static function getTotalExpenseAllTime(): float {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT SUM(amount) FROM transactions WHERE type = 'expense'");
        return (float) $stmt->fetchColumn();
    }

    public static function getExpensesByCategory(string $month, string $year): array {
        $db = Database::getConnection();
        $startDate = "$year-$month-01";
        $endDate = date("Y-m-t", strtotime($startDate));
        
        $query = "
            SELECT c.name, c.color_hex, SUM(t.amount) as total
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

    // --- Commitments Logic ---
    public static function getCommitments(): array {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT * FROM commitments ORDER BY due_date_day");
        return $stmt->fetchAll();
    }

    public static function getCommitmentsRemaining(): float {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT SUM(amount) FROM commitments");
        return (float) $stmt->fetchColumn();
    }

    public static function addCommitment(string $name, float $amount, int $due_date_day): bool {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO commitments (name, amount, due_date_day) VALUES (?, ?, ?)");
        return $stmt->execute([$name, $amount, $due_date_day]);
    }

    public static function deleteCommitment(int $id): bool {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM commitments WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
