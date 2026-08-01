<?php
require_once __DIR__ . '/../src/Budget.php';
require_once __DIR__ . '/../src/Expense.php';
require_once __DIR__ . '/../src/Category.php';

$m = Helper::parseMonth();
$year = $m['year'];
$month = $m['month'];
$reqMonth = $m['reqMonth'];
$currentDisplay = $m['currentDisplay'];

$budgets = Budget::getAll();
$allCategories = array_filter(Category::getAll(), fn($c) => $c['type'] === 'expense');
$monthlyTotals = [];
foreach (Expense::getExpensesByCategory($month, $year) as $cat) {
    $monthlyTotals[$cat['name']] = $cat['total'];
}

ob_start();
?>

<div class="mb-6">
    <h2 class="text-2xl font-bold" style="color:var(--text);">Budgets</h2>
    <p class="mt-0.5 text-sm" style="color:var(--text-secondary);">Set monthly spending limits per category and track your progress.</p>
</div>

<div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
    <!-- Set/Edit Budget -->
    <div class="rounded-xl border p-5" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
        <h3 class="mb-4 text-base font-semibold" style="color:var(--text);">Set Category Budget</h3>
        <form method="POST" action="/budget/set" class="space-y-3">
            <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
            <div>
                <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Category</label>
                <select name="category_id" required class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                    <option value="">Select category...</option>
                    <?php foreach ($allCategories as $cat): ?>
                        <option value="<?= (int)$cat['id'] ?>"><?= htmlspecialchars((string)$cat['name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Monthly Budget (RM)</label>
                <input type="number" step="0.01" min="0.01" name="amount" required placeholder="e.g. 300"
                    class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
            </div>
            <button type="submit" class="w-full rounded-lg py-2.5 text-sm font-semibold text-white" style="background:var(--accent);">
                Save Budget
            </button>
        </form>
    </div>

    <!-- Current Month Progress -->
    <div class="rounded-xl border p-5" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
        <h3 class="mb-4 text-base font-semibold" style="color:var(--text);"><?= htmlspecialchars((string)$currentDisplay, ENT_QUOTES, 'UTF-8') ?> Progress</h3>

        <?php if (empty($budgets)): ?>
            <p class="py-8 text-center text-sm" style="color:var(--text-muted);">No budgets set. Add one to start tracking.</p>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($budgets as $b): ?>
                    <?php
                        $spent = $monthlyTotals[$b['category_name']] ?? 0;
                        $pct = $b['amount'] > 0 ? min(($spent / $b['amount']) * 100, 100) : 0;
                        $over = $spent > $b['amount'];
                        $remaining = $b['amount'] - $spent;
                    ?>
                    <div class="rounded-lg border p-3" style="border-color:var(--border-light);background:var(--bg);">
                        <div class="mb-2 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="h-2.5 w-2.5 rounded-full" style="background:<?= htmlspecialchars((string)$b['color_hex'], ENT_QUOTES, 'UTF-8') ?>;"></div>
                                <span class="text-sm font-medium" style="color:var(--text);"><?= htmlspecialchars((string)$b['category_name'], ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <form method="POST" action="/budget/delete" class="inline" onsubmit="return confirm('Remove budget for <?= htmlspecialchars((string)$b['category_name'], ENT_QUOTES, 'UTF-8') ?>?');">
                                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                                <input type="hidden" name="category_id" value="<?= (int)$b['category_id'] ?>">
                                <button type="submit" class="rounded p-0.5 text-xs" style="color:var(--text-muted);" onmouseover="this.style.color='var(--danger)'" onmouseout="this.style.color='var(--text-muted)'" title="Remove budget">&times;</button>
                            </form>
                        </div>
                        <div class="mb-1.5 h-2 overflow-hidden rounded-full" style="background:var(--bg-hover);">
                            <div class="h-full rounded-full transition-all" style="width:<?= $pct ?>%;background:<?= $over ? 'var(--expense)' : 'var(--accent)' ?>;"></div>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span style="color:var(--text-muted);">RM <?= number_format($spent, 0) ?> spent of RM <?= number_format($b['amount'], 0) ?></span>
                            <span style="color:<?= $over ? 'var(--expense)' : 'var(--success)' ?>;"><?= $over ? 'Over by RM ' . number_format(abs($remaining), 0) : 'RM ' . number_format($remaining, 0) . ' left' ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
