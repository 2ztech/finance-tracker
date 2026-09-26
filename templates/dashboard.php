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
$budgetSpending = Expense::getExpensesByCategory($month, $year);
$spendByCategory = [];
foreach ($spending as $row) $spendByCategory[(int) $row['category_id']] = (float) $row['total'];
$previousByCategory = [];
foreach ($previousSpending as $row) $previousByCategory[(int) $row['category_id']] = (float) $row['total'];
$totalExpense = array_sum(array_column($spending, 'total'));
$budgetSpendByCategory = [];
foreach ($budgetSpending as $row) $budgetSpendByCategory[(int)$row['category_id']] = (float)$row['total'];

$db = Database::getConnection();
$accountFilter = $activeAccountId !== null ? ' AND t.account_id = ?' : '';
$recentStmt = $db->prepare("SELECT t.*, c.name AS category_name, c.color_hex, a.name AS account_name FROM transactions t LEFT JOIN categories c ON c.id=t.category_id LEFT JOIN accounts a ON a.id=t.account_id WHERE t.date >= ? AND t.date <= ?$accountFilter ORDER BY t.date DESC, t.id DESC LIMIT 6");
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
if ($activeAccountId && $activeLiability) {
    Bill::sync($activeAccountId);
    $billStmt = $db->prepare("SELECT b.account_id, b.due_date, b.amount_due, b.paid_amount, a.name AS account_name FROM bills b JOIN accounts a ON a.id=b.account_id WHERE b.account_id = ? AND b.status <> 'paid' AND b.due_date <= date(?, '+45 days') ORDER BY b.due_date ASC LIMIT 5");
    $billStmt->execute([$activeAccountId, $rangeEnd]);
    $upcoming = $billStmt->fetchAll();
}

$insight = null;
foreach ($spending as $row) {
    $id = (int) $row['category_id'];
    $current = (float) $row['total'];
    $previous = $previousByCategory[$id] ?? 0.0;
    if ($previous > 0 && $current > $previous * 1.1) {
        $insight = [
            'title' => $row['name'] . ' spending increased',
            'text' => 'Spending is ' . number_format((($current - $previous) / $previous) * 100, 0) . '% higher than last month.',
            'color' => 'var(--warning)',
        ];
        break;
    }
}
if ($insight === null) {
    foreach ($budgets as $budgetRow) {
        $spent = $budgetSpendByCategory[(int) $budgetRow['category_id']] ?? 0;
        $limit = (float) $budgetRow['amount'];
        if ($limit > 0 && $spent < $limit && $spent / $limit >= .8) {
            $insight = ['title' => $budgetRow['category_name'] . ' budget is nearly used', 'text' => number_format(($spent / $limit) * 100, 0) . '% used with RM ' . number_format($limit - $spent, 2) . ' remaining.', 'color' => 'var(--warning)'];
            break;
        }
    }
}

function dashboardDelta(float $current, float $previous): string {
    if ($previous == 0.0) return $current > 0 ? 'New this month' : 'No change';
    $pct = (($current - $previous) / abs($previous)) * 100;
    return ($pct >= 0 ? '+' : '') . number_format($pct, 1) . '% vs last month';
}

ob_start();
?>
<div class="dashboard-heading flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
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

<section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Financial summary">
    <?php
    $metrics = [
        [$activeLiability ? 'Outstanding' : 'Balance', $activeBalance, $activeAccount ? ($activeLiability ? 'Currently owed' : 'Available now') : 'Create an account to begin', $activeLiability ? 'var(--expense)' : 'var(--text)', 'var(--accent-soft)'],
        [$activeLiability ? 'Purchases' : 'Income', $activeLiability ? $expenses : $income, dashboardDelta($activeLiability ? $expenses : $income, $activeLiability ? $prevExpenses : $prevIncome), $activeLiability ? 'var(--expense)' : 'var(--income)', 'var(--success-soft)'],
        [$activeLiability ? 'Payments' : 'Expenses', $activeLiability ? $activeMovements['in'] : $expenses, $activeLiability ? 'Paid this month' : dashboardDelta($expenses, $prevExpenses), $activeLiability ? 'var(--income)' : 'var(--expense)', 'var(--danger-soft)'],
        ['Net Worth', $netWorth, 'Across all accounts', 'var(--purple)', 'var(--purple-soft)'],
    ];
    foreach ($metrics as [$label, $value, $note, $color, $soft]): ?>
    <article class="metric-card rounded-xl border p-5" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
        <p class="text-sm font-medium" style="color:var(--text-secondary);"><?= $label ?></p>
        <p class="mt-2 text-2xl font-bold tracking-tight" style="color:<?= $color ?>;">RM <?= number_format((float)$value, 2) ?></p>
        <p class="mt-2 text-xs" style="color:var(--text-muted);"> <?= htmlspecialchars((string)$note, ENT_QUOTES, 'UTF-8') ?></p>
    </article>
    <?php endforeach; ?>
