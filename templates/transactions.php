<?php
require_once __DIR__ . '/../src/Settings.php';
require_once __DIR__ . '/../src/Expense.php';
require_once __DIR__ . '/../src/Category.php';

$m = Helper::parseMonth();
$year = $m['year'];
$month = $m['month'];
$reqMonth = $m['reqMonth'];
$prevMonth = $m['prevMonth'];
$nextMonth = $m['nextMonth'];
$currentDisplay = $m['currentDisplay'];

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

$startingBalance = (float) Settings::get('starting_bank_balance', 0);
$transactions = Expense::getTransactions($month, $year);
$categories = Category::getAll();

ob_start();
?>

<!-- Header -->
<div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h2 class="text-2xl font-bold" style="color:var(--text);">Transactions</h2>
        <p class="mt-0.5 text-sm" style="color:var(--text-secondary);">Record your daily income and expenses.</p>
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

<!-- Forms -->
<div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
    <!-- Add Transaction -->
    <div class="rounded-xl border p-5" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
        <h3 class="mb-4 flex items-center gap-2 text-base font-semibold" style="color:var(--text);">
            <svg class="h-4 w-4" style="color:var(--accent);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Record
        </h3>
        <form method="POST" action="/transactions?month=<?= htmlspecialchars((string)$reqMonth, ENT_QUOTES, 'UTF-8') ?>" class="space-y-3">
            <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
            <input type="hidden" name="action" value="add_transaction">

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Type</label>
                    <select name="type" class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                        <option value="expense">Expense</option>
                        <option value="income">Income</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Amount (RM)</label>
                    <input type="number" step="0.01" min="0.01" name="amount" required placeholder="0.00"
                        class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Category</label>
                    <select name="category_id" required class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                        <option value="">Select...</option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>"><?= htmlspecialchars((string)$cat['name'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Date</label>
                    <input type="date" name="date" required value="<?= htmlspecialchars((string)(date('Y-m') === $reqMonth ? date('Y-m-d') : $reqMonth . '-01'), ENT_QUOTES, 'UTF-8') ?>"
                        class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                </div>
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Description</label>
                <input type="text" name="description" required placeholder="e.g. Lunch with client"
                    class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
            </div>

            <button type="submit" class="w-full rounded-lg py-2.5 text-sm font-semibold text-white" style="background:var(--accent);">
                Save Record
            </button>
        </form>
    </div>

    <!-- Starting Balance -->
    <div class="rounded-xl border p-5" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
        <h3 class="mb-3 flex items-center gap-2 text-base font-semibold" style="color:var(--text);">
            <svg class="h-4 w-4" style="color:var(--accent);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
            Starting Balance
        </h3>
        <p class="mb-4 text-sm" style="color:var(--text-secondary);">Your absolute baseline balance. Set once and leave it — the system handles monthly rollover.</p>
        <form method="POST" action="/transactions?month=<?= htmlspecialchars((string)$reqMonth, ENT_QUOTES, 'UTF-8') ?>" class="space-y-3">
            <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
            <input type="hidden" name="action" value="set_balance">
            <div>
                <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Balance (RM)</label>
                <input type="number" step="0.01" name="starting_balance" value="<?= htmlspecialchars((string)$startingBalance, ENT_QUOTES, 'UTF-8') ?>" required
                    class="w-full rounded-lg border px-3 py-3 text-lg font-bold" style="background:var(--bg);border-color:var(--border);color:var(--text);">
            </div>
            <button type="submit" class="w-full rounded-lg py-2.5 text-sm font-semibold text-white" style="background:var(--accent);">
                Update Baseline
            </button>
        </form>
    </div>
</div>

<!-- Transactions List -->
<?php if (empty($transactions)): ?>
    <div class="rounded-xl border py-16 text-center" style="background:var(--bg-alt);border-color:var(--border);">
        <p class="text-sm" style="color:var(--text-muted);">No transactions for <?= htmlspecialchars((string)$currentDisplay, ENT_QUOTES, 'UTF-8') ?>.</p>
    </div>
