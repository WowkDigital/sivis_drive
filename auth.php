<?php
session_start();
require_once 'db.php';

function is_logged_in() {
    return isset($_SESSION['user_id']);
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
?>