</section>

<div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
    <section class="rounded-xl border p-5 xl:col-span-7" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
        <div class="mb-4 flex items-center justify-between gap-3"><div><h3 class="text-base font-semibold">Income vs Expenses</h3><p class="mt-1 text-xs" style="color:var(--text-secondary);">Monthly totals · last 9 months</p></div><div class="flex gap-4 text-xs"><span><i class="legend-dot" style="background:var(--success)"></i>Income</span><span><i class="legend-dot" style="background:var(--expense)"></i>Expenses</span></div></div>
        <?php if ($hasChartData): ?><div class="h-64"><canvas id="incomeExpenseChart" aria-label="Income and expenses over nine months"></canvas></div>
        <?php else: ?><div class="flex h-64 flex-col items-center justify-center text-center"><p class="text-sm font-medium" style="color:var(--text-secondary);">No income or expenses to chart yet.</p><a href="/transactions" class="mt-2 text-sm font-semibold" style="color:var(--accent);">Add a transaction</a></div><?php endif; ?>
    </section>
    <section class="rounded-xl border p-5 xl:col-span-5" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
        <div class="mb-3"><h3 class="text-base font-semibold">Spending by Category</h3><p class="mt-1 text-xs" style="color:var(--text-secondary);">RM <?= number_format($totalExpense, 2) ?> total this month</p></div>
        <?php if (!$spending): ?><div class="flex min-h-48 items-center justify-center text-sm" style="color:var(--text-muted);">No expenses recorded this month.</div>
        <?php else: ?><div class="grid grid-cols-1 items-center gap-3 sm:grid-cols-2"><div class="mx-auto h-48 w-full max-w-52"><canvas id="categoryChart" aria-label="Spending by category"></canvas></div><div class="space-y-2">
            <?php foreach (array_slice($spending, 0, 7) as $cat): $pct = $totalExpense > 0 ? ((float)$cat['total'] / $totalExpense) * 100 : 0; ?>
            <div class="flex items-center gap-2 text-xs"><i class="legend-dot" style="background:<?= htmlspecialchars((string)$cat['color_hex'], ENT_QUOTES, 'UTF-8') ?>"></i><span class="min-w-0 flex-1 truncate"><?= htmlspecialchars((string)$cat['name'], ENT_QUOTES, 'UTF-8') ?></span><span style="color:var(--text-muted);width:34px;text-align:right;"> <?= number_format($pct, 0) ?>%</span><b class="font-medium" style="color:var(--text);width:66px;text-align:right;">RM <?= number_format((float)$cat['total'], 2) ?></b></div>
            <?php endforeach; ?>
        </div></div><?php endif; ?>
    </section>
</div>

