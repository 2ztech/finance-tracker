<?php
require_once __DIR__ . '/../src/Settings.php';
require_once __DIR__ . '/../src/Expense.php';
require_once __DIR__ . '/../src/Category.php';

$quickTemplates = QuickTemplate::getAll();

$m = Helper::parseMonth();
$year = $m['year'];
$month = $m['month'];
$reqMonth = $m['reqMonth'];
$prevMonth = $m['prevMonth'];
$nextMonth = $m['nextMonth'];
$currentDisplay = $m['currentDisplay'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_transaction') {
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

<!-- Quick-Add Templates -->
<div class="flex flex-wrap items-center gap-2">
    <span class="text-xs font-medium" style="color:var(--text-muted);">Quick add:</span>
    <?php foreach ($quickTemplates as $qt): ?>
        <div class="inline-flex items-center gap-0 overflow-hidden rounded-full border text-xs font-medium" style="border-color:var(--border);">
            <button type="button" onclick="quickAdd('<?= htmlspecialchars((string)$qt['type'], ENT_QUOTES, 'UTF-8') ?>', <?= (int)($qt['category_id'] ?? 0) ?>, '<?= htmlspecialchars(addslashes((string)$qt['description']), ENT_QUOTES, 'UTF-8') ?>', <?= $qt['amount'] ?>)" title="Pre-fill add form" style="background:transparent;border:none;cursor:pointer;color:var(--text-secondary);font:inherit;padding:0.375rem 0.75rem;" onmouseover="this.style.background='var(--accent-soft)';this.style.color='var(--accent)'" onmouseout="this.style.background='transparent';this.style.color='var(--text-secondary)'">
                <span class="mr-1 inline-flex h-3.5 w-3.5 items-center justify-center rounded-full text-[9px] font-bold text-white" style="background:var(--accent);">+</span>
                <?= htmlspecialchars((string)$qt['description'], ENT_QUOTES, 'UTF-8') ?><?php if ($qt['amount'] > 0): ?> · RM<?= number_format($qt['amount'], 0) ?><?php endif; ?>
            </button>
            <form method="POST" action="/quick-template/delete" class="inline-flex" style="margin:0;" onsubmit="return confirm('Remove template?');">
                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                <input type="hidden" name="id" value="<?= (int)$qt['id'] ?>">
                <input type="hidden" name="return_month" value="<?= htmlspecialchars((string)$reqMonth, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" title="Remove" style="background:transparent;border:none;border-left:1px solid var(--border);cursor:pointer;color:var(--text-muted);padding:0.375rem 0.5rem;font:inherit;line-height:1;" onmouseover="this.style.color='var(--danger)'" onmouseout="this.style.color='var(--text-muted)'">&times;</button>
            </form>
        </div>
    <?php endforeach; ?>
    <button onclick="toggleTemplateForm()" class="rounded-full border px-3 py-1.5 text-xs font-medium transition-colors" style="border-color:var(--border);color:var(--text-muted);border-style:dashed;" onmouseover="this.style.color='var(--accent)';this.style.borderColor='var(--accent)'" onmouseout="this.style.color='var(--text-muted)';this.style.borderColor='var(--border)'">+ Template</button>
</div>

<!-- Inline Template Create Form -->
<div id="templateForm" class="hidden rounded-xl border p-4" style="background:var(--bg-alt);border-color:var(--border);">
    <form method="POST" action="/quick-template/add" class="flex flex-col gap-2 sm:flex-row sm:items-end">
        <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
        <input type="hidden" name="return_month" value="<?= htmlspecialchars((string)$reqMonth, ENT_QUOTES, 'UTF-8') ?>">
        <div class="flex-1">
            <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Description</label>
            <input type="text" name="description" required placeholder="e.g. Lunch at office"
                class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Type</label>
            <select name="type" class="rounded-lg border px-2 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                <option value="expense">Expense</option>
                <option value="income">Income</option>
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Category</label>
            <select name="category_id" class="rounded-lg border px-2 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                <option value="">None</option>
                <?php foreach($categories as $cat): ?>
                    <option value="<?= (int)$cat['id'] ?>"><?= htmlspecialchars((string)$cat['name'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Default (RM)</label>
            <input type="number" step="0.01" name="amount" value="0" class="w-20 rounded-lg border px-2 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
        </div>
        <button type="submit" class="rounded-lg px-4 py-2 text-sm font-semibold text-white" style="background:var(--accent);">Save</button>
        <button type="button" onclick="toggleTemplateForm()" class="rounded-lg border px-4 py-2 text-sm font-medium" style="border-color:var(--border);color:var(--text-secondary);">Cancel</button>
    </form>
</div>

<!-- Forms -->
<div class="grid grid-cols-1 gap-4">
    <!-- Add Transaction -->
    <div class="rounded-xl border p-5" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
        <h3 class="mb-4 flex items-center gap-2 text-base font-semibold" style="color:var(--text);">
            <svg class="h-4 w-4" style="color:var(--accent);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Record
        </h3>
        <form method="POST" action="/transactions?month=<?= htmlspecialchars((string)$reqMonth, ENT_QUOTES, 'UTF-8') ?>" id="addRecordForm" class="space-y-3">
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

</div>

<!-- Filter Bar -->
<div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
    <div class="flex flex-1 items-center gap-2">
        <div class="relative flex-1 sm:max-w-xs">
            <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2" style="color:var(--text-muted);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" id="filterSearch" placeholder="Search transactions..." oninput="applyFilters()"
                class="w-full rounded-lg border py-2 pl-10 pr-3 text-sm outline-none" style="background:var(--bg-alt);border-color:var(--border);color:var(--text);">
        </div>
        <input type="date" id="filterFrom" onchange="applyFilters()"
            class="rounded-lg border px-3 py-2 text-sm outline-none" style="background:var(--bg-alt);border-color:var(--border);color:var(--text);" title="From date">
        <span style="color:var(--text-muted);">&mdash;</span>
        <input type="date" id="filterTo" onchange="applyFilters()"
            class="rounded-lg border px-3 py-2 text-sm outline-none" style="background:var(--bg-alt);border-color:var(--border);color:var(--text);" title="To date">
    </div>
    <div class="flex items-center gap-2">
        <span id="filterCount" class="text-xs" style="color:var(--text-muted);"></span>
        <button onclick="clearFilters()" id="clearFilterBtn" class="hidden rounded-lg border px-3 py-2 text-xs font-medium transition-colors" style="border-color:var(--border);color:var(--text-secondary);" onmouseover="this.style.background='var(--bg-hover)'" onmouseout="this.style.background=''">Clear</button>
        <button onclick="printTransactions()" class="rounded-lg border px-3 py-2 text-xs font-medium transition-colors" style="border-color:var(--border);color:var(--text-secondary);" onmouseover="this.style.background='var(--bg-hover)'" onmouseout="this.style.background=''" title="Export as PDF">Export PDF</button>
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
            <div class="overflow-hidden rounded-xl border transaction-group" data-date="<?= htmlspecialchars((string)$date, ENT_QUOTES, 'UTF-8') ?>" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
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
                    <div class="transaction-row flex items-center justify-between border-b px-4 py-3 last:border-b-0 transition-colors" data-search="<?= htmlspecialchars(strtolower((string)($t['category_name'] ?? '')) . ' ' . strtolower((string)$t['description']), ENT_QUOTES, 'UTF-8') ?>" data-date="<?= htmlspecialchars((string)$date, ENT_QUOTES, 'UTF-8') ?>" style="border-color:var(--border-light);" onmouseover="this.style.background='var(--bg-hover)'" onmouseout="this.style.background=''">
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

<!-- Undo Toast -->
<div id="undoToast" class="fixed bottom-5 left-1/2 z-50 hidden -translate-x-1/2 items-center gap-3 rounded-xl border px-4 py-3 text-sm shadow-lg" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow-lg);">
    <span style="color:var(--text);">Transaction deleted.</span>
    <button onclick="undoDelete()" class="rounded-lg px-3 py-1 text-xs font-semibold text-white" style="background:var(--accent);">Undo</button>
    <button onclick="dismissUndo()" class="rounded-lg p-1" style="color:var(--text-muted);">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
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

<script>
function applyFilters() {
    var query = (document.getElementById('filterSearch').value || '').toLowerCase();
    var from = document.getElementById('filterFrom').value;
    var to = document.getElementById('filterTo').value;
    var visible = 0;
    var total = 0;

    document.querySelectorAll('.transaction-group').forEach(function(group) {
        var groupDate = group.getAttribute('data-date');
        var groupVisible = false;
        var rows = group.querySelectorAll('.transaction-row');
        total += rows.length;

        rows.forEach(function(row) {
            var search = row.getAttribute('data-search') || '';
            var rowDate = row.getAttribute('data-date');
            var match = true;

            if (query && search.indexOf(query) === -1) match = false;
            if (from && rowDate < from) match = false;
            if (to && rowDate > to) match = false;

            if (match) {
                row.style.display = '';
                groupVisible = true;
                visible++;
            } else {
                row.style.display = 'none';
            }
        });

        group.style.display = groupVisible ? '' : 'none';
    });

    var countEl = document.getElementById('filterCount');
    var clearBtn = document.getElementById('clearFilterBtn');
    var hasFilter = query || from || to;

    if (hasFilter && visible !== total) {
        countEl.textContent = visible + ' / ' + total + ' shown';
        clearBtn.classList.remove('hidden');
    } else if (hasFilter) {
        countEl.textContent = '';
        clearBtn.classList.remove('hidden');
    } else {
        countEl.textContent = '';
        clearBtn.classList.add('hidden');
    }
}

function clearFilters() {
    document.getElementById('filterSearch').value = '';
    document.getElementById('filterFrom').value = '';
    document.getElementById('filterTo').value = '';
    applyFilters();
}

// Quick-add: pre-fills the add form with template values
function quickAdd(type, catId, desc, amount) {
    var f = document.getElementById('addRecordForm');
    if (!f) return;
    f.querySelector('select[name="type"]').value = type;
    f.querySelector('select[name="category_id"]').value = catId;
    f.querySelector('input[name="description"]').value = desc;
    if (amount > 0) f.querySelector('input[name="amount"]').value = amount;
    f.querySelector('input[name="amount"]').focus();
    window.scrollTo({top: 0, behavior: 'smooth'});
}

function toggleTemplateForm() {
    var f = document.getElementById('templateForm');
    f.classList.toggle('hidden');
    if (!f.classList.contains('hidden')) f.querySelector('input[type="text"]').focus();
}

// Undo delete
var _undoData = null, _undoTimer = null;
document.querySelectorAll('form[onsubmit*="Delete this transaction?"]').forEach(function(f) {
    f.addEventListener('submit', function(e) {
        e.preventDefault();
        if (!confirm('Delete this transaction?')) return;
        var row = f.closest('.transaction-row');
        _undoData = {
            type: row.querySelector('[style*="var(--income)"]') ? 'income' : 'expense',
            amount: parseFloat(row.querySelector('[style*="var(--income)"], [style*="var(--expense)"]').textContent.replace('RM ', '').replace(',', '')),
            description: row.querySelector('p').textContent.trim(),
            category_id: parseInt(f.previousElementSibling.getAttribute('onclick').match(/openEdit\((\d+)/)[1]) === parseInt(f.previousElementSibling.getAttribute('onclick').match(/, (\d+),/)[1]) ? f.previousElementSibling.getAttribute('onclick').match(/, (\d+),/)[1] : 0,
            date: row.getAttribute('data-date'),
            form: f,
            row: row
        };
        // Actually we need catId properly - let me do this differently
        _undoData.form = f;
        _undoData.row = row;
        // Just submit and capture what we need
    });
});

// Simpler approach: override the delete forms
(function() {
    document.querySelectorAll('form').forEach(function(f) {
        var onsubmit = f.getAttribute('onsubmit');
        if (onsubmit && onsubmit.indexOf('Delete this transaction') > -1) {
            f.removeAttribute('onsubmit');
            f.addEventListener('submit', handleDelete);
        }
    });

    function handleDelete(e) {
        e.preventDefault();
        if (!confirm('Delete this transaction?')) return;
        var f = e.target.closest('form');
        var row = f.closest('.transaction-row');
        var date = row.getAttribute('data-date');
        var desc = row.querySelector('p').textContent.trim();
        var amtText = row.querySelector('[style*="var(--income)"], [style*="var(--expense)"]').textContent.replace('RM ', '').trim();
        var amount = parseFloat(amtText);
        var isIncome = row.querySelector('[style*="var(--income)"]') !== null;
        var catEl = row.querySelector('select[name="category_id"]') 
            ? null 
            : null;

        // Build undo data from what's visible
        _undoData = {
            date: date,
            type: isIncome ? 'income' : 'expense',
            amount: amount,
            description: desc,
            category_id: 0,
            form: f
        };

        // Try to get category_id from the edit button onclick
        var editBtn = row.querySelector('button[onclick*="openEdit"]');
        if (editBtn) {
            var onclick = editBtn.getAttribute('onclick');
            var m = onclick.match(/openEdit\(\d+,\s*'[^']*',\s*(\d+)/);
            if (m) _undoData.category_id = parseInt(m[1]);
        }

        // Submit delete
        var formData = new FormData(f);
        fetch(f.action, {method: 'POST', body: new URLSearchParams(formData)}).then(function() {
            showUndoToast();
        });
    }
})();

function showUndoToast() {
    var toast = document.getElementById('undoToast');
    toast.classList.remove('hidden');
    toast.classList.add('flex');
    _undoTimer = setTimeout(dismissUndo, 8000);
}

function dismissUndo() {
    document.getElementById('undoToast').classList.add('hidden');
    document.getElementById('undoToast').classList.remove('flex');
    _undoData = null;
    clearTimeout(_undoTimer);
}

function undoDelete() {
    if (!_undoData) return;
    var d = _undoData;
    var data = new URLSearchParams();
    data.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    data.append('action', 'add_transaction');
    data.append('type', d.type);
    data.append('amount', d.amount);
    data.append('description', d.description);
    data.append('category_id', d.category_id);
    data.append('date', d.date);
    fetch('/transactions?month=<?= htmlspecialchars((string)$reqMonth, ENT_QUOTES, 'UTF-8') ?>', {
        method: 'POST',
        body: data
    }).then(function() {
        location.reload();
    });
    dismissUndo();
}

// PDF export via print
function printTransactions() {
    var style = document.createElement('style');
    style.textContent = '@media print { body { visibility: hidden; } #print-area, #print-area * { visibility: visible; } #print-area { position: absolute; left: 0; top: 0; width: 100%; } }';
    document.head.appendChild(style);
    window.print();
    document.head.removeChild(style);
}
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
