<?php
require_once __DIR__ . '/../src/Auth.php';
Auth::requireLogin();

$db = Database::getConnection();
$deleted = max(0, (int) ($_GET['deleted'] ?? 0));

$groups = $db->query("
    SELECT DATE(date) AS d, ROUND(amount, 2) AS amount, type,
           TRIM(LOWER(description)) AS descr, category_id,
           COUNT(*) AS c, GROUP_CONCAT(id) AS row_ids
    FROM transactions
    GROUP BY DATE(date), ROUND(amount, 2), type, TRIM(LOWER(description)), category_id
    HAVING c > 1
    ORDER BY d DESC, amount DESC
")->fetchAll();

$totalCandidates = 0;
foreach ($groups as $g) {
    $totalCandidates += (int) $g['c'] - 1;
}

$fetchByIdsSql = "
    SELECT t.id, t.date, t.amount, t.type, t.description, c.name AS category_name
    FROM transactions t
    LEFT JOIN categories c ON c.id = t.category_id
    WHERE t.id IN (%s)
    ORDER BY t.id
";

ob_start();
?>

<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h2 class="text-2xl font-bold" style="color:var(--text);">Find Duplicates</h2>
        <p class="mt-0.5 text-sm" style="color:var(--text-secondary);">Review likely duplicate transactions and delete only the ones you choose.</p>
    </div>
    <a href="/settings" class="rounded-lg border px-4 py-2 text-sm font-medium text-center transition-colors" style="border-color:var(--border);color:var(--text-secondary);" onmouseover="this.style.background='var(--bg-hover)'" onmouseout="this.style.background=''">Back to Settings</a>
</div>

<?php if ($deleted > 0): ?>
    <div class="mb-5 rounded-lg border px-4 py-3 text-sm" style="background:var(--success-soft);border-color:var(--success);color:var(--success);">
        Removed <?= $deleted ?> transaction(s).
    </div>
<?php endif; ?>

<div class="mb-5 flex items-start gap-2.5 rounded-lg border px-4 py-3 text-sm" style="background:var(--accent-soft);border-color:var(--border);color:var(--text-secondary);">
    <svg class="mt-0.5 h-4 w-4 shrink-0" style="color:var(--accent);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    <span>These entries look identical. Genuine duplicates from an old CSV import appear here, but <strong>valid</strong> same-day purchases (e.g. two identical fuel top-ups) also match. Select only the rows you want to remove.</span>
</div>

<?php if (empty($groups)): ?>
    <div class="rounded-xl border py-16 text-center" style="background:var(--bg-alt);border-color:var(--border);">
        <p class="text-sm" style="color:var(--text-muted);">No duplicate transactions found. Nothing to clean up.</p>
    </div>
<?php else: ?>
    <p class="mb-3 text-sm" style="color:var(--text-secondary);"><?= count($groups) ?> group(s) found &mdash; up to <?= $totalCandidates ?> redundant row(s).</p>
    <form method="POST" action="/settings/duplicates/delete" data-confirm="Delete the selected transaction(s)? This cannot be undone." data-confirm-label="Delete" data-confirm-danger="true">
        <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
        <div class="space-y-4">
            <?php foreach ($groups as $g): ?>
                <?php
                $ids = array_values(array_filter(array_map('intval', explode(',', (string) $g['row_ids'])), fn($v) => $v > 0));
                if (empty($ids)) {
                    continue;
                }
                $idList = implode(',', $ids);
                $rows = $db->query(sprintf($fetchByIdsSql, $idList))->fetchAll();
                $firstDesc = $rows[0]['description'] ?? '';
                ?>
                <div class="overflow-hidden rounded-xl border" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
                    <div class="flex items-center justify-between border-b px-4 py-2.5" style="border-color:var(--border-light);">
                        <div class="flex items-center gap-3">
                            <span class="text-sm font-semibold" style="color:var(--text);"><?= date('d M Y', strtotime((string) $g['d'])) ?></span>
                            <span class="text-xs" style="color:var(--text-muted);"><?= htmlspecialchars((string) $firstDesc, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <span class="text-xs font-semibold" style="color:<?= $g['type'] === 'income' ? 'var(--income)' : 'var(--expense)' ?>;">
                            <?= $g['type'] === 'income' ? '+' : '-' ?>RM <?= number_format((float) $g['amount'], 2) ?> &middot; <?= (int) $g['c'] ?>x
                        </span>
                    </div>
                    <?php foreach ($rows as $r): ?>
                        <label class="flex cursor-pointer items-center justify-between gap-3 border-b px-4 py-3 last:border-b-0" style="border-color:var(--border-light);" onmouseover="this.style.background='var(--bg-hover)'" onmouseout="this.style.background=''">
                            <span class="flex items-center gap-3">
                                <input type="checkbox" name="ids[]" value="<?= (int) $r['id'] ?>" class="h-4 w-4" style="accent-color:var(--accent);">
                                <span class="text-sm" style="color:var(--text);">#<?= (int) $r['id'] ?></span>
                                <span class="text-xs" style="color:var(--text-muted);"><?= htmlspecialchars((string) $r['date'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="text-xs" style="color:var(--text-secondary);"><?= htmlspecialchars((string) ($r['category_name'] ?? 'Uncategorized'), ENT_QUOTES, 'UTF-8') ?></span>
                            </span>
                            <span class="text-sm font-medium" style="color:var(--text-secondary);">RM <?= number_format((float) $r['amount'], 2) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="mt-5">
            <button type="submit" class="rounded-lg px-5 py-2.5 text-sm font-semibold text-white" style="background:var(--danger);">Delete Selected</button>
        </div>
    </form>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
