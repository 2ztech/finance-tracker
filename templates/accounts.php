<?php
require_once __DIR__ . '/../src/Account.php';
require_once __DIR__ . '/../src/Category.php';

$msg = $_GET['msg'] ?? '';
$accounts = Account::all(true);
$categories = Category::getAll();

$toasts = [
    'created'       => ['Account created.', 'success'],
    'updated'       => ['Account updated.', 'success'],
    'deleted'       => ['Account deleted.', 'success'],
    'blocked'       => ['Cannot delete: account still has transactions.', 'danger'],
    'name_required' => ['Account name is required.', 'danger'],
    'reassigned'    => ['Transactions reassigned.', 'success'],
];

ob_start();
?>

<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h2 class="text-2xl font-bold" style="color:var(--text);">Accounts</h2>
        <p class="mt-0.5 text-sm" style="color:var(--text-secondary);">Manage your savings, credit, and paylater accounts.</p>
    </div>
    <button onclick="openAccountForm()" class="rounded-lg px-4 py-2.5 text-sm font-semibold text-white" style="background:var(--accent);">+ Add Account</button>
</div>

<?php if (isset($toasts[$msg])): $t = $toasts[$msg]; ?>
    <div class="mb-5 rounded-lg border px-4 py-3 text-sm" style="background:<?= $t[1] === 'success' ? 'var(--success-soft)' : 'var(--danger-soft)' ?>;border-color:<?= $t[1] === 'success' ? 'var(--success)' : 'var(--danger)' ?>;color:<?= $t[1] === 'success' ? 'var(--success)' : 'var(--danger)' ?>;">
        <?= htmlspecialchars($t[0], ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<!-- Account list -->
<div class="space-y-3">
    <?php foreach ($accounts as $a): ?>
        <?php
        $isLiability = Account::isLiability($a);
        $bal = Account::balance((int) $a['id']);
        $isActive = Account::activeId() === (int) $a['id'];
        $kindLabel = ucfirst((string) $a['kind']);
        if ($a['kind'] === 'paylater') {
            $kindLabel .= ' · ' . ($a['bnpl_mode'] === 'per_purchase' ? 'per-purchase' : 'cycle');
        }
        $txCount = 0;
        ?>
        <div class="flex flex-col gap-3 rounded-xl border p-4 sm:flex-row sm:items-center sm:justify-between" style="background:var(--bg-alt);border-color:<?= $isActive ? 'var(--accent)' : 'var(--border)' ?>;box-shadow:var(--shadow);">
            <div class="flex items-center gap-3">
                <span class="h-9 w-9 shrink-0 rounded-lg" style="background:<?= htmlspecialchars((string) $a['color_hex'], ENT_QUOTES, 'UTF-8') ?>;"></span>
                <div>
                    <p class="text-base font-semibold" style="color:var(--text);">
                        <?= htmlspecialchars((string) $a['name'], ENT_QUOTES, 'UTF-8') ?>
                        <?php if ($isActive): ?><span class="ml-1 rounded px-1.5 py-0.5 text-[10px] font-bold" style="background:var(--accent-soft);color:var(--accent);">ACTIVE</span><?php endif; ?>
                        <?php if ((int) $a['archived'] === 1): ?><span class="ml-1 rounded px-1.5 py-0.5 text-[10px]" style="background:var(--bg-hover);color:var(--text-muted);">ARCHIVED</span><?php endif; ?>
                    </p>
                    <p class="text-xs" style="color:var(--text-muted);"><?= htmlspecialchars($kindLabel, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <div class="text-right">
                    <p class="text-[11px]" style="color:var(--text-muted);"><?= $isLiability ? 'Outstanding' : 'Balance' ?></p>
                    <p class="text-lg font-bold" style="color:<?= $isLiability ? 'var(--expense)' : 'var(--text)' ?>;">RM <?= number_format($bal, 2) ?></p>
                </div>
                <div class="flex items-center gap-1">
                    <?php if (!$isActive): ?>
                    <form method="POST" action="/accounts/activate">
                        <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                        <input type="hidden" name="account_id" value="<?= (int) $a['id'] ?>">
                        <input type="hidden" name="return" value="/accounts">
                        <button type="submit" class="rounded-lg border px-2.5 py-1.5 text-xs font-medium" style="border-color:var(--border);color:var(--text-secondary);">Switch</button>
                    </form>
                    <?php endif; ?>
                    <button type="button" onclick='editAccount(<?= json_encode($a, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' class="rounded p-1.5" style="color:var(--text-muted);" title="Edit">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                    </button>
                    <form method="POST" action="/accounts/delete" onsubmit="return confirm('Delete this account?');">
                        <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                        <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                        <button type="submit" class="rounded p-1.5" style="color:var(--text-muted);" title="Delete">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Reassign tool -->
<div class="mt-8 rounded-xl border p-5" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
    <h3 class="mb-1 text-base font-semibold" style="color:var(--text);">Move Transactions Between Accounts</h3>
    <p class="mb-4 text-sm" style="color:var(--text-secondary);">Bulk-move transactions (and commitments) from one account to another. Use this to reorganise after adding a new account.</p>
    <form method="POST" action="/accounts/reassign" onsubmit="return confirm('Move transactions? This cannot be undone automatically.');" class="grid grid-cols-1 gap-3 sm:grid-cols-4">
        <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
        <div>
            <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">From</label>
            <select name="from_account_id" required class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                <?php foreach ($accounts as $a): ?><option value="<?= (int) $a['id'] ?>"><?= htmlspecialchars((string) $a['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">To</label>
            <select name="to_account_id" required class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                <?php foreach ($accounts as $a): ?><option value="<?= (int) $a['id'] ?>"><?= htmlspecialchars((string) $a['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Category (optional)</label>
            <select name="category_id" class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                <option value="0">All categories</option>
                <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars((string) $c['name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars((string) $c['type'], ENT_QUOTES, 'UTF-8') ?>)</option><?php endforeach; ?>
            </select>
        </div>
        <div class="flex items-end">
            <button type="submit" class="w-full rounded-lg py-2.5 text-sm font-semibold text-white" style="background:var(--accent);">Move</button>
        </div>
    </form>
</div>

<!-- Add/Edit modal -->
<div id="accountModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4" style="backdrop-filter:blur(4px);">
    <div class="max-h-[90vh] w-full max-w-lg overflow-auto rounded-xl border p-6" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow-lg);">
        <div class="mb-5 flex items-center justify-between">
            <h3 id="accountModalTitle" class="text-lg font-semibold" style="color:var(--text);">Add Account</h3>
            <button onclick="closeAccountForm()" class="rounded p-1" style="color:var(--text-muted);">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form method="POST" action="/accounts/save" class="space-y-3">
            <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
            <input type="hidden" name="id" id="acc_id" value="">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Name</label>
                    <input type="text" name="name" id="acc_name" required class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Kind</label>
                    <select name="kind" id="acc_kind" onchange="toggleAccountFields()" class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                        <option value="savings">Savings</option>
                        <option value="credit">Credit</option>
                        <option value="paylater">Paylater</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Color</label>
                <input type="color" name="color_hex" id="acc_color" value="#4f6ef7" class="h-9 w-full cursor-pointer rounded border-0 bg-transparent p-0">
            </div>

            <!-- Opening balance (all kinds) -->
            <div>
                <label id="acc_opening_label" class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Opening Balance (RM)</label>
                <input type="number" step="0.01" name="opening_balance" id="acc_opening" value="0" class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
            </div>

            <!-- Savings-only -->
            <div class="acc-savings">
                <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Tracking Start Month</label>
                <input type="month" name="start_month" id="acc_start" class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
            </div>

            <!-- Liability fields -->
            <div class="acc-liability hidden space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Statement Day (cut-off)</label>
                        <input type="number" min="1" max="31" name="statement_day" id="acc_stmt" class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Due Day</label>
                        <input type="number" min="1" max="31" name="due_day" id="acc_due" class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                    </div>
                </div>
                <div class="acc-paylater grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">BNPL Mode</label>
                        <select name="bnpl_mode" id="acc_bnpl" class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                            <option value="cycle">Cycle (Shopee / TikTok / Atome Card)</option>
                            <option value="per_purchase">Per-purchase (Grab / Atome BNPL)</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">First Due</label>
                        <select name="first_due_offset" id="acc_offset" class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                            <option value="1">Next cycle</option>
                            <option value="0">At purchase</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Credit Limit (optional)</label>
                    <input type="number" step="0.01" name="credit_limit" id="acc_limit" class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                </div>
                <label class="flex items-center gap-2 text-sm" style="color:var(--text-secondary);">
                    <input type="checkbox" name="allow_partial" id="acc_partial" class="h-4 w-4" style="accent-color:var(--accent);"> Allow partial bill payments
                </label>
            </div>

            <label class="flex items-center gap-2 text-sm" style="color:var(--text-secondary);">
                <input type="checkbox" name="archived" id="acc_archived" class="h-4 w-4" style="accent-color:var(--accent);"> Archived
            </label>

            <button type="submit" class="w-full rounded-lg py-2.5 text-sm font-semibold text-white" style="background:var(--accent);">Save Account</button>
        </form>
    </div>
</div>

<script>
function toggleAccountFields() {
    var kind = document.getElementById('acc_kind').value;
    document.querySelectorAll('.acc-savings').forEach(function(el){ el.classList.toggle('hidden', kind !== 'savings'); });
    document.querySelectorAll('.acc-liability').forEach(function(el){ el.classList.toggle('hidden', kind === 'savings'); });
    document.querySelectorAll('.acc-paylater').forEach(function(el){ el.classList.toggle('hidden', kind !== 'paylater'); });
    document.getElementById('acc_opening_label').textContent = (kind === 'savings')
        ? 'Opening Balance (RM)'
        : 'Current Outstanding (RM)';
}

function openAccountForm() {
    document.getElementById('accountModalTitle').textContent = 'Add Account';
    document.getElementById('acc_id').value = '';
    document.getElementById('acc_name').value = '';
    document.getElementById('acc_kind').value = 'savings';
    document.getElementById('acc_color').value = '#4f6ef7';
    document.getElementById('acc_opening').value = '0';
    document.getElementById('acc_start').value = '';
    document.getElementById('acc_stmt').value = '';
    document.getElementById('acc_due').value = '';
    document.getElementById('acc_bnpl').value = 'cycle';
    document.getElementById('acc_offset').value = '1';
    document.getElementById('acc_limit').value = '';
    document.getElementById('acc_partial').checked = false;
    document.getElementById('acc_archived').checked = false;
    toggleAccountFields();
    showModal();
}

function editAccount(a) {
    document.getElementById('accountModalTitle').textContent = 'Edit Account';
    document.getElementById('acc_id').value = a.id;
    document.getElementById('acc_name').value = a.name;
    document.getElementById('acc_kind').value = a.kind;
    document.getElementById('acc_color').value = a.color_hex || '#4f6ef7';
    document.getElementById('acc_opening').value = a.opening_balance;
    document.getElementById('acc_start').value = a.start_month || '';
    document.getElementById('acc_stmt').value = a.statement_day || '';
    document.getElementById('acc_due').value = a.due_day || '';
    document.getElementById('acc_bnpl').value = a.bnpl_mode || 'cycle';
    document.getElementById('acc_offset').value = a.first_due_offset;
    document.getElementById('acc_limit').value = a.credit_limit || '';
    document.getElementById('acc_partial').checked = String(a.allow_partial) === '1';
    document.getElementById('acc_archived').checked = String(a.archived) === '1';
    toggleAccountFields();
    showModal();
}

function showModal() {
    var m = document.getElementById('accountModal');
    m.classList.remove('hidden');
    m.classList.add('flex');
}
function closeAccountForm() {
    var m = document.getElementById('accountModal');
    m.classList.add('hidden');
    m.classList.remove('flex');
}
document.getElementById('accountModal').addEventListener('click', function(e) {
    if (e.target === this) closeAccountForm();
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
