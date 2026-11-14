<?php

declare(strict_types=1);

final class Account
{
    public static function all(bool $includeArchived = false, ?int $institutionId = null): array
    {
        $db = Database::getConnection();
        $sql = "SELECT * FROM accounts";
        $where = [];
        $params = [];
        if (!$includeArchived) {
            $where[] = "archived = 0";
        }
        if ($institutionId !== null) {
            $where[] = "institution_id = ?";
            $params[] = $institutionId;
        }
        if ($where !== []) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $sql .= " ORDER BY sort_order, id";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Sections belonging to an institution (the user-facing account). */
    public static function forInstitution(int $institutionId, bool $includeArchived = false): array
    {
        return self::all($includeArchived, $institutionId);
    }

    public static function institution(int $accountId): ?array
    {
        return Institution::forAccount($accountId);
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

        // Sections always belong to an institution. Callers that only create a
        // standalone account (legacy custom form, tests) get one named after it.
        $institutionId = isset($d['institution_id']) && (int) $d['institution_id'] > 0
            ? (int) $d['institution_id']
            : null;
        if ($institutionId === null) {
            // The section's uploaded icon belongs to the section; the new
            // institution starts without one (it can be added in Edit Account).
            $institutionId = Institution::create([
                'name' => (string) ($d['name'] ?? 'Account'),
                'color_hex' => $d['color_hex'] ?? '#4f6ef7',
            ]);
        }

        $kind = (string) ($d['kind'] ?? '');
        $role = $kind === 'paylater'
            ? (in_array($d['paylater_role'] ?? '', ['bnpl', 'financing', 'both'], true) ? $d['paylater_role'] : 'bnpl')
            : null;
        $stmt = $db->prepare("
            INSERT INTO accounts
                (institution_id, name, kind, paylater_role, template_key, color_hex, icon_data, icon_mime, bnpl_mode, statement_day, due_day,
                 first_due_offset, allow_partial, no_interest_months, credit_limit, opening_balance, start_month,
                 archived, sort_order, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)
        ");
        $stmt->execute([
            $institutionId,
            $d['name'],
            $d['kind'],
            $role,
            $d['template_key'] ?? null,
            $d['color_hex'] ?? '#4f6ef7',
            $d['icon_data'] ?? null,
            $d['icon_mime'] ?? 'image/png',
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
        $id = (int) $db->lastInsertId();
        if (!empty($d['is_primary'])) {
            self::setPrimary($id);
        }
        return $id;
    }

    public static function update(int $id, array $d): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            UPDATE accounts SET
                institution_id = COALESCE(?, institution_id),
                paylater_role = CASE WHEN ? = 'paylater' THEN COALESCE(?, paylater_role) ELSE NULL END,
                name = ?, kind = ?, color_hex = ?, icon_data = CASE WHEN ? = '1' THEN NULL WHEN ? IS NOT NULL THEN ? ELSE icon_data END,
                icon_mime = CASE WHEN ? = '1' THEN 'image/png' WHEN ? IS NOT NULL THEN ? ELSE icon_mime END,
                bnpl_mode = ?, statement_day = ?, due_day = ?,
                first_due_offset = ?, allow_partial = ?, no_interest_months = ?, credit_limit = ?,
                opening_balance = ?, start_month = ?, archived = ?, sort_order = ?
            WHERE id = ?
        ");
        $kind = (string) ($d['kind'] ?? '');
        $role = in_array($d['paylater_role'] ?? '', ['bnpl', 'financing', 'both'], true) ? $d['paylater_role'] : null;
        $ok = $stmt->execute([
            isset($d['institution_id']) && (int) $d['institution_id'] > 0 ? (int) $d['institution_id'] : null,
            $kind,
            $role,
            $d['name'],
            $d['kind'],
            $d['color_hex'] ?? '#4f6ef7',
            (int) ($d['clear_icon'] ?? 0),
            $d['icon_data'] ?? null,
            $d['icon_data'] ?? null,
            (int) ($d['clear_icon'] ?? 0),
            $d['icon_data'] ?? null,
            $d['icon_mime'] ?? 'image/png',
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
        if ($ok && !empty($d['is_primary'])) {
            self::setPrimary($id);
        }
        return $ok;
    }

    /** Mark one savings account as the daily/main account (single-primary). */
    public static function setPrimary(int $id): void
    {
        $db = Database::getConnection();
        $db->prepare("UPDATE accounts SET is_primary = 0 WHERE id <> ?")->execute([$id]);
        $db->prepare("UPDATE accounts SET is_primary = 1 WHERE id = ?")->execute([$id]);
    }

    public static function primaryId(): ?int
    {
        $db = Database::getConnection();
        $row = $db->query("SELECT id FROM accounts WHERE archived = 0 AND is_primary = 1 LIMIT 1")->fetchColumn();
        return $row ? (int) $row : null;
    }

    public static function delete(int $id): bool
    {
        $db = Database::getConnection();
        // Refuse to delete an account that still has transactions (defence in
        // depth; the Accounts page enforces the same rule).
        $stmt = $db->prepare("SELECT COUNT(*) FROM transactions WHERE account_id = ?");
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() > 0) {
            return false;
        }
        $stmt = $db->prepare("DELETE FROM accounts WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // --- Active account (session) ---

    public static function activeId(): ?int
    {
        $id = (int) ($_SESSION['active_account_id'] ?? 0);
        $institutionId = (int) ($_SESSION['active_institution_id'] ?? 0);
        $account = $id > 0 ? self::find($id) : null;

        if ($account !== null && ($institutionId === 0 || (int) ($account['institution_id'] ?? 0) === $institutionId)) {
            if ($institutionId === 0) {
                $institution = Institution::forAccount($id);
                if ($institution !== null) {
                    $_SESSION['active_institution_id'] = (int) $institution['id'];
                }
            }
            return $id;
        }

        if ($institutionId > 0 && ($section = Institution::defaultSection($institutionId)) !== null) {
            $_SESSION['active_account_id'] = (int) $section['id'];
            return (int) $section['id'];
        }

        $default = self::defaultId();
        if ($default !== null) {
            $_SESSION['active_account_id'] = $default;
            $institution = Institution::forAccount($default);
            if ($institution !== null) {
                $_SESSION['active_institution_id'] = (int) $institution['id'];
            }
        }
        return $default;
    }

    public static function active(): ?array
    {
        $id = self::activeId();
        return $id ? self::find($id) : null;
    }

    public static function setActive(int $id): void
    {
        $account = self::find($id);
        if ($account === null) {
            return;
        }
        $_SESSION['active_account_id'] = $id;
        $institution = Institution::forAccount($id);
        if ($institution !== null) {
            $_SESSION['active_institution_id'] = (int) $institution['id'];
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
