<?php
require_once __DIR__ . '/../src/Account.php';
require_once __DIR__ . '/../src/Expense.php';
require_once __DIR__ . '/../src/Budget.php';

$m = Helper::parseMonth();
$year = $m['year'];
$month = $m['month'];
$reqMonth = $m['reqMonth'];
$prevMonth = $m['prevMonth'];
$nextMonth = $m['nextMonth'];
$currentDisplay = $m['currentDisplay'];

$account = Account::active();

$rangeStart = "$year-$month-01";
$rangeEnd = date('Y-m-t', strtotime($rangeStart));
$prevStart = date('Y-m-01', strtotime($prevMonth . '-01'));
$prevEnd = date('Y-m-t', strtotime($prevMonth . '-01'));

$budgets = Budget::getAll();
$expensesByCatTotal = [];
$expensesByCategory = [];

if ($account !== null) {
    $accountId = (int) $account['id'];
    $isLiability = Account::isLiability($account);

    $mov = Account::movements($accountId, $rangeStart, $rangeEnd);
    $prevMov = Account::movements($accountId, $prevStart, $prevEnd);
    $incomeThisMonth = $mov['income'];
    $expensesThisMonth = $mov['expense'];
    $paymentsThisMonth = $mov['in'];
    $balanceNow = Account::balance($accountId);

    $currNet = $incomeThisMonth - $expensesThisMonth;
    $prevNet = $prevMov['income'] - $prevMov['expense'];

    $expensesByCategory = Expense::getExpensesByCategory($month, $year, $accountId);
    foreach ($expensesByCategory as $cat) {
        $expensesByCatTotal[(int) $cat['category_id']] = $cat['total'];
    }
}

function delta(float $curr, float $prev): array {
    if ($prev == 0) return ['diff' => $curr, 'pct' => null, 'sign' => $curr >= 0 ? 'up' : 'down'];
    $diff = $curr - $prev;
    return ['diff' => $diff, 'pct' => round(($diff / abs($prev)) * 100, 1), 'sign' => $diff >= 0 ? 'up' : 'down'];
}
$dIncome = $account ? delta($incomeThisMonth, $prevMov['income']) : null;
$dExpense = $account ? delta($expensesThisMonth, $prevMov['expense']) : null;
$dNet = $account ? delta($currNet, $prevNet) : null;

ob_start();
?>

<?php if ($account === null): ?>
    <div class="rounded-xl border py-16 text-center" style="background:var(--bg-alt);border-color:var(--border);">
        <p class="text-sm" style="color:var(--text-muted);">No account yet. <a href="/accounts" style="color:var(--accent);">Create one</a> to get started.</p>
    </div>
<?php else: ?>

