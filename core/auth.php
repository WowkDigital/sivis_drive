<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

function is_logged_in() {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    global $db;
    try {
        $stmt = $db->prepare('SELECT id FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            $_SESSION = [];
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }
            return false;
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

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
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
?>
