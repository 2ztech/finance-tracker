<?php
require_once __DIR__ . '/../src/Expense.php';
require_once __DIR__ . '/../src/Category.php';
require_once __DIR__ . '/../src/Account.php';

$account = Account::active();
$accountId = $account ? (int) $account['id'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action !== '') {
        $id = (int) ($_POST['id'] ?? 0);

        if ($action === 'add' || $action === 'edit') {
            $name = trim($_POST['name'] ?? '');
            $amount = (float) ($_POST['amount'] ?? 0);
            $type = $_POST['type'] ?? 'expense';
            $due_date = (int) ($_POST['due_date_day'] ?? 1);
            $categoryId = (int) ($_POST['category_id'] ?? 0);
            $cleanCategoryId = $categoryId > 0 ? $categoryId : null;
            $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
            $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;

            if ($name && $amount > 0 && $due_date >= 1 && $due_date <= 31) {
                if ($action === 'add') {
                    Expense::addCommitment($name, $amount, $type, $due_date, $cleanCategoryId, $start_date, $end_date, $accountId);
                    // Only a brand-new schedule may build history from a past
                    // start date (the client asks for confirmation first).
                    Expense::processDueCommitments(true, true);
                } elseif ($id > 0) {
                    Expense::updateCommitment($id, $name, $amount, $type, $due_date, $cleanCategoryId, $start_date, $end_date, $accountId);
                    // Edits are forward-only: never create past transactions.
                    Expense::processDueCommitments(true);
                }
            }
        } elseif ($action === 'archive' && $id > 0) {
            // Stop future occurrences; history and posted transactions are kept.
            Expense::archiveCommitment($id);
        } elseif ($action === 'restore' && $id > 0) {
            Expense::restoreCommitment($id);
            Expense::processDueCommitments(true);
        } elseif ($action === 'post_now' && $id > 0) {
            // Post this month early; the period tag prevents a later duplicate.
            Expense::postCommitmentNow($id);
        }
        header('Location: /recurring');
        exit;
    }
}

$commitments = Expense::getCommitments($accountId);
$archivedCommitments = Expense::getArchivedCommitments($accountId);
$today = date('Y-m-d');
$activeCommitments = array_filter($commitments, function ($c) use ($today) {
    if (!empty($c['start_date']) && $c['start_date'] > $today) {
        return false;
    }
    if (!empty($c['end_date']) && $c['end_date'] < $today) {
        return false;
    }
    return true;
});
$totalExpense = array_sum(array_column(array_filter($activeCommitments, fn($c) => ($c['type'] ?? 'expense') === 'expense'), 'amount'));
$totalIncome = array_sum(array_column(array_filter($activeCommitments, fn($c) => ($c['type'] ?? 'expense') === 'income'), 'amount'));
$categories = Category::getAll();

ob_start();
?>

