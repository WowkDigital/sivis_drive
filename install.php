<?php
// install.php - Sivis Drive Installer

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$errors = [];
$checks = [
    'php_version' => version_compare(PHP_VERSION, '7.4.0', '>='),
    'pdo' => extension_loaded('pdo'),
    'pdo_sqlite' => extension_loaded('pdo_sqlite'),
    'writable_dir' => is_writable(__DIR__),
    'uploads_dir' => is_dir(__DIR__ . '/uploads') ? is_writable(__DIR__ . '/uploads') : is_writable(__DIR__),
];

$all_passed = !in_array(false, $checks, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 2) {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    
    if (!$email) $errors[] = "Proszę podać poprawny adres e-mail.";
    if (strlen($password) < 6) $errors[] = "Hasło musi mieć co najmniej 6 znaków.";
    
    if (empty($errors)) {
        try {
            $db_file = __DIR__ . '/database.sqlite';
            $db = new PDO("sqlite:$db_file");
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Create tables
            $db->exec("CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT UNIQUE,
                password_hash TEXT,
                role TEXT,
                user_group TEXT
            )");

            $db->exec("CREATE TABLE IF NOT EXISTS folders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT,
                access_groups TEXT
            )");

            $db->exec("CREATE TABLE IF NOT EXISTS files (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                folder_id INTEGER,
                name TEXT,
                original_name TEXT,
                size INTEGER,
                uploaded_by INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(folder_id) REFERENCES folders(id)
            )");

            // Create uploads directory if not exists
            if (!is_dir(__DIR__ . '/uploads')) {
                mkdir(__DIR__ . '/uploads', 0777, true);
            }

            // Insert custom admin
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (email, password_hash, role, user_group) VALUES (?, ?, 'admin', 'admin')");
            $stmt->execute([$email, $hash]);

            // Success! Delete installer and redirect
            $success = true;
        } catch (Exception $e) {
            $errors[] = "Błąd bazy danych: " . $e->getMessage();
        }
    }
}

