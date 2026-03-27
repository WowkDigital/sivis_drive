<?php
/**
 * Sivis Drive - Installation Helper
 * Automatic installation panel for clean deployment.
 */
require_once 'core/auth.php'; // Will include db.php and start session

// Check if database already has users
try {
    $stmt = $db->query("SELECT COUNT(*) FROM users");
    $user_count = $stmt->fetchColumn();
    // Redirect if installed, but only if the user is NOT in the middle of a POST request
    if ($user_count > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        header("Location: login.php");
        exit;
    }
} catch (Exception $e) {
    // Database tables might not exist yet, that's okay, db.php should create them
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Błąd weryfikacji tokenu CSRF.';
    } else {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $display_name = $_POST['display_name'] ?? 'Administrator';

        if (empty($email) || empty($password)) {
            $error = 'Wypełnij wszystkie pola.';
        } else {
            // Create the first admin user
            $hash = password_hash($password, PASSWORD_BCRYPT);
            try {
                $stmt = $db->prepare("INSERT INTO users (email, password_hash, role, user_group, display_name) VALUES (?, ?, 'admin', 'Zarząd', ?)");
                $stmt->execute([$email, $hash, $display_name]);
                
                // Success - redirect to login
                header("Location: login.php?installed=1");
                exit;
            } catch (Exception $e) {
                $error = 'Wystąpił błąd podczas instalacji: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pl" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sivis Drive - Instalacja</title>
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
                    <i data-lucide="shield-check" class="w-10 h-10"></i>
                </div>
            </div>
            <h1 class="text-3xl font-extrabold tracking-tight">Sivis Drive</h1>
            <p class="text-slate-400 mt-2 text-sm text-center font-medium uppercase tracking-widest">Panel Instalacyjny</p>
        </div>

        <!-- Install Card -->
        <div class="bg-slate-800 p-8 rounded-3xl shadow-2xl border border-slate-700/60 backdrop-blur-xl">
            <div class="flex items-center mb-4">
               <div class="p-2 bg-blue-500/10 rounded-xl mr-3">
                   <i data-lucide="user-plus" class="w-6 h-6 text-blue-400"></i>
               </div>
               <h2 class="text-xl font-bold text-slate-100">Pierwszy Administrator</h2>
            </div>
            <p class="text-slate-400 text-sm mb-6 leading-relaxed">Wykryto czystą instalację. Utwórz konto administratora, aby rozpocząć korzystanie z systemu.</p>
            
            <?php if ($error): ?>
                <div class="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-xl relative mb-6 text-sm flex items-center">
                    <i data-lucide="alert-triangle" class="w-4 h-4 mr-2"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="post" action="" class="space-y-5">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <div>
                    <label class="block text-slate-400 text-sm font-medium mb-2" for="display_name">Imię i Nazwisko</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                            <i data-lucide="user" class="w-5 h-5 text-slate-500 group-focus-within:text-blue-400 transition-colors"></i>
                        </div>
                        <input class="w-full bg-slate-900/50 border border-slate-700 rounded-xl py-3 pl-11 pr-4 text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition-all placeholder:text-slate-600" id="display_name" type="text" name="display_name" required placeholder="np. Adam Kowalski" value="Administrator">
                    </div>
                </div>
                <div>
                    <label class="block text-slate-400 text-sm font-medium mb-2" for="email">Adres e-mail (Login)</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                            <i data-lucide="mail" class="w-5 h-5 text-slate-500 group-focus-within:text-blue-400 transition-colors"></i>
                        </div>
                        <input class="w-full bg-slate-900/50 border border-slate-700 rounded-xl py-3 pl-11 pr-4 text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition-all placeholder:text-slate-600" id="email" type="email" name="email" required placeholder="admin@firma.pl">
                    </div>
                </div>
                <div>
                    <label class="block text-slate-400 text-sm font-medium mb-2" for="password">Hasło</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                            <i data-lucide="lock" class="w-5 h-5 text-slate-500 group-focus-within:text-blue-400 transition-colors"></i>
                        </div>
                        <input class="w-full bg-slate-900/50 border border-slate-700 rounded-xl py-3 pl-11 pr-4 text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition-all placeholder:text-slate-600" id="password" type="password" name="password" required placeholder="••••••••">
                    </div>
                    <p class="mt-2 text-[10px] text-slate-500 uppercase tracking-widest font-bold flex items-center">
                        <i data-lucide="shield-check" class="w-3 h-3 mr-1 text-emerald-500"></i>
                        Hasło zostanie bezpiecznie zahashowane.
                    </p>
                </div>
                <div class="pt-4 pb-2 border-t border-slate-700/50 mt-6 text-center">
                    <button class="bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-bold py-4 rounded-2xl w-full flex items-center justify-center transition-all duration-300 shadow-xl shadow-blue-500/20 active:scale-95 group" type="submit">
                        <i data-lucide="check-circle" class="w-5 h-5 mr-2 group-hover:scale-110 transition-transform"></i>
                        Zainstaluj i przejdź do systemu
                    </button>
                    <p class="mt-4 text-[10px] text-slate-500">Po instalacji zalecamy usunięcie pliku install.php dla bezpieczeństwa.</p>
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
