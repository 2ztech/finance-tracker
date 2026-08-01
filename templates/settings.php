<?php
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Settings.php';
Auth::requireLogin();

$msg = $_GET['msg'] ?? '';
$tsm = Settings::get('tracking_start_month', date('Y-m'));
$balance = Settings::get('starting_bank_balance', '0');

ob_start();
?>

<div class="mb-6">
    <h2 class="text-2xl font-bold" style="color:var(--text);">Settings</h2>
    <p class="mt-0.5 text-sm" style="color:var(--text-secondary);">Manage your account, data, and preferences.</p>
</div>

<!-- Toast Messages -->
<?php
$toasts = [
    'import_success'   => ['Data imported successfully.', 'success'],
    'import_error'     => ['Error importing data. Check CSV format.', 'danger'],
    'account_success'  => ['Credentials updated successfully.', 'success'],
    'account_error'    => ['Incorrect current password.', 'danger'],
    'restore_success'  => ['Database restored successfully.', 'success'],
    'restore_error'    => ['Invalid file. Upload .db or .sqlite only.', 'danger'],
    'clean_success'    => ['Removed ' . ($_GET['count'] ?? 0) . ' duplicate(s).', 'success'],
    'settings_success' => ['Settings saved.', 'success'],
];
if (isset($toasts[$msg])): $t = $toasts[$msg]; ?>
    <div class="mb-5 flex items-center gap-2.5 rounded-lg border px-4 py-3 text-sm" style="background:<?= $t[1] === 'success' ? 'var(--success-soft)' : 'var(--danger-soft)' ?>;border-color:<?= $t[1] === 'success' ? 'var(--success)' : 'var(--danger)' ?>;color:<?= $t[1] === 'success' ? 'var(--success)' : 'var(--danger)' ?>;">
        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <?php if ($t[1] === 'success'): ?><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/><?php else: ?><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/><?php endif; ?>
        </svg>
        <?= $t[0] ?>
    </div>
<?php endif; ?>

<!-- Section: Preferences & Account -->
<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <!-- General -->
    <div class="rounded-xl border p-5" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
        <h3 class="mb-1 text-base font-semibold" style="color:var(--text);">General</h3>
        <p class="mb-4 text-sm" style="color:var(--text-secondary);">Ledger configuration and baseline balance.</p>
        <form method="POST" action="/settings/ledger" class="space-y-3">
            <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
            <div>
                <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Tracking Start Month</label>
                <input type="month" name="tracking_start_month" value="<?= htmlspecialchars($tsm) ?>" required
                    class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Initial Baseline Balance (RM)</label>
                <input type="number" step="0.01" name="starting_balance" value="<?= htmlspecialchars($balance) ?>"
                    class="w-full rounded-lg border px-3 py-2 text-sm font-bold" style="background:var(--bg);border-color:var(--border);color:var(--text);">
            </div>
            <button type="submit" class="w-full rounded-lg py-2.5 text-sm font-semibold text-white" style="background:var(--accent);">
                Save
            </button>
        </form>
    </div>

    <!-- Account -->
    <div class="rounded-xl border p-5" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
        <h3 class="mb-1 text-base font-semibold" style="color:var(--text);">Account</h3>
        <p class="mb-4 text-sm" style="color:var(--text-secondary);">Change your username or password.</p>
        <form method="POST" action="/settings/account" class="space-y-3" onsubmit="return validatePw(this)">
            <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
            <div>
                <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Username</label>
                <input type="text" name="username" required value="<?= htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Current Password</label>
                <input type="password" name="old_password" required placeholder="Required to save"
                    class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">New Password</label>
                <input type="password" name="new_password" placeholder="Leave blank to keep current"
                    class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium" style="color:var(--text-secondary);">Confirm New Password</label>
                <input type="password" name="confirm_new_password" placeholder="Confirm new password"
                    class="w-full rounded-lg border px-3 py-2 text-sm" style="background:var(--bg);border-color:var(--border);color:var(--text);">
            </div>
            <button type="submit" class="w-full rounded-lg py-2.5 text-sm font-semibold text-white" style="background:var(--accent);">
                Update Credentials
            </button>
        </form>
    </div>
</div>

