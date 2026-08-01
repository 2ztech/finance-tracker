<?php
require_once __DIR__ . '/../src/Settings.php';
require_once __DIR__ . '/../src/Expense.php';

$m = Helper::parseMonth();
$year = $m['year'];
$month = $m['month'];
$reqMonth = $m['reqMonth'];
$prevMonth = $m['prevMonth'];
$nextMonth = $m['nextMonth'];
$currentDisplay = $m['currentDisplay'];

$incomeThisMonth = round(Expense::getTotalIncome($month, $year), 2);
$expensesThisMonth = round(Expense::getTotalExpense($month, $year), 2);
$onHandBalance = Expense::getOnHandBalance($month, $year);
$projectedBalance = Expense::getEOMProjection($month, $year);
$expensesByCategory = Expense::getExpensesByCategory($month, $year);

$prevYear = date('Y', strtotime($prevMonth . '-01'));
$prevMonthNum = date('m', strtotime($prevMonth . '-01'));
$prevIncome = round(Expense::getTotalIncome($prevMonthNum, $prevYear), 2);
$prevExpenses = round(Expense::getTotalExpense($prevMonthNum, $prevYear), 2);
$currNet = $incomeThisMonth - $expensesThisMonth;
$prevNet = $prevIncome - $prevExpenses;

function delta(float $curr, float $prev): array {
    if ($prev == 0) return ['diff' => $curr, 'pct' => null, 'sign' => $curr >= 0 ? 'up' : 'down'];
    $diff = $curr - $prev;
    $pct = round(($diff / abs($prev)) * 100, 1);
    return ['diff' => $diff, 'pct' => $pct, 'sign' => $diff >= 0 ? 'up' : 'down'];
}
$dIncome = delta($incomeThisMonth, $prevIncome);
$dExpense = delta($expensesThisMonth, $prevExpenses);
$dNet = delta($currNet, $prevNet);

ob_start();
?>

