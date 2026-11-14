<?php

declare(strict_types=1);

/**
 * Institution container (the user-facing "Account").
 *
 * A row in `institutions` groups one or more `accounts` rows, which are the
 * sections/products inside it (Wallet, PayLater, Card, Savings). All financial
 * logic stays on the section rows; this class only manages grouping/metadata.
 */
final class Institution
{
    /** @return array<int, array<string, mixed>> */
    public static function all(bool $includeArchived = false): array
    {
        $db = Database::getConnection();
        $sql = "SELECT * FROM institutions";
        if (!$includeArchived) {
            $sql .= " WHERE archived = 0";
        }
        $sql .= " ORDER BY sort_order, id";
        return $db->query($sql)->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM institutions WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $d): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO institutions (name, color_hex, icon_data, icon_mime, archived, sort_order, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            trim((string) ($d['name'] ?? 'Account')),
            $d['color_hex'] ?? '#4f6ef7',
            $d['icon_data'] ?? null,
            $d['icon_mime'] ?? 'image/png',
            (int) ($d['archived'] ?? 0),
            (int) ($d['sort_order'] ?? 0),
            date('Y-m-d H:i:s'),
        ]);
        return (int) $db->lastInsertId();
    }

    public static function update(int $id, array $d): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            UPDATE institutions SET
                name = ?, color_hex = ?,
                icon_data = CASE WHEN ? = '1' THEN NULL WHEN ? IS NOT NULL THEN ? ELSE icon_data END,
                icon_mime = CASE WHEN ? = '1' THEN 'image/png' WHEN ? IS NOT NULL THEN ? ELSE icon_mime END,
                archived = ?, sort_order = ?
            WHERE id = ?
        ");
        return $stmt->execute([
            trim((string) ($d['name'] ?? '')),
            $d['color_hex'] ?? '#4f6ef7',
            (int) ($d['clear_icon'] ?? 0),
            $d['icon_data'] ?? null,
            $d['icon_data'] ?? null,
            (int) ($d['clear_icon'] ?? 0),
            $d['icon_data'] ?? null,
            $d['icon_mime'] ?? 'image/png',
            (int) ($d['archived'] ?? 0),
            (int) ($d['sort_order'] ?? 0),
            $id,
        ]);
    }

    /**
     * Delete an institution and its sections. Callers must ensure no section
     * has transactions (see hasTransactions()).
     */
    public static function delete(int $id): bool
    {
        $db = Database::getConnection();
        $db->prepare("DELETE FROM accounts WHERE institution_id = ?")->execute([$id]);
        return $db->prepare("DELETE FROM institutions WHERE id = ?")->execute([$id]);
    }

    /** @return array<int, array<string, mixed>> */
    public static function sections(int $id, bool $includeArchived = false): array
    {
        return Account::forInstitution($id, $includeArchived);
    }

    /**
     * Group a flat account list by institution for <optgroup> rendering.
     *
     * @param array<int, array<string, mixed>> $accounts
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function groupAccounts(array $accounts): array
    {
        $byInstitution = [];
        foreach ($accounts as $account) {
            $byInstitution[(int) ($account['institution_id'] ?? 0)][] = $account;
        }

        $groups = [];
        foreach (self::all(true) as $institution) {
            $id = (int) $institution['id'];
            if (isset($byInstitution[$id])) {
                $groups[(string) $institution['name']] = $byInstitution[$id];
                unset($byInstitution[$id]);
            }
        }
        foreach ($byInstitution as $list) {
            $groups[(string) ($list[0]['name'] ?? 'Other')] = $list;
        }
        return $groups;
    }

    /** @return array<int, array<string, mixed>> institutions with ['sections' => ...] */
    public static function grouped(bool $includeArchived = false): array
    {
        $institutions = self::all($includeArchived);
        foreach ($institutions as &$institution) {
            $institution['sections'] = self::sections((int) $institution['id'], $includeArchived);
        }
        unset($institution);
        return $institutions;
    }

    /** @return array{assets:float, owed:float, net:float, limit:float} */
    public static function summary(int $id): array
    {
        $assets = 0.0;
        $owed = 0.0;
        $limit = 0.0;
        foreach (self::sections($id, true) as $section) {
            $balance = Account::balance((int) $section['id']);
            if (Account::isLiability($section)) {
                $owed += $balance;
            } else {
                $assets += $balance;
            }
            if (($section['credit_limit'] ?? null) !== null) {
                $limit += (float) $section['credit_limit'];
            }
        }
        return [
            'assets' => round($assets, 2),
            'owed' => round($owed, 2),
            'net' => round($assets - $owed, 2),
            'limit' => round($limit, 2),
        ];
    }

    public static function forAccount(int $accountId): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT i.* FROM institutions i
            JOIN accounts a ON a.institution_id = i.id
            WHERE a.id = ?
        ");
        $stmt->execute([$accountId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<int, array<string, mixed>> liability sections only */
    public static function liabilitySections(int $id): array
    {
        return array_values(array_filter(
            self::sections($id),
            static fn (array $section): bool => Account::isLiability($section)
        ));
    }

    public static function hasLiabilitySections(int $id): bool
    {
        return self::liabilitySections($id) !== [];
    }

    /**
     * Default section when an institution is selected: Wallet, then PayLater,
     * then Financing, then a card, then whatever exists.
     *
     * @return array<string, mixed>|null
     */
    public static function defaultSection(int $id): ?array
    {
        $sections = self::sections($id);
        foreach ($sections as $section) {
            if ($section['kind'] === 'savings') {
                return $section;
            }
        }
        foreach ($sections as $section) {
            if ($section['kind'] === 'paylater' && ($section['paylater_role'] ?? 'bnpl') === 'bnpl') {
                return $section;
            }
        }
        foreach ($sections as $section) {
            if ($section['kind'] === 'paylater' && ($section['paylater_role'] ?? 'bnpl') === 'financing') {
                return $section;
            }
        }
        foreach ($sections as $section) {
            if ($section['kind'] === 'credit') {
                return $section;
            }
        }
        return $sections[0] ?? null;
    }

    /** Currently selected institution (session-backed, derived from the section when needed). */
    public static function active(): ?array
    {
        $id = (int) ($_SESSION['active_institution_id'] ?? 0);
        if ($id > 0 && ($institution = self::find($id)) !== null) {
            return $institution;
        }

        $account = Account::active();
        if ($account !== null) {
            $institution = self::forAccount((int) $account['id']);
            if ($institution !== null) {
                $_SESSION['active_institution_id'] = (int) $institution['id'];
                return $institution;
            }
        }

        $all = self::all();
        if ($all !== []) {
            $_SESSION['active_institution_id'] = (int) $all[0]['id'];
            return $all[0];
        }
        return null;
    }

    /** Activate an institution and one of its sections (Wallet first). */
    public static function activate(int $id): void
    {
        if (self::find($id) === null) {
            return;
        }
        $_SESSION['active_institution_id'] = $id;
        $section = self::defaultSection($id);
        if ($section !== null) {
            $_SESSION['active_account_id'] = (int) $section['id'];
        }
    }

    /** Human label for a section, including its PayLater role. */
    public static function sectionRoleLabel(array $section): string
    {
        if (($section['kind'] ?? '') === 'savings') {
            return 'Savings';
        }
        if (($section['kind'] ?? '') === 'credit') {
            return 'Credit card';
        }
        if (($section['kind'] ?? '') === 'paylater') {
            $role = $section['paylater_role'] ?? 'bnpl';
            return match ($role) {
                'financing' => 'Financing',
                'both' => 'PayLater + Financing',
                default => 'PayLater',
            };
        }
        return ucfirst((string) ($section['kind'] ?? 'Account'));
    }

    /** True when any section in the institution has transactions. */
    public static function hasTransactions(int $id): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM transactions
            WHERE account_id IN (SELECT id FROM accounts WHERE institution_id = ?)
        ");
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** Archive/unarchive the institution and (optionally) all its sections. */
    public static function setArchived(int $id, int $archived, bool $cascade = true): void
    {
        $db = Database::getConnection();
        $db->prepare("UPDATE institutions SET archived = ? WHERE id = ?")->execute([$archived, $id]);
        if ($cascade) {
            $db->prepare("UPDATE accounts SET archived = ? WHERE institution_id = ?")->execute([$archived, $id]);
        }
    }
}
