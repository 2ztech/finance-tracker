<?php
require_once __DIR__ . '/../src/Auth.php';
Auth::requireLogin();

$currentRoute = $route ?? 'dashboard';

$navItems = [
    'dashboard'    => ['label' => 'Dashboard',    'icon' => 'home'],
    'transactions' => ['label' => 'Transactions', 'icon' => 'document'],
    'recurring'    => ['label' => 'Recurring',    'icon' => 'calendar'],
    'categories'   => ['label' => 'Categories',   'icon' => 'tag'],
    'settings'     => ['label' => 'Settings',     'icon' => 'cog'],
];
?><!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expenzz</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- ===== Theme Variables ===== -->
    <style>
        :root {
            --bg:             #f3f4f6;
            --bg-alt:         #ffffff;
            --bg-hover:       #e5e7eb;
            --border:         #e5e7eb;
            --border-light:   #f3f4f6;
            --text:           #111827;
            --text-secondary: #6b7280;
            --text-muted:     #9ca3af;
            --accent:         #4f6ef7;
            --accent-hover:   #3b57e0;
            --accent-soft:    #eef0ff;
            --danger:         #ef4444;
            --danger-soft:    #fef2f2;
            --success:        #10b981;
            --success-soft:   #ecfdf5;
            --income:         #059669;
            --expense:        #dc2626;
            --sidebar-bg:     #ffffff;
            --sidebar-border: #e5e7eb;
            --shadow:         0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
            --shadow-lg:      0 4px 12px rgba(0,0,0,.06);
            --radius:         12px;
        }
        .dark {
            --bg:             #0b0e14;
            --bg-alt:         #151923;
            --bg-hover:       #1e2433;
            --border:         #1e2433;
            --border-light:   #232940;
            --text:           #e4e8f1;
            --text-secondary: #8890a5;
            --text-muted:     #5c6378;
            --accent:         #5b8def;
            --accent-hover:   #7ba3f5;
            --accent-soft:    #1a2340;
            --danger:         #f87171;
            --danger-soft:    #2d1b1b;
            --success:        #4ade80;
            --success-soft:   #1a2d20;
            --income:         #4ade80;
            --expense:        #f87171;
            --sidebar-bg:     #111520;
            --sidebar-border: #1e2433;
            --shadow:         0 1px 3px rgba(0,0,0,.2);
            --shadow-lg:      0 4px 12px rgba(0,0,0,.3);
            --radius:         12px;
        }
        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg);
            color: var(--text);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--text-muted); border-radius: 4px; }
    </style>

    <!-- ===== Tailwind Config ===== -->
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Outfit', 'sans-serif'] },
                },
            },
        }
    </script>
