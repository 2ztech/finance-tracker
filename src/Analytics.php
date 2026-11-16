<?php

declare(strict_types=1);

/**
 * Analytics query engine.
 *
 * Pure data layer for the analytics API. It never renders HTML and every
 * public method returns JSON-safe arrays. All SQL is prepared.
 */
final class Analytics
{
    public const CURRENCY = 'MYR';
    public const MAX_MONTHS = 120;

    /** @return array<int, array{value:string,label:string}> */
    public static function ranges(): array
    {
        return [
            ['value' => 'this_month', 'label' => 'This month'],
            ['value' => 'last_3', 'label' => 'Last 3 months'],
            ['value' => 'last_6', 'label' => 'Last 6 months'],
            ['value' => 'last_12', 'label' => 'Last 12 months'],
            ['value' => 'custom', 'label' => 'Custom range'],
        ];
    }

    /**
     * Resolve and validate the requested date range.
     *
     * @return array{preset:string,label:string,from:string,to:string,months:array<int,array{value:string,label:string}>}
     */
    public static function range(array $query): array
    {
        $presetRaw = $query['range'] ?? 'last_6';
        if (!is_string($presetRaw)) {
            throw new InvalidArgumentException('Unknown range preset.');
        }
        $preset = trim($presetRaw);
        $valid = array_column(self::ranges(), 'value');
        if (!in_array($preset, $valid, true)) {
            throw new InvalidArgumentException('Unknown range preset.');
        }

        $today = new DateTimeImmutable('today');
        $monthStart = $today->modify('first day of this month');
        $monthEnd = $today->modify('last day of this month');

        if ($preset === 'custom') {
            $from = self::parseDate($query['from'] ?? '');
            $to = self::parseDate($query['to'] ?? '');
            if ($from > $to) {
                throw new InvalidArgumentException('Range start must be on or before range end.');
            }
            $label = $from->format('j M Y') . ' – ' . $to->format('j M Y');
        } else {
            $monthsBack = match ($preset) {
                'this_month' => 0,
                'last_3' => 2,
                'last_6' => 5,
                'last_12' => 11,
            };
            $from = $monthStart->modify('-' . $monthsBack . ' months');
            $to = $monthEnd;
            $label = self::rangeLabel($from, $to);
        }

        $months = [];
        $cursor = $from->modify('first day of this month');
        $last = $to->modify('first day of this month');
        while ($cursor <= $last) {
            $months[] = [
                'value' => $cursor->format('Y-m'),
                'label' => $cursor->format('M Y'),
            ];
            if (count($months) > self::MAX_MONTHS) {
                throw new InvalidArgumentException('Range cannot exceed ' . self::MAX_MONTHS . ' months.');
            }
            $cursor = $cursor->modify('+1 month');
        }

        return [
            'preset' => $preset,
            'label' => $label,
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'months' => $months,
        ];
    }

    /**
     * Resolve and validate the institution filter. 0 means all institutions.
     *
     * @return array{institution_id:int,institution_name:?string,institution_color_hex:?string}
     */
    public static function filter(array $query): array
    {
        $raw = $query['institution_id'] ?? 0;
        if ($raw === '' || $raw === null) {
            $raw = 0;
        }
        if (!is_int($raw) && !(is_string($raw) && ctype_digit($raw))) {
            throw new InvalidArgumentException('institution_id must be a non-negative integer.');
        }
        $id = (int) $raw;
        if ($id < 0) {
            throw new InvalidArgumentException('institution_id must be a non-negative integer.');
        }
        if ($id === 0) {
            return ['institution_id' => 0, 'institution_name' => null, 'institution_color_hex' => null];
        }

        $institution = Institution::find($id);
        if ($institution === null) {
            throw new InvalidArgumentException('Unknown institution.');
        }

        return [
            'institution_id' => $id,
            'institution_name' => (string) $institution['name'],
            'institution_color_hex' => (string) $institution['color_hex'],
        ];
    }