<!-- Header -->
<div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h2 class="text-2xl font-bold" style="color:var(--text);"><?= htmlspecialchars((string) $account['name'], ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="mt-0.5 text-sm" style="color:var(--text-secondary);">
            <?= $isLiability ? 'Outstanding and repayment overview.' : 'Overview of this account.' ?>
        </p>
    </div>
    <div class="flex items-center rounded-lg border p-0.5 text-sm" style="background:var(--bg-alt);border-color:var(--border);">
        <a href="?month=<?= htmlspecialchars((string)$prevMonth, ENT_QUOTES, 'UTF-8') ?>" class="rounded-md px-3 py-1.5 transition-colors" style="color:var(--text-secondary);" onmouseover="this.style.background='var(--bg-hover)';this.style.color='var(--text)'" onmouseout="this.style.background='';this.style.color='var(--text-secondary)'">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <span class="min-w-[120px] px-3 text-center font-semibold" style="color:var(--text);"><?= htmlspecialchars((string)$currentDisplay, ENT_QUOTES, 'UTF-8') ?></span>
        <a href="?month=<?= htmlspecialchars((string)$nextMonth, ENT_QUOTES, 'UTF-8') ?>" class="rounded-md px-3 py-1.5 transition-colors" style="color:var(--text-secondary);" onmouseover="this.style.background='var(--bg-hover)';this.style.color='var(--text)'" onmouseout="this.style.background='';this.style.color='var(--text-secondary)'">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>
    </div>
</div>

<!-- Stat Cards -->
<div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
    <div class="rounded-xl border p-5" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
        <p class="text-xs font-medium" style="color:var(--text-muted);"><?= $isLiability ? 'Outstanding' : 'Balance' ?></p>
        <p class="mt-2 text-2xl font-bold" style="color:<?= $isLiability ? 'var(--expense)' : 'var(--text)' ?>;">RM <?= number_format($balanceNow, 2) ?></p>
        <p class="mt-1 text-xs" style="color:var(--text-muted);"><?= $isLiability ? 'Currently owed' : 'Available now' ?></p>
    </div>
    <?php if ($isLiability): ?>
        <div class="rounded-xl border p-5" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
            <p class="text-xs font-medium" style="color:var(--text-muted);">Purchases</p>
            <p class="mt-2 text-2xl font-bold" style="color:var(--expense);">RM <?= number_format($expensesThisMonth, 2) ?></p>
            <p class="mt-1 text-xs" style="color:var(--text-muted);">This month</p>
        </div>
        <div class="rounded-xl border p-5" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
            <p class="text-xs font-medium" style="color:var(--text-muted);">Payments</p>
            <p class="mt-2 text-2xl font-bold" style="color:var(--income);">RM <?= number_format($paymentsThisMonth, 2) ?></p>
            <p class="mt-1 text-xs" style="color:var(--text-muted);">Paid this month</p>
        </div>
        <div class="rounded-xl border p-5" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
            <p class="text-xs font-medium" style="color:var(--text-muted);">Available Credit</p>
            <p class="mt-2 text-2xl font-bold" style="color:var(--text);">
                <?= $account['credit_limit'] !== null ? 'RM ' . number_format(max(0, (float) $account['credit_limit'] - $balanceNow), 2) : '—' ?>
            </p>
            <p class="mt-1 text-xs" style="color:var(--text-muted);"><?= $account['credit_limit'] !== null ? 'of RM ' . number_format((float) $account['credit_limit'], 2) : 'No limit set' ?></p>
        </div>
    <?php else: ?>
        <div class="rounded-xl border p-5" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
            <p class="text-xs font-medium" style="color:var(--text-muted);">Income</p>
            <p class="mt-2 text-2xl font-bold" style="color:var(--income);">RM <?= number_format($incomeThisMonth, 2) ?></p>
            <p class="mt-1 text-xs" style="color:var(--text-muted);">This month</p>
        </div>
        <div class="rounded-xl border p-5" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
            <p class="text-xs font-medium" style="color:var(--text-muted);">Expenses</p>
            <p class="mt-2 text-2xl font-bold" style="color:var(--expense);">RM <?= number_format($expensesThisMonth, 2) ?></p>
            <p class="mt-1 text-xs" style="color:var(--text-muted);">This month</p>
        </div>
        <div class="rounded-xl border p-5" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
            <p class="text-xs font-medium" style="color:var(--text-muted);">Net</p>
            <p class="mt-2 text-2xl font-bold" style="color:<?= $currNet >= 0 ? 'var(--income)' : 'var(--expense)' ?>;">RM <?= number_format($currNet, 2) ?></p>
            <p class="mt-1 text-xs" style="color:var(--text-muted);">This month</p>
        </div>
    <?php endif; ?>
</div>

<!-- Monthly Comparison -->
<div class="rounded-xl border p-5" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
    <h3 class="mb-4 text-sm font-semibold" style="color:var(--text);">vs <?= htmlspecialchars((string)date('F Y', strtotime($prevMonth . '-01')), ENT_QUOTES, 'UTF-8') ?></h3>
    <div class="grid grid-cols-3 gap-3 text-center">
        <?php
        $cols = $isLiability
            ? [['Purchases', $dExpense, true], ['Payments', delta($paymentsThisMonth, $prevMov['in']), false], ['Net Change', $dNet, false]]
            : [['Income', $dIncome, false], ['Expenses', $dExpense, true], ['Net', $dNet, false]];
        foreach ($cols as [$label, $d, $invert]):
            $good = $d['sign'] === 'up' ? !$invert : $invert;
        ?>
        <div>
            <p class="text-xs" style="color:var(--text-muted);"><?= $label ?></p>
            <p class="mt-1 text-lg font-bold" style="color:<?= $good ? 'var(--income)' : 'var(--expense)' ?>;">
                <?= $d['sign'] === 'up' ? '+' : '' ?>RM <?= number_format($d['diff'], 2) ?>
            </p>
            <?php if ($d['pct'] !== null): ?>
                <p class="text-xs" style="color:<?= $good ? 'var(--income)' : 'var(--expense)' ?>;"><?= $d['sign'] === 'up' ? '&uarr;' : '&darr;' ?> <?= $d['pct'] ?>%</p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Budgets (global across accounts) -->
<div class="rounded-xl border p-5" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
    <h3 class="mb-1 text-sm font-semibold" style="color:var(--text);">Category Budgets</h3>
    <p class="mb-4 text-xs" style="color:var(--text-muted);">Across all accounts.</p>
    <?php if (empty($budgets)): ?>
        <p class="text-xs" style="color:var(--text-muted);">No budgets set. Add one from the Budgets page.</p>
    <?php else: ?>
        <?php $allSpend = Expense::getExpensesByCategory($month, $year); $allSpendById = []; foreach ($allSpend as $c) { $allSpendById[(int)$c['category_id']] = $c['total']; } ?>
        <div class="space-y-3">
            <?php foreach ($budgets as $b): ?>
                <?php
                $spent = $allSpendById[(int) $b['category_id']] ?? 0;
                $pct = $b['amount'] > 0 ? min(($spent / $b['amount']) * 100, 100) : 0;
                $over = $spent > $b['amount'];
                ?>
                <div>
                    <div class="mb-1 flex items-center justify-between text-xs">
                        <span style="color:var(--text);"><?= htmlspecialchars((string)$b['category_name'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span style="color:<?= $over ? 'var(--expense)' : 'var(--text-secondary)' ?>;">RM <?= number_format($spent, 0) ?> / RM <?= number_format($b['amount'], 0) ?></span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full" style="background:var(--bg-hover);">
                        <div class="h-full rounded-full transition-all" style="width:<?= $pct ?>%;background:<?= $over ? 'var(--expense)' : 'var(--accent)' ?>;"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Chart -->
<div class="rounded-xl border p-6" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
    <h3 class="mb-6 text-center text-lg font-bold" style="color:var(--text);"><?= $isLiability ? 'Purchases' : 'Expense' ?> Breakdown &mdash; <?= htmlspecialchars((string)$currentDisplay, ENT_QUOTES, 'UTF-8') ?></h3>
    <?php if (empty($expensesByCategory)): ?>
        <p class="py-12 text-center text-sm" style="color:var(--text-muted);">No expenses recorded this month.</p>
    <?php else: ?>
        <div class="mx-auto h-72 max-w-md"><canvas id="expenseChart"></canvas></div>
        <?php $totalExp = array_sum(array_column($expensesByCategory, 'total')); ?>
        <div class="mt-6 space-y-1 rounded-lg border" style="border-color:var(--border-light);">
            <?php foreach ($expensesByCategory as $cat): ?>
                <?php $pct = $totalExp > 0 ? ($cat['total'] / $totalExp) * 100 : 0; ?>
                <div class="flex items-center justify-between px-4 py-3" style="border-bottom:1px solid var(--border-light);">
                    <div class="flex items-center gap-3">
                        <div class="h-3 w-3 rounded-full shrink-0" style="background:<?= htmlspecialchars((string)$cat['color_hex'], ENT_QUOTES, 'UTF-8') ?>;"></div>
                        <span class="text-sm font-medium" style="color:var(--text);"><?= htmlspecialchars((string)$cat['name'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-xs font-medium rounded px-2 py-0.5" style="background:var(--bg-hover);color:var(--text-secondary);"><?= number_format($pct, 0) ?>%</span>
                        <span class="text-sm font-semibold" style="color:var(--expense);">RM <?= number_format($cat['total'], 2) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($expensesByCategory)): ?>
<script>
(function(){
    var ctx = document.getElementById('expenseChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_map(fn($c) => htmlspecialchars((string)$c['name'], ENT_QUOTES, 'UTF-8'), $expensesByCategory), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
            datasets: [{
                data: <?= json_encode(array_column($expensesByCategory, 'total')) ?>,
                backgroundColor: <?= json_encode(array_column($expensesByCategory, 'color_hex')) ?>,
                borderWidth: 0,
                hoverOffset: 4,
            }],
        },
        options: {
            responsive: true, maintainAspectRatio: false, cutout: '72%',
            plugins: { legend: { position: 'bottom', labels: { color: document.documentElement.classList.contains('dark') ? '#8890a5' : '#6b7280', font: { family: "'Outfit', sans-serif" }, padding: 14 } } },
        },
    });
})();
</script>
<?php endif; ?>

<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
