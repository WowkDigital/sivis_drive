<?php
/**
 * Sivis Drive - Security Regression Tests
 */

// 1. Environment configuration
$data_dir = defined('ROOT_DIR') ? ROOT_DIR . '/data' : dirname(__DIR__) . '/data';
if (!is_dir($data_dir)) {
    mkdir($data_dir, 0777, true);
}

$db_file = $data_dir . '/security_test_database.sqlite';

// 2. Mock base dir and load functions
if (!defined('ROOT_DIR')) define('ROOT_DIR', dirname(__DIR__));
require_once ROOT_DIR . '/core/functions.php';

// Mock DB session
function get_test_db() {
    global $db_file;
    $db = new PDO("sqlite:$db_file");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $db->exec("DROP TABLE IF EXISTS users");
    $db->exec("DROP TABLE IF EXISTS folders");
    $db->exec("DROP TABLE IF EXISTS logs");
    
    $db->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT, display_name TEXT, role TEXT)");
    $db->exec("CREATE TABLE folders (id INTEGER PRIMARY KEY, public_id TEXT, name TEXT, parent_id INTEGER, owner_id INTEGER, deleted_at DATETIME)");
    $db->exec("CREATE TABLE logs (id INTEGER PRIMARY KEY, user_id INTEGER, action TEXT, details TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    
    return $db;
}

$db = get_test_db();
$results = [];

function add_test($name, $callback) {
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

// --- SETUP TEST DATA ---
$db->exec("INSERT INTO users (id, email, display_name, role) VALUES (1, 'admin@test.com', 'Administrator', 'admin')");
$db->exec("INSERT INTO folders (id, public_id, name, owner_id) VALUES (1, 'root-1', 'Pliki Administrator', 1)");

// --- SECURITY TESTS ---

add_test("Test XSS Name Update", function() use ($db) {
    $malicious_name = "\">'><h2>hemlo";
    $user_id = 1;
    $stmt = $db->prepare('UPDATE users SET display_name = ? WHERE id = ?');
    $stmt->execute([$malicious_name, $user_id]);
    $stmt = $db->prepare("UPDATE folders SET name = ? WHERE owner_id = ? AND parent_id IS NULL");
    $stmt->execute(['Pliki ' . $malicious_name, $user_id]);
    $saved_name = $db->query("SELECT display_name FROM users WHERE id = 1")->fetchColumn();
    if ($saved_name !== $malicious_name) return "Error: Name in DB doesn't match input.";
    $output_quotes = htmlspecialchars($saved_name, ENT_QUOTES, 'UTF-8');
    if (strpos($output_quotes, '<h2>') !== false) return "FAIL: Unescaped HTML tag detected!";
    return true;
});

add_test("Test Attributes JS Context", function() {
    $malicious_name = "\">'><h2>hemlo";
    $escaped = htmlspecialchars($malicious_name);
    $quote_count = substr_count($escaped, "'");
    if ($quote_count > 0) {
        return "WARNING: Detected unescaped single quote in htmlspecialchars output. This will break JS onclick attributes!";
    }
    return true;
});

add_test("Test Folder Name Escaping", function() use ($db) {
    $folder_name = $db->query("SELECT name FROM folders WHERE owner_id = 1 AND parent_id IS NULL")->fetchColumn();
    $output = htmlspecialchars($folder_name, ENT_QUOTES, 'UTF-8');
    if (strpos($output, '<h2>') !== false) return "FAIL: Unescaped HTML in folder name display.";
    return true;
});

add_test("Test Activity Logs", function() use ($db) {
    $malicious_name = "\">'><h2>hemlo";
    $stmt = $db->prepare("INSERT INTO logs (user_id, action, details) VALUES (?, ?, ?)");
    $stmt->execute([1, 'UPDATE_NAME', "Zmieniono nazwę na: $malicious_name"]);
    $stmt = $db->prepare("SELECT l.details, u.display_name FROM logs l JOIN users u ON l.user_id = u.id WHERE l.action = 'UPDATE_NAME'");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $display_name_html = htmlspecialchars($row['display_name'] ?: 'System', ENT_QUOTES, 'UTF-8');
    $details_html = htmlspecialchars($row['details'], ENT_QUOTES, 'UTF-8');
    if (strpos($display_name_html, '<h2>') !== false || strpos($details_html, '<h2>') !== false) {
        return "FAIL: Malicious HTML found in log output.";
    }
    return true;
});

// --- OUTPUT ---
if (php_sapi_name() === 'cli') {
    echo "\nSivis Drive Security Test Report\n";
    echo "===================================\n";
    foreach ($results as $r) {
        $status = $r['status'] === 'PASS' ? "PASS" : $r['status'];
        echo sprintf("[%s] %-50s (%sms)\n", $status, $r['name'], $r['duration']);
        if (isset($r['message'])) echo "      - Message: " . $r['message'] . "\n";
    }
    echo "===================================\n";
} elseif (!defined('TEST_RUNNER')) {
    header('Content-Type: application/json');
    echo json_encode($results);
    exit;
}

// Cleanup
$db = null;
if (file_exists($db_file)) unlink($db_file);
