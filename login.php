<?php
require_once 'auth.php';
if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['user_group'] = $user['user_group'];
        header('Location: index.php');
        exit;
    } else {
        $error = 'Nieprawidłowy email lub hasło.';
    }
}
?>
<!DOCTYPE html>
<html lang="pl" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logowanie - Sivis Drive</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        slate: {
                            800: '#1e293b',
                            900: '#0f172a',
                        }
                    }
                }
            }
        }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-slate-900 flex items-center justify-center min-h-screen p-4">
    <div class="w-full max-w-md">
        <!-- Logo Header -->
        <div class="flex flex-col items-center justify-center mb-10 text-slate-100">
            <div class="flex items-center space-x-3 text-blue-400 mb-2">
                <div class="p-3 bg-blue-500/10 rounded-2xl border border-blue-500/20 shadow-lg shadow-blue-500/10">
                    <i data-lucide="cloud" class="w-10 h-10"></i>
                </div>
            </div>
            <h1 class="text-3xl font-extrabold tracking-tight">Sivis Drive</h1>
            <p class="text-slate-400 mt-2 text-sm text-center">Inteligentny system wymiany plików.</p>
        </div>

        <!-- Login Card -->
        <div class="bg-slate-800 p-8 rounded-3xl shadow-2xl border border-slate-700/60 backdrop-blur-xl">
            <h2 class="text-xl font-bold text-slate-100 mb-6 flex items-center"><i data-lucide="log-in" class="w-5 h-5 mr-2 text-blue-400"></i> Zaloguj się</h2>
            
            <?php if ($error): ?>
                <div class="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-xl relative mb-6 text-sm">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['installed'])): ?>
                <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-xl relative mb-6 text-sm flex items-center">
                    <i data-lucide="check-circle" class="w-4 h-4 mr-2"></i> Instalacja zakończona pomyślnie! Możesz się zalogować.
                </div>
            <?php endif; ?>

            <form method="post" action="" class="space-y-5">
                <div>
                    <label class="block text-slate-400 text-sm font-medium mb-2" for="email">Adres e-mail</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                            <i data-lucide="mail" class="w-5 h-5 text-slate-500"></i>
                        </div>
                        <input class="w-full bg-slate-900/50 border border-slate-700 rounded-xl py-3 pl-11 pr-4 text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition-all placeholder-slate-600" id="email" type="email" name="email" required placeholder="jan@firma.pl">
                    </div>
                </div>
                <div>
                    <label class="block text-slate-400 text-sm font-medium mb-2" for="password">Hasło</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                            <i data-lucide="lock" class="w-5 h-5 text-slate-500"></i>
                        </div>
                        <input class="w-full bg-slate-900/50 border border-slate-700 rounded-xl py-3 pl-11 pr-4 text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition-all placeholder-slate-600" id="password" type="password" name="password" required placeholder="••••••••">
                    </div>
                </div>
                <div class="pt-2">
                    <button class="bg-blue-600 hover:bg-blue-500 text-white font-medium py-3 rounded-xl w-full flex items-center justify-center transition-all duration-200 shadow-lg shadow-blue-500/20 hover:shadow-blue-500/40" type="submit">
                        Zaloguj się
                    </button>
                </div>
            </form>
        </div>
        
        <footer class="max-w-7xl mx-auto py-10 px-4 text-center">
            <p class="text-slate-600 text-xs flex items-center justify-center gap-1.5">
                Made with <i data-lucide="heart" class="w-3 h-3 text-red-500 fill-red-500"></i> by <span class="font-bold text-slate-500">WowkDigital</span>
            </p>
        </footer>
    </div>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
