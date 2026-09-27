<?php
require_once __DIR__ . '/../src/Account.php';
require_once __DIR__ . '/../src/Expense.php';
require_once __DIR__ . '/../src/Budget.php';
require_once __DIR__ . '/../src/Bill.php';

$m = Helper::parseMonth();
$year = $m['year'];
$month = $m['month'];
$reqMonth = $m['reqMonth'];
$prevMonth = $m['prevMonth'];
$nextMonth = $m['nextMonth'];
$currentDisplay = $m['currentDisplay'];
$activeAccount = Account::active();
$activeAccountId = $activeAccount ? (int) $activeAccount['id'] : null;
$activeLiability = $activeAccount ? Account::isLiability($activeAccount) : false;
$accounts = Account::all();
$netWorth = Account::netWorth();
$rangeStart = "$year-$month-01";
$rangeEnd = date('Y-m-t', strtotime($rangeStart));
$prevStart = date('Y-m-01', strtotime($m['prevMonth'] . '-01'));
$prevEnd = date('Y-m-t', strtotime($m['prevMonth'] . '-01'));
$activeMovements = $activeAccountId ? Account::movements($activeAccountId, $rangeStart, $rangeEnd) : ['income' => 0, 'expense' => 0, 'in' => 0];
$previousMovements = $activeAccountId ? Account::movements($activeAccountId, $prevStart, $prevEnd) : ['income' => 0, 'expense' => 0, 'in' => 0];
$activeBalance = $activeAccountId ? Account::balance($activeAccountId) : 0.0;
$income = $activeMovements['income'];
$expenses = $activeMovements['expense'];
$prevIncome = $previousMovements['income'];
$prevExpenses = $previousMovements['expense'];
$prevNet = $prevIncome - $prevExpenses;
$net = $income - $expenses;
$budgets = Budget::getAll();
$spending = Expense::getExpensesByCategory($month, $year, $activeAccountId);
$previousSpending = Expense::getExpensesByCategory(substr($m['prevMonth'], 5, 2), substr($m['prevMonth'], 0, 4), $activeAccountId);
$budgetSpending = Expense::getExpensesByCategory($month, $year, $activeAccountId);
$spendByCategory = [];
foreach ($spending as $row) $spendByCategory[(int) $row['category_id']] = (float) $row['total'];
$previousByCategory = [];
$previousCategoryNames = [];
foreach ($previousSpending as $row) {
    $previousByCategory[(int) $row['category_id']] = (float) $row['total'];
    $previousCategoryNames[(int) $row['category_id']] = (string)$row['name'];
}
$totalExpense = array_sum(array_column($spending, 'total'));
$budgetSpendByCategory = [];
foreach ($budgetSpending as $row) $budgetSpendByCategory[(int)$row['category_id']] = (float)$row['total'];

$db = Database::getConnection();
$accountFilter = $activeAccountId !== null ? ' AND t.account_id = ?' : '';
$recentStmt = $db->prepare("SELECT t.*, c.name AS category_name, c.color_hex, c.icon_key, c.icon_data, c.icon_mime, a.name AS account_name FROM transactions t LEFT JOIN categories c ON c.id=t.category_id LEFT JOIN accounts a ON a.id=t.account_id WHERE t.date >= ? AND t.date <= ?$accountFilter ORDER BY t.date DESC, t.id DESC LIMIT 6");
$recentStmt->execute($activeAccountId !== null ? [$rangeStart, $rangeEnd, $activeAccountId] : [$rangeStart, $rangeEnd]);
$recent = $recentStmt->fetchAll();

$chartMonths = [];
$chartIncome = [];
$chartExpenses = [];
$chartAccountFilter = $activeAccountId !== null ? ' AND account_id = ?' : '';
$chartStmt = $db->prepare("SELECT COALESCE(SUM(CASE WHEN type='income' THEN amount ELSE 0 END),0) income, COALESCE(SUM(CASE WHEN type='expense' THEN amount ELSE 0 END),0) expenses FROM transactions WHERE date >= ? AND date <= ?$chartAccountFilter");
for ($i = 8; $i >= 0; $i--) {
    $stamp = strtotime("first day of -$i months", strtotime($rangeStart));
    $from = date('Y-m-01', $stamp);
    $to = date('Y-m-t', $stamp);
    $chartStmt->execute($activeAccountId !== null ? [$from, $to, $activeAccountId] : [$from, $to]);
    $r = $chartStmt->fetch(PDO::FETCH_ASSOC);
    $chartMonths[] = date('M', $stamp);
    $chartIncome[] = round((float) $r['income'], 2);
    $chartExpenses[] = round((float) $r['expenses'], 2);
}
$hasChartData = array_sum($chartIncome) + array_sum($chartExpenses) > 0;