    /** @return array<int, array{id:int,name:string,color_hex:string}> */
    public static function institutions(): array
    {
        $out = [];
        foreach (Institution::all() as $institution) {
            $out[] = [
                'id' => (int) $institution['id'],
                'name' => (string) $institution['name'],
                'color_hex' => (string) $institution['color_hex'],
            ];
        }
        return $out;
    }

    /**
     * Build one widget's data.
     *
     * @param array<string, mixed> $config
     * @param array{preset:string,label:string,from:string,to:string,months:array<int,array{value:string,label:string}>} $range
     * @param array{institution_id:int,institution_name:?string,institution_color_hex:?string} $filter
     * @return array<string, mixed>
     */
    public static function build(string $key, array $config, array $range, array $filter): array
    {
        return match ($key) {
            'income_expense' => self::incomeExpense($range, $filter),
            'spending_category' => self::spendingCategory($config, $range, $filter),
            'spending_institution' => self::spendingInstitution($range, $filter),
            'top_merchants' => self::topMerchants($config, $range, $filter),
            'credit_utilization' => self::creditUtilization($range, $filter),
            'net_worth' => self::netWorth($range, $filter),
            'recurring_oneoff' => self::recurringOneOff($range, $filter),
            'savings_rate' => self::savingsRate($range, $filter),
            default => throw new InvalidArgumentException('Unknown widget key.'),
        };
    }

    /** @param array<string, mixed> $data */
    public static function isEmpty(string $key, array $data): bool
    {
        return match ($key) {
            'income_expense' => abs((float) $data['totals']['income']) + abs((float) $data['totals']['expenses']) < 0.005,
            'spending_category' => (float) $data['total'] < 0.005,
            'spending_institution' => (float) $data['total'] < 0.005,
            'top_merchants' => (float) $data['total'] < 0.005,
            'credit_utilization' => $data['items'] === [],
            'net_worth' => (int) $data['account_count'] === 0,
            'recurring_oneoff' => (float) $data['total'] < 0.005,
            'savings_rate' => abs((float) $data['totals']['income']) + abs((float) $data['totals']['expenses']) < 0.005,
            default => true,
        };
    }

    // --- Widget builders -------------------------------------------------

