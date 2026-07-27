<?php
require_once __DIR__ . '/../src/Category.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $name = trim($_POST['name'] ?? '');
            $type = $_POST['type'] ?? 'expense';
            $color = $_POST['color_hex'] ?? '#4f6ef7';
            if ($name && in_array($type, ['income', 'expense'])) {
                Category::create($name, $type, $color);
            }
        } elseif ($_POST['action'] === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                Category::delete($id);
            }
        }
        header('Location: /categories');
        exit;
    }
}

$categories = Category::getAll();
$expenses = array_filter($categories, fn($c) => $c['type'] === 'expense');
$incomes = array_filter($categories, fn($c) => $c['type'] === 'income');

ob_start();
?>

<div class="mb-6">
    <h2 class="text-2xl font-bold" style="color:var(--text);">Categories</h2>
    <p class="mt-0.5 text-sm" style="color:var(--text-secondary);">Organize your transactions with custom categories.</p>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <!-- Lists -->
    <div class="space-y-6 lg:col-span-2">
        <!-- Expense Categories -->
        <div class="rounded-xl border p-5" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
            <h3 class="mb-4 flex items-center gap-2 text-base font-semibold" style="color:var(--expense);">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"/></svg>
                Expense Categories
            </h3>
            <?php if (empty($expenses)): ?>
                <p class="py-8 text-center text-sm" style="color:var(--text-muted);">No expense categories yet.</p>
            <?php else: ?>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <?php foreach ($expenses as $c): ?>
                        <div class="flex items-center justify-between rounded-lg border p-3 transition-colors" style="background:var(--bg);border-color:var(--border-light);" onmouseover="this.style.background='var(--bg-hover)'" onmouseout="this.style.background='var(--bg)'">
                            <div class="flex items-center gap-3">
                                <div class="h-4 w-4 rounded-full" style="background:<?= htmlspecialchars((string)$c['color_hex'], ENT_QUOTES, 'UTF-8') ?>;"></div>
                                <span class="text-sm font-medium" style="color:var(--text);"><?= htmlspecialchars((string)$c['name'], ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <form method="POST" action="/categories" onsubmit="return confirm('Delete this category? Transactions using it will become uncategorized.');">
                                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                <button type="submit" class="rounded p-1 opacity-0 transition-opacity sm:group-hover:opacity-100" style="opacity:0.3;color:var(--text-muted);" onmouseover="this.style.color='var(--danger)'" onmouseout="this.style.color='var(--text-muted)'">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Income Categories -->
        <div class="rounded-xl border p-5" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
            <h3 class="mb-4 flex items-center gap-2 text-base font-semibold" style="color:var(--income);">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                Income Categories
            </h3>
            <?php if (empty($incomes)): ?>
                <p class="py-8 text-center text-sm" style="color:var(--text-muted);">No income categories yet.</p>
            <?php else: ?>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <?php foreach ($incomes as $c): ?>
                        <div class="flex items-center justify-between rounded-lg border p-3 transition-colors" style="background:var(--bg);border-color:var(--border-light);" onmouseover="this.style.background='var(--bg-hover)'" onmouseout="this.style.background='var(--bg)'">
                            <div class="flex items-center gap-3">
                                <div class="h-4 w-4 rounded-full" style="background:<?= htmlspecialchars((string)$c['color_hex'], ENT_QUOTES, 'UTF-8') ?>;"></div>
                                <span class="text-sm font-medium" style="color:var(--text);"><?= htmlspecialchars((string)$c['name'], ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <form method="POST" action="/categories" onsubmit="return confirm('Delete this category? Transactions using it will become uncategorized.');">
                                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                <button type="submit" class="rounded p-1 opacity-0 transition-opacity sm:group-hover:opacity-100" style="opacity:0.3;color:var(--text-muted);" onmouseover="this.style.color='var(--danger)'" onmouseout="this.style.color='var(--text-muted)'">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Form -->
    <div>
        <div class="sticky top-6 rounded-xl border p-5" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
            <h3 class="mb-5 flex items-center gap-2 text-base font-semibold" style="color:var(--text);">
                <svg class="h-4 w-4" style="color:var(--accent);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add Category
            </h3>
            <form method="POST" action="/categories" class="space-y-3">
                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                <input type="hidden" name="action" value="add">

                <div>
                    <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Name</label>
                    <input type="text" name="name" required placeholder="e.g. Groceries"
                        class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Type</label>
                    <div class="flex rounded-lg border p-0.5" style="background:var(--bg);border-color:var(--border);">
                        <label class="flex-1 cursor-pointer rounded-md px-3 py-2 text-center text-sm font-medium transition-colors" style="color:var(--text);background:var(--accent-soft);">
                            <input type="radio" name="type" value="expense" class="sr-only" checked onchange="updateRadioStyles()"> Expense
                        </label>
                        <label class="flex-1 cursor-pointer rounded-md px-3 py-2 text-center text-sm font-medium transition-colors" style="color:var(--text-secondary);">
                            <input type="radio" name="type" value="income" class="sr-only" onchange="updateRadioStyles()"> Income
                        </label>
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Color</label>
                    <div class="flex items-center gap-3 rounded-lg border px-3 py-2" style="background:var(--bg);border-color:var(--border);">
                        <input type="color" name="color_hex" value="#4f6ef7" id="color_input"
                            class="h-8 w-8 cursor-pointer rounded border-0 bg-transparent p-0">
                        <span id="color_text" class="text-sm font-mono" style="color:var(--text);">#4f6ef7</span>
                    </div>
                </div>

                <button type="submit" class="w-full rounded-lg py-2.5 text-sm font-semibold text-white" style="background:var(--accent);">
                    Create Category
                </button>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('color_input').addEventListener('input', function(){
    document.getElementById('color_text').textContent = this.value;
});
document.querySelectorAll('input[name="type"]').forEach(function(r){
    r.addEventListener('change', updateRadioStyles);
});
function updateRadioStyles() {
    document.querySelectorAll('input[name="type"]').forEach(function(r){
        var label = r.parentElement;
        if (r.checked) {
            label.style.color = 'var(--text)';
            label.style.background = 'var(--accent-soft)';
        } else {
            label.style.color = 'var(--text-secondary)';
            label.style.background = 'transparent';
        }
    });
}
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