$upcoming = [];
$upcomingStart = date('Y-m-d');
$upcomingEnd = date('Y-m-d', strtotime($upcomingStart . ' +45 days'));
if ($activeAccountId) {
    if ($activeLiability) {
        Bill::sync($activeAccountId);
        $billStmt = $db->prepare("SELECT b.account_id, b.due_date, b.amount_due, b.paid_amount, a.name AS account_name, 0 AS is_commitment FROM bills b JOIN accounts a ON a.id=b.account_id WHERE b.account_id = ? AND b.status <> 'paid' AND b.due_date <= ? ORDER BY b.due_date ASC LIMIT 5");
        $billStmt->execute([$activeAccountId, $upcomingEnd]);
        $upcoming = $billStmt->fetchAll();
    }

    // Recurring expense commitments are forecasts/reminders, not bill-table rows.
    // Include their next due occurrence for the selected account, including savings accounts.
    $commitments = Expense::getCommitments($activeAccountId);

    // Months already posted for a schedule are hidden from "Upcoming".
    $postedStmt = $db->prepare("SELECT commitment_id, commitment_period FROM transactions WHERE account_id = ? AND commitment_period IS NOT NULL");
    $postedStmt->execute([$activeAccountId]);
    $postedPeriods = [];
    foreach ($postedStmt->fetchAll() as $pr) {
        $postedPeriods[(int) $pr['commitment_id'] . '|' . $pr['commitment_period']] = true;
    }

    $today = new DateTimeImmutable($upcomingStart);
    $monthStart = $today->modify('first day of this month');
    for ($offset = 0; $offset <= 2; $offset++) {
        $scheduleMonth = $monthStart->modify('+' . $offset . ' months');
        $dueDayLimit = (int)$scheduleMonth->format('t');
        foreach ($commitments as $commitment) {
            if (($commitment['type'] ?? 'expense') !== 'expense') continue;
            $dueDay = min(max(1, (int)$commitment['due_date_day']), $dueDayLimit);
            $dueDate = $scheduleMonth->setDate((int)$scheduleMonth->format('Y'), (int)$scheduleMonth->format('m'), $dueDay);
            $dueDateString = $dueDate->format('Y-m-d');
            if ($dueDateString < $upcomingStart || $dueDateString > $upcomingEnd) continue;
            if (!empty($commitment['start_date']) && $dueDateString < $commitment['start_date']) continue;
            if (!empty($commitment['end_date']) && $dueDateString > $commitment['end_date']) continue;
            if (isset($postedPeriods[(int) $commitment['id'] . '|' . substr($dueDateString, 0, 7)])) continue;
            $upcoming[] = [
                'account_id' => $activeAccountId,
                'due_date' => $dueDateString,
                'amount_due' => (float)$commitment['amount'],
                'paid_amount' => 0.0,
                'account_name' => (string)$commitment['name'],
                'is_commitment' => 1,
            ];
        }
    }
    usort($upcoming, static fn(array $a, array $b): int => strcmp((string)$a['due_date'], (string)$b['due_date']));
    $upcoming = array_slice($upcoming, 0, 5);
}

$insightCandidates = [];
$currentByCategory = [];
foreach ($spending as $row) $currentByCategory[(int)$row['category_id']] = $row;
foreach (array_unique(array_merge(array_keys($currentByCategory), array_keys($previousByCategory))) as $categoryId) {
    $currentRow = $currentByCategory[$categoryId] ?? null;
    $current = (float)($currentRow['total'] ?? 0);
    $previous = (float)($previousByCategory[$categoryId] ?? 0);
    $name = (string)($currentRow['name'] ?? $previousCategoryNames[$categoryId] ?? 'Category');
    $delta = $current - $previous;
    if ($delta >= 25 && ($previous == 0.0 ? $current >= 50 : $delta / $previous >= .15)) {
        $change = $previous == 0.0
            ? 'New this month · RM ' . number_format($current, 2) . ' spent.'
            : number_format(($delta / $previous) * 100, 0) . '% higher than last month · RM ' . number_format($current, 2) . ' spent.';
        $insightCandidates[] = ['title' => $name . ' spending increased', 'text' => $change, 'score' => 72 + min(25, $delta / 80)];
    } elseif ($delta <= -25 && $previous > 0 && abs($delta) / $previous >= .15) {
        $insightCandidates[] = ['title' => 'You spent less on ' . $name, 'text' => number_format((abs($delta) / $previous) * 100, 0) . '% lower than last month · RM ' . number_format(abs($delta), 2) . ' less.', 'score' => 58 + min(25, abs($delta) / 100)];
    }
}

foreach ($budgets as $budgetRow) {
    $spent = (float)($budgetSpendByCategory[(int)$budgetRow['category_id']] ?? 0);
    $limit = (float)$budgetRow['amount'];
    if ($limit <= 0) continue;
    $used = $spent / $limit;
    if ($used >= 1) {
        $insightCandidates[] = ['title' => $budgetRow['category_name'] . ' is over budget', 'text' => number_format(($used - 1) * 100, 0) . '% over · RM ' . number_format($spent - $limit, 2) . ' above the RM ' . number_format($limit, 2) . ' limit.', 'score' => 125 + min(30, ($used - 1) * 30)];
    } elseif ($used >= .8) {
        $insightCandidates[] = ['title' => $budgetRow['category_name'] . ' budget is nearly used', 'text' => number_format($used * 100, 0) . '% used with RM ' . number_format($limit - $spent, 2) . ' remaining.', 'score' => 82 + $used * 10];
    }
}