<!-- Header -->
<div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h2 class="text-2xl font-bold" style="color:var(--text);">Dashboard</h2>
        <p class="mt-0.5 text-sm" style="color:var(--text-secondary);">Overview of your financial position.</p>
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
        <p class="text-xs font-medium" style="color:var(--text-muted);">On Hand</p>
        <p class="mt-2 text-2xl font-bold" style="color:var(--text);">RM <?= number_format($onHandBalance, 2) ?></p>
        <p class="mt-1 text-xs" style="color:var(--success);">Available now</p>
    </div>
    <div class="rounded-xl border p-5" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
        <p class="text-xs font-medium" style="color:var(--text-muted);">EOM Projection</p>
        <p class="mt-2 text-2xl font-bold" style="color:var(--text);">RM <?= number_format($projectedBalance, 2) ?></p>
        <p class="mt-1 text-xs" style="color:var(--accent);">End of month est.</p>
    </div>
    <div class="rounded-xl border p-5" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
        <p class="text-xs font-medium" style="color:var(--text-muted);">Income</p>
        <p class="mt-2 text-2xl font-bold" style="color:var(--income);">RM <?= number_format($incomeThisMonth, 2) ?></p>
        <p class="mt-1 text-xs" style="color:var(--text-muted);"><?= htmlspecialchars((string)date('M', mktime(0,0,0,(int)$month,1)), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <div class="rounded-xl border p-5" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
        <p class="text-xs font-medium" style="color:var(--text-muted);">Expenses</p>
        <p class="mt-2 text-2xl font-bold" style="color:var(--expense);">RM <?= number_format($expensesThisMonth, 2) ?></p>
        <p class="mt-1 text-xs" style="color:var(--text-muted);"><?= htmlspecialchars((string)date('M', mktime(0,0,0,(int)$month,1)), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
</div>

<!-- Monthly Comparison -->
<div class="rounded-xl border p-5" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
    <h3 class="mb-4 text-sm font-semibold" style="color:var(--text);">
        vs <?= htmlspecialchars((string)date('F Y', strtotime($prevMonth . '-01')), ENT_QUOTES, 'UTF-8') ?>
    </h3>
    <div class="grid grid-cols-3 gap-3 text-center">
        <div>
            <p class="text-xs" style="color:var(--text-muted);">Income</p>
            <p class="mt-1 text-lg font-bold" style="color:<?= $dIncome['sign'] === 'up' ? 'var(--income)' : 'var(--expense)' ?>;">
                <?= $dIncome['sign'] === 'up' ? '+' : '' ?>RM <?= number_format($dIncome['diff'], 2) ?>
            </p>
            <?php if ($dIncome['pct'] !== null): ?>
                <p class="text-xs" style="color:<?= $dIncome['sign'] === 'up' ? 'var(--income)' : 'var(--expense)' ?>;"><?= $dIncome['sign'] === 'up' ? '&uarr;' : '&darr;' ?> <?= $dIncome['pct'] ?>%</p>
            <?php endif; ?>
        </div>
        <div>
            <p class="text-xs" style="color:var(--text-muted);">Expenses</p>
            <p class="mt-1 text-lg font-bold" style="color:<?= $dExpense['sign'] === 'up' ? 'var(--expense)' : 'var(--income)' ?>;">
                <?= $dExpense['sign'] === 'up' ? '+' : '' ?>RM <?= number_format($dExpense['diff'], 2) ?>
            </p>
            <?php if ($dExpense['pct'] !== null): ?>
                <p class="text-xs" style="color:<?= $dExpense['sign'] === 'up' ? 'var(--expense)' : 'var(--income)' ?>;"><?= $dExpense['sign'] === 'up' ? '&uarr;' : '&darr;' ?> <?= $dExpense['pct'] ?>%</p>
            <?php endif; ?>
        </div>
        <div>
            <p class="text-xs" style="color:var(--text-muted);">Net</p>
            <p class="mt-1 text-lg font-bold" style="color:<?= $dNet['sign'] === 'up' ? 'var(--income)' : 'var(--expense)' ?>;">
                <?= $dNet['sign'] === 'up' ? '+' : '' ?>RM <?= number_format($dNet['diff'], 2) ?>
            </p>
            <?php if ($dNet['pct'] !== null): ?>
                <p class="text-xs" style="color:<?= $dNet['sign'] === 'up' ? 'var(--income)' : 'var(--expense)' ?>;"><?= $dNet['sign'] === 'up' ? '&uarr;' : '&darr;' ?> <?= $dNet['pct'] ?>%</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Chart -->
<div class="rounded-xl border p-6" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
    <h3 class="mb-6 text-center text-lg font-bold" style="color:var(--text);">Expense Breakdown &mdash; <?= htmlspecialchars((string)$currentDisplay, ENT_QUOTES, 'UTF-8') ?></h3>

    <?php if (empty($expensesByCategory)): ?>
        <p class="py-12 text-center text-sm" style="color:var(--text-muted);">No expenses recorded this month.</p>
    <?php else: ?>
        <div class="mx-auto h-72 max-w-md">
            <canvas id="expenseChart"></canvas>
        </div>
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
            labels: <?= json_encode(array_map(fn($c) => htmlspecialchars((string)$c['name'], ENT_QUOTES, 'UTF-8'), $expensesByCategory)) ?>,
            datasets: [{
                data: <?= json_encode(array_column($expensesByCategory, 'total')) ?>,
                backgroundColor: <?= json_encode(array_column($expensesByCategory, 'color_hex')) ?>,
                borderWidth: 0,
                hoverOffset: 4,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '72%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: document.documentElement.classList.contains('dark') ? '#8890a5' : '#6b7280',
                        font: { family: "'Outfit', sans-serif" },
                        padding: 14,
                    },
                },
            },
        },
    });
})();
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