</head>
<body class="flex h-screen overflow-hidden">

    <!-- ===== Sidebar Backdrop (mobile) ===== -->
    <div id="sidebar-backdrop" class="fixed inset-0 z-40 hidden bg-black/50 backdrop-blur-sm lg:hidden" onclick="toggleSidebar()"></div>

    <!-- ===== Sidebar ===== -->
    <aside id="sidebar" class="fixed lg:static inset-y-0 left-0 z-50 flex w-60 flex-col border-r transition-transform duration-300 -translate-x-full lg:translate-x-0" style="background:var(--sidebar-bg);border-color:var(--sidebar-border);">
        <!-- Brand -->
        <div class="flex items-center justify-between px-5 py-4 border-b" style="border-color:var(--sidebar-border);">
            <div class="flex items-center gap-2.5">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg text-sm font-bold text-white" style="background:var(--accent);">E</div>
                <span class="text-lg font-semibold" style="color:var(--text);">Expenzz</span>
            </div>
            <button onclick="toggleSidebar()" class="rounded-lg p-1.5 lg:hidden hover:opacity-70" style="color:var(--text-secondary);">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 overflow-y-auto px-3 py-5 space-y-1">
            <?php foreach ($navItems as $key => $item): ?>
                <?php $isActive = ($currentRoute === $key || ($currentRoute === '' && $key === 'dashboard')); ?>
                <a href="/<?= $key ?>"
                   class="flex items-center gap-2.5 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors"
                   style="<?= $isActive ? "background:var(--accent-soft);color:var(--accent);" : "color:var(--text-secondary);" ?>"
                   onmouseover="if(!this.style.color.includes('var(--accent)'))this.style.background='var(--bg-hover)'"
                   onmouseout="if(!this.style.color.includes('var(--accent)'))this.style.background=''">
                    <span class="flex h-5 w-5 items-center justify-center">
                        <?php if ($item['icon'] === 'home'): ?>
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        <?php elseif ($item['icon'] === 'document'): ?>
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <?php elseif ($item['icon'] === 'calendar'): ?>
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <?php elseif ($item['icon'] === 'tag'): ?>
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                        <?php elseif ($item['icon'] === 'cog'): ?>
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <?php endif; ?>
                    </span>
                    <?= htmlspecialchars((string) $item['label'], ENT_QUOTES, 'UTF-8') ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <!-- Sidebar Footer -->
        <div class="border-t px-3 py-4 space-y-3" style="border-color:var(--sidebar-border);">
            <!-- Theme Toggle -->
            <button onclick="toggleTheme()" class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors" style="color:var(--text-secondary);" onmouseover="this.style.background='var(--bg-hover)'" onmouseout="this.style.background=''">
                <span class="flex h-5 w-5 items-center justify-center" id="theme-icon-light">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                </span>
                <span class="flex h-5 w-5 items-center justify-center hidden" id="theme-icon-dark">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                </span>
                <span id="theme-label">Light Mode</span>
            </button>

            <!-- User Info -->
            <div class="flex items-center gap-2.5 px-3">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold" style="background:var(--accent-soft);color:var(--accent);">
                    <?= strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)) ?>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium" style="color:var(--text);"><?= htmlspecialchars((string) ($_SESSION['username'] ?? 'User'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>

            <!-- Sign Out -->
            <a href="/logout" class="flex items-center gap-2.5 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors" style="color:var(--danger);" onmouseover="this.style.background='var(--danger-soft)'" onmouseout="this.style.background=''">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                Sign Out
            </a>
        </div>
    </aside>

    <!-- ===== Main Content ===== -->
    <main class="flex flex-1 flex-col min-w-0 overflow-hidden">
        <!-- Mobile Header -->
        <header class="flex items-center gap-3 border-b px-4 py-3 lg:hidden" style="background:var(--sidebar-bg);border-color:var(--sidebar-border);">
            <button onclick="toggleSidebar()" class="rounded-lg p-1.5" style="color:var(--text-secondary);">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <span class="text-lg font-semibold" style="color:var(--text);">Expenzz</span>
        </header>

        <!-- Page Content -->
        <div class="flex-1 overflow-auto p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-5xl space-y-6">
                <?php if (isset($content)) echo $content; ?>
            </div>
        </div>
    </main>

    <!-- ===== Scripts ===== -->
    <script>
        // Sidebar toggle
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('-translate-x-full');
            document.getElementById('sidebar-backdrop').classList.toggle('hidden');
        }

        // Theme toggle
        (function() {
            var stored = localStorage.getItem('theme');
            if (stored === 'light') {
                document.documentElement.classList.remove('dark');
            }
            updateThemeUI();
        })();

        function toggleTheme() {
            var html = document.documentElement;
            if (html.classList.contains('dark')) {
                html.classList.remove('dark');
                localStorage.setItem('theme', 'light');
            } else {
                html.classList.add('dark');
                localStorage.setItem('theme', 'dark');
            }
            updateThemeUI();
        }

        function updateThemeUI() {
            var isDark = document.documentElement.classList.contains('dark');
            document.getElementById('theme-icon-light').classList.toggle('hidden', !isDark);
            document.getElementById('theme-icon-dark').classList.toggle('hidden', isDark);
            document.getElementById('theme-label').textContent = isDark ? 'Light Mode' : 'Dark Mode';
        }
    </script>
</body>
</html>
