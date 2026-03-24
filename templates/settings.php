<?php
// templates/settings.php
require_once __DIR__ . '/../src/Auth.php';
Auth::requireLogin();

$msg = $_GET['msg'] ?? '';

ob_start();
?>
<div class="mb-8">
    <h2 class="text-3xl font-bold tracking-tight mb-1 text-white">System Settings</h2>
    <p class="text-gray-400">Manage your data exports, imports, and backups.</p>
</div>

<?php if ($msg === 'import_success'): ?>
    <div class="bg-emerald-500/10 border border-emerald-500/50 text-emerald-400 px-4 py-3 rounded-xl mb-6 text-sm flex items-center gap-3">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
        Data imported successfully!
    </div>
<?php elseif ($msg === 'import_error'): ?>
    <div class="bg-red-500/10 border border-red-500/50 text-red-400 px-4 py-3 rounded-xl mb-6 text-sm flex items-center gap-3">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        Error importing data. Ensure the CSV format matches the export format.
    </div>
<?php elseif ($msg === 'password_success'): ?>
    <div class="bg-emerald-500/10 border border-emerald-500/50 text-emerald-400 px-4 py-3 rounded-xl mb-6 text-sm flex items-center gap-3">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
        Password updated successfully!
    </div>
<?php elseif ($msg === 'password_error'): ?>
    <div class="bg-red-500/10 border border-red-500/50 text-red-400 px-4 py-3 rounded-xl mb-6 text-sm flex items-center gap-3">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        Incorrect current password. Could not update.
    </div>
<?php elseif ($msg === 'restore_success'): ?>
    <div class="bg-emerald-500/10 border border-emerald-500/50 text-emerald-400 px-4 py-3 rounded-xl mb-6 text-sm flex items-center gap-3">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
        Database restored successfully!
    </div>
<?php elseif ($msg === 'restore_error'): ?>
    <div class="bg-red-500/10 border border-red-500/50 text-red-400 px-4 py-3 rounded-xl mb-6 text-sm flex items-center gap-3">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        Error restoring database. Ensure you are uploading a valid .db or .sqlite file.
    </div>
<?php elseif ($msg === 'clean_success'): ?>
    <div class="bg-emerald-500/10 border border-emerald-500/50 text-emerald-400 px-4 py-3 rounded-xl mb-6 text-sm flex items-center gap-3">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
        Successfully removed <?= htmlspecialchars($_GET['count'] ?? 0) ?> duplicate transaction(s)!
    </div>
<?php endif; ?>

<!-- Group 1: Account Security -->
<div class="mb-10">
    <div class="bg-dark-800/80 backdrop-blur-md border border-dark-700/50 rounded-lg p-6 shadow-md relative group hover:border-dark-600 transition-colors w-full">
        <div class="flex items-center gap-4 mb-6">
            <div class="w-12 h-12 rounded-xl bg-red-500/10 text-red-400 flex items-center justify-center">
                 <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
            </div>
            <div>
                <h3 class="text-xl font-bold text-white">Account Security</h3>
                <p class="text-sm text-gray-400">Secure your account by updating your password.</p>
            </div>
        </div>
        <form method="POST" action="/settings/password" class="flex flex-col md:flex-row gap-4 items-start md:items-center">
            <input type="password" name="old_password" required placeholder="Current Password"
                class="block w-full md:w-auto flex-1 text-sm text-white px-4 py-2.5 bg-dark-900 border border-dark-600 rounded-lg outline-none focus:border-brand-500 transition-colors">
            <input type="password" name="new_password" required placeholder="New Password"
                class="block w-full md:w-auto flex-1 text-sm text-white px-4 py-2.5 bg-dark-900 border border-dark-600 rounded-lg outline-none focus:border-brand-500 transition-colors">
            <button type="submit" class="w-full md:w-auto inline-flex justify-center items-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white font-medium py-2.5 px-6 rounded-lg transition-colors shadow-sm">
                Update Password
            </button>
        </form>
    </div>
</div>

