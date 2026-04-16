<?php
require_once 'core/auth.php';
if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Błąd weryfikacji tokenu CSRF.';
    } else {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            $db->prepare('UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?')->execute([$user['id']]);
            
            // Check if 2FA is required for this user
            $enforce_2fa = get_setting($db, 'enforce_2fa_admin', '0') === '1';
            $is_admin_or_zarzad = ($user['role'] === 'admin' || $user['role'] === 'zarząd');
            
            if ($enforce_2fa && $is_admin_or_zarzad) {
                // Check if device is trusted
                $trust_token = $_COOKIE['2fa_trust'] ?? '';
                $is_trusted = false;
                if (!empty($trust_token)) {
                    $stmt_trust = $db->prepare("SELECT id FROM user_2fa_trust WHERE user_id = ? AND token = ? AND expires_at > datetime('now')");
                    $stmt_trust->execute([$user['id'], $trust_token]);
                    if ($stmt_trust->fetch()) {
                        $is_trusted = true;
                    }
                }

                if (!$is_trusted) {
                    $_SESSION['pending_2fa_user_id'] = $user['id'];
                    header('Location: 2fa.php');
                    exit;
                }
            }

            // Standard login or 2FA bypassed
            session_regenerate_id(true); // Prevent session fixation
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // New token after ID change
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['display_name'] = $user['display_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['user_group'] = $user['user_group'];
            $_SESSION['2fa_verified'] = $is_admin_or_zarzad ? 1 : 0; // If not enforced or trusted, consider verified
            $_SESSION['login_time'] = time();
            
            log_activity($db, $user['id'], 'LOGIN_SUCCESS', 'Zalogowano poprawnie.');

            header('Location: index.php');
            exit;

        } else {
            $error = 'Nieprawidłowy email lub hasło.';
            log_activity($db, 0, 'LOGIN_FAILED', "Próba logowania na email: $email");
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'contact_admin') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($csrf_token)) {
        $contact_name = $_POST['contact_name'] ?? 'Nieznany';
        $contact_message = $_POST['contact_message'] ?? '';
        $contact_email = $_POST['contact_email'] ?? 'Nie podano';
        $contact_phone = $_POST['contact_phone'] ?? 'Nie podano';

        if (!empty($contact_message)) {
            $msg = "👤 <b>Od:</b> " . tg_escape($contact_name) . "\n";
            $msg .= "📧 <b>Email:</b> " . tg_escape($contact_email) . "\n";
            $msg .= "📞 <b>Tel:</b> " . tg_escape($contact_phone) . "\n\n";
            $msg .= "💬 <b>Wiadomość:</b>\n" . tg_escape($contact_message);
            
            send_admin_notification($db, "Nowa prośba o kontakt (Logowanie)", $msg, 'warning');
            $success = 'Wysłano! Administrator skontaktuje się z Tobą.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pl" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logowanie - Sivis Drive</title>
    <script src="assets/js/tailwind.min.js"></script>
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
    <script src="assets/js/lucide.min.js"></script>
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

            <?php if (isset($success)): ?>
                <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-xl relative mb-6 text-sm">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['installed'])): ?>
                <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-xl relative mb-6 text-sm flex items-center">
                    <i data-lucide="check-circle" class="w-4 h-4 mr-2"></i> Instalacja zakończona pomyślnie! Możesz się zalogować.
                </div>
            <?php endif; ?>

            <form method="post" action="" class="space-y-5">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <div>
                    <label class="block text-slate-400 text-sm font-medium mb-2" for="email">Adres e-mail</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                            <i data-lucide="mail" class="w-5 h-5 text-slate-500"></i>
                        </div>
                        <input class="w-full bg-slate-900/50 border border-slate-700 rounded-xl py-3 pl-11 pr-4 text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition-all placeholder-slate-600" id="email" type="email" name="email" required placeholder="jan@firma.pl" autocomplete="off">
                    </div>
                </div>
                <div>
                    <label class="block text-slate-400 text-sm font-medium mb-2" for="password">Hasło</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                            <i data-lucide="lock" class="w-5 h-5 text-slate-500"></i>
                        </div>
                        <input class="w-full bg-slate-900/50 border border-slate-700 rounded-xl py-3 pl-11 pr-4 text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition-all placeholder-slate-600" id="password" type="password" name="password" required placeholder="••••••••" autocomplete="current-password">
                    </div>
                </div>
                <div class="pt-2">
                    <button class="bg-blue-600 hover:bg-blue-500 text-white font-medium py-3 rounded-xl w-full flex items-center justify-center transition-all duration-200 shadow-lg shadow-blue-500/20 hover:shadow-blue-500/40" type="submit">
                        Zaloguj się
                    </button>
                </div>
                <div class="pt-2 text-center">
                    <button type="button" onclick="showContactModal()" class="text-blue-400 hover:text-blue-300 text-sm font-medium transition-colors">
                        Zapomniałeś hasła? Problem z zalogowaniem?
                    </button>
                </div>
            </form>
        </div>
        
        <footer class="max-w-7xl mx-auto py-10 px-4 text-center">
            <p class="text-slate-600 text-xs flex flex-col sm:flex-row items-center justify-center gap-2 sm:gap-4 opacity-60 hover:opacity-100 transition-all duration-300">
                <span class="flex items-center gap-1.5 font-medium">
                    Made with <i data-lucide="heart" class="w-3 h-3 text-red-500 fill-red-500 animate-pulse"></i> by 
                    <a href="https://wowk.digital" target="_blank" class="font-black text-slate-500 hover:text-blue-400 transition-all tracking-tight">Wowk Digital</a>
                </span>
                <span class="hidden sm:inline-block w-1 h-1 bg-slate-700 rounded-full"></span>
                <span class="font-bold text-[9px] uppercase tracking-[0.2em] text-slate-600">&copy; <?= date('Y') ?> Sivis Drive</span>
            </p>
        </footer>
    </div>
    <!-- Contact Modal -->
    <div id="contactModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-slate-800 w-full max-w-lg rounded-3xl shadow-2xl border border-slate-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-700/60 bg-slate-800/50 flex items-center justify-between">
                <div class="flex items-center gap-2 text-blue-400">
                    <i data-lucide="help-circle" class="w-6 h-6"></i>
                    <h3 class="text-xl font-bold text-white">Pomoc techniczna</h3>
                </div>
                <button onclick="hideContactModal()" class="text-slate-400 hover:text-white transition-colors">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            
            <div class="p-6">
                <!-- Info Section -->
                <div class="mb-8 flex flex-col items-center text-center">
                    <div class="w-20 h-20 rounded-full bg-slate-700/50 flex items-center justify-center mb-4 border border-slate-600">
                        <i data-lucide="user" class="w-10 h-10 text-slate-300"></i>
                    </div>
                    <h2 class="text-xl font-bold text-white">Krzysztof Wowk</h2>
                    <p class="text-blue-400 font-medium mb-4">Główny Administrator</p>
                    
                    <div class="flex flex-col gap-3 w-full max-w-xs mx-auto text-sm">
                        <a href="mailto:wowk.digital@gmail.com" class="flex items-center gap-3 px-4 py-2 bg-slate-900/50 border border-slate-700 rounded-xl hover:border-blue-500/50 transition-all group">
                            <i data-lucide="mail" class="w-5 h-5 text-slate-500 group-hover:text-blue-400"></i>
                            <span class="text-slate-300">wowk.digital@gmail.com</span>
                        </a>
                        <a href="tel:+48664433505" class="flex items-center gap-3 px-4 py-2 bg-slate-900/50 border border-slate-700 rounded-xl hover:border-blue-500/50 transition-all group">
                            <i data-lucide="phone" class="w-5 h-5 text-slate-500 group-hover:text-blue-400"></i>
                            <span class="text-slate-300">+48 664 433 505</span>
                        </a>
                    </div>
                </div>

                <!-- Contact Form Section -->
                <?php if (get_setting($db, 'enable_contact_form', '0') == '1'): ?>
                <div class="border-t border-slate-700/50 pt-8 mt-2">
                    <h4 class="text-sm font-bold text-slate-300 uppercase tracking-widest mb-4">Wyślij szybkie powiadomienie</h4>
                    <form method="post" action="" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="action" value="contact_admin">
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-1.5 ml-1">Twoje Imię</label>
                                <input name="contact_name" type="text" placeholder="Jan Kowalski" class="w-full bg-slate-900/80 border border-slate-700 rounded-xl py-2.5 px-4 text-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/50 transition-all" required>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-1.5 ml-1">Twój Email</label>
                                <input name="contact_email" type="email" placeholder="jan@przyklad.pl" class="w-full bg-slate-900/80 border border-slate-700 rounded-xl py-2.5 px-4 text-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/50 transition-all" required>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-1.5 ml-1">Numer Telefonu (Opcjonalnie)</label>
                            <input name="contact_phone" type="tel" placeholder="+48 000 000 000" class="w-full bg-slate-900/80 border border-slate-700 rounded-xl py-2.5 px-4 text-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/50 transition-all">
                        </div>
                        
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-1.5 ml-1">Wiadomość / Problem</label>
                            <textarea name="contact_message" rows="3" placeholder="Opisz w czym problem..." class="w-full bg-slate-900/80 border border-slate-700 rounded-xl py-2.5 px-4 text-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/50" required></textarea>
                        </div>
                        
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 rounded-xl transition-all shadow-lg shadow-blue-500/20 active:scale-95 flex items-center justify-center gap-2">
                            <i data-lucide="send" class="w-4 h-4"></i> Wyślij powiadomienie
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();
        
        function showContactModal() {
            document.getElementById('contactModal').classList.remove('hidden');
            document.getElementById('contactModal').classList.add('flex');
        }
        
        function hideContactModal() {
            document.getElementById('contactModal').classList.remove('flex');
            document.getElementById('contactModal').classList.add('hidden');
        }

        // Close on escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') hideContactModal();
        });

        // Close on outside click
        document.getElementById('contactModal').addEventListener('click', (e) => {
            if (e.target.id === 'contactModal') hideContactModal();
        });
    </script>
</body>
</html>
