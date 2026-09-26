<?php
require_once __DIR__ . '/../src/Settings.php';
require_once __DIR__ . '/../src/Expense.php';
require_once __DIR__ . '/../src/Category.php';
require_once __DIR__ . '/../src/Account.php';
require_once __DIR__ . '/../src/Transfer.php';

$account = Account::active();
$accountId = $account ? (int) $account['id'] : null;
$isLiability = $account ? Account::isLiability($account) : false;
$accounts = Account::all();

$quickTemplates = QuickTemplate::getAll($accountId);

$m = Helper::parseMonth();
$year = $m['year'];
$month = $m['month'];
$reqMonth = $m['reqMonth'];
$prevMonth = $m['prevMonth'];
$nextMonth = $m['nextMonth'];
$currentDisplay = $m['currentDisplay'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    if ($action === 'add_transaction') {
        $catId = (int)($_POST['category_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);
        $type = $_POST['type'] ?? 'expense';
        $description = trim($_POST['description'] ?? '');
        $date = $_POST['date'] ?? date('Y-m-d');
        $accId = (int)($_POST['account_id'] ?? 0);
        $accId = $accId > 0 ? $accId : $accountId;
        $targetAcc = $accId ? Account::find((int) $accId) : null;
        $repayMode = $_POST['repay_mode'] ?? '';
        if ($catId > 0 && $amount > 0 && $description && $date) {
            if ($targetAcc !== null && $targetAcc['kind'] === 'paylater' && $type === 'expense' && $repayMode !== '') {
                $months = (int)($_POST['months'] ?? 1);
                $cash = ($_POST['cash_price'] ?? '') !== '' ? (float) $_POST['cash_price'] : null;
                if ($repayMode === 'full') {
                    $total = $amount;
                    $months = 1;
                    $offset = 1;
                } else {
                    $months = max(1, min(60, $months));
                    $total = round($amount * $months, 2);
                    $offset = 0;
                }
                PaylaterPlan::createPurchase((int) $accId, $catId, $description, $date, $total, $months, $cash, $offset);
            } else {
                Expense::addTransaction($catId, $amount, $type, $description, $date, $accId);
            }
        }
    } elseif ($action === 'edit_transaction') {
        $id = (int)($_POST['id'] ?? 0);
        $catId = (int)($_POST['category_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);
        $type = $_POST['type'] ?? 'expense';
        $description = trim($_POST['description'] ?? '');
        $date = $_POST['date'] ?? date('Y-m-d');
        $accId = (int)($_POST['account_id'] ?? 0);
        if ($id > 0 && $catId > 0 && $amount > 0 && $description && $date) {
            Expense::updateTransaction($id, $catId, $amount, $type, $description, $date, $accId > 0 ? $accId : $accountId);
        }
    } elseif ($action === 'delete_transaction') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            Expense::deleteTransaction($id);
        }
    }
    header("Location: /transactions?month=" . urlencode($reqMonth));
    exit;
}

$startDate = "$year-$month-01";
$endDate = date('Y-m-t', strtotime($startDate));

$transactions = Expense::getTransactions($month, $year, $accountId);
$categories = Category::getAll();
$transfers = $accountId ? Transfer::forAccountBetween($accountId, $startDate, $endDate) : [];

// Merge into a single dated ledger
$entries = [];
foreach ($transactions as $t) {
    $entries[] = ['date' => $t['date'], 'ord' => 't' . str_pad((string) $t['id'], 10, '0', STR_PAD_LEFT), 'kind' => 'txn', 'data' => $t];
}
foreach ($transfers as $tr) {
    $dir = ((int) $tr['from_account_id'] === $accountId) ? 'out' : 'in';
    $entries[] = ['date' => $tr['date'], 'ord' => 'x' . str_pad((string) $tr['id'], 10, '0', STR_PAD_LEFT), 'kind' => 'transfer', 'data' => $tr, 'dir' => $dir];
}
usort($entries, fn($a, $b) => strcmp($b['date'] . $b['ord'], $a['date'] . $a['ord']));

// Print statement data (savings only), chronological with running balance
$openingBal = 0.0;
if ($accountId && !$isLiability) {
    $openingBal = Account::balance($accountId, date('Y-m-d', strtotime($startDate . ' -1 day')));
}
$chrono = $entries;
usort($chrono, fn($a, $b) => strcmp($a['date'] . $a['ord'], $b['date'] . $b['ord']));
$runningBal = $openingBal;
$printRows = [];
foreach ($chrono as $e) {
    if ($e['kind'] === 'txn') {
        $t = $e['data'];
        $isInc = $t['type'] === 'income';
        $amt = (float) $t['amount'];
        $debit = $isInc ? '' : number_format($amt, 2);
        $credit = $isInc ? number_format($amt, 2) : '';
        $runningBal += $isInc ? $amt : -$amt;
        $desc = $t['description'];
    } else {
        $tr = $e['data'];
        $amt = (float) $tr['amount'];
        if ($e['dir'] === 'out') {
            $debit = number_format($amt, 2); $credit = ''; $runningBal -= $amt;
            $desc = 'Transfer to ' . ($tr['to_name'] ?? 'account') . ($tr['description'] ? ' — ' . $tr['description'] : '');
        } else {
            $debit = ''; $credit = number_format($amt, 2); $runningBal += $amt;
            $desc = 'Transfer from ' . ($tr['from_name'] ?? 'account') . ($tr['description'] ? ' — ' . $tr['description'] : '');
        }
    }
    $printRows[] = ['date' => date('d/m/Y', strtotime($e['date'])), 'desc' => $desc, 'debit' => $debit, 'credit' => $credit, 'balance' => number_format($runningBal, 2)];
}

ob_start();
?>

<!-- Header -->
<div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h2 class="text-2xl font-bold" style="color:var(--text);">Transactions</h2>
        <p class="mt-0.5 text-sm" style="color:var(--text-secondary);">
            <?= $account ? htmlspecialchars((string) $account['name'], ENT_QUOTES, 'UTF-8') : 'No account' ?>
            <?= $isLiability ? ' · purchases &amp; payments' : '' ?>
        </p>
    </div>
    <div class="flex items-center gap-2">
        <button onclick="toggleTransferForm()" class="rounded-lg border px-3 py-1.5 text-sm font-medium" style="border-color:var(--border);color:var(--text-secondary);">Transfer</button>
        <div class="flex items-center rounded-lg border p-0.5 text-sm" style="background:var(--bg-alt);border-color:var(--border);">
            <a href="?month=<?= htmlspecialchars((string)$prevMonth, ENT_QUOTES, 'UTF-8') ?>" class="rounded-md px-3 py-1.5" style="color:var(--text-secondary);">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <span class="min-w-[120px] px-3 text-center font-semibold" style="color:var(--text);"><?= htmlspecialchars((string)$currentDisplay, ENT_QUOTES, 'UTF-8') ?></span>
            <a href="?month=<?= htmlspecialchars((string)$nextMonth, ENT_QUOTES, 'UTF-8') ?>" class="rounded-md px-3 py-1.5" style="color:var(--text-secondary);">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
    </div>
</div>

<?php if ($account === null): ?>
    <div class="rounded-xl border py-16 text-center" style="background:var(--bg-alt);border-color:var(--border);">
        <p class="text-sm" style="color:var(--text-muted);">No account yet. <a href="/accounts" style="color:var(--accent);">Create one</a> first.</p>
    </div>
<?php else: ?>

<!-- Transfer form -->
<div id="transferForm" class="hidden rounded-xl border p-4" style="background:var(--bg-alt);border-color:var(--border);">
    <form method="POST" action="/transfers/save" class="grid grid-cols-1 gap-3 sm:grid-cols-5">
        <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
        <input type="hidden" name="return_month" value="<?= htmlspecialchars((string)$reqMonth, ENT_QUOTES, 'UTF-8') ?>">
        <div>
            <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">From</label>
            <select name="from_account_id" class="w-full rounded-lg border px-2 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                <?php foreach ($accounts as $a): ?><option value="<?= (int)$a['id'] ?>" <?= (int)$a['id']===$accountId?'selected':'' ?>><?= htmlspecialchars((string)$a['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">To</label>
            <select name="to_account_id" class="w-full rounded-lg border px-2 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                <?php foreach ($accounts as $a): ?><option value="<?= (int)$a['id'] ?>" <?= (int)$a['id']!==$accountId?'':'disabled' ?>><?= htmlspecialchars((string)$a['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Amount (RM)</label>
            <input type="number" step="0.01" min="0.01" name="amount" required class="w-full rounded-lg border px-2 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Date</label>
            <input type="date" name="date" required value="<?= date('Y-m') === $reqMonth ? date('Y-m-d') : $reqMonth . '-01' ?>" class="w-full rounded-lg border px-2 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="w-full rounded-lg py-2 text-sm font-semibold text-white" style="background:var(--accent);">Save</button>
            <button type="button" onclick="toggleTransferForm()" class="rounded-lg border px-3 py-2 text-sm" style="border-color:var(--border);color:var(--text-secondary);">×</button>
        </div>
        <div class="sm:col-span-5">
            <input type="text" name="description" placeholder="Description (optional)" class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
        </div>
    </form>
</div>

<!-- Quick-Add Templates -->
<div class="flex flex-wrap items-center gap-2">
    <span class="text-xs font-medium" style="color:var(--text-muted);">Quick add:</span>
    <?php foreach ($quickTemplates as $qt): ?>
        <div class="inline-flex items-center gap-0 overflow-hidden rounded-full border text-xs font-medium" style="border-color:var(--border);">
            <button type="button" onclick="quickAdd('<?= htmlspecialchars((string)$qt['type'], ENT_QUOTES, 'UTF-8') ?>', <?= (int)($qt['category_id'] ?? 0) ?>, '<?= htmlspecialchars(addslashes((string)$qt['description']), ENT_QUOTES, 'UTF-8') ?>', <?= $qt['amount'] ?>)" style="background:transparent;border:none;cursor:pointer;color:var(--text-secondary);font:inherit;padding:0.375rem 0.75rem;">
                <span class="mr-1 inline-flex h-3.5 w-3.5 items-center justify-center rounded-full text-[9px] font-bold text-white" style="background:var(--accent);">+</span>
                <?= htmlspecialchars((string)$qt['description'], ENT_QUOTES, 'UTF-8') ?><?php if ($qt['amount'] > 0): ?> · RM<?= number_format($qt['amount'], 0) ?><?php endif; ?>
            </button>
            <form method="POST" action="/quick-template/delete" class="inline-flex" onsubmit="return confirm('Remove template?');">
                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                <input type="hidden" name="id" value="<?= (int)$qt['id'] ?>">
                <input type="hidden" name="return_month" value="<?= htmlspecialchars((string)$reqMonth, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" style="background:transparent;border:none;border-left:1px solid var(--border);cursor:pointer;color:var(--text-muted);padding:0.375rem 0.5rem;font:inherit;line-height:1;">&times;</button>
            </form>
        </div>
    <?php endforeach; ?>
    <button onclick="toggleTemplateForm()" class="rounded-full border px-3 py-1.5 text-xs font-medium" style="border-color:var(--border);color:var(--text-muted);border-style:dashed;">+ Template</button>
</div>

<div id="templateForm" class="hidden rounded-xl border p-4" style="background:var(--bg-alt);border-color:var(--border);">
    <form method="POST" action="/quick-template/add" class="flex flex-col gap-2 sm:flex-row sm:items-end">
        <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
        <input type="hidden" name="return_month" value="<?= htmlspecialchars((string)$reqMonth, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="account_id" value="<?= (int)$accountId ?>">
        <div class="flex-1">
            <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Description</label>
            <input type="text" name="description" required class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Type</label>
            <select name="type" class="rounded-lg border px-2 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                <option value="expense">Expense</option><option value="income">Income</option>
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Category</label>
            <select name="category_id" class="rounded-lg border px-2 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                <option value="">None</option>
                <?php foreach($categories as $cat): ?><option value="<?= (int)$cat['id'] ?>"><?= htmlspecialchars((string)$cat['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
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

<!-- Add Transaction -->
<div class="rounded-xl border p-5" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
    <h3 class="mb-4 flex items-center gap-2 text-base font-semibold" style="color:var(--text);">
        <svg class="h-4 w-4" style="color:var(--accent);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        <?= $isLiability ? 'Add Purchase' : 'Add Record' ?>
    </h3>
    <form method="POST" action="/transactions?month=<?= htmlspecialchars((string)$reqMonth, ENT_QUOTES, 'UTF-8') ?>" id="addRecordForm" class="space-y-3">
        <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
        <input type="hidden" name="action" value="add_transaction">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            <div>
                <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Account</label>
                <select name="account_id" id="add_account_id" onchange="onAddAccountChange()" class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                    <?php foreach ($accounts as $a): ?><option value="<?= (int)$a['id'] ?>" <?= (int)$a['id']===$accountId?'selected':'' ?>><?= htmlspecialchars((string)$a['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Type</label>
                <select name="type" class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                    <option value="expense"><?= $isLiability ? 'Purchase' : 'Expense' ?></option>
                    <option value="income"><?= $isLiability ? 'Refund / Credit' : 'Income' ?></option>
                </select>
            </div>
            <div>
                <label id="amountLabel" class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);"><?= $isLiability ? 'Price (RM)' : 'Amount (RM)' ?></label>
                <input type="number" step="0.01" min="0.01" name="amount" required placeholder="0.00" class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Category</label>
                <select name="category_id" required class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                    <option value="">Select...</option>
                    <?php foreach($categories as $cat): ?><option value="<?= (int)$cat['id'] ?>"><?= htmlspecialchars((string)$cat['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Date</label>
                <input type="date" name="date" required value="<?= htmlspecialchars((string)(date('Y-m') === $reqMonth ? date('Y-m-d') : $reqMonth . '-01'), ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Description</label>
                <input type="text" name="description" required placeholder="e.g. Lunch" class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
            </div>
        </div>
        <?php if (in_array('paylater', array_column($accounts, 'kind'), true)): ?>
        <div id="repayBlock" class="<?= ($isLiability && $account && $account['kind'] === 'paylater') ? '' : 'hidden' ?> space-y-3 rounded-lg border p-3" style="border-color:var(--border-light);background:var(--bg);">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Repayment</label>
                    <select name="repay_mode" id="repay_mode" onchange="toggleRepay()" class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg-alt);border-color:var(--border);color:var(--text);">
                        <option value="full"><?= $account['bnpl_mode'] === 'cycle' ? 'Pay next cycle (full)' : 'Pay next month (full)' ?></option>
                        <option value="installments">Installments</option>
                    </select>
                </div>
                <div id="monthsWrap" class="hidden">
                    <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Months</label>
                    <input type="number" name="months" id="months" value="3" min="2" max="60" class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg-alt);border-color:var(--border);color:var(--text);">
                </div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Cash price (optional — leave blank for 0%)</label>
                <input type="number" step="0.01" min="0" name="cash_price" id="cash_price" placeholder="e.g. 150.00" class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg-alt);border-color:var(--border);color:var(--text);">
            </div>
            <p id="repayHint" class="text-xs" style="color:var(--text-muted);"></p>
        </div>
        <?php endif; ?>
        <button type="submit" class="w-full rounded-lg py-2.5 text-sm font-semibold text-white" style="background:var(--accent);">Save</button>
    </form>
</div>

<!-- Filter Bar -->
<div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
    <div class="flex flex-1 items-center gap-2">
        <div class="relative flex-1 sm:max-w-xs">
            <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2" style="color:var(--text-muted);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" id="filterSearch" placeholder="Search..." oninput="applyFilters()" class="w-full rounded-lg border py-2 pl-10 pr-3 text-sm outline-none" style="background:var(--bg-alt);border-color:var(--border);color:var(--text);">
        </div>
        <input type="date" id="filterFrom" onchange="applyFilters()" class="rounded-lg border px-3 py-2 text-sm outline-none" style="background:var(--bg-alt);border-color:var(--border);color:var(--text);" title="From">
        <span style="color:var(--text-muted);">&mdash;</span>
        <input type="date" id="filterTo" onchange="applyFilters()" class="rounded-lg border px-3 py-2 text-sm outline-none" style="background:var(--bg-alt);border-color:var(--border);color:var(--text);" title="To">
    </div>
    <div class="flex items-center gap-2">
        <span id="filterCount" class="text-xs" style="color:var(--text-muted);"></span>
        <button onclick="clearFilters()" id="clearFilterBtn" class="hidden rounded-lg border px-3 py-2 text-xs font-medium" style="border-color:var(--border);color:var(--text-secondary);">Clear</button>
        <?php if ($reqMonth < date('Y-m') && !$isLiability): ?>
        <button onclick="printTransactions()" class="rounded-lg border px-3 py-2 text-xs font-medium" style="border-color:var(--border);color:var(--text-secondary);" title="Export as PDF">Export PDF</button>
        <?php endif; ?>
    </div>
</div>

<!-- Ledger -->
<?php if (empty($entries)): ?>
    <div class="rounded-xl border py-16 text-center" style="background:var(--bg-alt);border-color:var(--border);">
        <p class="text-sm" style="color:var(--text-muted);">No activity for <?= htmlspecialchars((string)$currentDisplay, ENT_QUOTES, 'UTF-8') ?>.</p>
    </div>
<?php else: ?>
    <?php
    $grouped = [];
    foreach ($entries as $e) { $grouped[$e['date']][] = $e; }
    ?>
    <div class="space-y-3">
        <?php foreach ($grouped as $date => $dayEntries): ?>
            <?php
            $dayInc = 0.0; $dayExp = 0.0;
            foreach ($dayEntries as $e) {
                if ($e['kind'] === 'txn') { $e['data']['type'] === 'income' ? $dayInc += (float)$e['data']['amount'] : $dayExp += (float)$e['data']['amount']; }
                else { $e['dir'] === 'in' ? $dayInc += (float)$e['data']['amount'] : $dayExp += (float)$e['data']['amount']; }
            }
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
                <?php foreach ($dayEntries as $e): ?>
                    <?php if ($e['kind'] === 'txn'): $t = $e['data']; ?>
                    <div class="transaction-row group flex items-center justify-between border-b px-4 py-3 last:border-b-0 transition-colors" data-search="<?= htmlspecialchars(strtolower((string)($t['category_name'] ?? '')) . ' ' . strtolower((string)$t['description']), ENT_QUOTES, 'UTF-8') ?>" data-date="<?= htmlspecialchars((string)$date, ENT_QUOTES, 'UTF-8') ?>" style="border-color:var(--border-light);">
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
                            <span class="text-sm font-bold" style="color:<?= $t['type'] === 'income' ? 'var(--income)' : 'var(--expense)' ?>;">RM <?= number_format($t['amount'], 2) ?></span>
                            <div class="flex items-center gap-1 transition-opacity sm:opacity-0 sm:group-hover:opacity-100 focus-within:opacity-100">
                                <button type="button" onclick="openEdit(<?= $t['id'] ?>, '<?= htmlspecialchars((string)$t['date'], ENT_QUOTES, 'UTF-8') ?>', <?= (int)($t['category_id'] ?? 0) ?>, '<?= htmlspecialchars(addslashes((string)$t['description']), ENT_QUOTES, 'UTF-8') ?>', <?= $t['amount'] ?>, '<?= $t['type'] ?>', <?= (int)($t['account_id'] ?? 0) ?>)" class="rounded p-1" style="color:var(--text-muted);">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                </button>
                                <form method="POST" action="/transactions?month=<?= htmlspecialchars((string)$reqMonth, ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm('Delete this transaction?');" class="inline">
                                    <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                                    <input type="hidden" name="action" value="delete_transaction">
                                    <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                                    <button type="submit" class="rounded p-1" style="color:var(--text-muted);">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php else: $tr = $e['data']; $out = $e['dir'] === 'out'; ?>
                    <div class="transaction-row flex items-center justify-between border-b px-4 py-3 last:border-b-0" data-search="<?= htmlspecialchars(strtolower('transfer ' . ($tr['from_name'] ?? '') . ' ' . ($tr['to_name'] ?? '') . ' ' . (string)$tr['description']), ENT_QUOTES, 'UTF-8') ?>" data-date="<?= htmlspecialchars((string)$date, ENT_QUOTES, 'UTF-8') ?>" style="border-color:var(--border-light);background:var(--bg-hover);">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="rounded px-1.5 py-0.5 text-[10px] font-bold" style="background:var(--accent-soft);color:var(--accent);">TRANSFER</span>
                                <span class="text-xs" style="color:var(--text-muted);"><?= $out ? 'to ' . htmlspecialchars((string)($tr['to_name'] ?? ''), ENT_QUOTES, 'UTF-8') : 'from ' . htmlspecialchars((string)($tr['from_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <p class="mt-0.5 truncate text-sm font-medium" style="color:var(--text);"><?= htmlspecialchars((string)($tr['description'] ?: 'Internal transfer'), ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-bold" style="color:<?= $out ? 'var(--expense)' : 'var(--income)' ?>;"><?= $out ? '-' : '+' ?>RM <?= number_format($tr['amount'], 2) ?></span>
                            <form method="POST" action="/transfers/delete" onsubmit="return confirm('Delete this transfer?');">
                                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                                <input type="hidden" name="id" value="<?= (int)$tr['id'] ?>">
                                <input type="hidden" name="return_month" value="<?= htmlspecialchars((string)$reqMonth, ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit" class="rounded p-1" style="color:var(--text-muted);">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4" style="backdrop-filter:blur(4px);">
    <div class="w-full max-w-md rounded-xl border p-6" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow-lg);">
        <div class="mb-5 flex items-center justify-between">
            <h3 class="text-lg font-semibold" style="color:var(--text);">Edit Transaction</h3>
            <button onclick="closeEdit()" class="rounded p-1" style="color:var(--text-muted);">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form method="POST" action="/transactions?month=<?= htmlspecialchars((string)$reqMonth, ENT_QUOTES, 'UTF-8') ?>" class="space-y-3">
            <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
            <input type="hidden" name="action" value="edit_transaction">
            <input type="hidden" name="id" id="edit_id">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Account</label>
                    <select name="account_id" id="edit_account_id" class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                        <?php foreach ($accounts as $a): ?><option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars((string)$a['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Type</label>
                    <select name="type" id="edit_type" class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                        <option value="expense">Expense</option><option value="income">Income</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Amount (RM)</label>
                    <input type="number" step="0.01" min="0.01" name="amount" id="edit_amount" required class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Date</label>
                    <input type="date" name="date" id="edit_date" required class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                </div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Category</label>
                <select name="category_id" id="edit_category_id" required class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                    <option value="">Select...</option>
                    <?php foreach($categories as $cat): ?><option value="<?= (int)$cat['id'] ?>"><?= htmlspecialchars((string)$cat['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Description</label>
                <input type="text" name="description" id="edit_description" required class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
            </div>
            <button type="submit" class="w-full rounded-lg py-2.5 text-sm font-semibold text-white" style="background:var(--accent);">Update Transaction</button>
        </form>
    </div>
</div>

<!-- Undo toast -->
<div id="undoToast" class="fixed bottom-5 left-1/2 z-50 hidden -translate-x-1/2 items-center gap-3 rounded-xl border px-4 py-3 text-sm shadow-lg" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow-lg);">
    <span style="color:var(--text);">Transaction deleted.</span>
    <button onclick="undoDelete()" class="rounded-lg px-3 py-1 text-xs font-semibold text-white" style="background:var(--accent);">Undo</button>
    <button onclick="dismissUndo()" class="rounded-lg p-1" style="color:var(--text-muted);">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
</div>

<!-- Print area -->
<div id="printArea" style="display:none;">
    <style>@media print { body * { visibility: hidden; } #printArea, #printArea * { visibility: visible; } #printArea { position: absolute; left: 0; top: 0; width: 100%; } }</style>
    <table style="width:100%;border-collapse:collapse;font-family:Arial,sans-serif;font-size:11px;color:#1a1a1a;">
        <tr><td style="padding:0 0 20px 0;">
            <table style="width:100%;"><tr>
                <td style="width:70%;"><div style="font-size:22px;font-weight:700;color:#1a3a6b;">EXPENZZ</div><div style="font-size:12px;color:#4a6a9b;">Personal Finance Ledger</div></td>
                <td style="width:30%;text-align:right;vertical-align:bottom;"><div style="font-size:14px;font-weight:700;color:#1a3a6b;">MONTHLY STATEMENT</div></td>
            </tr></table>
        </td></tr>
        <tr><td style="border-top:2px solid #1a3a6b;padding-top:14px;">
            <table style="width:100%;font-size:11px;"><tr>
                <td style="width:50%;"><strong>Account:</strong> <?= htmlspecialchars((string)($account['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td style="width:50%;text-align:right;"><strong>Period:</strong> <?= htmlspecialchars((string)$currentDisplay, ENT_QUOTES, 'UTF-8') ?></td>
            </tr></table>
        </td></tr>
        <tr><td style="padding-top:20px;">
            <table style="width:100%;border-collapse:collapse;font-size:11px;">
                <thead><tr style="background:#f0f3f8;">
                    <th style="padding:8px 10px;text-align:left;border-bottom:2px solid #1a3a6b;">Date</th>
                    <th style="padding:8px 10px;text-align:left;border-bottom:2px solid #1a3a6b;">Description</th>
                    <th style="padding:8px 10px;text-align:right;border-bottom:2px solid #1a3a6b;">Debit (RM)</th>
                    <th style="padding:8px 10px;text-align:right;border-bottom:2px solid #1a3a6b;">Credit (RM)</th>
                    <th style="padding:8px 10px;text-align:right;border-bottom:2px solid #1a3a6b;">Balance (RM)</th>
                </tr></thead>
                <tbody>
                    <tr style="background:#eef1f6;font-weight:600;">
                        <td style="padding:6px 10px;border-bottom:1px solid #c5cdd8;">&nbsp;</td>
                        <td style="padding:6px 10px;border-bottom:1px solid #c5cdd8;">Beginning Balance</td>
                        <td style="padding:6px 10px;border-bottom:1px solid #c5cdd8;"></td>
                        <td style="padding:6px 10px;border-bottom:1px solid #c5cdd8;"></td>
                        <td style="padding:6px 10px;text-align:right;border-bottom:1px solid #c5cdd8;color:#1a3a6b;"><?= number_format($openingBal, 2) ?></td>
                    </tr>
                    <?php foreach ($printRows as $i => $r): ?>
                        <tr style="<?= $i % 2 === 0 ? 'background:#f8f9fb;' : '' ?>">
                            <td style="padding:6px 10px;border-bottom:1px solid #e5e9f0;"><?= htmlspecialchars((string)$r['date'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td style="padding:6px 10px;border-bottom:1px solid #e5e9f0;"><?= htmlspecialchars((string)$r['desc'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td style="padding:6px 10px;text-align:right;border-bottom:1px solid #e5e9f0;<?= $r['debit'] ? 'color:#c0392b;' : '' ?>"><?= $r['debit'] ?></td>
                            <td style="padding:6px 10px;text-align:right;border-bottom:1px solid #e5e9f0;<?= $r['credit'] ? 'color:#27ae60;' : '' ?>"><?= $r['credit'] ?></td>
                            <td style="padding:6px 10px;text-align:right;border-bottom:1px solid #e5e9f0;font-weight:600;color:#1a3a6b;"><?= $r['balance'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr style="background:#eef1f6;font-weight:700;">
                        <td style="padding:8px 10px;">&nbsp;</td>
                        <td style="padding:8px 10px;">Closing Balance</td>
                        <td style="padding:8px 10px;text-align:right;"></td>
                        <td style="padding:8px 10px;text-align:right;"></td>
                        <td style="padding:8px 10px;text-align:right;font-size:13px;color:#1a3a6b;">RM <?= number_format($runningBal, 2) ?></td>
                    </tr>
                </tbody>
            </table>
        </td></tr>
        <tr><td style="border-top:1px solid #c5cdd8;padding-top:14px;font-size:10px;color:#7a8599;text-align:center;">Generated by Expenzz &bull; <?= date('d F Y, h:i A') ?></td></tr>
    </table>
</div>

<?php endif; ?>

<script>
var accountKinds = <?= json_encode(array_column($accounts, 'kind', 'id'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
function onAddAccountChange() {
    var sel = document.getElementById('add_account_id');
    var kind = accountKinds[sel.value] || 'savings';
    var block = document.getElementById('repayBlock');
    if (block) block.classList.toggle('hidden', kind !== 'paylater');
    toggleRepay();
}
function toggleRepay() {
    var m = document.getElementById('repay_mode');
    if (!m) return;
    var inst = m.value === 'installments';
    document.getElementById('monthsWrap').classList.toggle('hidden', !inst);
    var lbl = document.getElementById('amountLabel');
    if (lbl) lbl.textContent = inst ? 'Monthly amount (RM)' : 'Amount / Price (RM)';
    updateRepayHint();
}
function updateRepayHint() {
    var hint = document.getElementById('repayHint');
    if (!hint) return;
    var m = document.getElementById('repay_mode');
    if (!m || m.value !== 'installments') { hint.textContent = 'Recorded as one purchase; a single bill is due next cycle.'; return; }
    var amt = parseFloat(document.getElementById('addRecordForm').querySelector('input[name="amount"]').value || '0');
    var months = parseInt(document.getElementById('months').value || '1', 10);
    hint.textContent = 'Total repayable: RM ' + (amt * months).toFixed(2) + ' over ' + months + ' month(s).';
}
document.getElementById('addRecordForm').querySelector('input[name="amount"]').addEventListener('input', updateRepayHint);
var _monthsEl = document.getElementById('months'); if (_monthsEl) _monthsEl.addEventListener('input', updateRepayHint);
document.addEventListener('DOMContentLoaded', function(){ toggleRepay(); });

function toggleTransferForm(){ var f=document.getElementById('transferForm'); if(f) f.classList.toggle('hidden'); }
function openEdit(id, date, catId, desc, amount, type, accountId) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_date').value = date;
    document.getElementById('edit_category_id').value = catId;
    document.getElementById('edit_description').value = desc;
    document.getElementById('edit_amount').value = amount;
    document.getElementById('edit_type').value = type;
    if (accountId) document.getElementById('edit_account_id').value = accountId;
    var m = document.getElementById('editModal');
    m.classList.remove('hidden'); m.classList.add('flex');
}
function closeEdit() { var m=document.getElementById('editModal'); m.classList.add('hidden'); m.classList.remove('flex'); }
document.getElementById('editModal').addEventListener('click', function(e){ if(e.target===this) closeEdit(); });

function applyFilters() {
    var query=(document.getElementById('filterSearch').value||'').toLowerCase();
    var from=document.getElementById('filterFrom').value, to=document.getElementById('filterTo').value;
    var visible=0,total=0;
    document.querySelectorAll('.transaction-group').forEach(function(group){
        var groupDate=group.getAttribute('data-date'); var groupVisible=false;
        var rows=group.querySelectorAll('.transaction-row'); total+=rows.length;
        rows.forEach(function(row){
            var search=row.getAttribute('data-search')||''; var rowDate=row.getAttribute('data-date'); var match=true;
            if(query && search.indexOf(query)===-1) match=false;
            if(from && rowDate<from) match=false;
            if(to && rowDate>to) match=false;
            if(match){row.style.display='';groupVisible=true;visible++;} else {row.style.display='none';}
        });
        group.style.display=groupVisible?'':'none';
    });
    var countEl=document.getElementById('filterCount'), clearBtn=document.getElementById('clearFilterBtn');
    var hasFilter=query||from||to;
    if(hasFilter && visible!==total){countEl.textContent=visible+' / '+total+' shown';clearBtn.classList.remove('hidden');}
    else if(hasFilter){countEl.textContent='';clearBtn.classList.remove('hidden');}
    else {countEl.textContent='';clearBtn.classList.add('hidden');}
}
function clearFilters(){document.getElementById('filterSearch').value='';document.getElementById('filterFrom').value='';document.getElementById('filterTo').value='';applyFilters();}
function quickAdd(type,catId,desc,amount){var f=document.getElementById('addRecordForm');if(!f)return;f.querySelector('select[name="type"]').value=type;f.querySelector('select[name="category_id"]').value=catId;f.querySelector('input[name="description"]').value=desc;if(amount>0)f.querySelector('input[name="amount"]').value=amount;f.querySelector('input[name="amount"]').focus();window.scrollTo({top:0,behavior:'smooth'});}
function toggleTemplateForm(){var f=document.getElementById('templateForm');f.classList.toggle('hidden');if(!f.classList.contains('hidden'))f.querySelector('input[type="text"]').focus();}

(function(){
    var toast=document.getElementById('undoToast'); var clearUndoTimer=null;
    if(sessionStorage.getItem('undoData')) showUndoToast();
    document.querySelectorAll('form').forEach(function(f){
        var onsubmit=f.getAttribute('onsubmit');
        if(!onsubmit||onsubmit.indexOf('Delete this transaction')<0) return;
        f.removeAttribute('onsubmit');
        f.addEventListener('submit',function(e){
            if(!confirm('Delete this transaction?')){e.preventDefault();return;}
            var row=f.closest('.transaction-row'); var date=row.getAttribute('data-date');
            var desc=row.querySelector('p').textContent.trim();
            var amtText=row.querySelector('[style*="var(--income)"], [style*="var(--expense)"]').textContent;
            var amount=parseFloat(amtText.replace(/[^0-9.]/g,''));
            var isIncome=row.querySelector('[style*="var(--income)"]')!==null;
            var catId=0; var editBtn=row.querySelector('button[onclick*="openEdit"]');
            if(editBtn){var m=editBtn.getAttribute('onclick').match(/openEdit\(\d+,\s*'[^']*',\s*(\d+)/); if(m) catId=parseInt(m[1]);}
            sessionStorage.setItem('undoData',JSON.stringify({date:date,type:isIncome?'income':'expense',amount:amount,description:desc,category_id:catId,month:'<?= htmlspecialchars((string)$reqMonth, ENT_QUOTES, 'UTF-8') ?>',account_id:<?= (int)$accountId ?>}));
        });
    });
    function showUndoToast(){toast.classList.remove('hidden');toast.classList.add('flex');clearUndoTimer=setTimeout(function(){clearUndo();},8000);}
    function clearUndo(){toast.classList.add('hidden');toast.classList.remove('flex');sessionStorage.removeItem('undoData');}
    window.dismissUndo=clearUndo;
    window.undoDelete=function(){
        var raw=sessionStorage.getItem('undoData'); if(!raw)return; var d=JSON.parse(raw);
        var fd=new URLSearchParams();
        fd.append('csrf_token',document.querySelector('input[name="csrf_token"]').value);
        fd.append('action','add_transaction'); fd.append('type',d.type); fd.append('amount',d.amount);
        fd.append('description',d.description); fd.append('category_id',d.category_id); fd.append('date',d.date);
        fd.append('account_id',d.account_id||0);
        fetch('/transactions?month='+d.month,{method:'POST',body:fd}).then(function(){location.reload();});
        clearUndo();
    };
})();

function printTransactions(){document.getElementById('printArea').style.display='';window.print();document.getElementById('printArea').style.display='none';}
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
