<?php
// templates/commitments.php
require_once __DIR__ . '/../src/Expense.php';
require_once __DIR__ . '/../src/Category.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $name = trim($_POST['name'] ?? '');
        $amount = (float) ($_POST['amount'] ?? 0);
        $due_date = (int) ($_POST['due_date_day'] ?? 1);
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $cleanCategoryId = $categoryId > 0 ? $categoryId : null;

        if ($_POST['action'] === 'add') {
            if ($name && $amount > 0 && $due_date >= 1 && $due_date <= 31) {
                Expense::addCommitment($name, $amount, $due_date, $cleanCategoryId);
            }
        } elseif ($_POST['action'] === 'edit') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0 && $name && $amount > 0 && $due_date >= 1 && $due_date <= 31) {
                Expense::updateCommitment($id, $name, $amount, $due_date, $cleanCategoryId);
            }
        } elseif ($_POST['action'] === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                Expense::deleteCommitment($id);
            }
        }
        header('Location: /commitments');
        exit;
    }
}

$commitments = Expense::getCommitments();
$totalCommitments = array_sum(array_column($commitments, 'amount'));
$categories = Category::getAll();

ob_start();
?>
<div class="mb-8 flex flex-col md:flex-row md:items-end justify-between gap-4">
    <div>
        <h2 class="text-3xl font-bold tracking-tight mb-1 text-white">Monthly Commitments</h2>
        <p class="text-gray-400">Manage your recurring bills and fixed expenses.</p>
    </div>
    <div class="bg-dark-800/80 backdrop-blur-md px-6 py-3 rounded-xl border border-dark-700/50 shadow-lg text-right">
        <p class="text-sm text-gray-400 mb-1">Total Monthly Fixed</p>
        <p class="text-2xl font-bold text-orange-400">RM <?= number_format($totalCommitments, 2) ?></p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- List -->
    <div class="lg:col-span-2 space-y-4">
        <?php if (empty($commitments)): ?>
            <div class="bg-dark-800/50 border border-dark-700/50 rounded-2xl p-8 text-center">
                <div class="w-16 h-16 bg-dark-700 text-gray-400 rounded-full flex items-center justify-center mx-auto mb-4 border border-dark-600">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </div>
                <h3 class="text-lg font-medium text-white mb-2">No commitments yet</h3>
                <p class="text-gray-400">Add your recurring bills to track your fixed expenses.</p>
            </div>
        <?php else: ?>
            <?php foreach ($commitments as $c): ?>
                <div class="bg-dark-800/80 backdrop-blur-md border border-dark-700/50 rounded-2xl p-5 flex items-center justify-between hover:border-dark-600 transition-colors group shadow-md shadow-black/10">
                    <div class="flex items-center gap-5">
                        <div class="w-14 h-14 shrink-0 rounded-xl bg-orange-500/10 text-orange-400 flex flex-col items-center justify-center border border-orange-500/20 shadow-sm">
                            <span class="text-[10px] font-bold uppercase opacity-80 tracking-wider">Day</span>
                            <span class="text-xl font-black leading-none"><?= htmlspecialchars((string)$c['due_date_day'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div>
                            <h4 class="text-lg font-bold text-white mb-0.5"><?= htmlspecialchars((string)$c['name'], ENT_QUOTES, 'UTF-8') ?></h4>
                            <p class="text-lg font-medium text-gray-300 mb-1">RM <?= number_format($c['amount'], 2) ?></p>
                            <?php if (!empty($c['category_name'])): ?>
                                <div class="flex items-center gap-1.5 opacity-80">
                                    <div class="w-2 h-2 rounded-full" style="background-color: <?= htmlspecialchars((string)$c['color_hex'], ENT_QUOTES, 'UTF-8') ?>"></div>
                                    <span class="text-xs font-medium text-gray-400"><?= htmlspecialchars((string)$c['category_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                            <?php else: ?>
                                <span class="text-xs font-medium text-gray-500 italic">Uncategorized</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="flex flex-col items-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity focus-within:opacity-100 h-full">
                        <button type="button" onclick="editCommitment(<?= $c['id'] ?>, '<?= htmlspecialchars((string)$c['name'], ENT_QUOTES, 'UTF-8') ?>', <?= $c['amount'] ?>, <?= $c['due_date_day'] ?>, <?= (int)($c['category_id'] ?? 0) ?>)" class="p-2 text-gray-500 hover:text-brand-400 hover:bg-brand-500/10 rounded-xl transition-all" title="Edit Commitment">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                        </button>
                        <form method="POST" action="/commitments" onsubmit="return confirm('Delete this commitment?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <button type="submit" class="p-2 text-gray-500 hover:text-red-400 hover:bg-red-500/10 rounded-xl transition-all" title="Delete Commitment">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Add Form -->
    <div>
        <div class="bg-dark-800/80 backdrop-blur-md border border-dark-700/50 rounded-2xl p-6 shadow-xl sticky top-6 custom-glow">
            <h3 id="form_title" class="text-xl font-bold text-white mb-6 flex items-center gap-2">
                <svg class="w-5 h-5 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Add Commitment
            </h3>
            <form method="POST" action="/commitments" id="commitment_form" class="space-y-5">
                <input type="hidden" name="action" id="form_action" value="add">
                <input type="hidden" name="id" id="form_id" value="">
                
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5 ml-1">Name / Identifier</label>
                    <input type="text" name="name" id="form_name" required placeholder="e.g. Car Loan"
                        class="w-full px-4 py-3 bg-dark-900 border border-dark-600 rounded-xl focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 outline-none text-white placeholder-gray-600 transition-all shadow-inner">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5 ml-1">Amount (RM)</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-500">
                            <span class="font-medium">RM</span>
                        </div>
                        <input type="number" step="0.01" min="0.01" name="amount" id="form_amount" required placeholder="0.00"
                            class="w-full pl-9 pr-4 py-3 bg-dark-900 border border-dark-600 rounded-xl focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 outline-none text-white placeholder-gray-600 transition-all shadow-inner">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5 ml-1">Category</label>
                    <select name="category_id" id="form_category_id" class="w-full px-4 py-3 bg-dark-900 border border-dark-600 rounded-xl focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 outline-none text-white transition-all shadow-inner">
                        <option value="">No Category</option>
                        <?php foreach($categories as $cat): ?>
                            <?php if ($cat['type'] === 'expense'): ?>
                                <option value="<?= htmlspecialchars((string)$cat['id'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$cat['name'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5 ml-1">Due Date (Day of Month)</label>
                    <div class="flex items-center gap-4 bg-dark-900 p-3 rounded-xl border border-dark-600 shadow-inner">
                        <input type="range" min="1" max="31" value="1" name="due_date_day" id="due_date_slider"
                            class="w-full h-2 bg-dark-700 rounded-lg appearance-none cursor-pointer accent-brand-500">
                        <span id="due_date_display" class="w-12 text-center py-1.5 bg-dark-800 border border-dark-500 rounded-lg text-white font-bold text-sm shadow-sm ring-1 ring-white/5">1</span>
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" 
                        class="w-full relative overflow-hidden group bg-brand-500 hover:bg-brand-400 text-white font-semibold py-3 px-4 rounded-xl transition-all duration-300 shadow-[0_0_20px_rgba(16,185,129,0.3)] hover:shadow-[0_0_30px_rgba(16,185,129,0.5)] transform hover:-translate-y-0.5">
                        <span class="relative z-10" id="form_submit_btn_text">Save Commitment</span>
                        <div class="absolute inset-0 h-full w-full bg-gradient-to-r from-transparent via-white/20 to-transparent -translate-x-full group-hover:animate-[shimmer_1.5s_infinite]"></div>
                    </button>
                    <button type="button" id="cancel_edit_btn" onclick="cancelEdit()" class="hidden w-full mt-3 bg-dark-700 hover:bg-dark-600 text-gray-300 font-semibold py-3 px-4 rounded-xl transition-all duration-300 border border-dark-600 shadow-sm">
                        Cancel Edit
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
.custom-glow {
    box-shadow: inset 0 1px 0 0 rgba(255, 255, 255, 0.05);
}
</style>

<script>
    const slider = document.getElementById('due_date_slider');
    const display = document.getElementById('due_date_display');
    slider.addEventListener('input', function() {
        display.textContent = this.value;
    });

    function editCommitment(id, name, amount, day, catId) {
        document.getElementById('form_action').value = 'edit';
        document.getElementById('form_id').value = id;
        document.getElementById('form_name').value = name;
        document.getElementById('form_amount').value = amount;
        
        document.getElementById('due_date_slider').value = day;
        document.getElementById('due_date_display').textContent = day;
        
        if (catId > 0) {
            document.getElementById('form_category_id').value = catId;
        } else {
            document.getElementById('form_category_id').value = "";
        }

        document.getElementById('form_title').innerHTML = '<svg class="w-5 h-5 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg> Edit Commitment';
        document.getElementById('form_submit_btn_text').textContent = 'Update Commitment';
        document.getElementById('cancel_edit_btn').classList.remove('hidden');
        
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function cancelEdit() {
        document.getElementById('form_action').value = 'add';
        document.getElementById('form_id').value = '';
        document.getElementById('form_name').value = '';
        document.getElementById('form_amount').value = '';
        
        document.getElementById('due_date_slider').value = 1;
        document.getElementById('due_date_display').textContent = 1;
        document.getElementById('form_category_id').value = '';

        document.getElementById('form_title').innerHTML = '<svg class="w-5 h-5 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg> Add Commitment';
        document.getElementById('form_submit_btn_text').textContent = 'Save Commitment';
        document.getElementById('cancel_edit_btn').classList.add('hidden');
    }
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
