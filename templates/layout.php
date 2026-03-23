<?php
require_once __DIR__ . '/../src/Auth.php';
Auth::requireLogin();

$currentRoute = $route ?? 'dashboard';

$navItems = [
    'dashboard' => ['label' => 'Dashboard', 'icon' => 'home'],
    'transactions' => ['label' => 'Transactions', 'icon' => 'document'],
    'commitments' => ['label' => 'Commitments', 'icon' => 'calendar'],
    'categories' => ['label' => 'Categories', 'icon' => 'tag'],
    'settings' => ['label' => 'Settings', 'icon' => 'cog'],
];
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ExpenseOwl - Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Outfit', sans-serif; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #475569; }
    </style>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Outfit', 'sans-serif'] },
                    colors: {
                        brand: { 400: '#34d399', 500: '#10b981', 600: '#059669' },
                        dark: { 900: '#0f172a', 800: '#1e293b', 700: '#334155', 600: '#475569' }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-dark-900 text-gray-100 flex h-screen overflow-hidden selection:bg-brand-500/30">

    <!-- Mobile sidebar backdrop -->
    <div id="sidebar-backdrop" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 hidden lg:hidden transition-opacity" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <aside id="sidebar" class="fixed lg:static inset-y-0 left-0 w-72 bg-dark-800/80 backdrop-blur-xl border-r border-dark-700/50 transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-[cubic-bezier(0.4,0,0.2,1)] z-50 flex flex-col shadow-2xl lg:shadow-none">
        <div class="p-6 flex items-center justify-between lg:justify-center border-b border-dark-700/50 relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-r from-brand-500/10 to-transparent opacity-50"></div>
            <div class="relative flex items-center gap-3 w-full justify-center">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-brand-400 to-brand-600 flex items-center justify-center shadow-lg shadow-brand-500/30">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <h1 class="text-2xl font-bold tracking-tight text-transparent bg-clip-text bg-gradient-to-r from-white to-gray-400">ExpenseOwl</h1>
            </div>
            <button onclick="toggleSidebar()" class="lg:hidden absolute right-4 text-gray-400 hover:text-white p-2 rounded-lg hover:bg-dark-700/50">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        
        <nav class="flex-1 px-4 py-8 space-y-2 overflow-y-auto w-full">
            <div class="text-xs font-semibold text-gray-500 tracking-wider mb-4 px-3">MENU</div>
            <?php foreach ($navItems as $key => $item): ?>
                <?php $isActive = ($currentRoute === $key || ($currentRoute==='' && $key==='dashboard')); ?>
                <a href="/<?= $key ?>" class="group flex items-center gap-3 px-3 py-3.5 rounded-xl transition-all duration-200 <?= $isActive ? 'bg-brand-500/10 text-brand-400 shadow-[inset_2px_0_0_0_#10b981]' : 'text-gray-400 hover:bg-dark-700/30 hover:text-gray-100' ?>">
                    <span class="w-6 h-6 flex items-center justify-center <?= $isActive ? 'text-brand-400' : 'text-gray-500 group-hover:text-gray-300' ?> transition-colors">
                        <?php if($item['icon'] === 'home'): ?>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        <?php elseif($item['icon'] === 'document'): ?>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        <?php elseif($item['icon'] === 'calendar'): ?>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        <?php elseif($item['icon'] === 'tag'): ?>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                        <?php elseif($item['icon'] === 'cog'): ?>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        <?php endif; ?>
                    </span>
                    <span class="font-medium inline-block"><?= htmlspecialchars((string)$item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="p-6 border-t border-dark-700/50 w-full">
            <div class="flex items-center gap-3 mb-6 px-2">
                <div class="w-10 h-10 rounded-full bg-dark-700 flex items-center justify-center text-white border border-dark-600 shrink-0">
                    <span class="font-semibold"><?= strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)) ?></span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-white truncate"><?= htmlspecialchars((string)($_SESSION['username'] ?? 'User'), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="text-xs text-brand-400">Pro Member</p>
                </div>
            </div>
            <a href="/logout" class="flex items-center gap-3 px-3 py-3 text-red-400 hover:bg-red-500/10 rounded-xl transition-all duration-200 group w-full">
                <span class="w-6 h-6 flex items-center justify-center group-hover:text-red-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                </span>
                <span class="font-medium">Sign Out</span>
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col min-w-0 relative">
        <!-- Floating shapes background -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none z-0">
            <div class="absolute top-[20%] left-[60%] w-[600px] h-[600px] bg-brand-500/5 rounded-full blur-[100px]"></div>
            <div class="absolute top-[80%] left-[10%] w-[400px] h-[400px] bg-blue-500/5 rounded-full blur-[100px]"></div>
        </div>

        <!-- Mobile Header -->
        <header class="lg:hidden relative z-10 flex items-center gap-4 bg-dark-800/80 backdrop-blur-xl border-b border-dark-700/50 p-4">
            <button onclick="toggleSidebar()" class="text-gray-400 hover:text-white p-2 bg-dark-700/50 rounded-lg">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
            </button>
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-brand-500 flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <h1 class="text-lg font-bold">ExpenseOwl</h1>
            </div>
        </header>

        <!-- View Content -->
        <div class="flex-1 overflow-auto relative z-10 p-6 lg:p-10">
            <div class="max-w-6xl mx-auto space-y-8">
                <?php if (isset($content)) echo $content; ?>
            </div>
        </div>
    </main>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const backdrop = document.getElementById('sidebar-backdrop');
            sidebar.classList.toggle('-translate-x-full');
            backdrop.classList.toggle('hidden');
        }
    </script>
</body>
</html>
