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
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

    <!-- Export CSV -->
    <div class="bg-dark-800/80 backdrop-blur-md border border-dark-700/50 rounded-2xl p-6 shadow-xl relative group hover:border-dark-600 transition-colors">
        <div class="absolute inset-0 bg-gradient-to-b from-blue-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity rounded-2xl"></div>
        <div class="w-12 h-12 rounded-xl bg-blue-500/10 text-blue-400 flex items-center justify-center mb-4">
             <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
        </div>
        <h3 class="text-lg font-bold text-white mb-2">Export CSV</h3>
        <p class="text-sm text-gray-400 mb-6">Download all your transactions as a generic CSV file for spreadsheet analysis.</p>
        <a href="/settings/export" class="w-full inline-flex justify-center items-center gap-2 bg-dark-700 hover:bg-dark-600 text-white font-medium py-2 px-4 rounded-xl transition-colors">
            Download CSV
        </a>
    </div>

    <!-- Import CSV -->
    <div class="bg-dark-800/80 backdrop-blur-md border border-dark-700/50 rounded-2xl p-6 shadow-xl relative group hover:border-dark-600 transition-colors">
        <div class="absolute inset-0 bg-gradient-to-b from-brand-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity rounded-2xl"></div>
        <div class="w-12 h-12 rounded-xl bg-brand-500/10 text-brand-400 flex items-center justify-center mb-4">
             <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
        </div>
        <h3 class="text-lg font-bold text-white mb-2">Import CSV</h3>
        <p class="text-sm text-gray-400 mb-4">Upload a matching CSV file to populate transactions. Expected format: Date, Type, Amount, Category, Description</p>
        <form method="POST" action="/settings/import" enctype="multipart/form-data" class="flex flex-col gap-3">
            <input type="file" name="csv_file" accept=".csv" required
                class="block w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-dark-700 file:text-brand-400 hover:file:bg-dark-600 cursor-pointer">
            <button type="submit" class="w-full inline-flex justify-center flex-1 items-center gap-2 bg-brand-500 hover:bg-brand-400 text-white font-medium py-2 px-4 rounded-xl transition-colors">
                Upload & Merge
            </button>
        </form>
    </div>

    <!-- DB Backup -->
    <div class="bg-dark-800/80 backdrop-blur-md border border-dark-700/50 rounded-2xl p-6 shadow-xl relative group hover:border-dark-600 transition-colors">
        <div class="absolute inset-0 bg-gradient-to-b from-purple-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity rounded-2xl"></div>
        <div class="w-12 h-12 rounded-xl bg-purple-500/10 text-purple-400 flex items-center justify-center mb-4">
             <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"></path></svg>
        </div>
        <h3 class="text-lg font-bold text-white mb-2">Download SQLite DB</h3>
        <p class="text-sm text-gray-400 mb-6">Create a full raw backup of your <code>finance.db</code>. This includes settings, commitments, categories, and transactions.</p>
        <a href="/settings/backup" class="w-full inline-flex justify-center items-center gap-2 bg-purple-600 hover:bg-purple-500 text-white font-medium py-2 px-4 rounded-xl transition-colors shadow-md">
            Download Database
        </a>
    </div>

    <!-- Change Password -->
    <div class="bg-dark-800/80 backdrop-blur-md border border-dark-700/50 rounded-2xl p-6 shadow-xl relative group hover:border-dark-600 transition-colors">
        <div class="absolute inset-0 bg-gradient-to-b from-red-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity rounded-2xl"></div>
        <div class="w-12 h-12 rounded-xl bg-red-500/10 text-red-400 flex items-center justify-center mb-4">
             <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
        </div>
        <h3 class="text-lg font-bold text-white mb-2">Change Password</h3>
        <p class="text-sm text-gray-400 mb-4">Secure your account by updating your password.</p>
        <form method="POST" action="/settings/password" class="flex flex-col gap-3">
            <input type="password" name="old_password" required placeholder="Current Password"
                class="block w-full text-sm text-white px-4 py-2 bg-dark-900 border border-dark-600 rounded-lg outline-none focus:border-brand-500 transition-colors">
            <input type="password" name="new_password" required placeholder="New Password"
                class="block w-full text-sm text-white px-4 py-2 bg-dark-900 border border-dark-600 rounded-lg outline-none focus:border-brand-500 transition-colors">
            <button type="submit" class="w-full inline-flex justify-center flex-1 items-center gap-2 bg-red-600 hover:bg-red-500 text-white font-medium py-2 px-4 rounded-xl transition-colors mt-1">
                Update Password
            </button>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