<!-- Group 2: Data Management -->
<div>
    <h3 class="text-xl font-bold text-white mb-4">Data & Backups</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        <!-- Left Column: Transaction Data (CSV) -->
        <div class="bg-dark-800/80 backdrop-blur-md border border-dark-700/50 rounded-lg p-6 shadow-md relative group hover:border-dark-600 transition-colors flex flex-col">
            <div class="flex items-center gap-4 mb-6">
                <div class="w-12 h-12 rounded-xl bg-blue-500/10 text-blue-400 flex items-center justify-center">
                     <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                </div>
                <div>
                    <h4 class="text-lg font-bold text-white">Transaction Data (CSV)</h4>
                    <p class="text-sm text-gray-400">Export and import transactions manually.</p>
                </div>
            </div>
            
            <div>
                <p class="text-sm text-gray-400 mb-3">Download all your transactions as a generic CSV file for spreadsheet analysis.</p>
                <a href="/settings/export" class="w-full inline-flex justify-center items-center gap-2 bg-dark-700 hover:bg-dark-600 border border-dark-600 text-white font-medium py-2.5 px-4 rounded-lg transition-colors shadow-sm">
                    Export Data (CSV)
                </a>
            </div>
            
            <hr class="border-dark-700/50 my-6">
            
            <div class="mt-auto">
                <p class="text-sm text-gray-400 mb-3">Upload a matching CSV file to populate transactions. Expected format: Date, Type, Amount, Category, Description</p>
                <form method="POST" action="/settings/import" enctype="multipart/form-data" class="flex flex-col gap-3">
                    <input type="file" name="csv_file" accept=".csv" required
                        class="block w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-dark-700 file:text-brand-400 hover:file:bg-dark-600 cursor-pointer border border-dark-600">
                    <button type="submit" class="w-full inline-flex justify-center flex-1 items-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white font-medium py-2.5 px-4 rounded-lg transition-colors shadow-sm">
                        Import Data
                    </button>
                </form>
            </div>
        </div>

        <!-- Right Column: System Database -->
        <div class="bg-dark-800/80 backdrop-blur-md border border-dark-700/50 rounded-lg p-6 shadow-md relative group hover:border-dark-600 transition-colors flex flex-col">
            <div class="flex items-center gap-4 mb-6">
                <div class="w-12 h-12 rounded-xl bg-purple-500/10 text-purple-400 flex items-center justify-center">
                     <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002-2h8a2 2 0 002-2v-2"></path></svg>
                </div>
                <div>
                    <h4 class="text-lg font-bold text-white">System Backup (SQLite)</h4>
                    <p class="text-sm text-gray-400">Total raw database backup & restore control.</p>
                </div>
            </div>
            
            <div>
                <p class="text-sm text-gray-400 mb-3">Create a full raw backup of your <code>finance.db</code>. This includes settings, commitments, categories, and transactions.</p>
                <a href="/settings/backup" class="w-full inline-flex justify-center items-center gap-2 bg-dark-700 hover:bg-dark-600 border border-dark-600 text-white font-medium py-2.5 px-4 rounded-lg transition-colors shadow-sm">
                    Database Backup (Download)
                </a>
            </div>
            
            <hr class="border-dark-700/50 my-6">
            
            <div>
                <p class="text-sm text-gray-400 mb-3">Upload a <code>.db</code> or <code>.sqlite</code> file to completely restore your database. This will overwrite all current data.</p>
                <form method="POST" action="/settings/restore" enctype="multipart/form-data" class="flex flex-col gap-3">
                    <input type="file" name="db_file" accept=".db,.sqlite" required
                        class="block w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-dark-700 file:text-indigo-400 hover:file:bg-dark-600 cursor-pointer border border-dark-600">
                    <button type="submit" class="w-full inline-flex justify-center flex-1 items-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white font-medium py-2.5 px-4 rounded-lg transition-colors shadow-sm">
                        Restore Database Backup
                    </button>
                </form>
            </div>

            <hr class="border-dark-700/50 my-6">

            <div class="mt-auto">
                <p class="text-sm text-gray-400 mb-3">Remove duplicated transactions accidentally imported from previous CSV uploads.</p>
                <form method="POST" action="/settings/clean-duplicates" class="flex flex-col">
                    <button type="submit" class="w-full inline-flex justify-center items-center gap-2 bg-dark-700 hover:bg-dark-600 text-red-400 border border-red-500/30 hover:border-red-500/50 font-medium py-2 px-4 rounded-lg transition-colors shadow-sm" onclick="return confirm('Are you sure you want to clean up duplicates? This cannot be undone.');">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        Clean Duplicates
                    </button>
                </form>
            </div>

        </div>

    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