<!-- Section: Data Management -->
<div class="rounded-xl border" style="background:var(--bg-alt);border-color:var(--border);box-shadow:var(--shadow);">
    <div class="border-b px-5 py-4" style="border-color:var(--border-light);">
        <h3 class="text-base font-semibold" style="color:var(--text);">Data & Backups</h3>
        <p class="text-sm" style="color:var(--text-secondary);">Export, import, backup, and restore your financial data.</p>
    </div>

    <!-- Export CSV -->
    <div class="flex flex-col gap-3 border-b px-5 py-4 sm:flex-row sm:items-center sm:justify-between" style="border-color:var(--border-light);">
        <div>
            <p class="text-sm font-medium" style="color:var(--text);">Export Transactions (CSV)</p>
            <p class="text-xs" style="color:var(--text-secondary);">Download all records as a spreadsheet-ready CSV file.</p>
        </div>
        <a href="/settings/export" class="shrink-0 rounded-lg px-4 py-2.5 text-sm font-semibold text-white text-center" style="background:var(--accent);">Download CSV</a>
    </div>

    <!-- Import CSV -->
    <div class="flex flex-col gap-3 border-b px-5 py-4 sm:flex-row sm:items-center sm:justify-between" style="border-color:var(--border-light);">
        <div>
            <p class="text-sm font-medium" style="color:var(--text);">Import Transactions (CSV)</p>
            <p class="text-xs" style="color:var(--text-secondary);">Upload a CSV with columns: Date, Type, Amount, Category, Description.</p>
        </div>
        <form method="POST" action="/settings/import" enctype="multipart/form-data" class="flex flex-col gap-2 sm:flex-row sm:items-center">
            <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
            <input type="file" name="csv_file" accept=".csv" required
                class="text-sm file:mr-3 file:rounded-lg file:border-0 file:px-3 file:py-2 file:text-sm file:font-semibold file:cursor-pointer" style="color:var(--text-secondary);">
            <button type="submit" class="shrink-0 rounded-lg px-4 py-2.5 text-sm font-semibold text-white" style="background:var(--accent);">Upload & Import</button>
        </form>
    </div>

    <!-- Database Backup -->
    <div class="flex flex-col gap-3 border-b px-5 py-4 sm:flex-row sm:items-center sm:justify-between" style="border-color:var(--border-light);">
        <div>
            <p class="text-sm font-medium" style="color:var(--text);">Database Backup</p>
            <p class="text-xs" style="color:var(--text-secondary);">Download a full snapshot of your finance.db file.</p>
        </div>
        <a href="/settings/backup" class="shrink-0 rounded-lg px-4 py-2.5 text-sm font-semibold text-white text-center" style="background:var(--accent);">Download Backup</a>
    </div>

    <!-- Database Restore -->
    <div class="flex flex-col gap-3 border-b px-5 py-4 sm:flex-row sm:items-center sm:justify-between" style="border-color:var(--border-light);">
        <div>
            <p class="text-sm font-medium" style="color:var(--text);">Restore Database</p>
            <p class="text-xs" style="color:var(--text-secondary);">Upload a .db or .sqlite file to replace all current data.</p>
        </div>
        <form method="POST" action="/settings/restore" enctype="multipart/form-data" class="flex flex-col gap-2 sm:flex-row sm:items-center">
            <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
            <input type="file" name="db_file" accept=".db,.sqlite" required
                class="text-sm file:mr-3 file:rounded-lg file:border-0 file:px-3 file:py-2 file:text-sm file:font-semibold file:cursor-pointer" style="color:var(--text-secondary);">
            <button type="submit" class="shrink-0 rounded-lg px-4 py-2.5 text-sm font-semibold text-white" style="background:var(--accent);">Restore</button>
        </form>
    </div>

    <!-- Clean Duplicates -->
    <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm font-medium" style="color:var(--text);">Clean Duplicates</p>
            <p class="text-xs" style="color:var(--text-secondary);">Remove duplicate transactions. This cannot be undone.</p>
        </div>
        <form method="POST" action="/settings/clean-duplicates" onsubmit="return confirm('Remove all duplicate transactions? This cannot be undone.');">
            <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
            <button type="submit" class="shrink-0 rounded-lg px-4 py-2.5 text-sm font-semibold text-white" style="background:var(--danger);">Clean Duplicates</button>
        </form>
    </div>
</div>

<script>
function validatePw(f) {
    if (f.new_password.value !== f.confirm_new_password.value) {
        alert('New passwords do not match.');
        return false;
    }
    return true;
}
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