<div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
    <section class="overflow-hidden rounded-xl border xl:col-span-8" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
        <div class="flex items-center justify-between border-b px-5 py-4" style="border-color:var(--border-light);"><div><h3 class="text-base font-semibold">Recent Transactions</h3><p class="mt-1 text-xs" style="color:var(--text-secondary);">Latest activity for <?= htmlspecialchars((string)$currentDisplay, ENT_QUOTES, 'UTF-8') ?></p></div><a href="/transactions?month=<?= htmlspecialchars((string)$reqMonth, ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-medium" style="color:var(--accent);">View all →</a></div>
        <?php if (!$recent): ?><div class="px-5 py-12 text-center"><p class="text-sm font-medium">No transactions yet</p><p class="mt-1 text-sm" style="color:var(--text-secondary);">Add a transaction to see activity here.</p><a href="/transactions" class="mt-4 inline-block text-sm font-semibold" style="color:var(--accent);">Go to Transactions</a></div>
        <?php else: ?><div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr style="background:var(--bg-hover);color:var(--text-secondary);"><th class="px-5 py-3">Date</th><th class="px-4 py-3">Description</th><th class="px-4 py-3">Category</th><th class="hidden px-4 py-3 lg:table-cell">Account</th><th class="px-5 py-3 text-right">Amount</th></tr></thead><tbody>
            <?php foreach ($recent as $tx): $isIncome = $tx['type'] === 'income'; ?>
            <tr class="border-t" style="border-color:var(--border-light);"><td class="whitespace-nowrap px-5 py-3 text-xs" style="color:var(--text-secondary);"> <?= date('d M', strtotime($tx['date'])) ?></td><td class="max-w-48 truncate px-4 py-3 font-medium" style="color:var(--text);"> <?= htmlspecialchars((string)$tx['description'], ENT_QUOTES, 'UTF-8') ?></td><td class="px-4 py-3 text-xs"><span class="mr-2 inline-block h-2 w-2 rounded-full" style="background:<?= htmlspecialchars((string)($tx['color_hex'] ?? '#8A94A6'), ENT_QUOTES, 'UTF-8') ?>"></span><?= htmlspecialchars((string)($tx['category_name'] ?? 'Uncategorized'), ENT_QUOTES, 'UTF-8') ?></td><td class="hidden px-4 py-3 text-xs lg:table-cell" style="color:var(--text-secondary);"> <?= htmlspecialchars((string)($tx['account_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td><td class="whitespace-nowrap px-5 py-3 text-right font-semibold" style="color:<?= $isIncome ? 'var(--income)' : 'var(--expense)' ?>;"> <?= $isIncome ? '+' : '−' ?>RM <?= number_format((float)$tx['amount'], 2) ?></td></tr>
            <?php endforeach; ?>
        </tbody></table></div><?php endif; ?>
    </section>

    <section class="rounded-xl border p-5 xl:col-span-4" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
        <div class="mb-4 flex items-center justify-between"><h3 class="text-base font-semibold">Accounts Overview</h3><a href="/accounts" class="text-sm font-medium" style="color:var(--accent);">See all</a></div>
        <?php if (!$accounts): ?><p class="py-8 text-center text-sm" style="color:var(--text-muted);">No accounts yet. <a href="/accounts" style="color:var(--accent);">Add an account</a></p>
        <?php else: ?><div class="max-h-72 divide-y overflow-y-auto" style="--tw-divide-opacity:1;divide-color:var(--border-light);">
            <?php foreach ($accounts as $a): $bal = Account::balance((int)$a['id']); $liability = Account::isLiability($a); ?>
            <div class="flex items-center justify-between gap-3 py-3"><div class="min-w-0"><p class="truncate text-sm font-medium"><?= htmlspecialchars((string)$a['name'], ENT_QUOTES, 'UTF-8') ?></p><p class="mt-0.5 text-xs capitalize" style="color:var(--text-muted);"><?= htmlspecialchars((string)$a['kind'], ENT_QUOTES, 'UTF-8') ?><?= $liability ? ' · outstanding' : '' ?></p></div><b class="whitespace-nowrap text-sm" style="color:<?= $liability ? 'var(--expense)' : 'var(--text)' ?>;">RM <?= number_format($bal, 2) ?></b></div>
            <?php endforeach; ?>
        </div><?php endif; ?>
    </section>
</div>

<div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
    <section class="rounded-xl border p-5" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
        <div class="mb-4 flex items-center justify-between"><h3 class="text-base font-semibold">Upcoming Bills</h3><a href="/bills" class="text-sm font-medium" style="color:var(--accent);">See all</a></div>
        <?php if (!$upcoming): ?><div class="py-6 text-center"><p class="text-sm font-medium">No upcoming bills</p><p class="mt-1 text-xs" style="color:var(--text-secondary);">You're clear for now.</p></div>
        <?php else: ?><div class="space-y-3"><?php foreach ($upcoming as $bill): $dueLeft = max(0, (float)$bill['amount_due'] - (float)$bill['paid_amount']); ?><div class="flex items-center justify-between gap-3"><div class="min-w-0"><p class="truncate text-sm font-medium"><?= htmlspecialchars((string)$bill['account_name'], ENT_QUOTES, 'UTF-8') ?></p><p class="mt-0.5 text-xs" style="color:var(--text-secondary);">Due <?= date('d M Y', strtotime($bill['due_date'])) ?></p></div><b class="whitespace-nowrap text-sm">RM <?= number_format($dueLeft, 2) ?></b></div><?php endforeach; ?></div><?php endif; ?>
    </section>
    <section class="rounded-xl border p-5" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
        <div class="mb-4 flex items-center justify-between"><div><h3 class="text-base font-semibold">Budgets</h3><p class="mt-1 text-xs" style="color:var(--text-secondary);">This month</p></div><a href="/budgets" class="text-sm font-medium" style="color:var(--accent);">See all</a></div>
        <?php if (!$budgets): ?><div class="py-5 text-center"><p class="text-sm" style="color:var(--text-secondary);">No budgets set.</p><a href="/budgets" class="mt-2 inline-block text-sm font-medium" style="color:var(--accent);">Create a budget</a></div>
        <?php else: ?><div class="space-y-4"><?php foreach (array_slice($budgets, 0, 4) as $b): $spent = $budgetSpendByCategory[(int)$b['category_id']] ?? 0; $limit=(float)$b['amount']; $pct=$limit>0?($spent/$limit)*100:0; $over=$pct>100; ?><div><div class="mb-1 flex items-center justify-between gap-2 text-xs"><span class="truncate font-medium"><?= htmlspecialchars((string)$b['category_name'], ENT_QUOTES, 'UTF-8') ?></span><span style="color:<?= $over?'var(--expense)':'var(--text-secondary)' ?>;"><?= number_format($pct,0) ?>%</span></div><div class="h-2 overflow-hidden rounded-full" style="background:var(--bg-hover);"><div class="h-full rounded-full" style="width:<?= min(100,$pct) ?>%;background:<?= $over?'var(--expense)':'var(--success)' ?>;"></div></div><p class="mt-1 text-xs" style="color:var(--text-muted);">RM <?= number_format($spent,2) ?> spent of RM <?= number_format($limit,2) ?><?= $over ? ' · over by RM '.number_format($spent-$limit,2) : ' · RM '.number_format($limit-$spent,2).' left' ?></p></div><?php endforeach; ?></div><?php endif; ?>
    </section>
    <section class="rounded-xl border p-5" style="background:<?= $insight ? 'var(--warning-soft)' : 'var(--bg-alt)' ?>;border-color:var(--border);box-shadow:var(--shadow);">
        <h3 class="text-base font-semibold"><?= $insight ? 'Spending insight' : 'Monthly summary' ?></h3>
        <?php if ($insight): ?><p class="mt-3 text-sm font-semibold" style="color:var(--text);"> <?= htmlspecialchars((string)$insight['title'], ENT_QUOTES, 'UTF-8') ?></p><p class="mt-1 text-sm" style="color:var(--text-secondary);"> <?= htmlspecialchars((string)$insight['text'], ENT_QUOTES, 'UTF-8') ?></p>
        <?php elseif ($income == 0.0 && $expenses == 0.0): ?><p class="mt-3 text-sm" style="color:var(--text-secondary);">No financial activity recorded for this account this month.</p>
        <?php else: ?><p class="mt-3 text-sm" style="color:var(--text-secondary);"> <?= $net >= 0 ? 'Income is ahead of expenses' : 'Expenses are ahead of income' ?> by <strong style="color:var(--text);">RM <?= number_format(abs($net),2) ?></strong> this month.</p><p class="mt-2 text-xs" style="color:var(--text-muted);">Based on recorded transactions for <?= htmlspecialchars((string)$currentDisplay, ENT_QUOTES, 'UTF-8') ?>.</p><?php endif; ?>
    </section>
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
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
