<?php
require_once 'core/db.php';
require_once 'core/functions.php';
require_once 'core/auth.php';
require_once 'core/totp.php';

if (!isset($_SESSION['pending_2fa_user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['pending_2fa_user_id'];
$stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    unset($_SESSION['pending_2fa_user_id']);
    header('Location: login.php');
    exit;
}

$error = '';
$setup_mode = ($user['totp_enabled'] == 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = $_POST['code'] ?? '';
    
    // In setup mode, we generate the secret if not exists
    $secret = $user['totp_secret'];
    if (empty($secret)) {
        // This shouldn't happen if we show the QR, but let's be safe
        header('Location: 2fa.php');
        exit;
    }

    if (TOTP::verifyCode($secret, $code)) {
        // Success!
        if ($setup_mode) {
            $db->prepare('UPDATE users SET totp_enabled = 1 WHERE id = ?')->execute([$user_id]);
            log_activity($db, $user_id, 'USER_SETUP_2FA', "Użytkownik skonfigurował 2FA");
        }
        
        // Handle "Remember for 30 days"
        if (isset($_POST['remember_device'])) {
            $trust_token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + (86400 * 30));
            $db->prepare("INSERT INTO user_2fa_trust (user_id, token, expires_at) VALUES (?, ?, ?)")->execute([$user_id, $trust_token, $expires]);
            setcookie('2fa_trust', $trust_token, time() + (86400 * 30), "/", "", false, true); // Secure if HTTPs but we use default for local
        }
        
        // Finalize login
        session_regenerate_id(true); // Prevent session fixation
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // New token after ID change
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['display_name'] = $user['display_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['user_group'] = $user['user_group'];
        $_SESSION['2fa_verified'] = 1;
        $_SESSION['login_time'] = time();
        
        unset($_SESSION['pending_2fa_user_id']);
        
        log_activity($db, $user_id, 'USER_LOGIN_2FA', "Zalogowano z 2FA");
        
        header('Location: index.php');
        exit;
    } else {

        $error = 'Nieprawidłowy kod. Spróbuj ponownie.';
        log_activity($db, $user_id, 'USER_LOGIN_2FA_FAIL', "Nieudana próba logowania 2FA");
    }
}

// Prepare setup data if needed
$qr_url = '';
$secret_key = '';
if ($setup_mode) {
    if (empty($user['totp_secret'])) {
        $secret_key = TOTP::generateSecret();
        $db->prepare('UPDATE users SET totp_secret = ? WHERE id = ?')->execute([$secret_key, $user_id]);
    } else {
        $secret_key = $user['totp_secret'];
    }
    
    $otpauth_url = TOTP::getQrCodeUrl($user['email'], $secret_key);
    $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($otpauth_url);
}

?>
<!DOCTYPE html>
<html lang="pl" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weryfikacja 2FA - Sivis Drive</title>
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
    <div class="w-full max-w-md text-center">
        <!-- Logo Header -->
        <div class="flex flex-col items-center justify-center mb-10 text-slate-100">
            <div class="flex items-center space-x-3 text-blue-400 mb-2">
                <div class="p-3 bg-blue-500/10 rounded-2xl border border-blue-500/20">
                    <i data-lucide="shield-check" class="w-10 h-10"></i>
                </div>
            </div>
            <h1 class="text-3xl font-extrabold tracking-tight">Weryfikacja 2FA</h1>
            <p class="text-slate-400 mt-2 text-sm">Dwuetapowe uwierzytelnianie</p>
        </div>

        <!-- 2FA Card -->
        <div class="bg-slate-800 p-8 rounded-3xl shadow-2xl border border-slate-700/60 backdrop-blur-xl">
            <?php if ($error): ?>
                <div class="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-xl relative mb-6 text-sm">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($setup_mode): ?>
                <div class="mb-8">
                    <h2 class="text-xl font-bold text-slate-100 mb-4">Skonfiguruj 2FA</h2>
                    <p class="text-slate-400 text-sm mb-6">Zeskanuj kod QR w aplikacji Google Authenticator lub Microsoft Authenticator.</p>
                    
                    <div class="bg-white p-4 rounded-2xl inline-block mb-6 shadow-lg">
                        <img src="<?= $qr_url ?>" alt="QR Code" class="w-48 h-48">
                    </div>
                    
                    <div class="text-left mb-6">
                        <label class="block text-slate-500 text-xs font-bold uppercase tracking-wider mb-2 ml-1">Klucz ręczny</label>
                        <div class="flex items-center bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3">
                            <span class="font-mono text-blue-400 tracking-widest text-lg grow"><?= $secret_key ?></span>
                            <button onclick="copySecret()" class="text-slate-400 hover:text-white transition-colors p-1" title="Kopiuj klucz">
                                <i data-lucide="copy" class="w-5 h-5"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="mb-8">
                    <h2 class="text-xl font-bold text-slate-100 mb-4">Podaj kod</h2>
                    <p class="text-slate-400 text-sm mb-2">Wprowadź 6-cyfrowy kod wygenerowany przez aplikację.</p>
                </div>
            <?php endif; ?>

            <form method="post" action="" class="space-y-6">
                <div>
                    <input class="w-full bg-slate-900/50 border border-slate-700 rounded-xl py-4 text-center text-3xl font-bold tracking-[0.5em] text-white focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition-all placeholder-slate-700" 
                           id="code" type="text" name="code" required 
                           placeholder="000000" 
                           pattern="[0-9]{6}" 
                           maxlength="6"
                           autocomplete="one-time-code"
                           autofocus>
                </div>

                <div class="flex items-center justify-center space-x-3 py-2">
                    <label class="flex items-center cursor-pointer group">
                        <div class="relative">
                            <input type="checkbox" name="remember_device" value="1" class="sr-only peer" checked>
                            <div class="w-5 h-5 bg-slate-900 border border-slate-700 rounded-md transition-all peer-checked:bg-blue-600 peer-checked:border-blue-500 group-hover:border-slate-500 flex items-center justify-center">
                                <i data-lucide="check" class="w-3.5 h-3.5 text-white opacity-0 peer-checked:opacity-100 transition-opacity"></i>
                            </div>
                        </div>
                        <span class="ml-3 text-sm text-slate-400 group-hover:text-slate-300 transition-colors font-medium">Zapamiętaj to urządzenie przez 30 dni</span>
                    </label>
                </div>
                
                <button class="bg-blue-600 hover:bg-blue-500 text-white font-bold py-4 rounded-xl w-full flex items-center justify-center transition-all duration-200 shadow-lg shadow-blue-500/20 active:scale-95" type="submit">

                    <?= $setup_mode ? 'Zweryfikuj i zapisz' : 'Zaloguj się' ?>
                </button>
            </form>
            
            <div class="mt-8 pt-6 border-t border-slate-700/50">
                <a href="logout.php" class="text-slate-500 hover:text-slate-300 text-sm transition-colors flex items-center justify-center">
                    <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i> Powrót do logowania
                </a>
            </div>
        </div>
    </div>
    
    <script>
        lucide.createIcons();
        
        function copySecret() {
            const secret = '<?= $secret_key ?>';
            navigator.clipboard.writeText(secret).then(() => {
                const btn = document.querySelector('button[onclick="copySecret()"]');
                const originalIcon = btn.innerHTML;
                btn.innerHTML = '<i data-lucide="check" class="w-5 h-5 text-emerald-400"></i>';
                lucide.createIcons();
                setTimeout(() => {
                    btn.innerHTML = originalIcon;
                    lucide.createIcons();
                }, 2000);
            });
        }
        
        // Auto-submit when 6 digits are entered
        document.getElementById('code').addEventListener('input', function(e) {
            if (this.value.length === 6 && /^\d+$/.test(this.value)) {
                this.form.submit();
            }
        });
    </script>
</body>
</html>