<!-- Header -->
<div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h2 class="text-2xl font-bold" style="color:var(--text);">Recurring Items</h2>
        <p class="mt-0.5 text-sm" style="color:var(--text-secondary);">Bills, subscriptions, and regular income.</p>
    </div>
    <div class="flex gap-3">
        <div class="rounded-lg border px-4 py-2 text-right" style="background:var(--bg-alt);border-color:var(--border);">
            <p class="text-xs" style="color:var(--text-muted);">Monthly Out</p>
            <p class="text-lg font-bold" style="color:var(--expense);">RM <?= number_format($totalExpense, 2) ?></p>
        </div>
        <div class="rounded-lg border px-4 py-2 text-right hidden sm:block" style="background:var(--bg-alt);border-color:var(--border);">
            <p class="text-xs" style="color:var(--text-muted);">Monthly In</p>
            <p class="text-lg font-bold" style="color:var(--income);">RM <?= number_format($totalIncome, 2) ?></p>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <!-- List -->
    <div class="space-y-3 lg:col-span-2">
        <?php if (empty($commitments)): ?>
            <div class="rounded-xl border py-16 text-center" style="background:var(--bg-alt);border-color:var(--border);">
                <p class="text-sm" style="color:var(--text-muted);">No recurring items yet. Add one to start tracking.</p>
            </div>
        <?php else: ?>
            <?php foreach ($commitments as $c): ?>
                <?php $cType = $c['type'] ?? 'expense'; $isIncome = $cType === 'income'; ?>
                <div class="group flex items-center justify-between rounded-xl border p-4 transition-colors" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);" onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">
                    <div class="flex items-center gap-4">
                        <div class="flex h-12 w-12 flex-col items-center justify-center rounded-lg border text-center" style="background:<?= $isIncome ? 'var(--success-soft)' : 'var(--danger-soft)' ?>;border-color:<?= $isIncome ? 'var(--success)' : 'var(--danger)' ?>;color:<?= $isIncome ? 'var(--income)' : 'var(--expense)' ?>;">
                            <span class="text-[10px] font-bold uppercase">Day</span>
                            <span class="text-lg font-black leading-none"><?= (int)$c['due_date_day'] ?></span>
                        </div>
                        <div>
                            <h4 class="text-base font-semibold" style="color:var(--text);"><?= htmlspecialchars((string)$c['name'], ENT_QUOTES, 'UTF-8') ?></h4>
                            <p class="text-sm font-medium" style="color:<?= $isIncome ? 'var(--income)' : 'var(--expense)' ?>;">
                                RM <?= number_format($c['amount'], 2) ?>
                            </p>
                            <?php if (!empty($c['category_name'])): ?>
                                <div class="mt-1 flex items-center gap-1.5">
                                    <div class="h-2 w-2 rounded-full" style="background:<?= htmlspecialchars((string)$c['color_hex'], ENT_QUOTES, 'UTF-8') ?>;"></div>
                                    <span class="text-xs" style="color:var(--text-secondary);"><?= htmlspecialchars((string)$c['category_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                            <?php endif; ?>
                            <?php $today = date('Y-m-d'); $hasStarted = empty($c['start_date']) || $c['start_date'] <= $today; $hasEnded = !empty($c['end_date']) && $c['end_date'] < $today; ?>
                            <?php if (!$hasStarted): ?>
                                <span class="mt-1 inline-block rounded px-2 py-0.5 text-xs" style="background:var(--accent-soft);color:var(--accent);">Starts <?= date('M j, Y', strtotime($c['start_date'])) ?></span>
                            <?php elseif ($hasEnded): ?>
                                <span class="mt-1 inline-block rounded px-2 py-0.5 text-xs" style="background:var(--danger-soft);color:var(--danger);">Expired</span>
                            <?php elseif (!empty($c['end_date'])): ?>
                                <span class="mt-1 inline-block rounded px-2 py-0.5 text-xs" style="background:var(--bg-hover);color:var(--text-muted);">Ends <?= date('M j, Y', strtotime($c['end_date'])) ?></span>
                            <?php endif; ?>
                            <?php $currentPosted = Expense::currentPeriodPosted((int) $c['id']); $canPost = $hasStarted && !$hasEnded && !$currentPosted; $showPosted = $hasStarted && !$hasEnded && $currentPosted; ?>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <?php if ($canPost): ?>
                            <form method="POST" action="/recurring">
                                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                                <input type="hidden" name="action" value="post_now">
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                <button type="submit" class="rounded-lg px-3 py-1.5 text-xs font-semibold text-white" style="background:var(--accent);">Post now</button>
                            </form>
                        <?php elseif ($showPosted): ?>
                            <span class="rounded-lg px-3 py-1.5 text-xs font-semibold" style="background:var(--success-soft);color:var(--income);">Posted</span>
                        <?php endif; ?>
                        <div class="flex flex-col gap-1 transition-opacity sm:opacity-0 sm:group-hover:opacity-100 focus-within:opacity-100">
                            <button type="button" onclick="editItem(<?= $c['id'] ?>, '<?= htmlspecialchars((string)$c['name'], ENT_QUOTES, 'UTF-8') ?>', <?= $c['amount'] ?>, '<?= $cType ?>', <?= $c['due_date_day'] ?>, <?= (int)($c['category_id'] ?? 0) ?>, '<?= htmlspecialchars((string)($c['start_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars((string)($c['end_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>')"
                                class="rounded p-1.5 transition-colors" style="color:var(--text-muted);" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--text-muted)'">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                            </button>
                            <form method="POST" action="/recurring" data-confirm="Archive this recurring item? Past transactions are kept." data-confirm-label="Archive" data-confirm-danger="true">
                                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                                <input type="hidden" name="action" value="archive">
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                <button type="submit" class="rounded p-1.5 transition-colors" style="color:var(--text-muted);" onmouseover="this.style.color='var(--danger)'" onmouseout="this.style.color='var(--text-muted)'" title="Archive">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v1a2 2 0 01-2 2M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 3v6m4-6v6"/></svg>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php if (!empty($archivedCommitments)): ?>
            <details class="rounded-xl border" style="background:var(--bg-alt);border-color:var(--border);">
                <summary class="cursor-pointer px-4 py-3 text-sm font-medium" style="color:var(--text-secondary);">Archived (<?= count($archivedCommitments) ?>)</summary>
                <div class="space-y-2 border-t px-4 py-3" style="border-color:var(--border-light);">
                    <?php foreach ($archivedCommitments as $c): ?>
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium" style="color:var(--text);"><?= htmlspecialchars((string)$c['name'], ENT_QUOTES, 'UTF-8') ?></p>
                                <p class="text-xs" style="color:var(--text-muted);">RM <?= number_format((float)$c['amount'], 2) ?> · day <?= (int)$c['due_date_day'] ?></p>
                            </div>
                            <form method="POST" action="/recurring">
                                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                                <input type="hidden" name="action" value="restore">
                                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                <button type="submit" class="rounded-lg border px-3 py-1.5 text-xs font-semibold" style="border-color:var(--border);color:var(--text-secondary);">Restore</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php endif; ?>
    </div>

    <!-- Form -->
    <div>
        <div class="sticky top-6 rounded-xl border p-5" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
            <h3 id="form_title" class="mb-5 flex items-center gap-2 text-base font-semibold" style="color:var(--text);">
                <svg class="h-4 w-4" style="color:var(--accent);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add Recurring Item
            </h3>
            <form method="POST" action="/recurring" id="commitment_form" class="space-y-3">
                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                <input type="hidden" name="action" id="form_action" value="add">
                <input type="hidden" name="id" id="form_id" value="">

                <div>
                    <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Type</label>
                    <select name="type" id="form_type" onchange="filterCats()" class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                        <option value="expense">Expense</option>
                        <option value="income">Income</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Name</label>
                    <input type="text" name="name" id="form_name" required placeholder="e.g. Car Loan"
                        class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Amount (RM)</label>
                    <input type="number" step="0.01" min="0.01" name="amount" id="form_amount" required placeholder="0.00"
                        class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Category</label>
                    <select name="category_id" id="form_category_id" class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                        <option value="">No Category</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Due Day: <span id="due_date_display" class="font-bold" style="color:var(--text);">1</span></label>
                    <input type="range" min="1" max="31" value="1" name="due_date_day" id="due_date_slider"
                        class="w-full" style="accent-color:var(--accent);">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Start Date</label>
                        <input type="date" name="start_date" id="form_start_date"
                            class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                        <p class="mt-1 text-[11px]" style="color:var(--text-muted);">Leave blank to start from this month (no past entries).</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">End Date</label>
                        <input type="date" name="end_date" id="form_end_date"
                            class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                    </div>
                </div>

                <button type="submit" id="form_submit_btn" class="w-full rounded-lg py-2.5 text-sm font-semibold text-white" style="background:var(--accent);">
                    Save Item
                </button>
                <button type="button" id="cancel_edit_btn" onclick="cancelEdit()" class="hidden w-full rounded-lg border py-2.5 text-sm font-semibold transition-colors" style="border-color:var(--border);color:var(--text-secondary);background:var(--bg-hover);">
                    Cancel
                </button>
            </form>
        </div>
    </div>
</div>

<script>
var cats = <?= json_encode($categories) ?>;
function filterCats() {
    var type = document.getElementById('form_type').value;
    var sel = document.getElementById('form_category_id');
    var cur = sel.value;
    sel.innerHTML = '<option value="">No Category</option>';
    cats.filter(function(c){return c.type===type;}).forEach(function(c){
        var o = document.createElement('option');
        o.value = c.id;
        o.textContent = c.name;
        if (c.id == cur) o.selected = true;
        sel.appendChild(o);
    });
}
// How many past months a brand-new schedule with this start date would backfill.
function backdatedCount(startVal, dueDay, endVal) {
    if (!startVal) return 0;
    var parts = startVal.split('-');
    if (parts.length < 3) return 0;
    var y = parseInt(parts[0], 10), m = parseInt(parts[1], 10);
    var now = new Date();
    var cy = now.getFullYear(), cm = now.getMonth() + 1, cd = now.getDate();
    var limitY = cy, limitM = cm;
    if (endVal) {
        var ep = endVal.split('-');
        if (ep.length >= 3) {
            var ey = parseInt(ep[0], 10), em = parseInt(ep[1], 10);
            if (ey * 12 + em < cy * 12 + cm) { limitY = ey; limitM = em; }
        }
    }
    var count = 0, yy = y, mm = m;
    while (yy * 12 + mm <= limitY * 12 + limitM) {
        var lastDay = new Date(yy, mm, 0).getDate();
        var day = Math.min(dueDay, lastDay);
        var beforeCurrent = (yy * 12 + mm) < (cy * 12 + cm);
        var isCurrent = (yy === cy && mm === cm);
        if (beforeCurrent || (isCurrent && day <= cd)) count++;
        mm++; if (mm > 12) { mm = 1; yy++; }
    }
    return count;
}
function updateBackfillConfirm() {
    var form = document.getElementById('commitment_form');
    if (!form) return;
    if (document.getElementById('form_action').value !== 'add') { form.removeAttribute('data-confirm'); return; }
    var startVal = document.getElementById('form_start_date').value;
    var dueDay = parseInt(document.getElementById('due_date_slider').value || '1', 10);
    var endVal = document.getElementById('form_end_date').value;
    var n = backdatedCount(startVal, dueDay, endVal);
    if (n > 0) {
        form.setAttribute('data-confirm', 'This will create ' + n + ' backdated entr' + (n === 1 ? 'y' : 'ies') + ' from ' + startVal + ' to now. Continue?');
        form.setAttribute('data-confirm-label', 'Create');
    } else {
        form.removeAttribute('data-confirm');
    }
}
document.addEventListener('DOMContentLoaded', function(){
    filterCats();
    document.getElementById('form_start_date').value = new Date().toISOString().split('T')[0];
    updateBackfillConfirm();
});
document.getElementById('due_date_slider').addEventListener('input', function(){
    document.getElementById('due_date_display').textContent = this.value;
    updateBackfillConfirm();
});
document.getElementById('form_start_date').addEventListener('input', updateBackfillConfirm);
document.getElementById('form_start_date').addEventListener('change', updateBackfillConfirm);
document.getElementById('form_end_date').addEventListener('input', updateBackfillConfirm);
document.getElementById('form_end_date').addEventListener('change', updateBackfillConfirm);

function editItem(id, name, amount, type, day, catId, startDate, endDate) {
    document.getElementById('form_action').value = 'edit';
    document.getElementById('form_id').value = id;
    document.getElementById('form_type').value = type;
    document.getElementById('form_name').value = name;
    document.getElementById('form_amount').value = amount;
    document.getElementById('due_date_slider').value = day;
    document.getElementById('due_date_display').textContent = day;
    document.getElementById('form_start_date').value = startDate || '';
    document.getElementById('form_end_date').value = endDate || '';
    filterCats();
    document.getElementById('form_category_id').value = catId > 0 ? catId : '';
    document.getElementById('form_title').innerHTML = '<svg class="h-4 w-4" style="color:var(--accent);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg> Edit Item';
    document.getElementById('form_submit_btn').textContent = 'Update Item';
    document.getElementById('cancel_edit_btn').classList.remove('hidden');
    updateBackfillConfirm();
    window.scrollTo({top:0,behavior:'smooth'});
}

function cancelEdit() {
    document.getElementById('form_action').value = 'add';
    document.getElementById('form_id').value = '';
    document.getElementById('form_type').value = 'expense';
    document.getElementById('form_name').value = '';
    document.getElementById('form_amount').value = '';
    document.getElementById('due_date_slider').value = 1;
    document.getElementById('due_date_display').textContent = 1;
    document.getElementById('form_start_date').value = new Date().toISOString().split('T')[0];
    document.getElementById('form_end_date').value = '';
    filterCats();
    document.getElementById('form_category_id').value = '';
    document.getElementById('form_title').innerHTML = '<svg class="h-4 w-4" style="color:var(--accent);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg> Add Recurring Item';
    document.getElementById('form_submit_btn').textContent = 'Save Item';
    document.getElementById('cancel_edit_btn').classList.add('hidden');
    updateBackfillConfirm();
}
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