<?php else: ?>
    <?php
    $grouped = [];
    foreach ($transactions as $t) { $grouped[$t['date']][] = $t; }
    ?>
    <div class="space-y-3">
        <?php foreach ($grouped as $date => $dayTxs): ?>
            <?php
                $dayInc = array_sum(array_map(fn($t) => $t['type'] === 'income' ? $t['amount'] : 0, $dayTxs));
                $dayExp = array_sum(array_map(fn($t) => $t['type'] === 'expense' ? $t['amount'] : 0, $dayTxs));
            ?>
            <div class="overflow-hidden rounded-xl border" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
                <div class="flex items-center justify-between border-b px-4 py-2.5" style="border-color:var(--border-light);">
                    <div class="flex items-center gap-3">
                        <span class="text-xl font-bold" style="color:var(--text);"><?= date('d', strtotime($date)) ?></span>
                        <span class="text-xs font-semibold" style="color:var(--text-muted);"><?= date('D', strtotime($date)) ?></span>
                        <span class="text-xs" style="color:var(--text-muted);"><?= date('m/Y', strtotime($date)) ?></span>
                    </div>
                    <div class="flex items-center gap-4 text-sm font-semibold">
                        <span style="color:var(--income);">+RM <?= number_format($dayInc, 2) ?></span>
                        <span style="color:var(--expense);">-RM <?= number_format($dayExp, 2) ?></span>
                    </div>
                </div>
                <?php foreach ($dayTxs as $t): ?>
                    <div class="flex items-center justify-between border-b px-4 py-3 last:border-b-0 transition-colors" style="border-color:var(--border-light);" onmouseover="this.style.background='var(--bg-hover)'" onmouseout="this.style.background=''">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <?php if ($t['category_name']): ?>
                                    <span class="h-2 w-2 shrink-0 rounded-full" style="background:<?= htmlspecialchars((string)$t['color_hex'], ENT_QUOTES, 'UTF-8') ?>;"></span>
                                    <span class="text-xs" style="color:var(--text-muted);"><?= htmlspecialchars((string)$t['category_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                <?php else: ?>
                                    <span class="text-xs" style="color:var(--text-muted);">Uncategorized</span>
                                <?php endif; ?>
                            </div>
                            <p class="mt-0.5 truncate text-sm font-medium" style="color:var(--text);"><?= htmlspecialchars((string)$t['description'], ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-bold <?= $t['type'] === 'income' ? '' : '' ?>" style="color:<?= $t['type'] === 'income' ? 'var(--income)' : 'var(--expense)' ?>;">
                                RM <?= number_format($t['amount'], 2) ?>
                            </span>
                            <!-- Actions (visible on hover, always visible on mobile via opacity) -->
                            <div class="flex items-center gap-1 opacity-0 transition-opacity sm:group-hover:opacity-100" style="opacity:0.4;">
                                <button type="button" onclick="openEdit(<?= $t['id'] ?>, '<?= htmlspecialchars((string)$t['date'], ENT_QUOTES, 'UTF-8') ?>', <?= (int)($t['category_id'] ?? 0) ?>, '<?= htmlspecialchars(addslashes((string)$t['description']), ENT_QUOTES, 'UTF-8') ?>', <?= $t['amount'] ?>, '<?= $t['type'] ?>')"
                                    class="rounded p-1 transition-colors" style="color:var(--text-muted);" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--text-muted)'">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                </button>
                                <form method="POST" action="/transactions?month=<?= htmlspecialchars((string)$reqMonth, ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm('Delete this transaction?');" class="inline">
                                    <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                                    <input type="hidden" name="action" value="delete_transaction">
                                    <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                                    <button type="submit" class="rounded p-1 transition-colors" style="color:var(--text-muted);" onmouseover="this.style.color='var(--danger)'" onmouseout="this.style.color='var(--text-muted)'">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4" style="backdrop-filter:blur(4px);">
    <div class="w-full max-w-sm rounded-xl border p-6" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow-lg);">
        <div class="mb-5 flex items-center justify-between">
            <h3 class="text-lg font-semibold" style="color:var(--text);">Edit Transaction</h3>
            <button onclick="closeEdit()" class="rounded p-1" style="color:var(--text-muted);" onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--text-muted)'">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form method="POST" action="/transactions?month=<?= htmlspecialchars((string)$reqMonth, ENT_QUOTES, 'UTF-8') ?>" class="space-y-3">
            <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
            <input type="hidden" name="action" value="edit_transaction">
            <input type="hidden" name="id" id="edit_id">

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Type</label>
                    <select name="type" id="edit_type" class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                        <option value="expense">Expense</option>
                        <option value="income">Income</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Amount (RM)</label>
                    <input type="number" step="0.01" min="0.01" name="amount" id="edit_amount" required
                        class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Category</label>
                    <select name="category_id" id="edit_category_id" required class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                        <option value="">Select...</option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>"><?= htmlspecialchars((string)$cat['name'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Date</label>
                    <input type="date" name="date" id="edit_date" required
                        class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                </div>
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Description</label>
                <input type="text" name="description" id="edit_description" required
                    class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
            </div>

            <button type="submit" class="w-full rounded-lg py-2.5 text-sm font-semibold text-white" style="background:var(--accent);">
                Update Transaction
            </button>
        </form>
    </div>
</div>

<script>
function openEdit(id, date, catId, desc, amount, type) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_date').value = date;
    document.getElementById('edit_category_id').value = catId;
    document.getElementById('edit_description').value = desc;
    document.getElementById('edit_amount').value = amount;
    document.getElementById('edit_type').value = type;
    var m = document.getElementById('editModal');
    m.classList.remove('hidden');
    m.classList.add('flex');
}
function closeEdit() {
    var m = document.getElementById('editModal');
    m.classList.add('hidden');
    m.classList.remove('flex');
}
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEdit();
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