$expenseChange = $expenses - $prevExpenses;
if (abs($expenseChange) >= 50 && ($prevExpenses == 0.0 || abs($expenseChange) / $prevExpenses >= .15)) {
    $insightCandidates[] = [
        'title' => $expenseChange > 0 ? 'Monthly expenses are up' : 'Monthly expenses are down',
        'text' => ($prevExpenses > 0 ? number_format(abs($expenseChange) / $prevExpenses * 100, 0) . '% ' : '')
            . ($expenseChange > 0 ? 'higher' : 'lower') . ' than last month · RM ' . number_format(abs($expenseChange), 2) . ' difference.',
        'score' => 64 + min(25, abs($expenseChange) / 150),
    ];
}
if ($income > 0 || $expenses > 0) {
    $insightCandidates[] = [
        'title' => $net >= 0 ? 'Income is ahead this month' : 'Expenses are ahead this month',
        'text' => ($net >= 0 ? 'Income is ahead of expenses' : 'Expenses are ahead of income') . ' by RM ' . number_format(abs($net), 2) . ' for ' . $currentDisplay . '.',
        'score' => 52 + min(20, abs($net) / 250),
    ];
}
usort($insightCandidates, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
$insights = array_slice($insightCandidates, 0, 3);

function dashboardDelta(float $current, float $previous): string {
    if ($previous == 0.0) return $current > 0 ? 'New this month' : 'No change';
    $pct = (($current - $previous) / abs($previous)) * 100;
    return ($pct >= 0 ? '+' : '') . number_format($pct, 1) . '% vs last month';
}

function dashboardMerchantMark(string $description, string $category, bool $income): array {
    $name = strtolower($description);
    $marks = [
        'grab' => ['G', '#00a86b', '#e7f8ef'],
        'shopee' => ['S', '#ee4d2d', '#fff0eb'],
        'shell' => ['S', '#e23b35', '#fff4d6'],
        'tng' => ['T', '#1749b8', '#eaf1ff'],
        'salary' => ['↗', '#2774d8', '#eaf2ff'],
        'maybank' => ['M', '#e8b600', '#fff8d8'],
        'cimb' => ['C', '#df2935', '#ffedef'],
    ];
    foreach ($marks as $needle => $mark) if (str_contains($name, $needle)) return $mark;
    $category = strtolower($category);
    $categoryMarks = [
        'salary' => ['↑', '#159b7e', '#e7f8f3'],
        'food' => ['F', '#e34f52', '#ffeded'],
        'grocer' => ['G', '#689d18', '#eff8dd'],
        'shopping' => ['S', '#df6b34', '#fff0e8'],
        'fuel' => ['P', '#db7425', '#fff3e5'],
        'transport' => ['T', '#397dcc', '#eaf2ff'],
        'motorcycle' => ['M', '#64748b', '#edf1f6'],
        'entertainment' => ['E', '#7056c8', '#f0edff'],
        'utilities' => ['U', '#b58413', '#fff8d8'],
        'subscription' => ['▶', '#7959c9', '#f0ecff'],
        'health' => ['+', '#d24c91', '#ffedf6'],
    ];
    foreach ($categoryMarks as $needle => $mark) if (str_contains($category, $needle)) return $mark;
    return $income ? ['+', '#159b7e', '#e7f8f3'] : ['−', '#e34f52', '#fdecec'];
}

ob_start();
?>
<div class="dashboard-screen">
<div class="dashboard-heading flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h2 class="text-3xl font-bold" style="color:var(--text);">Dashboard</h2>
        <p class="mt-1 text-sm" style="color:var(--text-secondary);"><?= $activeAccount ? htmlspecialchars((string)$activeAccount['name'], ENT_QUOTES, 'UTF-8') . ' · ' : '' ?>Your financial overview for <?= htmlspecialchars((string) $currentDisplay, ENT_QUOTES, 'UTF-8') ?>.</p>
    </div>
    <div class="flex items-center rounded-lg border p-1 text-sm" style="background:var(--bg-alt);border-color:var(--border);">
        <a aria-label="Previous month" href="?month=<?= htmlspecialchars((string)$prevMonth, ENT_QUOTES, 'UTF-8') ?>" class="rounded-md px-3 py-2" style="color:var(--text-secondary);">‹</a>
        <span class="min-w-[132px] text-center font-semibold" style="color:var(--text);"><?= htmlspecialchars((string)$currentDisplay, ENT_QUOTES, 'UTF-8') ?></span>
        <a aria-label="Next month" href="?month=<?= htmlspecialchars((string)$nextMonth, ENT_QUOTES, 'UTF-8') ?>" class="rounded-md px-3 py-2" style="color:var(--text-secondary);">›</a>
    </div>
</div>

<?php $primaryId = Account::primaryId(); ?>
<?php if ($primaryId !== null): $primaryAccount = Account::find($primaryId); $eom = Expense::projectedEomBreakdown($primaryId, $month, $year); $projectedEOM = $eom['eom']; ?>
<section class="rounded-xl border p-4" aria-label="Projected end of month" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-xs font-medium uppercase tracking-wide" style="color:var(--text-muted);">Projected end of month</p>
            <p class="mt-1 text-2xl font-bold" style="color:var(--text);">RM <?= number_format($projectedEOM, 2) ?></p>
            <p class="mt-0.5 text-xs" style="color:var(--text-muted);"><?= htmlspecialchars((string)($primaryAccount['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?> · by <?= date('j M Y', strtotime((string) $eom['month_end'])) ?></p>
            <div class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px]" style="color:var(--text-secondary);">
                <span class="rounded px-1.5 py-0.5" style="background:var(--bg-hover);">RM <?= number_format($eom['balance'], 2) ?> balance</span>
                <?php if ($eom['recurring_income'] > 0): ?><span>+RM <?= number_format($eom['recurring_income'], 2) ?> recurring in</span><?php endif; ?>
                <?php if ($eom['recurring_expense'] > 0): ?><span>−RM <?= number_format($eom['recurring_expense'], 2) ?> recurring out</span><?php endif; ?>
                <?php foreach ($eom['bills_by_account'] as $billAccount => $billAmount): ?>
                    <span>−RM <?= number_format($billAmount, 2) ?> <?= htmlspecialchars((string) $billAccount, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endforeach; ?>
                <span class="font-semibold" style="color:var(--text);">= RM <?= number_format($eom['eom'], 2) ?></span>
            </div>
        </div>
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl" style="background:var(--accent-soft);color:var(--accent);">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 17l5-5 4 3 7-8M15 7h5v5"/></svg>
        </span>
    </div>
</section>
<?php endif; ?>

<section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Financial summary">
    <?php
    $metrics = [
        [$activeLiability ? 'Outstanding' : 'Balance', $activeBalance, $activeAccount ? ($activeLiability ? 'Currently owed' : 'Available now') : 'Create an account to begin', $activeLiability ? 'var(--expense)' : 'var(--text)', 'var(--accent-soft)'],
        [$activeLiability ? 'Purchases' : 'Income', $activeLiability ? $expenses : $income, dashboardDelta($activeLiability ? $expenses : $income, $activeLiability ? $prevExpenses : $prevIncome), $activeLiability ? 'var(--expense)' : 'var(--income)', 'var(--success-soft)'],
        [$activeLiability ? 'Payments' : 'Expenses', $activeLiability ? $activeMovements['in'] : $expenses, $activeLiability ? 'Paid this month' : dashboardDelta($expenses, $prevExpenses), $activeLiability ? 'var(--income)' : 'var(--expense)', 'var(--danger-soft)'],
        ['Net Worth', $netWorth, 'Across all accounts', 'var(--purple)', 'var(--purple-soft)'],
    ];
    foreach ($metrics as [$label, $value, $note, $color, $soft]): ?>
    <article class="metric-card flex items-center gap-3 rounded-xl border p-4" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
        <span class="metric-icon flex h-10 w-10 shrink-0 items-center justify-center rounded-xl" style="background:<?= $soft ?>;color:<?= $color ?>;">
            <?php if ($label === 'Net Worth'): ?><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 17l5-5 4 3 7-8M15 7h5v5"/></svg>
            <?php elseif (str_contains($label, 'Income') || $label === 'Payments'): ?><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 19V5m0 0L6 11m6-6 6 6"/></svg>
            <?php elseif (str_contains($label, 'Expenses') || $label === 'Purchases'): ?><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 5v14m0 0 6-6m-6 6-6-6"/></svg>
            <?php else: ?><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M3 8h18v11H3zM3 8l2-4h14l2 4m-18 0h18m-13 5h4"/></svg><?php endif; ?>
        </span>
        <div class="min-w-0"><p class="text-xs font-medium" style="color:var(--text-secondary);"><?= $label ?></p>
        <p class="mt-0.5 text-xl font-bold tracking-tight" style="color:<?= $color ?>;">RM <?= number_format((float)$value, 2) ?></p>
        <p class="mt-0.5 truncate text-[11px]" style="color:var(--text-muted);"> <?= htmlspecialchars((string)$note, ENT_QUOTES, 'UTF-8') ?></p></div>
    </article>
    <?php endforeach; ?>
</section>

<div class="dashboard-grid grid grid-cols-1 gap-4 xl:grid-cols-12">
<div class="dashboard-main space-y-4 xl:col-span-9">
<div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
    <section class="rounded-xl border p-4 xl:col-span-7" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
        <div class="mb-4 flex items-center justify-between gap-3"><div><h3 class="text-base font-semibold">Income vs Expenses</h3><p class="mt-1 text-xs" style="color:var(--text-secondary);">Monthly totals · last 9 months</p></div><div class="flex gap-4 text-xs"><span><i class="legend-dot" style="background:var(--success)"></i>Income</span><span><i class="legend-dot" style="background:var(--expense)"></i>Expenses</span></div></div>
        <?php if ($hasChartData): ?><div class="h-56"><canvas id="incomeExpenseChart" aria-label="Income and expenses over nine months"></canvas></div>
        <?php else: ?><div class="flex h-56 flex-col items-center justify-center text-center"><p class="text-sm font-medium" style="color:var(--text-secondary);">No income or expenses to chart yet.</p><a href="/transactions" class="mt-2 text-sm font-semibold" style="color:var(--accent);">Add a transaction</a></div><?php endif; ?>
    </section>
    <section class="rounded-xl border p-4 xl:col-span-5" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
        <div class="mb-3"><h3 class="text-base font-semibold">Spending by Category</h3><p class="mt-1 text-xs" style="color:var(--text-secondary);">This month</p></div>
        <?php if (!$spending): ?><div class="flex min-h-48 items-center justify-center text-sm" style="color:var(--text-muted);">No expenses recorded this month.</div>
        <?php else: ?><div class="category-content items-center gap-3"><div class="relative mx-auto h-44 w-full max-w-52"><canvas id="categoryChart" aria-label="Spending by category"></canvas><div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center"><strong class="text-sm font-bold" style="color:var(--text);">RM <?= number_format($totalExpense, 2) ?></strong><span class="mt-0.5 text-xs" style="color:var(--text-secondary);">Total Expenses</span></div></div><div class="space-y-2">
            <?php foreach (array_slice($spending, 0, 7) as $cat): $pct = $totalExpense > 0 ? ((float)$cat['total'] / $totalExpense) * 100 : 0; ?>
            <div class="flex items-center gap-2 text-xs"><span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md" style="background:color-mix(in srgb, <?= htmlspecialchars((string)$cat['color_hex'], ENT_QUOTES, 'UTF-8') ?> 14%, white);color:<?= htmlspecialchars((string)$cat['color_hex'], ENT_QUOTES, 'UTF-8') ?>;"><?php if (!empty($cat['icon_data'])): ?><img class="h-5 w-5 rounded object-contain" src="data:<?= htmlspecialchars((string)($cat['icon_mime'] ?? 'image/png'), ENT_QUOTES, 'UTF-8') ?>;base64,<?= htmlspecialchars((string)$cat['icon_data'], ENT_QUOTES, 'UTF-8') ?>" alt=""><?php else: ?><?= IconCatalog::svg((string)($cat['icon_key'] ?? 'other'), 'h-4 w-4') ?><?php endif; ?></span><span class="min-w-0 flex-1 truncate"><?= htmlspecialchars((string)$cat['name'], ENT_QUOTES, 'UTF-8') ?></span><span style="color:var(--text-muted);width:34px;text-align:right;"> <?= number_format($pct, 0) ?>%</span><b class="font-medium" style="color:var(--text);width:66px;text-align:right;">RM <?= number_format((float)$cat['total'], 2) ?></b></div>
            <?php endforeach; ?>
        </div></div><?php endif; ?>
    </section>
</div>

<div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
    <section class="overflow-hidden rounded-xl border xl:col-span-8" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
        <div class="flex items-center justify-between border-b px-5 py-4" style="border-color:var(--border-light);"><div><h3 class="text-base font-semibold">Recent Transactions</h3><p class="mt-1 text-xs" style="color:var(--text-secondary);">Latest activity for <?= htmlspecialchars((string)$currentDisplay, ENT_QUOTES, 'UTF-8') ?></p></div><a href="/transactions?month=<?= htmlspecialchars((string)$reqMonth, ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-medium" style="color:var(--accent);">View all →</a></div>
        <?php if (!$recent): ?><div class="px-5 py-12 text-center"><p class="text-sm font-medium">No transactions yet</p><p class="mt-1 text-sm" style="color:var(--text-secondary);">Add a transaction to see activity here.</p><a href="/transactions" class="mt-4 inline-block text-sm font-semibold" style="color:var(--accent);">Go to Transactions</a></div>
        <?php else: ?><div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr style="background:var(--bg-hover);color:var(--text-secondary);"><th class="px-5 py-3">Date</th><th class="px-4 py-3">Description</th><th class="px-4 py-3">Category</th><th class="hidden px-4 py-3 lg:table-cell">Account</th><th class="px-5 py-3 text-right">Amount</th></tr></thead><tbody>
            <?php foreach ($recent as $tx): $isIncome = $tx['type'] === 'income'; [$mark, $markColor, $markSoft] = dashboardMerchantMark((string)$tx['description'], (string)($tx['category_name'] ?? ''), $isIncome); ?>
            <tr class="border-t" style="border-color:var(--border-light);"><td class="whitespace-nowrap px-5 py-3 text-xs" style="color:var(--text-secondary);"> <?= date('d M', strtotime($tx['date'])) ?></td><td class="max-w-52 px-4 py-3"><div class="flex min-w-0 items-center gap-2.5"><span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-xs font-bold" style="color:<?= $markColor ?>;background:<?= $markSoft ?>;"> <?= htmlspecialchars($mark, ENT_QUOTES, 'UTF-8') ?></span><span class="truncate font-medium" style="color:var(--text);"> <?= htmlspecialchars((string)$tx['description'], ENT_QUOTES, 'UTF-8') ?></span></div></td><td class="px-4 py-3 text-xs"><span class="mr-1.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-md align-middle" style="color:<?= htmlspecialchars((string)($tx['color_hex'] ?? '#8A94A6'), ENT_QUOTES, 'UTF-8') ?>;background:color-mix(in srgb, <?= htmlspecialchars((string)($tx['color_hex'] ?? '#8A94A6'), ENT_QUOTES, 'UTF-8') ?> 14%, white);"><?php if (!empty($tx['icon_data'])): ?><img class="h-4 w-4 rounded object-contain" src="data:<?= htmlspecialchars((string)($tx['icon_mime'] ?? 'image/png'), ENT_QUOTES, 'UTF-8') ?>;base64,<?= htmlspecialchars((string)$tx['icon_data'], ENT_QUOTES, 'UTF-8') ?>" alt=""><?php else: ?><?= IconCatalog::svg((string)($tx['icon_key'] ?? 'other'), 'h-4 w-4') ?><?php endif; ?></span><?= htmlspecialchars((string)($tx['category_name'] ?? 'Uncategorized'), ENT_QUOTES, 'UTF-8') ?></td><td class="hidden px-4 py-3 text-xs lg:table-cell" style="color:var(--text-secondary);"> <?= htmlspecialchars((string)($tx['account_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td><td class="whitespace-nowrap px-5 py-3 text-right font-semibold" style="color:<?= $isIncome ? 'var(--income)' : 'var(--expense)' ?>;"> <?= $isIncome ? '+' : '−' ?>RM <?= number_format((float)$tx['amount'], 2) ?></td></tr>
            <?php endforeach; ?>
        </tbody></table></div><?php endif; ?>
    </section>

    <section class="rounded-xl border p-5 xl:col-span-4" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
        <div class="mb-4 flex items-center justify-between"><h3 class="text-base font-semibold">Accounts Overview</h3><a href="/accounts" class="text-sm font-medium" style="color:var(--accent);">See all</a></div>
        <?php if (!$accounts): ?><p class="py-8 text-center text-sm" style="color:var(--text-muted);">No accounts yet. <a href="/accounts" style="color:var(--accent);">Add an account</a></p>
        <?php else: ?><div class="divide-y" style="--tw-divide-opacity:1;divide-color:var(--border-light);">
            <?php foreach ($accounts as $a): $bal = Account::balance((int)$a['id']); $liability = Account::isLiability($a); $accountIcon = AccountIcon::for((string)$a['name'], (string)$a['kind'], (string)($a['color_hex'] ?? '')); ?>
            <div class="flex items-center justify-between gap-2 py-2.5"><div class="flex min-w-0 items-center gap-2.5"><?php if (!empty($a['icon_data'])): ?><img class="h-8 w-8 shrink-0 rounded-lg object-contain" src="data:<?= htmlspecialchars((string)($a['icon_mime'] ?? 'image/png'), ENT_QUOTES, 'UTF-8') ?>;base64,<?= htmlspecialchars((string)$a['icon_data'], ENT_QUOTES, 'UTF-8') ?>" alt=""><?php else: ?><span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-sm font-bold" style="background:<?= $accountIcon['background'] ?>;color:<?= $accountIcon['foreground'] ?>;"><?= htmlspecialchars($accountIcon['mark'], ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?><div class="min-w-0"><p class="truncate text-sm font-medium"><?= htmlspecialchars((string)$a['name'], ENT_QUOTES, 'UTF-8') ?></p><p class="mt-0.5 text-xs capitalize" style="color:var(--text-muted);"><?= htmlspecialchars((string)$a['kind'], ENT_QUOTES, 'UTF-8') ?><?= $liability ? ' · outstanding' : '' ?></p></div></div><b class="whitespace-nowrap text-sm" style="color:<?= $liability ? 'var(--expense)' : 'var(--text)' ?>;">RM <?= number_format($bal, 2) ?></b></div>
            <?php endforeach; ?>
        </div><?php endif; ?>
    </section>
</div>
</div>
<aside class="dashboard-rail space-y-4 xl:col-span-3">
    <section class="rounded-xl border p-5" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
        <div class="mb-4 flex items-center justify-between"><h3 class="text-base font-semibold">Upcoming Bills</h3><a href="<?= $activeLiability ? '/bills' : '/recurring' ?>" class="text-sm font-medium" style="color:var(--accent);">See all</a></div>
        <?php if (!$upcoming): ?><div class="py-6 text-center"><p class="text-sm font-medium">No upcoming bills</p><p class="mt-1 text-xs" style="color:var(--text-secondary);">You're clear for now.</p></div>
        <?php else: ?><div class="space-y-3"><?php foreach ($upcoming as $bill): $dueLeft = max(0, (float)$bill['amount_due'] - (float)$bill['paid_amount']); ?><div class="flex items-center justify-between gap-3"><div class="min-w-0"><p class="truncate text-sm font-medium"><?= htmlspecialchars((string)$bill['account_name'], ENT_QUOTES, 'UTF-8') ?></p><p class="mt-0.5 text-xs" style="color:var(--text-secondary);"><?= !empty($bill['is_commitment']) ? 'Recurring · ' : '' ?>Due <?= date('d M Y', strtotime($bill['due_date'])) ?></p></div><b class="whitespace-nowrap text-sm">RM <?= number_format($dueLeft, 2) ?></b></div><?php endforeach; ?></div><?php endif; ?>
    </section>
    <section class="rounded-xl border p-5" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
        <div class="mb-4 flex items-center justify-between"><div><h3 class="text-base font-semibold">Budgets</h3><p class="mt-1 text-xs" style="color:var(--text-secondary);">This month</p></div><a href="/budgets" class="text-sm font-medium" style="color:var(--accent);">See all</a></div>
        <?php if (!$budgets): ?><div class="py-5 text-center"><p class="text-sm" style="color:var(--text-secondary);">No budgets set.</p><a href="/budgets" class="mt-2 inline-block text-sm font-medium" style="color:var(--accent);">Create a budget</a></div>
        <?php else: ?><div class="space-y-4"><?php foreach (array_slice($budgets, 0, 4) as $b): $spent = $budgetSpendByCategory[(int)$b['category_id']] ?? 0; $limit=(float)$b['amount']; $pct=$limit>0?($spent/$limit)*100:0; $over=$pct>100; ?><div><div class="mb-1 flex items-center justify-between gap-2 text-xs"><span class="flex min-w-0 items-center gap-1.5 truncate font-medium"><span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md" style="color:<?= htmlspecialchars((string)$b['color_hex'], ENT_QUOTES, 'UTF-8') ?>;background:color-mix(in srgb, <?= htmlspecialchars((string)$b['color_hex'], ENT_QUOTES, 'UTF-8') ?> 14%, white);"><?php if (!empty($b['icon_data'])): ?><img class="h-4 w-4 rounded object-contain" src="data:<?= htmlspecialchars((string)($b['icon_mime'] ?? 'image/png'), ENT_QUOTES, 'UTF-8') ?>;base64,<?= htmlspecialchars((string)$b['icon_data'], ENT_QUOTES, 'UTF-8') ?>" alt=""><?php else: ?><?= IconCatalog::svg((string)($b['icon_key'] ?? 'other'), 'h-4 w-4') ?><?php endif; ?></span><?= htmlspecialchars((string)$b['category_name'], ENT_QUOTES, 'UTF-8') ?></span><span style="color:<?= $over?'var(--expense)':'var(--text-secondary)' ?>;"><?= number_format($pct,0) ?>%</span></div><div class="h-2 overflow-hidden rounded-full" style="background:var(--bg-hover);"><div class="h-full rounded-full" style="width:<?= min(100,$pct) ?>%;background:<?= $over?'var(--expense)':'var(--success)' ?>;"></div></div><p class="mt-1 text-xs" style="color:var(--text-muted);">RM <?= number_format($spent,2) ?> spent of RM <?= number_format($limit,2) ?><?= $over ? ' · over by RM '.number_format($spent-$limit,2) : ' · RM '.number_format($limit-$spent,2).' left' ?></p></div><?php endforeach; ?></div><?php endif; ?>
    </section>
    <section id="dashboardInsights" class="rounded-xl border p-3" style="height:160px;box-sizing:border-box;background:linear-gradient(115deg,var(--warning-soft),var(--accent-soft));border-color:var(--border);box-shadow:var(--shadow);" aria-label="Dashboard insights">
        <div class="flex h-full flex-col justify-between gap-2 overflow-hidden">
            <div class="flex min-w-0 items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl" style="background:var(--bg-alt);color:var(--warning);">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 18h6m-5 3h4m-2-19a7 7 0 0 0-4 12.745c.62.44 1 1.1 1 1.755h6c0-.655.38-1.315 1-1.755A7 7 0 0 0 12 2Z"/></svg>
                </span>
                <div class="min-w-0 flex-1" aria-live="off">
                    <p class="text-xs font-semibold uppercase tracking-wide" style="color:var(--text-secondary);">Insights · <?= htmlspecialchars((string)$currentDisplay, ENT_QUOTES, 'UTF-8') ?></p>
                    <div class="insight-slides mt-1" id="insightSlides">
                        <?php if ($insights): foreach ($insights as $index => $insight): ?>
                            <article class="insight-slide <?= $index === 0 ? 'is-active' : 'hidden' ?>" <?= $index === 0 ? '' : 'hidden' ?> aria-label="Insight <?= $index + 1 ?> of <?= count($insights) ?>">
                                <h3 class="insight-title-clamp text-sm font-semibold leading-5" style="color:var(--text);" title="<?= htmlspecialchars((string)$insight['title'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$insight['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                                <p class="insight-detail-clamp mt-1 text-sm leading-5" style="color:var(--text-secondary);" title="<?= htmlspecialchars((string)$insight['text'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$insight['text'], ENT_QUOTES, 'UTF-8') ?></p>
                            </article>
                        <?php endforeach; else: ?>
                            <article class="insight-slide is-active"><h3 class="text-sm font-semibold leading-5" style="color:var(--text);">You're all caught up</h3><p class="mt-1 text-sm leading-5" style="color:var(--text-secondary);">Keep tracking your finances to see personalized insights here.</p></article>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php if (count($insights) > 1): ?><div class="flex justify-center gap-2" role="group" aria-label="Choose an insight">
                <?php foreach ($insights as $index => $_insight): ?><button type="button" class="insight-dot flex h-4 items-center justify-center" data-insight-index="<?= $index ?>" aria-label="Show insight <?= $index + 1 ?>" aria-pressed="<?= $index === 0 ? 'true' : 'false' ?>"><span class="h-2 rounded-full" style="width:<?= $index === 0 ? '16px' : '8px' ?>;background:<?= $index === 0 ? 'var(--accent)' : 'var(--border)' ?>;"></span></button><?php endforeach; ?>
            </div><?php endif; ?>
        </div>
    </section>
</aside>
</div>

<script>
(function(){
    if (!window.Chart) return;
    const text = '#64748b';
    const grid = 'rgba(148,163,184,.18)';
    const trendCanvas=document.getElementById('incomeExpenseChart');
    if(trendCanvas) new Chart(trendCanvas, {type:'bar',data:{labels:<?= json_encode($chartMonths) ?>,datasets:[{label:'Income',data:<?= json_encode($chartIncome) ?>,backgroundColor:'#2DBE9B',borderRadius:4,maxBarThickness:16},{label:'Expenses',data:<?= json_encode($chartExpenses) ?>,backgroundColor:'#F05A5A',borderRadius:4,maxBarThickness:16}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{callbacks:{label:(ctx)=>ctx.dataset.label+': RM '+Number(ctx.raw).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}}},scales:{x:{grid:{display:false},ticks:{color:text}},y:{beginAtZero:true,grid:{color:grid},ticks:{color:text,callback:(v)=>'RM '+Number(v).toLocaleString()}}}}});
    const catCanvas=document.getElementById('categoryChart');
    if(catCanvas) new Chart(catCanvas,{type:'doughnut',data:{labels:<?= json_encode(array_column($spending, 'name'), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>,datasets:[{data:<?= json_encode(array_column($spending, 'total')) ?>,backgroundColor:<?= json_encode(array_column($spending, 'color_hex')) ?>,borderWidth:0,hoverOffset:3}]},options:{responsive:true,maintainAspectRatio:false,cutout:'70%',plugins:{legend:{display:false},tooltip:{callbacks:{label:(ctx)=>ctx.label+': RM '+Number(ctx.raw).toFixed(2)}}}}});
})();
</script>
<script>
(function(){
    const card = document.getElementById('dashboardInsights');
    if (!card) return;
    const slides = Array.from(card.querySelectorAll('.insight-slide'));
    if (slides.length < 2) return;
    let index = 0;
    let timer;
    let animating = false;
    const dots = Array.from(card.querySelectorAll('.insight-dot'));
    function show(next) {
        next = (next + slides.length) % slides.length;
        if (next === index || animating) return;
        const previous = slides[index];
        const incoming = slides[next];
        const direction = next > index ? 1 : -1;
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            previous.hidden = true;
            previous.classList.add('hidden');
            previous.classList.remove('is-active');
            incoming.hidden = false;
            incoming.classList.remove('hidden');
            incoming.classList.add('is-active');
        } else {
            animating = true;
            incoming.hidden = false;
            incoming.classList.remove('hidden');
            incoming.style.transition = 'none';
            incoming.style.opacity = '0';
            incoming.style.transform = 'translateX(' + (direction * 18) + 'px)';
            void incoming.offsetWidth;
            incoming.style.transition = '';
            previous.classList.remove('is-active');
            previous.style.opacity = '0';
            previous.style.transform = 'translateX(' + (-direction * 18) + 'px)';
            incoming.classList.add('is-active');
            incoming.style.opacity = '';
            incoming.style.transform = '';
            window.setTimeout(() => {
                previous.hidden = true;
                previous.classList.add('hidden');
                previous.style.opacity = '';
                previous.style.transform = '';
                animating = false;
            }, 320);
        }
        index = next;
        dots.forEach((dot, i) => {
            dot.setAttribute('aria-pressed', i === index ? 'true' : 'false');
            const pip = dot.firstElementChild;
            pip.style.background = i === index ? 'var(--accent)' : 'var(--border)';
            pip.style.width = i === index ? '16px' : '8px';
        });
    }
    function start() {
        window.clearInterval(timer);
        if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) timer = window.setInterval(() => show(index + 1), 7000);
    }
    dots.forEach((dot) => dot.addEventListener('click', () => { show(Number(dot.dataset.insightIndex)); start(); }));
    card.addEventListener('mouseenter', () => window.clearInterval(timer));
    card.addEventListener('mouseleave', start);
    card.addEventListener('focusin', () => window.clearInterval(timer));
    card.addEventListener('focusout', (event) => { if (!card.contains(event.relatedTarget)) start(); });
    start();
})();
</script>
 </div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
