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
        } elseif ($_POST['action'] === 'edit_transaction') {
            $id = (int)($_POST['id'] ?? 0);
            $catId = (int)($_POST['category_id'] ?? 0);
            $amount = (float)($_POST['amount'] ?? 0);
            $type = $_POST['type'] ?? 'expense';
            $description = trim($_POST['description'] ?? '');
            $date = $_POST['date'] ?? date('Y-m-d');
            
            if ($id > 0 && $catId > 0 && $amount > 0 && $description && $date) {
                Expense::updateTransaction($id, $catId, $amount, $type, $description, $date);
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

    <!-- Transactions List -->
    <div class="mb-8">
        <?php if(empty($transactions)): ?>
            <div class="bg-dark-900 border border-dark-800 rounded-2xl p-12 text-center text-gray-500 shadow-lg">
                <div class="flex flex-col items-center gap-2">
                    <svg class="w-8 h-8 text-dark-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    <span>No transactions recorded for <?= htmlspecialchars((string)$currentDisplay, ENT_QUOTES, 'UTF-8') ?>.</span>
                </div>
            </div>
        <?php else: ?>
            <?php 
            $groupedTransactions = [];
            foreach ($transactions as $t) {
                $groupedTransactions[$t['date']][] = $t;
            }
            ?>
            <div class="space-y-4">
                <?php foreach ($groupedTransactions as $date => $dayTransactions): ?>
                    <div class="bg-dark-900 border border-dark-800 rounded-xl overflow-hidden shadow-sm">
                        <!-- Date Header -->
                        <?php
                            $dayIncome = array_sum(array_map(fn($t) => $t['type'] === 'income' ? $t['amount'] : 0, $dayTransactions));
                            $dayExpense = array_sum(array_map(fn($t) => $t['type'] === 'expense' ? $t['amount'] : 0, $dayTransactions));
                        ?>
                        <div class="px-4 py-3 border-b border-dark-800/80 flex justify-between items-center bg-dark-900/50">
                            <div class="flex items-center gap-3">
                                <span class="text-2xl font-bold text-white leading-none"><?= date('d', strtotime($date)) ?></span>
                                <span class="text-xs font-bold text-dark-900 px-2 py-0.5 rounded-full tracking-wide" style="background-color: #d8b4fe;"><?= date('D', strtotime($date)) ?></span>
                                <span class="text-sm text-gray-400 font-medium"><?= date('m/Y', strtotime($date)) ?></span>
                            </div>
                            <div class="flex items-center gap-6 text-[15px] font-semibold tracking-tight">
                                <?php if ($dayIncome > 0): ?>
                                    <span class="text-blue-400">RM <?= number_format($dayIncome, 2) ?></span>
                                <?php else: ?>
                                    <span class="text-blue-400">RM 0.00</span>
                                <?php endif; ?>
                                
                                <?php if ($dayExpense > 0): ?>
                                    <span class="text-red-400">RM <?= number_format($dayExpense, 2) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- List Items -->
                        <div class="divide-y divide-dark-800/50">
                            <?php foreach ($dayTransactions as $t): ?>
                                <div class="px-4 py-3.5 flex items-center justify-between hover:bg-dark-800/30 transition-colors group relative">
                                    <div class="flex flex-row items-center min-w-0 flex-1 gap-2">
                                        <!-- Left Column: Category -->
                                        <div class="w-20 shrink-0 flex items-center gap-1.5 overflow-hidden">
                                            <span class="text-xs font-medium text-gray-400 truncate"><?= htmlspecialchars_decode((string)($t['category_name'] ?? 'Uncategorized'), ENT_QUOTES) ?></span>
                                        </div>
                                        
                                        <!-- Middle Column: Description -->
                                        <div class="flex flex-col min-w-0 flex-1">
                                            <div class="text-[15px] font-bold text-gray-200 truncate leading-tight">
                                                <?= htmlspecialchars((string)$t['description'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                            <div class="text-[11px] text-gray-500 truncate mt-0.5">
                                                Cash
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Right Column: Amount & Actions -->
                                    <div class="flex items-center gap-3 shrink-0 ml-4">
                                        <div class="text-[15px] font-bold text-right tracking-tight <?= $t['type'] === 'income' ? 'text-blue-400' : 'text-red-400' ?>">
                                            RM <?= number_format($t['amount'], 2) ?>
                                        </div>
                                        
                                        <!-- Subtle Hover Actions -->
                                        <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity absolute right-4 bg-dark-900/95 shadow-lg border border-dark-700/50 rounded-lg px-1 py-0.5 z-10 hidden sm:flex">
                                            <button type="button" onclick="openEditModal(<?= $t['id'] ?>, '<?= htmlspecialchars((string)$t['date'], ENT_QUOTES, 'UTF-8') ?>', <?= (int)($t['category_id'] ?? 0) ?>, '<?= htmlspecialchars(str_replace("'", "\'", (string)$t['description']), ENT_QUOTES, 'UTF-8') ?>', <?= $t['amount'] ?>, '<?= $t['type'] ?>')" class="text-gray-400 hover:text-brand-400 p-1.5 rounded-md hover:bg-dark-800 transition-colors" title="Edit">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                            </button>
                                            <form method="POST" action="/transactions?month=<?= htmlspecialchars((string)$reqMonth, ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm('Delete this transaction?');" class="inline">
                                                <input type="hidden" name="action" value="delete_transaction">
                                                <input type="hidden" name="id" value="<?= htmlspecialchars((string)$t['id'], ENT_QUOTES, 'UTF-8') ?>">
                                                <button type="submit" class="text-gray-400 hover:text-red-400 p-1.5 rounded-md hover:bg-dark-800 transition-colors" title="Delete">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
</div>

<!-- Edit Transaction Modal -->
<div id="editModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4 opacity-0 transition-opacity duration-300">
    <div class="bg-dark-800/90 border border-dark-700/50 rounded-2xl p-6 shadow-2xl w-full max-w-md transform scale-95 transition-transform duration-300">
        <div class="flex justify-between items-center mb-5">
            <h3 class="text-xl font-bold text-white flex items-center gap-2">
                <svg class="w-5 h-5 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                Edit Transaction
            </h3>
            <button onclick="closeEditModal()" class="text-gray-400 hover:text-white p-1 rounded-lg hover:bg-dark-700 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form method="POST" action="/transactions?month=<?= htmlspecialchars((string)$reqMonth, ENT_QUOTES, 'UTF-8') ?>" class="space-y-4">
            <input type="hidden" name="action" value="edit_transaction">
            <input type="hidden" name="id" id="edit_id" value="">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-400 mb-1 ml-1">Type</label>
                    <select name="type" id="edit_type" class="w-full px-3 py-2 bg-dark-900 border border-dark-600 rounded-lg text-white text-sm outline-none focus:border-brand-500 transition-colors">
                        <option value="expense">Expense</option>
                        <option value="income">Income</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-400 mb-1 ml-1">Amount (RM)</label>
                    <input type="number" step="0.01" min="0.01" name="amount" id="edit_amount" required placeholder="0.00"
                        class="w-full px-3 py-2 bg-dark-900 border border-dark-600 rounded-lg text-white text-sm outline-none focus:border-brand-500 transition-colors">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-400 mb-1 ml-1">Category</label>
                    <select name="category_id" id="edit_category_id" required class="w-full px-3 py-2 bg-dark-900 border border-dark-600 rounded-lg text-white text-sm outline-none focus:border-brand-500 transition-colors">
                        <option value="">Select Category...</option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?= htmlspecialchars((string)$cat['id'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$cat['name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars(ucfirst((string)$cat['type']), ENT_QUOTES, 'UTF-8') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-400 mb-1 ml-1">Date</label>
                    <input type="date" name="date" id="edit_date" required
                        class="w-full px-3 py-2 bg-dark-900 border border-dark-600 rounded-lg text-white text-sm outline-none focus:border-brand-500 transition-colors">
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-400 mb-1 ml-1">Description</label>
                <input type="text" name="description" id="edit_description" required placeholder="e.g. Lunch at Cafe"
                    class="w-full px-3 py-2 bg-dark-900 border border-dark-600 rounded-lg text-white text-sm outline-none focus:border-brand-500 transition-colors">
            </div>
            <div class="pt-2">
                <button type="submit" class="w-full bg-brand-500 hover:bg-brand-400 text-white font-semibold py-2.5 px-4 rounded-lg transition-colors text-sm shadow-md">
                    Update Transaction
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openEditModal(id, date, category_id, description, amount, type) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_date').value = date;
        document.getElementById('edit_category_id').value = category_id;
        document.getElementById('edit_description').value = description;
        document.getElementById('edit_amount').value = amount;
        document.getElementById('edit_type').value = type;

        const modal = document.getElementById('editModal');
        const modalContent = modal.firstElementChild;
        modal.classList.remove('hidden');
        
        // Trigger reflow
        void modal.offsetWidth;
        
        modal.classList.remove('opacity-0');
        modalContent.classList.remove('scale-95');
    }

    function closeEditModal() {
        const modal = document.getElementById('editModal');
        const modalContent = modal.firstElementChild;
        modal.classList.add('opacity-0');
        modalContent.classList.add('scale-95');
        
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