// Handle deletion after success
if (isset($success) && $success) {
    // We will use a small script to redirect and then delete
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Instalacja zakończona</title></head><body style='background:#0f172a;color:#fff;display:flex;justify-content:center;align-items:center;height:100vh;font-family:sans-serif;'>";
    echo "<div style='text-align:center;'><h2>Instalacja zakończona sukcesem!</h2><p>Trwa przekierowanie do logowania...</p></div>";
    echo "<script>
        setTimeout(function() {
            window.location.href = 'login.php?installed=1';
        }, 2000);
    </script></body></html>";
    
    // Attempt to delete self
    @unlink(__FILE__);
    exit;
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
                        slate: { 800: '#1e293b', 900: '#0f172a' }
                    }
                }
            }
        }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .glass { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.1); }
        .step-active { color: #3b82f6; border-bottom: 2px solid #3b82f6; }
    </style>
</head>
<body class="bg-slate-900 text-slate-200 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full glass rounded-3xl shadow-2xl overflow-hidden">
        <div class="bg-blue-600 p-8 text-white text-center relative overflow-hidden">
            <div class="absolute top-0 left-0 w-full h-full opacity-10">
                <i data-lucide="cloud" class="w-64 h-64 -ml-20 -mt-20 transform rotate-12"></i>
            </div>
            <div class="relative z-10">
                <i data-lucide="settings" class="w-12 h-12 mx-auto mb-4"></i>
                <h1 class="text-3xl font-bold tracking-tight">Instalacja</h1>
                <p class="text-blue-100 opacity-80 mt-2">Dokończ konfigurację Sivis Drive</p>
            </div>
        </div>

        <div class="p-8">
            <!-- Progress Steps -->
            <div class="flex justify-between mb-8 border-b border-slate-700 pb-2">
                <div class="text-sm font-semibold <?= $step === 1 ? 'step-active' : 'text-slate-500' ?>">1. Wymagania</div>
                <div class="text-sm font-semibold <?= $step === 2 ? 'step-active' : 'text-slate-500' ?>">2. Konto Admina</div>
            </div>

            <?php if ($step === 1): ?>
                <div class="space-y-4">
                    <h3 class="text-lg font-medium mb-4 flex items-center">
                        <i data-lucide="search" class="w-5 h-5 mr-2 text-blue-400"></i> Sprawdzanie środowiska
                    </h3>
                    
                    <div class="space-y-3">
                        <div class="flex items-center justify-between p-3 bg-slate-800/50 rounded-xl">
                            <span class="text-sm">Wersja PHP (>= 7.4)</span>
                            <i data-lucide="<?= $checks['php_version'] ? 'check-circle' : 'x-circle' ?>" class="<?= $checks['php_version'] ? 'text-emerald-400' : 'text-red-400' ?> w-5 h-5"></i>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-slate-800/50 rounded-xl">
                            <span class="text-sm">Rozszerzenie PDO</span>
                            <i data-lucide="<?= $checks['pdo'] ? 'check-circle' : 'x-circle' ?>" class="<?= $checks['pdo'] ? 'text-emerald-400' : 'text-red-400' ?> w-5 h-5"></i>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-slate-800/50 rounded-xl">
                            <span class="text-sm">Rozszerzenie PDO SQLite</span>
                            <i data-lucide="<?= $checks['pdo_sqlite'] ? 'check-circle' : 'x-circle' ?>" class="<?= $checks['pdo_sqlite'] ? 'text-emerald-400' : 'text-red-400' ?> w-5 h-5"></i>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-slate-800/50 rounded-xl">
                            <span class="text-sm">Uprawnienia do zapisu</span>
                            <i data-lucide="<?= $checks['writable_dir'] ? 'check-circle' : 'x-circle' ?>" class="<?= $checks['writable_dir'] ? 'text-emerald-400' : 'text-red-400' ?> w-5 h-5"></i>
                        </div>
                    </div>

                    <?php if ($all_passed): ?>
                        <div class="mt-8">
                            <a href="?step=2" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-4 rounded-2xl flex items-center justify-center transition-all shadow-lg shadow-blue-500/20">
                                Kontynuuj <i data-lucide="arrow-right" class="ml-2 w-5 h-5"></i>
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="mt-8 p-4 bg-red-500/10 border border-red-500/20 rounded-2xl">
                            <p class="text-red-400 text-sm text-center">Niestety Twój serwer nie spełnia wymagań.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif ($step === 2): ?>
                <form method="post" class="space-y-5">
                    <h3 class="text-lg font-medium mb-4 flex items-center">
                        <i data-lucide="user-plus" class="w-5 h-5 mr-2 text-blue-400"></i> Ustawienia Admina
                    </h3>

                    <?php if (!empty($errors)): ?>
                        <div class="p-4 bg-red-500/10 border border-red-500/20 rounded-2xl">
                            <?php foreach ($errors as $err): ?>
                                <p class="text-red-400 text-sm italic">• <?= htmlspecialchars($err) ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div>
                        <label class="block text-sm font-medium text-slate-400 mb-2">E-mail Administratora</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i data-lucide="mail" class="h-5 w-5 text-slate-500"></i>
                            </div>
                            <input type="email" name="email" required placeholder="admin@example.com" class="block w-full pl-11 pr-4 py-4 bg-slate-800/50 border border-slate-700 rounded-2xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all outline-none">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-400 mb-2">Hasło</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i data-lucide="lock" class="h-5 w-5 text-slate-500"></i>
                            </div>
                            <input type="password" name="password" required placeholder="••••••••" class="block w-full pl-11 pr-4 py-4 bg-slate-800/50 border border-slate-700 rounded-2xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all outline-none">
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-4 rounded-2xl flex items-center justify-center transition-all shadow-lg shadow-blue-500/20 mt-4">
                        <i data-lucide="check" class="mr-2 w-5 h-5"></i> Zakończ Instalację
                    </button>
                    
                    <div class="text-center">
                        <a href="?step=1" class="text-slate-500 hover:text-slate-400 text-xs transition-colors">Wróć do wymagań</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
        
        <div class="p-6 bg-slate-800/30 border-t border-slate-700 text-center">
            <p class="text-slate-500 text-xs">Sivis Drive &copy; 2026</p>
        </div>
    </div>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
