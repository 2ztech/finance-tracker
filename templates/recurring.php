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
$todayDate = new DateTimeImmutable('today');
$upcomingItems = [];
foreach ($activeCommitments as $c) {
    $day = max(1, min(31, (int)$c['due_date_day']));
    $candidate = $todayDate->modify('first day of this month')->setDate((int)$todayDate->format('Y'), (int)$todayDate->format('n'), min($day, (int)$todayDate->format('t')));
    if ($candidate < $todayDate) {
        $nextMonth = $todayDate->modify('first day of next month');
        $candidate = $nextMonth->setDate((int)$nextMonth->format('Y'), (int)$nextMonth->format('n'), min($day, (int)$nextMonth->format('t')));
    }
    if (!empty($c['start_date']) && $candidate->format('Y-m-d') < $c['start_date']) {
        $start = new DateTimeImmutable($c['start_date']);
        $candidate = $start->modify('first day of this month')->setDate((int)$start->format('Y'), (int)$start->format('n'), min($day, (int)$start->format('t')));
        if ($candidate < $start) {
            $nextMonth = $start->modify('first day of next month');
            $candidate = $nextMonth->setDate((int)$nextMonth->format('Y'), (int)$nextMonth->format('n'), min($day, (int)$nextMonth->format('t')));
        }
    }
    if ((!empty($c['end_date']) && $candidate->format('Y-m-d') > $c['end_date']) || $candidate > $todayDate->modify('+30 days')) continue;
    $c['_next_due'] = $candidate;
    $upcomingItems[] = $c;
}
usort($upcomingItems, static fn(array $a, array $b): int => $a['_next_due'] <=> $b['_next_due']);
$nextDueItem = $upcomingItems[0] ?? null;

ob_start();
?>

<div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div><h2 class="text-2xl font-bold" style="color:var(--text);">Recurring Items</h2><p class="mt-0.5 text-sm" style="color:var(--text-secondary);">Bills, subscriptions, and regular income. Set it once and it repeats monthly.</p></div>
    <a href="#recurring-form" class="inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold text-white" style="background:var(--accent);"><span class="text-lg leading-none">+</span> Add Recurring Item</a>
</div>

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <div class="rounded-xl border p-4" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);"><p class="text-xs" style="color:var(--text-secondary);">Total Recurring</p><p class="mt-1 text-xl font-bold" style="color:var(--text);"><?= count($commitments) ?> items</p><p class="mt-2 text-xs" style="color:var(--text-muted);"><?= count(array_filter($commitments, fn($c) => ($c['type'] ?? 'expense') === 'expense')) ?> expenses · <?= count(array_filter($commitments, fn($c) => ($c['type'] ?? 'expense') === 'income')) ?> income</p></div>
    <div class="rounded-xl border p-4" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);"><p class="text-xs" style="color:var(--text-secondary);">Monthly Out (Expense)</p><p class="mt-1 text-xl font-bold" style="color:var(--expense);">RM <?= number_format($totalExpense, 2) ?></p><p class="mt-2 text-xs" style="color:var(--text-muted);">Active monthly commitments</p></div>
    <div class="rounded-xl border p-4" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);"><p class="text-xs" style="color:var(--text-secondary);">Monthly In (Income)</p><p class="mt-1 text-xl font-bold" style="color:var(--income);">RM <?= number_format($totalIncome, 2) ?></p><p class="mt-2 text-xs" style="color:var(--text-muted);">Active monthly commitments</p></div>
    <div class="rounded-xl border p-4" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);"><p class="text-xs" style="color:var(--text-secondary);">Next Due</p><?php if ($nextDueItem): ?><p class="mt-1 truncate text-base font-bold" style="color:var(--text);"><?= htmlspecialchars((string)$nextDueItem['name'], ENT_QUOTES, 'UTF-8') ?></p><p class="mt-1 text-xs" style="color:var(--text-muted);"><?= $nextDueItem['_next_due']->format('j M Y') ?> · RM <?= number_format((float)$nextDueItem['amount'], 2) ?></p><?php else: ?><p class="mt-1 text-base font-bold" style="color:var(--text-muted);">Nothing due</p><p class="mt-1 text-xs" style="color:var(--text-muted);">In the next 30 days</p><?php endif; ?></div>
