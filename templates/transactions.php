<?php
// templates/transactions.php
require_once __DIR__ . '/../src/Settings.php';
require_once __DIR__ . '/../src/Expense.php';
require_once __DIR__ . '/../src/Category.php';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'set_balance') {
            $balance = (float) ($_POST['starting_balance'] ?? 0);
            Settings::set('starting_bank_balance', (string)$balance);
        } elseif ($_POST['action'] === 'add_transaction') {
            $catId = (int)($_POST['category_id'] ?? 0);
            $amount = (float)($_POST['amount'] ?? 0);
            $type = $_POST['type'] ?? 'expense';
            $description = trim($_POST['description'] ?? '');
            $date = $_POST['date'] ?? date('Y-m-d');
            
            if ($catId > 0 && $amount > 0 && $description && $date) {
                Expense::addTransaction($catId, $amount, $type, $description, $date);
            }
        } elseif ($_POST['action'] === 'delete_transaction') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                Expense::deleteTransaction($id);
            }
        }
        header("Location: /transactions?month=" . urlencode($reqMonth));
        exit;
    }
}

$prevMonth = date('Y-m', strtotime($reqMonth . '-01 -1 month'));
$nextMonth = date('Y-m', strtotime($reqMonth . '-01 +1 month'));
$currentDisplay = date('F Y', strtotime($reqMonth . '-01'));

$startingBalance = (float) Settings::get('starting_bank_balance', 0);
$transactions = Expense::getTransactions($month, $year);
$categories = Category::getAll();

