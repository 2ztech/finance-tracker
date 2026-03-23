<?php
// templates/categories.php
require_once __DIR__ . '/../src/Category.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $name = trim($_POST['name'] ?? '');
            $type = $_POST['type'] ?? 'expense';
            $color = $_POST['color_hex'] ?? '#10b981';
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
<div class="mb-8 flex flex-col md:flex-row md:items-end justify-between gap-4">
    <div>
        <h2 class="text-3xl font-bold tracking-tight mb-1 text-white">Categories</h2>
        <p class="text-gray-400">Organize your transactions with custom categories.</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- List -->
    <div class="lg:col-span-2 space-y-8">
        <!-- Expenses -->
        <div class="bg-dark-800/50 backdrop-blur-sm border border-dark-700/50 rounded-3xl p-6 shadow-inner relative overflow-hidden">
            <div class="absolute -top-10 -right-10 w-40 h-40 bg-red-500/5 rounded-full blur-3xl pointer-events-none"></div>
            <h3 class="text-xl font-bold text-white mb-6 flex items-center gap-2">
                <span class="w-8 h-8 rounded-lg bg-red-500/10 text-red-400 flex items-center justify-center border border-red-500/20">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path></svg>
                </span>
                Expense Categories
            </h3>
            
            <?php if(empty($expenses)): ?>
                <div class="text-center py-6 text-gray-500 bg-dark-900/50 rounded-2xl border border-dark-700/50 border-dashed">No expense categories yet.</div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($expenses as $c): ?>
                        <div class="bg-dark-800 backdrop-blur-md border border-dark-700 rounded-xl p-4 flex items-center justify-between group hover:border-dark-500 transition-all shadow-sm hover:shadow-md">
                            <div class="flex items-center gap-4">
                                <div class="w-5 h-5 rounded-full shadow-inner ring-4 ring-dark-700 group-hover:ring-dark-600 transition-all" style="background-color: <?= htmlspecialchars((string)$c['color_hex'], ENT_QUOTES, 'UTF-8') ?>"></div>
                                <span class="font-medium text-gray-200 text-lg"><?= htmlspecialchars((string)$c['name'], ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <form method="POST" action="/categories" onsubmit="return confirm('Delete this category? Transactions using it will be uncategorized.');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                <button type="submit" class="p-2 text-gray-500 hover:text-red-400 hover:bg-red-500/10 rounded-lg transition-colors opacity-0 group-hover:opacity-100 focus:opacity-100">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Incomes -->
        <div class="bg-dark-800/50 backdrop-blur-sm border border-dark-700/50 rounded-3xl p-6 shadow-inner relative overflow-hidden">
            <div class="absolute -top-10 -right-10 w-40 h-40 bg-brand-500/5 rounded-full blur-3xl pointer-events-none"></div>
            <h3 class="text-xl font-bold text-white mb-6 flex items-center gap-2">
                <span class="w-8 h-8 rounded-lg bg-brand-500/10 text-brand-400 flex items-center justify-center border border-brand-500/20">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                </span>
                Income Categories
            </h3>
            
            <?php if(empty($incomes)): ?>
                <div class="text-center py-6 text-gray-500 bg-dark-900/50 rounded-2xl border border-dark-700/50 border-dashed">No income categories yet.</div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($incomes as $c): ?>
                        <div class="bg-dark-800 backdrop-blur-md border border-dark-700 rounded-xl p-4 flex items-center justify-between group hover:border-dark-500 transition-all shadow-sm hover:shadow-md">
                            <div class="flex items-center gap-4">
                                <div class="w-5 h-5 rounded-full shadow-inner ring-4 ring-dark-700 group-hover:ring-dark-600 transition-all" style="background-color: <?= htmlspecialchars((string)$c['color_hex'], ENT_QUOTES, 'UTF-8') ?>"></div>
                                <span class="font-medium text-gray-200 text-lg"><?= htmlspecialchars((string)$c['name'], ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <form method="POST" action="/categories" onsubmit="return confirm('Delete this category? Transactions using it will be uncategorized.');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                <button type="submit" class="p-2 text-gray-500 hover:text-red-400 hover:bg-red-500/10 rounded-lg transition-colors opacity-0 group-hover:opacity-100 focus:opacity-100">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
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
        <div class="bg-dark-800/80 backdrop-blur-md border border-dark-700/50 rounded-2xl p-6 shadow-xl sticky top-6">
            <h3 class="text-xl font-bold text-white mb-6 flex items-center gap-2">
                <svg class="w-5 h-5 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Add Category
            </h3>
            <form method="POST" action="/categories" class="space-y-6">
                <input type="hidden" name="action" value="add">
                
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-2 ml-1">Category Name</label>
                    <input type="text" name="name" required placeholder="e.g. Groceries"
                        class="w-full px-4 py-3 bg-dark-900 border border-dark-600 rounded-xl focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 outline-none text-white placeholder-gray-600 transition-all shadow-inner">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-2 ml-1">Type</label>
                    <div class="grid grid-cols-2 gap-3 p-1.5 bg-dark-900 border border-dark-600 rounded-xl shadow-inner">
                        <label class="cursor-pointer">
                            <input type="radio" name="type" value="expense" class="peer sr-only" checked>
                            <div class="text-center py-2 rounded-lg peer-checked:bg-white/10 peer-checked:text-white text-gray-500 transition-all font-medium">Expense</div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="type" value="income" class="peer sr-only">
                            <div class="text-center py-2 rounded-lg peer-checked:bg-white/10 peer-checked:text-white text-gray-500 transition-all font-medium">Income</div>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-2 ml-1">Color Marker</label>
                    <div class="flex items-center gap-4 bg-dark-900 border border-dark-600 rounded-xl p-2 shadow-inner">
                        <input type="color" name="color_hex" value="#10b981" id="color_input"
                            class="w-10 h-10 rounded-lg cursor-pointer border-0 p-0 bg-transparent shrink-0">
                        <div class="flex-1 pr-2">
                            <input type="text" id="color_text" value="#10b981" readonly
                                class="w-full bg-transparent text-white font-mono text-lg outline-none cursor-default font-medium uppercase tracking-wider">
                        </div>
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" 
                        class="w-full relative overflow-hidden group bg-brand-500 hover:bg-brand-400 text-white font-semibold py-3 px-4 rounded-xl transition-all duration-300 shadow-[0_0_20px_rgba(16,185,129,0.3)] hover:shadow-[0_0_30px_rgba(16,185,129,0.5)] transform hover:-translate-y-0.5">
                        <span class="relative z-10">Create Category</span>
                        <div class="absolute inset-0 h-full w-full bg-gradient-to-r from-transparent via-white/20 to-transparent -translate-x-full group-hover:animate-[shimmer_1.5s_infinite]"></div>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
@keyframes shimmer {
    100% { transform: translateX(100%); }
}
</style>

<script>
    const colorInput = document.getElementById('color_input');
    const colorText = document.getElementById('color_text');
    colorInput.addEventListener('input', function() {
        colorText.value = this.value;
    });
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
