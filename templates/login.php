<?php
require_once __DIR__ . '/../src/Auth.php';

$error = '';
$isFirstTime = !Auth::hasUsers();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($isFirstTime) {
        if (Auth::setupFirstUser($username, $password)) {
            header('Location: /dashboard');
            exit;
        }
        $error = 'Failed to create user. Please try again.';
    } else {
        if (Auth::attemptLogin($username, $password)) {
            header('Location: /dashboard');
            exit;
        }
        $error = 'Invalid credentials. Please try again.';
    }
}
?><!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expenzz</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg: #f3f4f6;
            --card: #ffffff;
            --border: #e5e7eb;
            --text: #111827;
            --text-secondary: #6b7280;
            --text-muted: #9ca3af;
            --accent: #4f6ef7;
            --accent-hover: #3b57e0;
            --input-bg: #f9fafb;
        }
        .dark {
            --bg: #0b0e14;
            --card: #151923;
            --border: #1e2433;
            --text: #e4e8f1;
            --text-secondary: #8890a5;
            --text-muted: #5c6378;
            --accent: #5b8def;
            --accent-hover: #7ba3f5;
            --input-bg: #111520;
        }
        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg);
            color: var(--text);
            -webkit-font-smoothing: antialiased;
        }
    </style>

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { extend: { fontFamily: { sans: ['Outfit', 'sans-serif'] } } },
        }
    </script>
</head>
<body class="flex min-h-screen items-center justify-center p-4">

    <div class="w-full max-w-sm">
        <div class="rounded-2xl border p-8 shadow-lg" style="background:var(--card);border-color:var(--border);">
            <!-- Brand -->
            <div class="mb-8 text-center">
                <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-xl text-lg font-bold text-white" style="background:var(--accent);">E</div>
                <h1 class="text-2xl font-bold" style="color:var(--text);">Expenzz</h1>
                <p class="mt-1 text-sm" style="color:var(--text-secondary);">
                    <?= $isFirstTime ? 'Set up your master account.' : 'Your personal ledger.' ?>
                </p>
            </div>

            <!-- Error -->
            <?php if ($error): ?>
                <div class="mb-5 flex items-center gap-2.5 rounded-lg border px-3.5 py-3 text-sm" style="background:#fef2f2;border-color:#fecaca;color:#dc2626;">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <!-- Form -->
            <form method="POST" action="/login" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">

                <div>
                    <label class="mb-1.5 block text-xs font-medium" style="color:var(--text-secondary);">Username</label>
                    <input type="text" name="username" required placeholder="Enter username"
                        class="w-full rounded-lg border px-3.5 py-2.5 text-sm outline-none transition-colors focus:ring-2"
                        style="background:var(--input-bg);border-color:var(--border);color:var(--text);"
                        onfocus="this.style.borderColor='var(--accent)';this.style.boxShadow='0 0 0 2px var(--accent-soft)'"
                        onblur="this.style.borderColor='var(--border)';this.style.boxShadow='none'">
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-medium" style="color:var(--text-secondary);">Password</label>
                    <input type="password" name="password" required placeholder="Enter password"
                        class="w-full rounded-lg border px-3.5 py-2.5 text-sm outline-none transition-colors focus:ring-2"
                        style="background:var(--input-bg);border-color:var(--border);color:var(--text);"
                        onfocus="this.style.borderColor='var(--accent)';this.style.boxShadow='0 0 0 2px var(--accent-soft)'"
                        onblur="this.style.borderColor='var(--border)';this.style.boxShadow='none'">
                </div>

                <button type="submit"
                    class="w-full rounded-lg py-2.5 text-sm font-semibold text-white transition-all hover:-translate-y-0.5"
                    style="background:var(--accent);">
                    <?= $isFirstTime ? 'Create Account' : 'Sign In' ?>
                </button>
            </form>
        </div>
    </div>

</body>
</html>