ob_start();
?>
<div>
    <!-- dynamic month navigation & header -->
    <div class="mb-8 flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h2 class="text-3xl font-bold tracking-tight mb-1 text-white">Transactions</h2>
            <p class="text-gray-400">Manage your daily records and initialize your bank balance mapping.</p>
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

    <!-- Forms Section: Data Entry & Starting Balance -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Add Transaction Form -->
        <div class="bg-dark-800/80 backdrop-blur-md rounded-2xl p-6 border border-dark-700/50 shadow-lg relative overflow-hidden flex flex-col justify-center">
            <h3 class="text-xl font-bold text-white mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Add Daily Record
            </h3>
            <form method="POST" action="/transactions?month=<?= htmlspecialchars((string)$reqMonth, ENT_QUOTES, 'UTF-8') ?>" class="space-y-4">
                <input type="hidden" name="action" value="add_transaction">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-400 mb-1 ml-1">Type</label>
                        <select name="type" class="w-full px-3 py-2 bg-dark-900 border border-dark-600 rounded-lg text-white text-sm outline-none focus:border-brand-500 transition-colors">
                            <option value="expense">Expense</option>
                            <option value="income">Income</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-400 mb-1 ml-1">Amount (RM)</label>
                        <input type="number" step="0.01" min="0.01" name="amount" required placeholder="0.00"
                            class="w-full px-3 py-2 bg-dark-900 border border-dark-600 rounded-lg text-white text-sm outline-none focus:border-brand-500 transition-colors">
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-400 mb-1 ml-1">Category</label>
                        <select name="category_id" required class="w-full px-3 py-2 bg-dark-900 border border-dark-600 rounded-lg text-white text-sm outline-none focus:border-brand-500 transition-colors">
                            <option value="">Select Category...</option>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?= htmlspecialchars((string)$cat['id'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$cat['name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars(ucfirst((string)$cat['type']), ENT_QUOTES, 'UTF-8') ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-400 mb-1 ml-1">Date</label>
                        <input type="date" name="date" required value="<?= htmlspecialchars((string)(date('Y-m') === $reqMonth ? date('Y-m-d') : $reqMonth . '-01'), ENT_QUOTES, 'UTF-8') ?>"
                            class="w-full px-3 py-2 bg-dark-900 border border-dark-600 rounded-lg text-white text-sm outline-none focus:border-brand-500 transition-colors">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-400 mb-1 ml-1">Description</label>
                    <input type="text" name="description" required placeholder="e.g. Lunch at Cafe"
                        class="w-full px-3 py-2 bg-dark-900 border border-dark-600 rounded-lg text-white text-sm outline-none focus:border-brand-500 transition-colors">
                </div>
                <button type="submit" class="w-full bg-brand-500 hover:bg-brand-400 text-white font-semibold py-2.5 px-4 rounded-lg transition-colors text-sm shadow-md mt-2">
                    Save Record
                </button>
            </form>
        </div>

        <!-- Starting Balance Form -->
        <div class="bg-dark-800/80 backdrop-blur-md rounded-2xl p-6 border border-dark-700/50 shadow-lg flex flex-col justify-center">
            <h3 class="text-xl font-bold text-white mb-2 flex items-center gap-2">
                <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                Absolute Initial Balance Baseline
            </h3>
            <p class="text-sm text-gray-400 mb-6">Set your baseline income/balance before tracking starts. Do not change this month-to-month. System rolls over correctly.</p>
            <form method="POST" action="/transactions?month=<?= htmlspecialchars((string)$reqMonth, ENT_QUOTES, 'UTF-8') ?>" class="space-y-4">
                <input type="hidden" name="action" value="set_balance">
                <div>
                    <label class="block text-xs font-medium text-gray-400 mb-1 ml-1">Initial Balance (RM)</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-gray-500 font-bold">RM</span>
                        <input type="number" step="0.01" name="starting_balance" value="<?= htmlspecialchars((string)$startingBalance, ENT_QUOTES, 'UTF-8') ?>" required
                            class="w-full pl-12 pr-4 py-4 bg-dark-900 border border-dark-600 rounded-xl text-white outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500/50 text-xl font-bold shadow-inner transition-all">
                    </div>
                </div>
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-semibold py-3 px-4 rounded-xl transition-colors text-sm shadow-md mt-2">
                    Update Absolute Baseline
                </button>
            </form>
        </div>
    </div>

    <!-- Transactions Table -->
    <div class="bg-dark-800/80 backdrop-blur-md rounded-2xl border border-dark-700/50 shadow-lg overflow-hidden flex flex-col">
        <div class="p-6 border-b border-dark-700/50 flex justify-between items-center">
            <h3 class="text-lg font-bold text-white">Monthly Transactions Data</h3>
            <span class="text-sm text-gray-400 font-medium px-3 py-1 bg-dark-900 rounded-lg">
                <?= htmlspecialchars((string)date('F Y', mktime(0,0,0,$month,1,$year)), ENT_QUOTES, 'UTF-8') ?>
            </span>
        </div>
        <div class="overflow-x-auto flex-1 p-0">
            <table class="w-full text-left text-sm text-gray-400">
                <thead class="text-xs text-gray-500 uppercase bg-dark-900/50 border-b border-dark-700/50">
                    <tr>
                        <th class="px-6 py-4 font-semibold tracking-wider">Date</th>
                        <th class="px-6 py-4 font-semibold tracking-wider">Category</th>
                        <th class="px-6 py-4 font-semibold tracking-wider">Description</th>
                        <th class="px-6 py-4 font-semibold tracking-wider text-right">Amount (RM)</th>
                        <th class="px-4 py-4 font-semibold tracking-wider text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-dark-700/30">
                    <?php if(empty($transactions)): ?>
                        <tr><td colspan="5" class="px-6 py-12 text-center text-gray-500">
                            <div class="flex flex-col items-center gap-2">
                                <svg class="w-8 h-8 text-dark-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                <span>No transactions recorded for <?= htmlspecialchars((string)$currentDisplay, ENT_QUOTES, 'UTF-8') ?>.</span>
                            </div>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach($transactions as $t): ?>
                            <tr class="hover:bg-dark-700/20 transition-colors group">
                                <td class="px-6 py-4 whitespace-nowrap text-gray-300"><?= htmlspecialchars((string)$t['date'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-2.5 h-2.5 rounded-full" style="background-color: <?= htmlspecialchars((string)($t['color_hex'] ?? '#ccc'), ENT_QUOTES, 'UTF-8') ?>"></div>
                                        <span class="font-medium text-gray-300"><?= htmlspecialchars((string)($t['category_name'] ?? 'Uncategorized'), ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-gray-300"><?= htmlspecialchars((string)$t['description'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-6 py-4 text-right font-medium text-base <?= $t['type'] === 'income' ? 'text-brand-400' : 'text-gray-100' ?>">
                                    <?= $t['type'] === 'income' ? '+' : '-' ?><?= number_format($t['amount'], 2) ?>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <form method="POST" action="/transactions?month=<?= htmlspecialchars((string)$reqMonth, ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm('Delete this transaction?');">
                                        <input type="hidden" name="action" value="delete_transaction">
                                        <input type="hidden" name="id" value="<?= htmlspecialchars((string)$t['id'], ENT_QUOTES, 'UTF-8') ?>">
                                        <button type="submit" class="text-gray-600 hover:text-red-400 p-2 rounded-lg hover:bg-red-500/10 transition-colors inline-block opacity-0 group-hover:opacity-100 focus:opacity-100">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