    /** @return array<string, mixed> */
    private static function incomeExpense(array $range, array $filter): array
    {
        $series = self::monthlySeries($range, $filter);
        $income = array_sum($series['income']);
        $expenses = array_sum($series['expenses']);

        return [
            'labels' => array_column($range['months'], 'value'),
            'display_labels' => array_column($range['months'], 'label'),
            'income' => $series['income'],
            'expenses' => $series['expenses'],
            'totals' => [
                'income' => round((float) $income, 2),
                'expenses' => round((float) $expenses, 2),
                'net' => round((float) $income - (float) $expenses, 2),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function spendingCategory(array $config, array $range, array $filter): array
    {
        $db = Database::getConnection();
        $where = self::transactionWhere($range, $filter);
        $top = self::topValue($config['top'] ?? 8);

        $sql = "
            SELECT t.category_id,
                   COALESCE(c.name, 'Uncategorized') AS name,
                   COALESCE(c.color_hex, '#94a3b8') AS color_hex,
                   ROUND(SUM(t.amount), 2) AS total
            FROM transactions t
            LEFT JOIN categories c ON c.id = t.category_id
            WHERE t.type = 'expense' AND {$where['sql']}
            GROUP BY t.category_id
            ORDER BY total DESC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($where['params']);
        $rows = $stmt->fetchAll();

        $total = 0.0;
        $items = [];
        foreach ($rows as $row) {
            $amount = round((float) $row['total'], 2);
            $total += $amount;
            $items[] = [
                'category_id' => $row['category_id'] !== null ? (int) $row['category_id'] : null,
                'name' => (string) $row['name'],
                'color_hex' => (string) $row['color_hex'],
                'total' => $amount,
            ];
        }
        $total = round($total, 2);
        $topItems = array_slice($items, 0, $top);
        $topTotal = array_sum(array_column($topItems, 'total'));
        foreach ($topItems as &$item) {
            $item['share_pct'] = $total > 0 ? round(((float) $item['total'] / $total) * 100, 2) : 0.0;
        }
        unset($item);

        return [
            'items' => $topItems,
            'total' => $total,
            'other_total' => round(max(0.0, $total - (float) $topTotal), 2),
        ];
    }

    /** @return array<string, mixed> */
    private static function spendingInstitution(array $range, array $filter): array
    {
        $db = Database::getConnection();
        $params = [$range['from'], $range['to']];
        $sectionMode = (int) $filter['institution_id'] > 0;

        if ($sectionMode) {
            $sql = "
                SELECT a.institution_id,
                       a.id AS account_id,
                       a.name AS name,
                       a.color_hex AS color_hex,
                       i.name AS institution_name,
                       ROUND(SUM(t.amount), 2) AS total
                FROM transactions t
                JOIN accounts a ON a.id = t.account_id
                LEFT JOIN institutions i ON i.id = a.institution_id
                WHERE t.type = 'expense' AND t.date >= ? AND t.date <= ?
                  AND a.institution_id = ?
                GROUP BY a.id
                ORDER BY total DESC
            ";
            $params[] = (int) $filter['institution_id'];
        } else {
            $sql = "
                SELECT a.institution_id,
                       NULL AS account_id,
                       COALESCE(i.name, 'Other') AS name,
                       COALESCE(i.color_hex, '#64748b') AS color_hex,
                       COALESCE(i.name, 'Other') AS institution_name,
                       ROUND(SUM(t.amount), 2) AS total
                FROM transactions t
                JOIN accounts a ON a.id = t.account_id
                LEFT JOIN institutions i ON i.id = a.institution_id
                WHERE t.type = 'expense' AND t.date >= ? AND t.date <= ?
                GROUP BY a.institution_id
                ORDER BY total DESC
            ";
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $items = [];
        $total = 0.0;
        foreach ($rows as $row) {
            $amount = round((float) $row['total'], 2);
            $total += $amount;
            $items[] = [
                'institution_id' => $row['institution_id'] !== null ? (int) $row['institution_id'] : 0,
                'account_id' => $row['account_id'] !== null ? (int) $row['account_id'] : null,
                'name' => (string) $row['name'],
                'institution_name' => (string) $row['institution_name'],
                'color_hex' => (string) $row['color_hex'],
                'total' => $amount,
            ];
        }
        $total = round($total, 2);
        foreach ($items as &$item) {
            $item['share_pct'] = $total > 0 ? round(((float) $item['total'] / $total) * 100, 2) : 0.0;
        }
        unset($item);

        return [
            'mode' => $sectionMode ? 'section' : 'institution',
            'items' => $items,
            'total' => $total,
        ];
    }

    /** @return array<string, mixed> */
    private static function topMerchants(array $config, array $range, array $filter): array
    {
        $db = Database::getConnection();
        $where = self::transactionWhere($range, $filter);
        $top = self::topValue($config['top'] ?? 8);

        $sql = "
            SELECT MAX(TRIM(t.description)) AS name,
                   ROUND(SUM(t.amount), 2) AS total,
                   COUNT(*) AS tx_count
            FROM transactions t
            WHERE t.type = 'expense'
              AND TRIM(COALESCE(t.description, '')) <> ''
              AND {$where['sql']}
            GROUP BY LOWER(TRIM(t.description))
            ORDER BY total DESC
            LIMIT {$top}
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($where['params']);
        $rows = $stmt->fetchAll();

        $totalSql = "
            SELECT ROUND(COALESCE(SUM(t.amount), 0), 2)
            FROM transactions t
            WHERE t.type = 'expense'
              AND TRIM(COALESCE(t.description, '')) <> ''
              AND {$where['sql']}
        ";
        $totalStmt = $db->prepare($totalSql);
        $totalStmt->execute($where['params']);
        $total = (float) $totalStmt->fetchColumn();

        $items = [];
        $itemsTotal = 0.0;
        foreach ($rows as $row) {
            $amount = round((float) $row['total'], 2);
            $itemsTotal += $amount;
            $items[] = [
                'name' => (string) $row['name'],
                'total' => $amount,
                'count' => (int) $row['tx_count'],
                'share_pct' => $total > 0 ? round(($amount / $total) * 100, 2) : 0.0,
            ];
        }

        return [
            'items' => $items,
            'total' => round($total, 2),
            'other_total' => round(max(0.0, $total - $itemsTotal), 2),
        ];
    }

    /** @return array<string, mixed> */
    private static function creditUtilization(array $range, array $filter): array
    {
        $institutionId = (int) $filter['institution_id'];
        $accounts = Account::all(false, $institutionId > 0 ? $institutionId : null);
        $institutions = [];
        foreach (Institution::all(true) as $institution) {
            $institutions[(int) $institution['id']] = $institution;
        }

        $asOf = $range['to'];
        $items = [];
        $totalLimit = 0.0;
        $totalOutstanding = 0.0;

        foreach ($accounts as $account) {
            if (!in_array((string) $account['kind'], ['credit', 'paylater'], true)) {
                continue;
            }
            $limit = (float) ($account['credit_limit'] ?? 0);
            if ($limit <= 0) {
                continue;
            }
            $outstanding = max(0.0, Account::balance((int) $account['id'], $asOf));
            $available = max(0.0, $limit - $outstanding);
            $usedPct = round(($outstanding / $limit) * 100, 2);
            $institution = $institutions[(int) ($account['institution_id'] ?? 0)] ?? null;
            $items[] = [
                'account_id' => (int) $account['id'],
                'institution_id' => $account['institution_id'] !== null ? (int) $account['institution_id'] : 0,
                'account_name' => (string) $account['name'],
                'institution_name' => $institution !== null ? (string) $institution['name'] : null,
                'color_hex' => (string) ($account['color_hex'] ?? '#64748b'),
                'limit' => round($limit, 2),
                'outstanding' => round($outstanding, 2),
                'available' => round($available, 2),
                'used_pct' => $usedPct,
                'over_limit' => $outstanding > $limit,
            ];
            $totalLimit += $limit;
            $totalOutstanding += $outstanding;
        }

        usort($items, static fn (array $a, array $b): int => $b['outstanding'] <=> $a['outstanding']);

        return [
            'as_of' => $asOf,
            'items' => $items,
            'totals' => [
                'limit' => round($totalLimit, 2),
                'outstanding' => round($totalOutstanding, 2),
                'available' => round(max(0.0, $totalLimit - $totalOutstanding), 2),
                'used_pct' => $totalLimit > 0 ? round(($totalOutstanding / $totalLimit) * 100, 2) : 0.0,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function netWorth(array $range, array $filter): array
    {
        $institutionId = (int) $filter['institution_id'];
        $accounts = Account::all(false, $institutionId > 0 ? $institutionId : null);
        $labels = array_column($range['months'], 'value');
        $displayLabels = array_column($range['months'], 'label');
        $monthCount = count($labels);

        if ($accounts === []) {
            return [
                'labels' => $labels,
                'display_labels' => $displayLabels,
                'values' => array_fill(0, $monthCount, 0.0),
                'change' => 0.0,
                'change_pct' => null,
                'account_count' => 0,
            ];
        }

        $db = Database::getConnection();
        $ids = array_map(static fn (array $a): int => (int) $a['id'], $accounts);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        // Natural monthly movement per account: income - expense + transfers in - out.
        $nets = [];
        $txSql = "
            SELECT t.account_id,
                   substr(t.date, 1, 7) AS ym,
                   SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE -t.amount END) AS net
            FROM transactions t
            WHERE t.date <= ? AND t.account_id IN ({$placeholders})
            GROUP BY t.account_id, substr(t.date, 1, 7)
        ";
        $txParams = array_merge([$range['to']], $ids);
        $txStmt = $db->prepare($txSql);
        $txStmt->execute($txParams);
        foreach ($txStmt->fetchAll() as $row) {
            $nets[(int) $row['account_id']][(string) $row['ym']] =
                ($nets[(int) $row['account_id']][(string) $row['ym']] ?? 0.0) + (float) $row['net'];
        }

        $trSql = "
            SELECT account_id, ym, SUM(net) AS net
            FROM (
                SELECT from_account_id AS account_id, substr(date, 1, 7) AS ym, -amount AS net
                FROM transfers
                WHERE date <= ? AND from_account_id IN ({$placeholders})
                UNION ALL
                SELECT to_account_id AS account_id, substr(date, 1, 7) AS ym, amount AS net
                FROM transfers
                WHERE date <= ? AND to_account_id IN ({$placeholders})
            )
            GROUP BY account_id, ym
        ";
        $trParams = array_merge([$range['to']], $ids, [$range['to']], $ids);
        $trStmt = $db->prepare($trSql);
        $trStmt->execute($trParams);
        foreach ($trStmt->fetchAll() as $row) {
            $nets[(int) $row['account_id']][(string) $row['ym']] =
                ($nets[(int) $row['account_id']][(string) $row['ym']] ?? 0.0) + (float) $row['net'];
        }

        $values = array_fill(0, $monthCount, 0.0);
        foreach ($accounts as $account) {
            $accountId = (int) $account['id'];
            $opening = (float) ($account['opening_balance'] ?? 0);
            // Account::balance() starts savings at start_month; liabilities have no start.
            $startMonth = ($account['kind'] === 'savings' && !empty($account['start_month']))
                ? (string) $account['start_month']
                : null;

            $accountNets = $nets[$accountId] ?? [];
            ksort($accountNets);
            $keys = array_keys($accountNets);
            $keyIndex = 0;
            $keyCount = count($keys);
            $cumulative = 0.0;

            foreach ($labels as $index => $ym) {
                while ($keyIndex < $keyCount && (string) $keys[$keyIndex] <= $ym) {
                    $key = (string) $keys[$keyIndex];
                    if ($startMonth === null || $key >= $startMonth) {
                        $cumulative += (float) $accountNets[$key];
                    }
                    $keyIndex++;
                }
                // Natural ledger position: assets add it, liabilities subtract
                // their owed balance (which is the negated natural position).
                $values[$index] += $opening + $cumulative;
            }
        }

        $values = array_map(static fn (float $v): float => round($v, 2), $values);
        $first = $values[0] ?? 0.0;
        $last = $values[$monthCount - 1] ?? 0.0;
        $change = round($last - $first, 2);

        return [
            'labels' => $labels,
            'display_labels' => $displayLabels,
            'values' => $values,
            'change' => $change,
            'change_pct' => abs($first) > 0.00001 ? round(($change / abs($first)) * 100, 2) : null,
            'account_count' => count($accounts),
        ];
    }

    /** @return array<string, mixed> */
    private static function recurringOneOff(array $range, array $filter): array
    {
        $db = Database::getConnection();
        $where = self::transactionWhere($range, $filter);

        $sql = "
            SELECT
                ROUND(COALESCE(SUM(CASE WHEN t.commitment_id IS NULL THEN t.amount ELSE 0 END), 0), 2) AS one_off,
                ROUND(COALESCE(SUM(CASE WHEN t.commitment_id IS NOT NULL THEN t.amount ELSE 0 END), 0), 2) AS recurring,
                COALESCE(SUM(CASE WHEN t.commitment_id IS NULL THEN 1 ELSE 0 END), 0) AS one_off_count,
                COALESCE(SUM(CASE WHEN t.commitment_id IS NOT NULL THEN 1 ELSE 0 END), 0) AS recurring_count
            FROM transactions t
            WHERE t.type = 'expense' AND {$where['sql']}
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($where['params']);
        $row = $stmt->fetch() ?: [];

        $recurring = round((float) ($row['recurring'] ?? 0), 2);
        $oneOff = round((float) ($row['one_off'] ?? 0), 2);

        return [
            'recurring' => $recurring,
            'one_off' => $oneOff,
            'recurring_count' => (int) ($row['recurring_count'] ?? 0),
            'one_off_count' => (int) ($row['one_off_count'] ?? 0),
            'total' => round($recurring + $oneOff, 2),
        ];
    }

    /** @return array<string, mixed> */
    private static function savingsRate(array $range, array $filter): array
    {
        $series = self::monthlySeries($range, $filter);
        $rates = [];
        foreach ($series['income'] as $index => $income) {
            $income = (float) $income;
            $expenses = (float) $series['expenses'][$index];
            $rates[] = $income > 0 ? round((($income - $expenses) / $income) * 100, 2) : null;
        }

        $totalIncome = array_sum($series['income']);
        $totalExpenses = array_sum($series['expenses']);

        return [
            'labels' => array_column($range['months'], 'value'),
            'display_labels' => array_column($range['months'], 'label'),
            'income' => $series['income'],
            'expenses' => $series['expenses'],
            'rate_pct' => $rates,
            'totals' => [
                'income' => round((float) $totalIncome, 2),
                'expenses' => round((float) $totalExpenses, 2),
                'rate_pct' => $totalIncome > 0
                    ? round((((float) $totalIncome - (float) $totalExpenses) / (float) $totalIncome) * 100, 2)
                    : null,
            ],
        ];
    }

    // --- Helpers ---------------------------------------------------------

    /**
     * @return array{sql:string,params:array<int, mixed>}
     */
    private static function transactionWhere(array $range, array $filter): array
    {
        $sql = 't.date >= ? AND t.date <= ?';
        $params = [$range['from'], $range['to']];
        if ((int) $filter['institution_id'] > 0) {
            $sql .= ' AND t.account_id IN (SELECT id FROM accounts WHERE institution_id = ?)';
            $params[] = (int) $filter['institution_id'];
        }
        return ['sql' => $sql, 'params' => $params];
    }

    /**
     * @return array{income:array<int, float>, expenses:array<int, float>}
     */
    private static function monthlySeries(array $range, array $filter): array
    {
        $db = Database::getConnection();
        $where = self::transactionWhere($range, $filter);
        $sql = "
            SELECT substr(t.date, 1, 7) AS ym,
                   COALESCE(SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE 0 END), 0) AS income,
                   COALESCE(SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END), 0) AS expenses
            FROM transactions t
            WHERE {$where['sql']}
            GROUP BY substr(t.date, 1, 7)
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($where['params']);
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[(string) $row['ym']] = [
                'income' => (float) $row['income'],
                'expenses' => (float) $row['expenses'],
            ];
        }

        $income = [];
        $expenses = [];
        foreach ($range['months'] as $month) {
            $ym = (string) $month['value'];
            $income[] = round((float) ($rows[$ym]['income'] ?? 0), 2);
            $expenses[] = round((float) ($rows[$ym]['expenses'] ?? 0), 2);
        }

        return ['income' => $income, 'expenses' => $expenses];
    }

    private static function topValue(mixed $value): int
    {
        $top = (int) $value;
        return in_array($top, [5, 8, 10], true) ? $top : 8;
    }

    private static function parseDate(mixed $value): DateTimeImmutable
    {
        $text = is_string($value) ? trim($value) : '';
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $text);
        if ($date === false || $date->format('Y-m-d') !== $text) {
            throw new InvalidArgumentException('Dates must use YYYY-MM-DD.');
        }
        return $date;
    }

    private static function rangeLabel(DateTimeImmutable $from, DateTimeImmutable $to): string
    {
        if ($from->format('Y-m') === $to->format('Y-m')) {
            return $from->format('M Y');
        }
        if ($from->format('Y') === $to->format('Y')) {
            return $from->format('M') . ' – ' . $to->format('M Y');
        }
        return $from->format('M Y') . ' – ' . $to->format('M Y');
    }
}
