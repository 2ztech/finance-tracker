<?php
require_once __DIR__ . '/../src/Category.php';

$msg = $_GET['msg'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'save') {
            $name = trim($_POST['name'] ?? '');
            $type = $_POST['type'] ?? 'expense';
            $color = $_POST['color_hex'] ?? '#4f6ef7';
            $iconKey = (string)($_POST['icon_key'] ?? '');
            if (!IconCatalog::has($iconKey)) $iconKey = IconCatalog::defaultForName($name);
            try {
                $iconUpload = IconUpload::fromUpload($_FILES['icon_file'] ?? null);
            } catch (InvalidArgumentException $e) {
                header('Location: /categories?msg=icon_upload_invalid');
                exit;
            }
            $id = (int)($_POST['id'] ?? 0);
            if ($name && in_array($type, ['income', 'expense'], true)) {
                if ($id > 0) {
                    Category::update($id, $name, $type, $color, $iconKey, $iconUpload['data'] ?? null, ($_POST['clear_icon'] ?? '0') === '1', $iconUpload['mime'] ?? 'image/png');
                    header('Location: /categories?msg=updated');
                } else {
                    Category::create($name, $type, $color, $iconKey, $iconUpload['data'] ?? null, $iconUpload['mime'] ?? 'image/png');
                    header('Location: /categories?msg=created');
                }
                exit;
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

function renderCategoryIcon(array $category, string $size = 'h-5 w-5'): string
{
    if (!empty($category['icon_data'])) {
        $mime = htmlspecialchars((string)($category['icon_mime'] ?? 'image/png'), ENT_QUOTES, 'UTF-8');
        return '<img class="' . $size . ' rounded-md object-contain" src="data:' . $mime . ';base64,' . htmlspecialchars((string)$category['icon_data'], ENT_QUOTES, 'UTF-8') . '" alt="">';
    }
    return IconCatalog::svg((string)($category['icon_key'] ?? 'other'), $size);
}

ob_start();
?>

<div class="mb-6">
    <h2 class="text-2xl font-bold" style="color:var(--text);">Categories</h2>
    <p class="mt-0.5 text-sm" style="color:var(--text-secondary);">Organize your transactions with custom categories.</p>
</div>

<?php if (isset(['created' => 'Category created.', 'updated' => 'Category updated.', 'icon_upload_invalid' => 'Icon rejected. Use a square PNG, JPEG, WebP, or ICO within the displayed size and file limits.'][$msg])): ?>
<div class="mb-4 rounded-lg border px-4 py-3 text-sm" style="background:<?= $msg === 'icon_upload_invalid' ? 'var(--danger-soft)' : 'var(--success-soft)' ?>;border-color:<?= $msg === 'icon_upload_invalid' ? 'var(--danger)' : 'var(--success)' ?>;color:<?= $msg === 'icon_upload_invalid' ? 'var(--danger)' : 'var(--success)' ?>;">
    <?= htmlspecialchars(['created' => 'Category created.', 'updated' => 'Category updated.', 'icon_upload_invalid' => 'Icon rejected. Use a square PNG, JPEG, WebP, or ICO within the displayed size and file limits.'][$msg], ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>

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
                        <div class="group flex items-center justify-between rounded-lg border p-3 transition-colors" style="background:var(--bg);border-color:var(--border-light);" onmouseover="this.style.background='var(--bg-hover)'" onmouseout="this.style.background='var(--bg)'">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg" style="background:color-mix(in srgb, <?= htmlspecialchars((string)$c['color_hex'], ENT_QUOTES, 'UTF-8') ?> 15%, white);color:<?= htmlspecialchars((string)$c['color_hex'], ENT_QUOTES, 'UTF-8') ?>;"><?= renderCategoryIcon($c) ?></span>
                                <span class="truncate text-sm font-medium" style="color:var(--text);"><?= htmlspecialchars((string)$c['name'], ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <div class="flex items-center gap-1">
                                <button type="button" onclick='editCategory(<?= json_encode($c, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' class="rounded p-1.5" style="color:var(--text-muted);" title="Edit category" aria-label="Edit <?= htmlspecialchars((string)$c['name'], ENT_QUOTES, 'UTF-8') ?>">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="m16 4 4 4M4 20l4-.8L19 8a2.8 2.8 0 00-4-4L4 15l-.8 5z"/></svg>
                                </button>
                            <form method="POST" action="/categories" data-confirm="Delete this category? Transactions using it will become uncategorized." data-confirm-label="Delete" data-confirm-danger="true">
                                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                <button type="submit" class="rounded p-1 transition-opacity sm:opacity-0 sm:group-hover:opacity-100 focus:opacity-100" style="color:var(--text-muted);" onmouseover="this.style.color='var(--danger)'" onmouseout="this.style.color='var(--text-muted)'">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form></div>
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
                        <div class="group flex items-center justify-between rounded-lg border p-3 transition-colors" style="background:var(--bg);border-color:var(--border-light);" onmouseover="this.style.background='var(--bg-hover)'" onmouseout="this.style.background='var(--bg)'">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg" style="background:color-mix(in srgb, <?= htmlspecialchars((string)$c['color_hex'], ENT_QUOTES, 'UTF-8') ?> 15%, white);color:<?= htmlspecialchars((string)$c['color_hex'], ENT_QUOTES, 'UTF-8') ?>;"><?= renderCategoryIcon($c) ?></span>
                                <span class="truncate text-sm font-medium" style="color:var(--text);"><?= htmlspecialchars((string)$c['name'], ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <div class="flex items-center gap-1">
                                <button type="button" onclick='editCategory(<?= json_encode($c, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' class="rounded p-1.5" style="color:var(--text-muted);" title="Edit category" aria-label="Edit <?= htmlspecialchars((string)$c['name'], ENT_QUOTES, 'UTF-8') ?>">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="m16 4 4 4M4 20l4-.8L19 8a2.8 2.8 0 00-4-4L4 15l-.8 5z"/></svg>
                                </button>
                            <form method="POST" action="/categories" data-confirm="Delete this category? Transactions using it will become uncategorized." data-confirm-label="Delete" data-confirm-danger="true">
                                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                <button type="submit" class="rounded p-1 transition-opacity sm:opacity-0 sm:group-hover:opacity-100 focus:opacity-100" style="color:var(--text-muted);" onmouseover="this.style.color='var(--danger)'" onmouseout="this.style.color='var(--text-muted)'">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Form -->
    <div>
        <div class="sticky top-6 rounded-xl border p-5" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
            <h3 id="categoryFormTitle" class="mb-5 flex items-center gap-2 text-base font-semibold" style="color:var(--text);">
                <svg class="h-4 w-4" style="color:var(--accent);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Create Category
            </h3>
            <form id="categoryForm" method="POST" action="/categories" enctype="multipart/form-data" class="space-y-3">
                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="category_id" value="">
                <input type="hidden" name="icon_key" id="category_icon_key" value="other">
                <input type="hidden" name="clear_icon" id="category_clear_icon" value="0">

                <div>
                    <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Name</label>
                    <input type="text" name="name" id="category_name" required placeholder="e.g. Groceries"
                        class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Icon</label>
                    <div id="categoryIconPicker" class="grid grid-cols-5 gap-1.5 rounded-lg border p-2" style="border-color:var(--border);background:var(--bg-hover);">
                        <?php foreach (IconCatalog::keys() as $iconKey): $iconLabel = ucfirst(str_replace('_', ' ', $iconKey)); ?>
                        <button type="button" data-icon-key="<?= htmlspecialchars($iconKey, ENT_QUOTES, 'UTF-8') ?>" onclick="selectCategoryIcon('<?= htmlspecialchars($iconKey, ENT_QUOTES, 'UTF-8') ?>')" class="category-icon-option flex flex-col items-center gap-1 rounded-md p-1.5" title="<?= htmlspecialchars($iconLabel, ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($iconLabel, ENT_QUOTES, 'UTF-8') ?> icon">
                            <?= IconCatalog::svg($iconKey, 'h-5 w-5') ?><span class="text-[9px] leading-none"><?= htmlspecialchars($iconLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="rounded-lg border p-3" style="border-color:var(--border);background:var(--bg-hover);">
                    <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Or upload a custom icon</label>
                    <div class="flex items-center gap-3">
                        <img id="category_icon_preview" class="hidden h-10 w-10 shrink-0 rounded-lg object-contain" alt="Custom icon preview">
                        <div class="min-w-0 flex-1"><div class="flex items-center gap-2"><button type="button" id="category_icon_choose" aria-controls="category_icon_file" class="rounded-lg border px-3 py-2 text-xs font-semibold transition hover:opacity-80" style="border-color:var(--border);background:var(--bg-alt);color:var(--text);">Choose image</button><span id="category_icon_filename" class="truncate text-xs" style="color:var(--text-muted);">No file selected</span></div></div>
                    </div>
                    <input type="file" name="icon_file" id="category_icon_file" accept="image/png,image/jpeg,image/webp,image/x-icon,image/vnd.microsoft.icon,.ico" class="sr-only" aria-label="Upload category icon">
                    <p id="category_icon_error" class="mt-1 hidden text-xs" style="color:var(--danger);" role="alert"></p>
                    <p class="mt-1 text-[10px]" style="color:var(--text-muted);">PNG, JPEG, WebP, or ICO · square 32–512 px (ICO frames 16–256 px, including one 32 px+) · maximum 512 KiB. Scales to fit.</p>
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
                    <span id="categorySubmitText">Create Category</span>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
var categoryIconManuallySelected = false;
function selectCategoryIcon(key, manual = true) {
    if (manual) categoryIconManuallySelected = true;
    document.getElementById('category_icon_key').value = key;
    document.getElementById('category_clear_icon').value = document.getElementById('category_id').value ? '1' : '0';
    document.getElementById('category_icon_preview').classList.add('hidden');
    document.getElementById('category_icon_preview').removeAttribute('src');
    document.getElementById('category_icon_file').value = '';
    document.getElementById('category_icon_filename').textContent = 'No file selected';
    document.getElementById('category_icon_error').classList.add('hidden');
    document.querySelectorAll('.category-icon-option').forEach(function(button) {
        var selected = button.dataset.iconKey === key;
        button.style.background = selected ? 'var(--accent-soft)' : 'transparent';
        button.style.color = selected ? 'var(--accent)' : 'var(--text-secondary)';
        button.setAttribute('aria-pressed', selected ? 'true' : 'false');
    });
}
function editCategory(category) {
    categoryIconManuallySelected = true;
    document.getElementById('categoryFormTitle').lastChild.textContent = ' Edit Category';
    document.getElementById('categorySubmitText').textContent = 'Update Category';
    document.getElementById('category_id').value = category.id;
    document.getElementById('category_name').value = category.name;
    document.getElementById('color_input').value = category.color_hex;
    document.getElementById('color_text').textContent = category.color_hex;
    document.querySelectorAll('input[name="type"]').forEach(function(radio) { radio.checked = radio.value === category.type; });
    updateRadioStyles();
    document.getElementById('category_clear_icon').value = '0';
    document.getElementById('category_icon_file').value = '';
    document.getElementById('category_icon_filename').textContent = 'No file selected';
    document.getElementById('category_icon_error').classList.add('hidden');
    var preview = document.getElementById('category_icon_preview');
    if (category.icon_data) {
        preview.src = 'data:' + (category.icon_mime || 'image/png') + ';base64,' + category.icon_data;
        preview.classList.remove('hidden');
    } else {
        preview.removeAttribute('src');
        preview.classList.add('hidden');
    }
    selectCategoryIcon(category.icon_key || 'other', false);
    document.getElementById('category_clear_icon').value = '0';
    if (category.icon_data) {
        preview.src = 'data:' + (category.icon_mime || 'image/png') + ';base64,' + category.icon_data;
        preview.classList.remove('hidden');
    }
    document.getElementById('category_name').focus();
    document.getElementById('categoryForm').scrollIntoView({behavior:'smooth', block:'center'});
}
document.getElementById('category_name').addEventListener('input', function() {
    if (categoryIconManuallySelected || document.getElementById('category_id').value) return;
    var name = this.value.toLowerCase();
    var rules = [
        ['food', ['food', 'dining', 'makan']], ['groceries', ['grocer', 'market']],
        ['shopping', ['shop', 'clothing', 'retail']], ['transport', ['transport', 'fuel', 'travel', 'motorcycle']],
        ['home', ['home', 'rent']], ['utilities', ['utilit', 'internet', 'phone']],
        ['entertainment', ['entertain', 'subscription', 'music', 'video']], ['health', ['health', 'medical', 'clinic']],
        ['education', ['education', 'school', 'course']], ['pet', ['pet']], ['salary', ['salary', 'income', 'wage']],
        ['work', ['work', 'business']], ['investment', ['invest']], ['transfer', ['transfer', 'refund', 'reimburse']],
        ['loan', ['loan', 'debt', 'paylater']]
    ];
    var key = 'other';
    rules.some(function(rule) { if (rule[1].some(function(needle) { return name.includes(needle); })) { key = rule[0]; return true; } return false; });
    selectCategoryIcon(key, false);
});
document.getElementById('category_icon_choose').addEventListener('click', function() { document.getElementById('category_icon_file').click(); });
document.getElementById('category_icon_file').addEventListener('change', function() {
    var file = this.files[0];
    var error = document.getElementById('category_icon_error');
    var filename = document.getElementById('category_icon_filename');
    error.classList.add('hidden');
    error.textContent = '';
    if (!file) { filename.textContent = 'No file selected'; return; }
    filename.textContent = file.name;
    var input = this;
    function reject(message) {
        error.textContent = message;
        error.classList.remove('hidden');
        input.value = '';
        filename.textContent = 'No file selected';
    }
    var ico = file.type === 'image/x-icon' || file.type === 'image/vnd.microsoft.icon' || /\.ico$/i.test(file.name);
    var accepted = ['image/png', 'image/jpeg', 'image/webp', 'image/x-icon', 'image/vnd.microsoft.icon'].includes(file.type) || ico;
    if (!accepted || file.size > 524288) {
        reject('Choose a PNG, JPEG, WebP, or ICO file no larger than 512 KiB.');
        return;
    }
    var img = new Image();
    img.onload = function() {
        var max = ico ? 256 : 512;
        if (img.naturalWidth !== img.naturalHeight || img.naturalWidth < 32 || img.naturalWidth > max) {
            reject(ico ? 'ICO frames must be square and 32–256 pixels.' : 'The icon must be square and 32–512 pixels.');
            URL.revokeObjectURL(img.src);
            return;
        }
        var preview = document.getElementById('category_icon_preview');
        var previewUrl = img.src;
        preview.onload = function() { URL.revokeObjectURL(previewUrl); };
        preview.onerror = function() { URL.revokeObjectURL(previewUrl); };
        preview.src = previewUrl;
        preview.classList.remove('hidden');
        document.getElementById('category_clear_icon').value = '0';
    };
    img.onerror = function() { reject('This image could not be opened. Choose a valid icon file.'); URL.revokeObjectURL(img.src); };
    img.src = URL.createObjectURL(file);
});
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
selectCategoryIcon('other', false);
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
