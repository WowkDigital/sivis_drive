<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}
if (!defined('ROOT_DIR')) define('ROOT_DIR', dirname(__DIR__));
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// Global error & exception handlers to catch application problems
set_exception_handler(function($e) use ($db) {
    $msg = "Wyjątek: " . $e->getMessage() . " w " . $e->getFile() . ":" . $e->getLine();
    log_activity($db, $_SESSION['user_id'] ?? 0, 'SYSTEM_ERROR', $msg);
    if (ini_get('display_errors')) {
        echo "Błąd Systemowy: " . $e->getMessage();
    } else {
        die("Wystąpił błąd systemowy. Administrator został powiadomiony.");
    }
});

set_error_handler(function($errno, $errstr, $errfile, $errline) use ($db) {
    if (!(error_reporting() & $errno)) return false;
    $msg = "Error [$errno]: $errstr w $errfile:$errline";
    log_activity($db, $_SESSION['user_id'] ?? 0, 'SYSTEM_ERROR', $msg);
    return false; // Let standard PHP error handler continue
});

function is_logged_in() {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    global $db;
    try {
        $stmt = $db->prepare('SELECT id, role, totp_enabled FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            $_SESSION = [];
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }
            return false;
        }

        // Check 2FA enforcement
        $enforce_2fa = get_setting($db, 'enforce_2fa_admin', '0') === '1';
        $is_admin_or_zarzad = ($user['role'] === 'admin' || $user['role'] === 'zarząd');
        
        if ($enforce_2fa && $is_admin_or_zarzad) {
            if (!isset($_SESSION['2fa_verified']) || $_SESSION['2fa_verified'] !== 1) {
                // If they are logged in but not 2FA verified, and it's enforced, we must end session
                log_activity($db, $_SESSION['user_id'], 'AUTH_ERROR', 'Próba pominięcia wymuszonego 2FA.');
                $_SESSION = [];
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_destroy();
                }
                return false;
            }
        }

    } catch (Exception $e) {
        return false;
    }

    return true;

}

function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Auto-generate token if missing to avoid timing issues on first-time actions
if (isset($_SESSION['user_id']) && empty($_SESSION['csrf_token'])) {
    generate_csrf_token();
}

function verify_csrf_token($token) {
    $valid = isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    if (!$valid && $_SERVER['REQUEST_METHOD'] === 'POST') {
        global $db;
        $user_id = $_SESSION['user_id'] ?? 0;
        $reason = !isset($_SESSION['csrf_token']) ? 'Brak tokenu w sesji' : ($token === '' ? 'Brak tokenu w żądaniu' : 'Niezgodność tokenów');
        $url = $_SERVER['REQUEST_URI'] ?? 'unknown';
        log_activity($db, $user_id, 'CSRF_ERROR', "Błąd weryfikacji CSRF: $reason. URL: $url");
    }
    return $valid;
}

function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function is_zarzad() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'zarząd';
}

function can_manage_files() {
    return is_admin() || is_zarzad();
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function require_ajax_login() {
    if (!is_logged_in()) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Sesja wygasła. Proszę zalogować się ponownie.']);
        exit;
    }
}

function require_admin() {
    require_login();
    if (!is_admin()) {
        die('Brak dostępu. <a href="index.php">Powrót</a>');
    }
}

function require_manager() {
    require_login();
    if (!can_manage_files()) {
        die('Brak uprawnień do zarządzania plikami.');
    }
}

function get_user_group() {
    return isset($_SESSION['user_group']) ? $_SESSION['user_group'] : '';
}

function generate_random_password($length = 16) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_=+[]{}|;:,.<>?';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}
/**
 * Run periodic tasks (Daily Report etc)
 */
run_periodic_tasks($db);
?>
