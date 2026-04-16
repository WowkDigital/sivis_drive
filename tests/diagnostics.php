<?php
/**
 * Sivis Drive - Diagnostic & Integrity Tests
 * Checks for local assets, .htaccess security, and environment health.
 */

if (!defined('ROOT_DIR')) define('ROOT_DIR', dirname(__DIR__));
require_once ROOT_DIR . '/core/functions.php';

$results = [];

if (!function_exists('add_diagnostic_test')) {
    function add_diagnostic_test($name, $callback) {
        global $results;
        try {
            $start = microtime(true);
            $res = $callback();
            $duration = round((microtime(true) - $start) * 1000, 2);
            
            if ($res === true) {
                $results[] = ['name' => $name, 'status' => 'PASS', 'duration' => $duration];
            } else {
                $results[] = ['name' => $name, 'status' => 'FAIL', 'duration' => $duration, 'message' => is_string($res) ? $res : 'Zwrócono false'];
            }
        } catch (Exception $e) {
            $results[] = ['name' => $name, 'status' => 'ERROR', 'duration' => 0, 'message' => $e->getMessage()];
        }
    }
}

// 1. Local Assets Check
add_diagnostic_test("Zasoby lokalne: Lucide JS", function() {
    $path = ROOT_DIR . '/assets/js/lucide.min.js';
    if (!file_exists($path)) return "Brak pliku lucide.min.js w assets/js/. Przeglądarka może blokować wersję CDN.";
    if (filesize($path) < 1000) return "Plik lucide.min.js wydaje się uszkodzony (zbyt mały rozmiar).";
    return true;
});

add_diagnostic_test("Zasoby lokalne: Tailwind JS", function() {
    $path = ROOT_DIR . '/assets/js/tailwind.min.js';
    if (!file_exists($path)) return "Brak pliku tailwind.min.js w assets/js/.";
    return true;
});

// 2. .htaccess Security Checks (Simulating external access)
add_diagnostic_test("Bezpieczeństwo: Blokada bezpośredniego dostępu do /core/", function() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $uri = str_replace('api/ajax.php', '', $_SERVER['SCRIPT_NAME']);
    $url = $protocol . "://" . $host . $uri . "core/db.php";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 200) return "KRYTYCZNE: Plik core/db.php jest dostępny publicznie! Sprawdź .htaccess.";
    if ($code === 403 || $code === 404) return true;
    return "Ostrzeżenie: Próba dostępu zwróciła kod $code (oczekiwano 403).";
});

add_diagnostic_test("Bezpieczeństwo: Dostępność punktów wejściowych API", function() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $uri = str_replace('api/ajax.php', '', $_SERVER['SCRIPT_NAME']);
    $url = $protocol . "://" . $host . $uri . "api/ajax.php";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // ajax.php requires session, so it might return 302 or 403 depending on session, 
    // but it should NOT be 403 FROM HTACCESS RedirectMatch if session is valid.
    // However, since we are doing a server-to-server call without cookies, it will probably fail session check.
    // We just want to make sure it's NOT blocked by RedirectMatch.
    // A RedirectMatch 403 will return a 403 even before PHP runs.
    if ($code === 403) {
        // We need to check if it's PHP's 403 or .htaccess's 403.
        // PHP's 403 in this app often comes with a body.
        return "Informacja: API zwróciło 403. Upewnij się, że .htaccess pozwala na wywołania ajax.php.";
    }
    return true; 
});

// 3. Environment & Session
add_diagnostic_test("Środowisko: Aktywna sesja i CSRF", function() {
    if (session_status() !== PHP_SESSION_ACTIVE) return "Sesja PHP nie jest aktywna.";
    if (!isset($_SESSION['csrf_token'])) return "Brak wygenerowanego tokenu CSRF w sesji. Może to powodować błędy przy formularzach.";
    return true;
});

add_diagnostic_test("Środowisko: Uprawnienia zapisu", function() {
    $dirs = [
        ROOT_DIR . '/data',
        ROOT_DIR . '/uploads',
        ROOT_DIR . '/data/backups'
    ];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        if (!is_writable($dir)) return "Brak uprawnień do zapisu w: " . basename($dir);
    }
    return true;
});

// Output for integrated runner
if (defined('TEST_RUNNER')) {
    // Return the results to the runner
    return $results;
} else {
    header('Content-Type: application/json');
    echo json_encode($results);
}
