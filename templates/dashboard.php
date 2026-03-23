<?php
ob_start();

$realBalance = 4250.75;
$commitmentsRemaining = 850.00;
$incomeThisMonth = 5100.00;
$expensesThisMonth = 1450.25;

?>
<div>
    <div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
        <div>
            <h2 class="text-3xl font-bold tracking-tight mb-1 text-white">Overview</h2>
            <p class="text-gray-400">Here's your financial summary for <span class="text-brand-400 font-medium"><?= date('F Y') ?></span></p>
        </div>
        <div class="flex gap-3">
            <button class="px-4 py-2 bg-dark-800 hover:bg-dark-700 border border-dark-600 rounded-xl text-sm font-medium transition-colors cursor-not-allowed opacity-50">
                <svg class="w-4 h-4 inline-block mr-1 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                Prev
            </button>
            <button class="px-4 py-2 bg-dark-800 hover:bg-dark-700 border border-dark-600 rounded-xl text-sm font-medium transition-colors cursor-not-allowed opacity-50">
                Next
                <svg class="w-4 h-4 inline-block ml-1 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            </button>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Real Balance -->
        <div class="bg-dark-800/80 backdrop-blur-md rounded-2xl p-6 border border-dark-700/50 shadow-lg relative overflow-hidden group hover:-translate-y-1 transition-transform duration-300">
            <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                <svg class="w-16 h-16 text-brand-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
            </div>
            <p class="text-sm font-medium text-gray-400 mb-2">Real Balance</p>
            <h3 class="text-3xl font-bold text-white mb-1 tooltip" title="Starting Bank Balance + Total Income - Total Expenses">$<?= number_format($realBalance, 2) ?></h3>
            <div class="flex items-center text-sm text-brand-400 font-medium bg-brand-500/10 w-fit px-2 py-1 rounded-lg">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>
                +12% vs last month
            </div>
        </div>

        <!-- Commitments Remaining -->
        <div class="bg-dark-800/80 backdrop-blur-md rounded-2xl p-6 border border-dark-700/50 shadow-lg relative overflow-hidden group hover:-translate-y-1 transition-transform duration-300">
            <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                <svg class="w-16 h-16 text-orange-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
            </div>
            <p class="text-sm font-medium text-gray-400 mb-2">Commitments (Unpaid)</p>
            <h3 class="text-3xl font-bold text-white mb-1">$<?= number_format($commitmentsRemaining, 2) ?></h3>
            <div class="w-full bg-dark-700 rounded-full h-1.5 mt-4">
                <div class="bg-orange-500 h-1.5 rounded-full" style="width: 45%"></div>
            </div>
        </div>

        <!-- Income -->
        <div class="bg-dark-800/80 backdrop-blur-md rounded-2xl p-6 border border-dark-700/50 shadow-lg relative overflow-hidden group hover:-translate-y-1 transition-transform duration-300">
            <p class="text-sm font-medium text-gray-400 mb-2">Total Income</p>
            <h3 class="text-3xl font-bold text-white mb-1">$<?= number_format($incomeThisMonth, 2) ?></h3>
            <div class="flex items-center text-sm text-brand-400 font-medium">
                Target: $5,000
            </div>
        </div>

        <!-- Expenses -->
        <div class="bg-dark-800/80 backdrop-blur-md rounded-2xl p-6 border border-dark-700/50 shadow-lg relative overflow-hidden group hover:-translate-y-1 transition-transform duration-300">
            <p class="text-sm font-medium text-gray-400 mb-2">Total Expenses</p>
            <h3 class="text-3xl font-bold text-white mb-1">$<?= number_format($expensesThisMonth, 2) ?></h3>
            <div class="flex items-center text-sm text-red-400 font-medium">
                Within budget limit
            </div>
        </div>
    </div>

    <!-- Charts & Lists -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Chart -->
        <div class="lg:col-span-2 bg-dark-800/80 backdrop-blur-md rounded-2xl p-6 border border-dark-700/50 shadow-lg">
            <h3 class="text-lg font-bold text-white mb-6">Expense Breakdown</h3>
            <div class="h-64 flex items-center justify-center relative">
                <canvas id="expenseChart"></canvas>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="bg-dark-800/80 backdrop-blur-md rounded-2xl p-6 border border-dark-700/50 shadow-lg flex flex-col">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-bold text-white">Recent Activity</h3>
                <a href="#" class="text-sm text-brand-400 hover:text-brand-300">View All</a>
            </div>
            <div class="space-y-4 flex-1">
                <!-- Mock item 1 -->
                <div class="flex items-center justify-between p-3 hover:bg-dark-700/30 rounded-xl transition-colors">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-white">Groceries</p>
                            <p class="text-xs text-gray-400">Today</p>
                        </div>
                    </div>
                    <span class="text-sm font-semibold text-white">-$125.50</span>
                </div>
                <!-- Mock item 2 -->
                <div class="flex items-center justify-between p-3 hover:bg-dark-700/30 rounded-xl transition-colors">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-blue-500/10 text-blue-400 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-white">Netflix Sub</p>
                            <p class="text-xs text-gray-400">Yesterday</p>
                        </div>
                    </div>
                    <span class="text-sm font-semibold text-white">-$15.99</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const ctx = document.getElementById('expenseChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Housing', 'Food', 'Transport', 'Utilities', 'Entertainment'],
            datasets: [{
                data: [45, 20, 15, 10, 10],
                backgroundColor: [
                    '#3b82f6', // blue
                    '#10b981', // emerald
                    '#f59e0b', // amber
                    '#8b5cf6', // violet
                    '#ec4899'  // pink
                ],
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
                    position: 'right',
                    labels: { color: '#94a3b8', padding: 20, font: { family: "'Outfit', sans-serif" } }
                }
            }
        }
    });
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
