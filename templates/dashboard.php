<?php
// templates/dashboard.php
require_once __DIR__ . '/../src/Settings.php';
require_once __DIR__ . '/../src/Expense.php';

$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');

$startingBalance = round((float) Settings::get('starting_bank_balance', 0), 2);
$allIncome = round(Expense::getTotalIncomeAllTime(), 2);
$allExpense = round(Expense::getTotalExpenseAllTime(), 2);

$realBalance = round($startingBalance + $allIncome - $allExpense, 2);

$incomeThisMonth = round(Expense::getTotalIncome($month, $year), 2);
$expensesThisMonth = round(Expense::getTotalExpense($month, $year), 2);

$commitmentsTotal = round(Expense::getCommitmentsRemaining(), 2);

// Monthly Projection calculation as instructed. 
// Starting Balance + All Time Income MINUS (All Time Daily Expenses + ALL Monthly Commitments)
// This essentially drops all active commitments from your real balance to project liquidity for the month end.
$projectedBalance = round(($startingBalance + $allIncome) - ($allExpense + $commitmentsTotal), 2);

$expensesByCategory = Expense::getExpensesByCategory($month, $year);

ob_start();
?>
<div>
    <div class="mb-8 flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h2 class="text-3xl font-bold tracking-tight mb-1 text-white">Dashboard Overview</h2>
            <p class="text-gray-400">High-level financial analytics and projections.</p>
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
            <h3 class="text-3xl font-bold text-white mb-1 tooltip" title="Real Balance MINUS Monthly Commitments">RM <?= number_format($projectedBalance, 2, '.', '') ?></h3>
            <div class="flex items-center text-xs text-blue-400 font-medium">
                (Real Bal. - Commitments)
            </div>
        </div>

        <!-- On Hand Balance -->
        <div class="bg-dark-800/80 backdrop-blur-md rounded-2xl p-6 border border-dark-700/50 shadow-lg relative overflow-hidden group hover:-translate-y-1 transition-transform duration-300">
            <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                <svg class="w-16 h-16 text-brand-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
            </div>
            <p class="text-sm font-medium text-gray-400 mb-2">On Hand Balance</p>
            <h3 class="text-3xl font-bold text-white mb-1" title="Starting Bank Balance + Total Income - Total Expenses">RM <?= number_format($realBalance, 2, '.', '') ?></h3>
            <div class="flex items-center text-xs text-brand-400 font-medium">
                Cash on hand
            </div>
        </div>

        <!-- Income -->
        <div class="bg-dark-800/80 backdrop-blur-md rounded-2xl p-6 border border-dark-700/50 shadow-lg relative overflow-hidden group hover:-translate-y-1 transition-transform duration-300">
            <p class="text-sm font-medium text-gray-400 mb-2">Income (<?= date('M', mktime(0,0,0,$month,1)) ?>)</p>
            <h3 class="text-3xl font-bold text-white mb-1">RM <?= number_format($incomeThisMonth, 2, '.', '') ?></h3>
        </div>

        <!-- Expenses -->
        <div class="bg-dark-800/80 backdrop-blur-md rounded-2xl p-6 border border-dark-700/50 shadow-lg relative overflow-hidden group hover:-translate-y-1 transition-transform duration-300">
            <p class="text-sm font-medium text-gray-400 mb-2">Expenses (<?= date('M', mktime(0,0,0,$month,1)) ?>)</p>
            <h3 class="text-3xl font-bold text-white mb-1">RM <?= number_format($expensesThisMonth, 2, '.', '') ?></h3>
        </div>
    </div>

    <!-- Main Data & Charts -->
    <div class="grid grid-cols-1 gap-6">
        <!-- Chart -->
        <div class="bg-dark-800/80 backdrop-blur-md rounded-2xl p-6 border border-dark-700/50 shadow-lg flex flex-col justify-center max-w-4xl mx-auto w-full">
            <h3 class="text-lg font-bold text-white mb-6 text-center">Monthly Expense Breakdown (<?= date('F Y', mktime(0,0,0,$month,1,$year)) ?>)</h3>
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
