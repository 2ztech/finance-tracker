<?php
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Account.php';
Auth::requireLogin();

$currentRoute = $route ?? 'dashboard';

$accounts = Account::all();
$activeAccount = Account::active();
$activeId = $activeAccount ? (int) $activeAccount['id'] : 0;
$netWorth = Account::netWorth();

$accountNav = [
    'dashboard'    => ['label' => 'Dashboard',    'icon' => 'home'],
    'transactions' => ['label' => 'Transactions', 'icon' => 'document'],
    'bills'        => ['label' => 'Bills & BNPL', 'icon' => 'bill'],
    'recurring'    => ['label' => 'Recurring',    'icon' => 'calendar'],
];
$globalNav = [
    'budgets'    => ['label' => 'Budgets',    'icon' => 'chart'],
    'categories' => ['label' => 'Categories', 'icon' => 'tag'],
    'accounts'   => ['label' => 'Accounts',   'icon' => 'wallet'],
    'settings'   => ['label' => 'Settings',   'icon' => 'cog'],
];

$returnPath = $_SERVER['REQUEST_URI'] ?? '/dashboard';

function navIcon(string $icon): string
{
    return match ($icon) {
        'home' => '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>',
        'document' => '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
        'calendar' => '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>',
        'bill' => '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/></svg>',
        'tag' => '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>',
        'chart' => '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>',
        'wallet' => '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>',
        'cog' => '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
        default => '',
    };
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expenzz</title>
    <link href="/tailwind.min.css" rel="stylesheet">
    <script src="/vendor/chart.umd.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link href="/app.css" rel="stylesheet">
</head>
<body class="flex h-screen overflow-hidden">

    <div id="sidebar-backdrop" class="fixed inset-0 z-40 hidden bg-black/50 backdrop-blur-sm lg:hidden" onclick="toggleSidebar()"></div>

    <aside id="sidebar" class="fixed lg:static inset-y-0 left-0 z-50 flex w-60 flex-col border-r transition-transform duration-300 -translate-x-full lg:translate-x-0" style="background:var(--sidebar-bg);border-color:var(--sidebar-border);">
        <div class="flex items-center justify-between px-5 py-4 border-b" style="border-color:var(--sidebar-border);">
            <div class="flex items-center gap-2.5">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg text-sm font-bold text-white" style="background:var(--accent);">E</div>
                <span class="text-lg font-semibold" style="color:var(--text);">Expenzz</span>
            </div>
            <button onclick="toggleSidebar()" class="rounded-lg p-1.5 lg:hidden hover:opacity-70" style="color:var(--text-secondary);">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <!-- Net worth + account switcher -->
        <div class="px-3 py-4 border-b space-y-3" style="border-color:var(--sidebar-border);">
            <div class="rounded-lg border px-3 py-2.5" style="background:var(--bg-alt);border-color:var(--border);">
                <p class="text-[11px] font-medium" style="color:var(--text-muted);">Net Worth</p>
                <p class="text-lg font-bold" style="color:var(--text);">RM <?= number_format($netWorth, 2) ?></p>
            </div>
            <?php if (!empty($accounts)): ?>
            <form method="POST" action="/accounts/activate">
                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                <input type="hidden" name="return" value="<?= htmlspecialchars((string) $returnPath, ENT_QUOTES, 'UTF-8') ?>">
                <label class="mb-1 block text-[11px] font-medium" style="color:var(--text-muted);">Account</label>
                <select name="account_id" onchange="this.form.submit()"
                    class="w-full rounded-lg border px-3 py-2 text-sm font-semibold"
                    style="background:var(--bg-alt);border-color:var(--border);color:var(--text);">
                    <?php foreach ($accounts as $a): ?>
                        <option value="<?= (int) $a['id'] ?>" <?= (int) $a['id'] === $activeId ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) $a['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php endif; ?>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
            <?php
            $renderNav = function (array $items) use ($currentRoute) {
                foreach ($items as $key => $item) {
                    $isActive = ($currentRoute === $key || ($currentRoute === '' && $key === 'dashboard'));
                    echo '<a href="/' . $key . '" class="flex items-center gap-2.5 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors" style="' . ($isActive ? 'background:var(--accent-soft);color:var(--accent);' : 'color:var(--text-secondary);') . '" onmouseover="if(!this.style.color.includes(\'var(--accent)\'))this.style.background=\'var(--bg-hover)\'" onmouseout="if(!this.style.color.includes(\'var(--accent)\'))this.style.background=\'\'">';
                    echo '<span class="flex h-5 w-5 items-center justify-center">' . navIcon($item['icon']) . '</span>';
                    echo htmlspecialchars((string) $item['label'], ENT_QUOTES, 'UTF-8') . '</a>';
                }
            };
            $renderNav($accountNav);
            ?>
            <div class="my-2 border-t" style="border-color:var(--border);"></div>
            <?php $renderNav($globalNav); ?>
            <div class="my-2 border-t" style="border-color:var(--border);"></div>
            <a href="/settings#data-management" class="flex items-center gap-2.5 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors" style="color:var(--text-secondary);">
                <span class="flex h-5 w-5 items-center justify-center"><?= navIcon('document') ?></span>Import / Export
            </a>
            <a href="/settings#restore-database" class="flex items-center gap-2.5 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors" style="color:var(--text-secondary);">
                <span class="flex h-5 w-5 items-center justify-center"><?= navIcon('wallet') ?></span>Backup &amp; Restore
            </a>
        </nav>

        <div class="border-t px-3 py-4 space-y-3" style="border-color:var(--sidebar-border);">
            <div class="flex items-center gap-2.5 px-3">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold" style="background:var(--accent-soft);color:var(--accent);">
                    <?= strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)) ?>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium" style="color:var(--text);"><?= htmlspecialchars((string) ($_SESSION['username'] ?? 'User'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>

            <a href="/logout" class="flex items-center gap-2.5 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors" style="color:var(--danger);" onmouseover="this.style.background='var(--danger-soft)'" onmouseout="this.style.background=''">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                Sign Out
            </a>
        </div>
    </aside>

    <main class="flex flex-1 flex-col min-w-0 overflow-hidden">
        <header class="flex items-center gap-3 border-b px-4 py-3 lg:hidden" style="background:var(--sidebar-bg);border-color:var(--sidebar-border);">
            <button onclick="toggleSidebar()" class="rounded-lg p-1.5" style="color:var(--text-secondary);">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <span class="text-lg font-semibold" style="color:var(--text);">Expenzz</span>
            <?php if ($activeAccount): ?>
                <span class="ml-auto text-sm" style="color:var(--text-secondary);"><?= htmlspecialchars((string) $activeAccount['name'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
        </header>

        <div class="flex-1 overflow-auto p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-screen-2xl space-y-6">
                <?php if (isset($content)) echo $content; ?>
            </div>
        </div>
    </main>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('-translate-x-full');
            document.getElementById('sidebar-backdrop').classList.toggle('hidden');
        }

        const appModals = ['editModal', 'accountModal', 'payModal', 'refundModal']
            .map((id) => document.getElementById(id)).filter(Boolean);
        appModals.forEach((modal) => {
            modal.setAttribute('role', 'dialog');
            modal.setAttribute('aria-modal', 'true');
            if (!modal.hasAttribute('aria-label')) {
                const title = modal.querySelector('h3');
                if (title) modal.setAttribute('aria-label', title.textContent.trim());
            }
        });
        const modalFocusReturn = new WeakMap();
        const modalObserver = new MutationObserver((changes) => changes.forEach((change) => {
            const modal = change.target;
            if (modal.classList.contains('hidden')) {
                if (modal.dataset.wasOpen !== 'true') return;
                modal.dataset.wasOpen = 'false';
                const returnTo = modalFocusReturn.get(modal);
                if (returnTo && returnTo.isConnected) returnTo.focus();
                return;
            }
            if (modal.dataset.wasOpen === 'true') return;
            modal.dataset.wasOpen = 'true';
            modalFocusReturn.set(modal, document.activeElement);
            const firstField = modal.querySelector('input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled])') || modal.querySelector('button:not([disabled])');
            if (firstField) firstField.focus();
        }));
        appModals.forEach((modal) => modalObserver.observe(modal, { attributes: true, attributeFilter: ['class'] }));
        document.addEventListener('keydown', (event) => {
            const modal = appModals.find((item) => !item.classList.contains('hidden'));
            if (!modal) return;
            if (event.key === 'Escape') {
                event.preventDefault();
                const close = { editModal: 'closeEdit', accountModal: 'closeAccountForm', payModal: 'closePay', refundModal: 'closeRefund' }[modal.id];
                if (close && typeof window[close] === 'function') window[close]();
                return;
            }
            if (event.key === 'Tab') {
                const focusable = [...modal.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])')]
                    .filter((el) => el.getClientRects().length > 0);
                if (!focusable.length) return;
                const first = focusable[0], last = focusable[focusable.length - 1];
                if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
                else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
            }
        });

    </script>
</body>
</html>
