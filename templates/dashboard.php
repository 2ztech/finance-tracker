<?php
// templates/dashboard.php
require_once __DIR__ . '/../src/Settings.php';
require_once __DIR__ . '/../src/Expense.php';

$reqMonth = $_GET['month'] ?? date('Y-m');
$parts = explode('-', $reqMonth);
if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
    $year = $parts[0];
    $month = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
} else {
    $year = date('Y');
    $month = date('m');
    $reqMonth = "$year-$month";
}

$prevMonth = date('Y-m', strtotime($reqMonth . '-01 -1 month'));
$nextMonth = date('Y-m', strtotime($reqMonth . '-01 +1 month'));
$currentDisplay = date('F Y', strtotime($reqMonth . '-01'));

$incomeThisMonth = round(Expense::getTotalIncome($month, $year), 2);
$expensesThisMonth = round(Expense::getTotalExpense($month, $year), 2);

$onHandBalance = Expense::getOnHandBalance($month, $year);
$projectedBalance = Expense::getEOMProjection($month, $year);

$expensesByCategory = Expense::getExpensesByCategory($month, $year);

ob_start();
?>
<div>
    <!-- dynamic month navigation & header -->
    <div class="mb-8 flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h2 class="text-3xl font-bold tracking-tight mb-1 text-white">Dashboard Overview</h2>
            <p class="text-gray-400">High-level financial analytics and projections.</p>
        </div>
        
        <div class="flex items-center bg-dark-800 border border-dark-700 rounded-xl p-1 shadow-inner h-fit">
            <a href="?month=<?= htmlspecialchars((string)$prevMonth, ENT_QUOTES, 'UTF-8') ?>" class="p-2 text-gray-400 hover:text-white hover:bg-dark-700 rounded-lg transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            </a>
            <span class="px-4 font-semibold text-sm text-gray-200 min-w-32 text-center w-36"><?= htmlspecialchars((string)$currentDisplay, ENT_QUOTES, 'UTF-8') ?></span>
            <a href="?month=<?= htmlspecialchars((string)$nextMonth, ENT_QUOTES, 'UTF-8') ?>" class="p-2 text-gray-400 hover:text-white hover:bg-dark-700 rounded-lg transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            </a>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        
        <!-- Projected End-of-Month Balance -->
        <div class="bg-dark-800/80 backdrop-blur-md rounded-2xl p-6 border border-dark-700/50 shadow-lg relative overflow-hidden group hover:-translate-y-1 transition-transform duration-300">
            <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                <svg class="w-16 h-16 text-blue-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
            </div>
            <p class="text-sm font-medium text-gray-400 mb-2">Projected EOM Balance</p>
            <h3 class="text-3xl font-bold text-white mb-1 tooltip" title="Start Bal + All Month Income - All Month Expenses - Unpaid Commitments">RM <?= number_format($projectedBalance, 2, '.', '') ?></h3>
            <div class="flex items-center text-xs text-blue-400 font-medium">
                End of Month Est.
            </div>
        </div>

        <!-- On Hand Balance -->
        <div class="bg-dark-800/80 backdrop-blur-md rounded-2xl p-6 border border-dark-700/50 shadow-lg relative overflow-hidden group hover:-translate-y-1 transition-transform duration-300">
            <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                <svg class="w-16 h-16 text-brand-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
            </div>
            <p class="text-sm font-medium text-gray-400 mb-2">On Hand Balance</p>
            <h3 class="text-3xl font-bold text-white mb-1" title="Starts + Income (to date) - Expenses (to date)">RM <?= number_format($onHandBalance, 2, '.', '') ?></h3>
            <div class="flex items-center text-xs text-brand-400 font-medium">
                Liquidity to Date
            </div>
        </div>

        <!-- Income -->
        <div class="bg-dark-800/80 backdrop-blur-md rounded-2xl p-6 border border-dark-700/50 shadow-lg relative overflow-hidden group hover:-translate-y-1 transition-transform duration-300">
            <p class="text-sm font-medium text-gray-400 mb-2">Income (<?= htmlspecialchars((string)date('M', mktime(0,0,0,$month,1)), ENT_QUOTES, 'UTF-8') ?>)</p>
            <h3 class="text-3xl font-bold text-white mb-1">RM <?= number_format($incomeThisMonth, 2, '.', '') ?></h3>
        </div>

        <!-- Expenses -->
        <div class="bg-dark-800/80 backdrop-blur-md rounded-2xl p-6 border border-dark-700/50 shadow-lg relative overflow-hidden group hover:-translate-y-1 transition-transform duration-300">
            <p class="text-sm font-medium text-gray-400 mb-2">Expenses (<?= htmlspecialchars((string)date('M', mktime(0,0,0,$month,1)), ENT_QUOTES, 'UTF-8') ?>)</p>
            <h3 class="text-3xl font-bold text-white mb-1">RM <?= number_format($expensesThisMonth, 2, '.', '') ?></h3>
        </div>
    </div>

    <!-- Main Data & Charts -->
    <div class="grid grid-cols-1 gap-6">
        <!-- Chart -->
        <div class="bg-dark-800/80 backdrop-blur-md rounded-2xl p-6 border border-dark-700/50 shadow-lg flex flex-col justify-center max-w-4xl mx-auto w-full">
            <h3 class="text-lg font-bold text-white mb-6 text-center">Monthly Expense Breakdown (<?= htmlspecialchars((string)$currentDisplay, ENT_QUOTES, 'UTF-8') ?>)</h3>
            <div class="h-80 flex items-center justify-center relative">
                <?php if (empty($expensesByCategory)): ?>
                    <p class="text-gray-500 text-sm">No expenses this month to chart.</p>
                <?php else: ?>
                    <canvas id="expenseChart"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($expensesByCategory)): ?>
<script>
    const ctx = document.getElementById('expenseChart').getContext('2d');
    const dataLabels = <?= json_encode(array_map(function($c) { return htmlspecialchars((string)$c['name'], ENT_QUOTES, 'UTF-8'); }, $expensesByCategory)) ?>;
    const dataValues = <?= json_encode(array_column($expensesByCategory, 'total')) ?>;
    const dataColors = <?= json_encode(array_column($expensesByCategory, 'color_hex')) ?>;

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: dataLabels,
            datasets: [{
                data: dataValues,
                backgroundColor: dataColors,
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '75%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { color: '#94a3b8', font: { family: "'Outfit', sans-serif" }, padding: 15 }
                }
            }
        }
    });
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