</div>

<div class="grid grid-cols-1 items-start gap-4 xl:grid-cols-12">
<section class="min-w-0 overflow-hidden rounded-xl border xl:col-span-9" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
    <div class="flex flex-col gap-3 border-b p-3 sm:flex-row sm:items-center sm:justify-between" style="border-color:var(--border-light);">
        <div class="flex items-center gap-1" role="tablist" aria-label="Filter recurring items">
            <button type="button" class="recurring-filter rounded-lg px-3 py-2 text-xs font-semibold" data-filter="all" aria-pressed="true" style="background:var(--accent-soft);color:var(--accent);">All <span class="ml-1 rounded-full px-1.5 py-0.5" style="background:var(--bg-alt);"><?= count($commitments) ?></span></button>
            <button type="button" class="recurring-filter rounded-lg px-3 py-2 text-xs font-medium" data-filter="expense" aria-pressed="false" style="color:var(--text-secondary);">Expenses <span class="ml-1 rounded-full px-1.5 py-0.5" style="background:var(--bg-hover);"><?= count(array_filter($commitments, fn($c) => ($c['type'] ?? 'expense') === 'expense')) ?></span></button>
            <button type="button" class="recurring-filter rounded-lg px-3 py-2 text-xs font-medium" data-filter="income" aria-pressed="false" style="color:var(--text-secondary);">Income <span class="ml-1 rounded-full px-1.5 py-0.5" style="background:var(--bg-hover);"><?= count(array_filter($commitments, fn($c) => ($c['type'] ?? 'expense') === 'income')) ?></span></button>
        </div>
        <div class="flex flex-wrap gap-2">
            <label class="flex min-w-44 flex-1 items-center gap-2 rounded-lg border px-3 py-2 sm:flex-none" style="border-color:var(--border);"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:var(--text-muted);"><circle cx="11" cy="11" r="7" stroke-width="1.8"/><path d="m16 16 4 4" stroke-width="1.8"/></svg><input id="recurringSearch" type="search" placeholder="Search recurring items…" class="w-full border-0 bg-transparent p-0 text-xs outline-none" style="color:var(--text);"></label>
            <select id="recurringSort" class="rounded-lg border px-3 py-2 text-xs" style="background:var(--bg-alt);border-color:var(--border);color:var(--text);"><option value="due">Sort: Next due</option><option value="name">Sort: Name</option><option value="amount">Sort: Amount</option></select>
            <?php if ($archivedCommitments): ?><details class="relative"><summary class="cursor-pointer list-none rounded-lg border px-3 py-2 text-xs font-medium" style="border-color:var(--border);color:var(--text-secondary);">Archived (<?= count($archivedCommitments) ?>)</summary><div class="absolute right-0 z-20 mt-2 max-h-72 w-72 space-y-2 overflow-auto rounded-xl border p-3" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);"><?php foreach ($archivedCommitments as $archived): ?><div class="flex items-center justify-between gap-3 border-b pb-2" style="border-color:var(--border-light);"><div class="min-w-0"><p class="truncate text-sm font-medium" style="color:var(--text);"><?= htmlspecialchars((string)$archived['name'], ENT_QUOTES, 'UTF-8') ?></p><p class="text-xs" style="color:var(--text-muted);">RM <?= number_format((float)$archived['amount'], 2) ?> · Monthly, day <?= (int)$archived['due_date_day'] ?></p></div><form method="POST" action="/recurring"><input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>"><input type="hidden" name="action" value="restore"><input type="hidden" name="id" value="<?= (int)$archived['id'] ?>"><button class="rounded-lg border px-2.5 py-1.5 text-xs font-semibold" style="border-color:var(--border);color:var(--accent);">Restore</button></form></div><?php endforeach; ?></div></details><?php endif; ?>
        </div>
    </div>
    <div class="overflow-x-auto"><table class="recurring-items-table w-full text-left text-sm">
        <thead><tr style="background:var(--bg-hover);color:var(--text-secondary);"><th class="px-4 py-3 text-xs font-medium">Item</th><th class="px-3 py-3 text-xs font-medium">Amount</th><th class="px-3 py-3 text-xs font-medium">Category</th><th class="px-3 py-3 text-xs font-medium">Frequency</th><th class="px-3 py-3 text-xs font-medium">Next Due</th><th class="px-3 py-3 text-xs font-medium">End Date</th><th class="px-4 py-3 text-right text-xs font-medium">Actions</th></tr></thead>
        <tbody id="recurringRows">
        <?php foreach ($commitments as $c): ?>
            <?php
                $cType = $c['type'] ?? 'expense'; $isIncome = $cType === 'income';
                $hasStarted = empty($c['start_date']) || $c['start_date'] <= $today;
                $hasEnded = !empty($c['end_date']) && $c['end_date'] < $today;
                $posted = Expense::currentPeriodPosted((int)$c['id']);
                $nextDue = null;
                foreach ($upcomingItems as $upcomingItem) if ((int)$upcomingItem['id'] === (int)$c['id']) { $nextDue = $upcomingItem['_next_due']; break; }
            ?>
            <tr class="recurring-row border-t" data-type="<?= htmlspecialchars((string)$cType, ENT_QUOTES, 'UTF-8') ?>" data-name="<?= htmlspecialchars(strtolower((string)$c['name']), ENT_QUOTES, 'UTF-8') ?>" data-category="<?= htmlspecialchars(strtolower((string)($c['category_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?>" data-amount="<?= (float)$c['amount'] ?>" data-due="<?= $nextDue?->format('Y-m-d') ?? '9999-12-31' ?>" style="border-color:var(--border-light);">
                <td class="px-4 py-3"><div class="flex items-center gap-3"><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl" style="background:<?= $isIncome ? 'var(--success-soft)' : 'var(--danger-soft)' ?>;color:<?= $isIncome ? 'var(--income)' : 'var(--expense)' ?>;"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><?php if ($isIncome): ?><path stroke-linecap="round" stroke-linejoin="round" d="M7 17 17 7M7 7h10v10"/><?php else: ?><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 5-5m-5 5-5-5M5 21h14"/><?php endif; ?></svg></span><div class="min-w-0"><p class="truncate font-semibold" style="color:var(--text);"><?= htmlspecialchars((string)$c['name'], ENT_QUOTES, 'UTF-8') ?></p><p class="text-xs" style="color:var(--text-secondary);">Monthly<?= !empty($c['start_date']) ? ' · Started '.date('j M Y', strtotime($c['start_date'])) : '' ?></p></div></div></td>
                <td class="whitespace-nowrap px-3 py-3 font-semibold" style="color:<?= $isIncome ? 'var(--income)' : 'var(--expense)' ?>;"><?= $isIncome ? '+' : '−' ?>RM <?= number_format((float)$c['amount'], 2) ?></td>
                <td class="px-3 py-3"><span class="inline-flex items-center gap-2 text-xs"><?php if (!empty($c['icon_data'])): ?><img class="h-6 w-6 rounded-md object-contain" src="data:<?= htmlspecialchars((string)($c['icon_mime'] ?? 'image/png'), ENT_QUOTES, 'UTF-8') ?>;base64,<?= htmlspecialchars((string)$c['icon_data'], ENT_QUOTES, 'UTF-8') ?>" alt=""><?php else: ?><span class="flex h-6 w-6 items-center justify-center rounded-md" style="background:color-mix(in srgb, <?= htmlspecialchars((string)($c['color_hex'] ?? '#94a3b8'), ENT_QUOTES, 'UTF-8') ?> 14%, white);color:<?= htmlspecialchars((string)($c['color_hex'] ?? '#94a3b8'), ENT_QUOTES, 'UTF-8') ?>;"><?= IconCatalog::svg((string)($c['icon_key'] ?? 'other'), 'h-4 w-4') ?></span><?php endif; ?><?= htmlspecialchars((string)($c['category_name'] ?? 'Uncategorized'), ENT_QUOTES, 'UTF-8') ?></span></td>
                <td class="px-3 py-3 text-xs" style="color:var(--text-secondary);">Monthly<br><span style="color:var(--text-muted);">on day <?= (int)$c['due_date_day'] ?></span></td>
                <td class="whitespace-nowrap px-3 py-3 text-xs"><?php if ($nextDue): ?><span class="font-medium" style="color:var(--text);"><?= $nextDue->format('j M Y') ?></span><br><span class="inline-flex rounded-md px-2 py-0.5" style="background:<?= $nextDue->diff($todayDate)->days <= 5 ? 'var(--danger-soft)' : 'var(--accent-soft)' ?>;color:<?= $nextDue->diff($todayDate)->days <= 5 ? 'var(--expense)' : 'var(--accent)' ?>;"><?= $nextDue == $todayDate ? 'Today' : $nextDue->diff($todayDate)->days.' days' ?></span><?php elseif (!$hasStarted): ?><span style="color:var(--text-muted);">Starts <?= date('j M Y', strtotime((string)$c['start_date'])) ?></span><?php elseif ($hasEnded): ?><span style="color:var(--text-muted);">Expired</span><?php else: ?><span style="color:var(--text-muted);">—</span><?php endif; ?><?php if ($posted): ?><span class="ml-1 rounded px-1.5 py-0.5 text-[10px] font-semibold" style="background:var(--success-soft);color:var(--income);">Posted</span><?php endif; ?></td>
                <td class="whitespace-nowrap px-3 py-3 text-xs" style="color:var(--text-secondary);"><?= !empty($c['end_date']) ? date('j M Y', strtotime((string)$c['end_date'])) : '—' ?></td>
                <td class="px-4 py-3"><div class="flex items-center justify-end gap-1"><?php if ($hasStarted && !$hasEnded && !$posted): ?><form method="POST" action="/recurring"><input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>"><input type="hidden" name="action" value="post_now"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button type="submit" class="whitespace-nowrap rounded-lg px-3 py-2 text-xs font-semibold" style="background:var(--accent-soft);color:var(--accent);">Post now</button></form><?php endif; ?><button type="button" aria-label="Edit <?= htmlspecialchars((string)$c['name'], ENT_QUOTES, 'UTF-8') ?>" onclick="editItem(<?= (int)$c['id'] ?>, <?= htmlspecialchars(json_encode((string)$c['name'], JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_TAG|JSON_HEX_AMP), ENT_QUOTES, 'UTF-8') ?>, <?= (float)$c['amount'] ?>, '<?= htmlspecialchars((string)$cType, ENT_QUOTES, 'UTF-8') ?>', <?= (int)$c['due_date_day'] ?>, <?= (int)($c['category_id'] ?? 0) ?>, '<?= htmlspecialchars((string)($c['start_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars((string)($c['end_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>')" class="rounded-lg p-2" style="color:var(--text-secondary);"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m15 5 4 4M4 20l4-.8L19 8a2.1 2.1 0 0 0-3-3L5 16l-1 4Z"/></svg></button><form method="POST" action="/recurring" data-confirm="Archive this recurring item? Past transactions are kept." data-confirm-label="Archive" data-confirm-danger="true"><input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>"><input type="hidden" name="action" value="archive"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button type="submit" title="Archive" aria-label="Archive <?= htmlspecialchars((string)$c['name'], ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg p-2" style="color:var(--text-secondary);"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7h16M5 7l1 13h12l1-13M9 7V4h6v3m-5 4v5m4-5v5"/></svg></button></form></div></td>
            </tr>
        <?php endforeach; ?>
        <tr id="recurringEmpty" class="hidden"><td colspan="7" class="px-4 py-12 text-center text-sm" style="color:var(--text-muted);">No recurring items match your search.</td></tr>
        </tbody></table></div>
    <div class="border-t px-4 py-3 text-xs" style="border-color:var(--border-light);color:var(--text-muted);">Showing <span id="recurringShown"><?= count($commitments) ?></span> of <?= count($commitments) ?> items</div>
</section>

<aside class="space-y-4 xl:col-span-3">
    <section id="recurring-form" class="rounded-xl border p-4" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
        <h3 id="form_title" class="mb-4 flex items-center gap-2 text-base font-semibold" style="color:var(--text);"><span class="text-xl leading-none" style="color:var(--accent);">+</span> Add Recurring Item</h3>
        <form method="POST" action="/recurring" id="commitment_form" class="space-y-3">
            <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>"><input type="hidden" name="action" id="form_action" value="add"><input type="hidden" name="id" id="form_id" value="">
            <div><label for="form_type" class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Type</label><select name="type" id="form_type" onchange="filterCats()" class="w-full rounded-lg border px-3 py-2.5 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);"><option value="expense">↓　Expense</option><option value="income">↑　Income</option></select></div>
            <div><label for="form_name" class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Name</label><input type="text" name="name" id="form_name" required placeholder="e.g. Unifi, Car Loan, Salary" class="w-full rounded-lg border px-3 py-2.5 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);"></div>
            <div><label for="form_amount" class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Amount (RM)</label><input type="number" step="0.01" min="0.01" name="amount" id="form_amount" required placeholder="0.00" class="w-full rounded-lg border px-3 py-2.5 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);"></div>
            <div><label for="form_category_id" class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Category</label><select name="category_id" id="form_category_id" class="w-full rounded-lg border px-3 py-2.5 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);"><option value="">No Category</option></select></div>
            <div><label class="mb-1 flex items-center justify-between text-xs font-medium" style="color:var(--text-secondary);"><span>Monthly due day</span><span>Day <b id="due_date_display" style="color:var(--text);">1</b></span></label><input type="range" min="1" max="31" value="1" name="due_date_day" id="due_date_slider" class="w-full" style="accent-color:var(--accent);"><p class="mt-1 text-[11px]" style="color:var(--text-muted);">Repeats automatically once a month.</p></div>
            <div class="grid grid-cols-2 gap-2"><div><label for="form_start_date" class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Start Date</label><input type="date" name="start_date" id="form_start_date" class="w-full rounded-lg border px-2 py-2 text-xs" style="background:var(--bg);border-color:var(--border);color:var(--text);"></div><div><label for="form_end_date" class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">End Date <span class="font-normal">(optional)</span></label><input type="date" name="end_date" id="form_end_date" class="w-full rounded-lg border px-2 py-2 text-xs" style="background:var(--bg);border-color:var(--border);color:var(--text);"></div></div>
            <button type="submit" id="form_submit_btn" class="w-full rounded-lg py-2.5 text-sm font-semibold text-white" style="background:var(--accent);">Save Recurring Item</button><button type="button" id="cancel_edit_btn" onclick="cancelEdit()" class="hidden w-full rounded-lg border py-2.5 text-sm font-semibold" style="border-color:var(--border);color:var(--text-secondary);background:var(--bg-hover);">Cancel</button>
        </form>
    </section>
    <section class="rounded-xl border p-4" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);"><div class="mb-3 flex items-center justify-between"><h3 class="text-sm font-semibold" style="color:var(--text);">Upcoming · Next 30 Days</h3><span class="text-xs" style="color:var(--text-muted);"><?= count($upcomingItems) ?> due</span></div><?php if (!$upcomingItems): ?><p class="py-5 text-center text-xs" style="color:var(--text-muted);">Nothing due in the next 30 days.</p><?php else: ?><div class="space-y-3"><?php foreach (array_slice($upcomingItems, 0, 6) as $u): $isIncome = ($u['type'] ?? 'expense') === 'income'; $daysAway = $u['_next_due']->diff($todayDate)->days; ?><div class="flex items-center justify-between gap-2"><div class="flex min-w-0 items-center gap-2.5"><span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg" style="background:<?= $isIncome ? 'var(--success-soft)' : 'var(--danger-soft)' ?>;color:<?= $isIncome ? 'var(--income)' : 'var(--expense)' ?>;"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><?php if ($isIncome): ?><path stroke-linecap="round" stroke-linejoin="round" d="M7 17 17 7M7 7h10v10"/><?php else: ?><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 5-5m-5 5-5-5M5 21h14"/><?php endif; ?></svg></span><div class="min-w-0"><p class="truncate text-xs font-semibold" style="color:var(--text);"><?= htmlspecialchars((string)$u['name'], ENT_QUOTES, 'UTF-8') ?></p><p class="text-[11px]" style="color:var(--text-secondary);"><span style="color:<?= $daysAway <= 5 ? 'var(--expense)' : 'var(--accent)' ?>;"><?= $daysAway === 0 ? 'Today' : $daysAway.' days' ?></span> · <?= $u['_next_due']->format('j M Y') ?></p></div></div><b class="whitespace-nowrap text-xs" style="color:<?= $isIncome ? 'var(--income)' : 'var(--expense)' ?>;">RM <?= number_format((float)$u['amount'], 2) ?></b></div><?php endforeach; ?></div><?php endif; ?></section>
</aside>
</div>

<script>
var cats = <?= json_encode($categories) ?>;
var recurringTypeFilter = 'all';
function filterRecurringRows() {
    var query = document.getElementById('recurringSearch').value.trim().toLowerCase();
    var rows = Array.from(document.querySelectorAll('.recurring-row'));
    var sort = document.getElementById('recurringSort').value;
    rows.sort(function(a, b) {
        if (sort === 'name') return a.dataset.name.localeCompare(b.dataset.name);
        if (sort === 'amount') return Number(b.dataset.amount) - Number(a.dataset.amount);
        return a.dataset.due.localeCompare(b.dataset.due);
    }).forEach(function(row) { document.getElementById('recurringRows').appendChild(row); });
    var shown = 0;
    rows.forEach(function(row) {
        var text = row.dataset.name + ' ' + row.dataset.category;
        var visible = (recurringTypeFilter === 'all' || row.dataset.type === recurringTypeFilter) && text.indexOf(query) !== -1;
        row.classList.toggle('hidden', !visible);
        if (visible) shown++;
    });
    document.getElementById('recurringShown').textContent = shown;
    document.getElementById('recurringEmpty').classList.toggle('hidden', shown !== 0);
}
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
    document.getElementById('recurringSearch').addEventListener('input', filterRecurringRows);
    document.getElementById('recurringSort').addEventListener('change', filterRecurringRows);
    document.querySelectorAll('.recurring-filter').forEach(function(button) {
        button.addEventListener('click', function() {
            recurringTypeFilter = button.dataset.filter;
            document.querySelectorAll('.recurring-filter').forEach(function(tab) {
                var selected = tab === button;
                tab.setAttribute('aria-pressed', selected ? 'true' : 'false');
                tab.classList.toggle('font-semibold', selected);
                tab.style.background = selected ? 'var(--accent-soft)' : 'transparent';
                tab.style.color = selected ? 'var(--accent)' : 'var(--text-secondary)';
            });
            filterRecurringRows();
        });
    });
    filterRecurringRows();
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
    document.getElementById('recurring-form').scrollIntoView({behavior:'smooth',block:'start'});
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
