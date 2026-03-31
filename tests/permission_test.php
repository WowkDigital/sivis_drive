<?php
/**
 * Sivis Drive - Automated System Tests
 */

// 1. Environment configuration
$db_file = __DIR__ . '/test_database.sqlite';
$upload_dir = __DIR__ . '/test_uploads';

if (file_exists($db_file)) unlink($db_file);
if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

// 2. Mock base dir and load functions
if (!defined('ROOT_DIR')) define('ROOT_DIR', dirname(__DIR__));
require_once ROOT_DIR . '/core/functions.php';

function get_test_db() {
    global $db_file;
    $db = new PDO("sqlite:$db_file");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create tables (minimal for testing)
    $db->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, public_id TEXT, email TEXT, role TEXT, user_group TEXT, display_name TEXT)");
    $db->exec("CREATE TABLE folders (id INTEGER PRIMARY KEY, public_id TEXT, name TEXT, parent_id INTEGER, owner_id INTEGER, access_groups TEXT, deleted_at DATETIME)");
    $db->exec("CREATE TABLE files (id INTEGER PRIMARY KEY, public_id TEXT, folder_id INTEGER, name TEXT, original_name TEXT, size INTEGER, uploaded_by INTEGER, deleted_at DATETIME)");
    $db->exec("CREATE TABLE settings (setting_key TEXT PRIMARY KEY, setting_value TEXT)");
    $db->exec("CREATE TABLE logs (id INTEGER PRIMARY KEY, user_id INTEGER, action TEXT, details TEXT, created_at DATETIME)");
    
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

// --- PREPARE DATA ---
$db->exec("INSERT INTO users (id, email, role, user_group) VALUES (1, 'admin@test.com', 'admin', 'Zarząd')");
$db->exec("INSERT INTO users (id, email, role, user_group) VALUES (2, 'pracownik@test.com', 'pracownik', 'Pracownicy')");
$db->exec("INSERT INTO users (id, email, role, user_group) VALUES (3, 'zarzad@test.com', 'zarząd', 'Zarząd')");

// --- CORE TESTS ---

add_test("ID Translation: NanoID to Internal ID", function() use ($db) {
    $db->exec("INSERT INTO folders (id, public_id, name) VALUES (10, 'nano-123', 'Folder Testowy')");
    return resolve_folder_id($db, 'nano-123') === 10;
});

add_test("Permissions: Own Private Tree Access", function() use ($db) {
    $db->prepare("INSERT INTO folders (id, owner_id, name) VALUES (20, 2, 'Folder Pracownika')")->execute();
    return is_private_tree($db, 20, 2) === true;
});

add_test("Permissions: Zarząd can access Employee folder", function() use ($db) {
    // Note: can_user_access_folder requires owner_role info joined
    return can_user_access_folder($db, 20, 3, 'zarząd', 'Zarząd') === true;
});

add_test("Permissions: Employee cannot edit Shared Root", function() use ($db) {
    $db->prepare("INSERT INTO folders (id, owner_id, name) VALUES (30, NULL, 'Udostępniony')")->execute();
    return can_user_edit_folder($db, 30, 2, 'pracownik', 'Pracownicy') === false;
});

add_test("Usage: Private storage calculation", function() use ($db) {
    $db->prepare("INSERT INTO folders (id, owner_id, name) VALUES (40, 2, 'Limit Folder')")->execute();
    $db->prepare("INSERT INTO files (folder_id, size) VALUES (40, 1024)")->execute();
    $usage = get_private_usage($db, 2);
    return $usage['count'] === 1 && $usage['size'] === 1024;
});

add_test("Settings: Retrieve default and custom values", function() use ($db) {
    set_setting($db, 'test_key', 'test_val');
    $val = get_setting($db, 'test_key');
    $def = get_setting($db, 'non_existent', 'default');
    return $val === 'test_val' && $def === 'default';
});

add_test("Trash: Soft-delete folder logic", function() use ($db) {
    $db->prepare("INSERT INTO folders (id, name, owner_id) VALUES (50, 'Do usunięcia', 2)")->execute();
    soft_delete_folder_recursive($db, 50);
    $stmt = $db->prepare("SELECT deleted_at FROM folders WHERE id = 50");
    $stmt->execute();
    return $stmt->fetchColumn() !== null;
});

add_test("GC: Garbage collector cleanup", function() use ($db, $upload_dir) {
    // Mock file on disk
    file_put_contents($upload_dir . '/old_file.txt', 'old data');
    $old_date = date('Y-m-d H:i:s', strtotime('-40 days'));
    $db->prepare("INSERT INTO files (name, original_name, size, deleted_at) VALUES ('old_file.txt', 'old_file.txt', 123, ?)")->execute([$old_date]);
    
    // Run GC (requires absolute upload_dir)
    cleanup_garbage_collector($db, $upload_dir);
    
    return !file_exists($upload_dir . '/old_file.txt');
});

add_test("Security: CSRF token validation", function() {
    $_SESSION['csrf_token'] = 'test-token-123';
    return verify_csrf_token('test-token-123') === true && verify_csrf_token('wrong') === false;
});

add_test("Database: CRUD on logs table", function() use ($db) {
    log_activity($db, 1, 'TEST_ACTION', 'Testing logs');
    $stmt = $db->query("SELECT COUNT(*) FROM logs WHERE action = 'TEST_ACTION'");
    return (int)$stmt->fetchColumn() === 1;
});

// --- OUTPUT ---

if (php_sapi_name() === 'cli') {
    echo "\nSivis Drive System Test Report\n";
    echo "===================================\n";
    foreach ($results as $r) {
        $status = $r['status'] === 'PASS' ? "\033[32mPASS\033[0m" : "\033[31m" . $r['status'] . "\033[0m";
        echo sprintf("[%s] %-40s (%sms)\n", $status, $r['name'], $r['duration']);
        if (isset($r['message'])) echo "      - Message: " . $r['message'] . "\n";
    }
    echo "===================================\n";
} else {
    // Web request - return JSON
    header('Content-Type: application/json');
    echo json_encode($results);
}

// Cleanup
if (file_exists($db_file)) unlink($db_file);
$files = glob($upload_dir . '/*');
foreach($files as $file) if(is_file($file)) unlink($file);
rmdir($upload_dir);
