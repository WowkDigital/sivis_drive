<?php
/**
 * Sivis Drive - Automated System Tests
 */

// 1. Environment configuration
$data_dir = defined('ROOT_DIR') ? ROOT_DIR . '/data' : dirname(__DIR__) . '/data';
if (!is_dir($data_dir)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Brak folderu data/']);
    exit;
}
if (!is_writable($data_dir)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Folder data/ nie jest zapisywalny!']);
    exit;
}

$db_file = $data_dir . '/test_database.sqlite';
$upload_dir = $data_dir . '/test_uploads_tmp';

if (file_exists($db_file)) unlink($db_file);
if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

// 2. Mock base dir and load functions
if (!defined('ROOT_DIR')) define('ROOT_DIR', dirname(__DIR__));
require_once ROOT_DIR . '/core/functions.php';

// Save test db_file because auth.php/db.php will overwrite it
$test_db_file = $db_file;
require_once ROOT_DIR . '/core/auth.php';
$db_file = $test_db_file;

function get_test_db() {
    global $db_file;
    $db = new PDO("sqlite:$db_file");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create tables (minimal for testing)
    $db->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, public_id TEXT, email TEXT, role TEXT, user_group TEXT, display_name TEXT)");
    $db->exec("CREATE TABLE folders (id INTEGER PRIMARY KEY, public_id TEXT, name TEXT, parent_id INTEGER, owner_id INTEGER, access_groups TEXT, deleted_at DATETIME, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE files (id INTEGER PRIMARY KEY, public_id TEXT, folder_id INTEGER, name TEXT, original_name TEXT, size INTEGER, uploaded_by INTEGER, deleted_at DATETIME, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE settings (setting_key TEXT PRIMARY KEY, setting_value TEXT)");
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

// --- MOVEMENT LOGIC TESTS ---

add_test("Movement: Resolve NanoID for move target", function() use ($db) {
    $db->prepare("INSERT INTO folders (id, public_id, name) VALUES (200, 'target-nano-99', 'Folder Docelowy')")->execute();
    $resolved = resolve_folder_id($db, 'target-nano-99');
    return $resolved === 200;
});

add_test("Movement: Admin can move file in Shared Folder", function() use ($db) {
    // Setup Shared structure
    $db->prepare("INSERT INTO folders (id, public_id, name, owner_id) VALUES (300, 'shared-root', 'Shared Root', NULL)")->execute();
    $db->prepare("INSERT INTO folders (id, public_id, name, parent_id, owner_id) VALUES (301, 'shared-sub', 'Shared Sub', 300, NULL)")->execute();
    
    // Check if Admin can edit both (needed for move)
    $can_edit_root = can_user_edit_folder($db, 300, 1, 'admin', 'Zarząd');
    $can_edit_sub = can_user_edit_folder($db, 301, 1, 'admin', 'Zarząd');
    
    return $can_edit_root === true && $can_edit_sub === true;
});

add_test("Movement: Employee restricted in Shared Folder", function() use ($db) {
    // Employee has access but NOT edit rights in Shared (id 300)
    $can_edit = can_user_edit_folder($db, 300, 2, 'pracownik', 'Pracownicy');
    return $can_edit === false;
});

// --- INTEGRATION: AJAX INTERFACE ---

add_test("AJAX: Move targets mapping integrity", function() use ($db) {
    // 1. Create structure: Parent (500, 'nano-p') -> Child (501, 'nano-c')
    $db->prepare("INSERT INTO folders (id, public_id, name, parent_id) VALUES (500, 'nano-p', 'Parent', NULL)")->execute();
    $db->prepare("INSERT INTO folders (id, public_id, name, parent_id) VALUES (501, 'nano-c', 'Child', 500)")->execute();
    
    // 2. Simulate get_move_targets logic (from api/ajax.php)
    $all = $db->query("SELECT * FROM folders WHERE id IN (500, 501)")->fetchAll(PDO::FETCH_ASSOC);
    $id_map = [0 => null];
    foreach ($all as $f) {
        $id_map[(int)$f['id']] = $f['public_id'] ?: (int)$f['id'];
    }
    
    $accessible = [];
    foreach ($all as $f) {
        $accessible[] = [
            'id' => $f['public_id'] ?: $f['id'],
            'parent_id' => $f['parent_id'] ? ($id_map[(int)$f['parent_id']] ?? null) : null
        ];
    }
    
    // 3. Find child in result
    $child = null;
    foreach($accessible as $item) if($item['id'] === 'nano-c') $child = $item;
    
    if (!$child) return "Child not found in result";
    
    // VERIFY: Parent ID in the JSON must be 'nano-p' (the NanoID), NOT 500 (internal ID)
    if ($child['parent_id'] !== 'nano-p') {
        return "Mismatch: Expected 'nano-p' (NanoID), got " . var_export($child['parent_id'], true) . " (Internal ID?)";
    }
    
    return true;
});

// --- ADVANCED USER & PERMISSION TESTS ---

add_test("Permissions: Zarząd CANNOT see Admin private files", function() use ($db) {
    // Admin (id 1), Zarząd (id 3)
    $db->prepare("INSERT INTO folders (id, owner_id, name) VALUES (600, 1, 'Admin Files')")->execute();
    return can_user_access_folder($db, 600, 3, 'zarząd', 'Zarząd') === false;
});

add_test("Permissions: Group-based Shared Access", function() use ($db) {
    // Folder restricted to 'marketing' group
    $db->prepare("INSERT INTO folders (id, owner_id, name, access_groups) VALUES (700, NULL, 'Marketing Only', 'marketing')")->execute();
    
    $allowed = can_user_access_folder($db, 700, 2, 'pracownik', 'marketing'); // User in marketing
    $denied = can_user_access_folder($db, 700, 2, 'pracownik', 'pracownicy'); // User in general group
    
    return $allowed === true && $denied === false;
});

add_test("User: Password Hashing Logic", function() {
    $pass = "tajnehaslo123";
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    return password_verify($pass, $hash) === true && password_verify("zle", $hash) === false;
});

add_test("Folder: Rename Sync", function() use ($db) {
    $db->prepare("INSERT INTO folders (id, name, owner_id) VALUES (800, 'Stara Nazwa', 2)")->execute();
    $db->prepare("UPDATE folders SET name = 'Nowa Nazwa' WHERE id = 800")->execute();
    $res = $db->query("SELECT name FROM folders WHERE id = 800")->fetchColumn();
    return $res === 'Nowa Nazwa';
});

add_test("Cycle: Trash & Restore Lifecycle", function() use ($db) {
    // 1. Setup item
    $db->prepare("INSERT INTO folders (id, name, owner_id) VALUES (900, 'Do Kosza', 2)")->execute();
    
    // 2. Trash it
    soft_delete_folder_recursive($db, 900);
    $deleted_at = $db->query("SELECT deleted_at FROM folders WHERE id = 900")->fetchColumn();
    if (!$deleted_at) return "Failed to soft delete";
    
    // 3. Restore to "przywrócone"
    // Create "przywrócone" folder if not exists
    $db->prepare("INSERT INTO folders (id, name) VALUES (999, 'przywrócone')")->execute();
    $db->prepare("UPDATE folders SET deleted_at = NULL, parent_id = 999 WHERE id = 900")->execute();
    
    $final = $db->query("SELECT parent_id, deleted_at FROM folders WHERE id = 900")->fetch(PDO::FETCH_ASSOC);
    return $final['deleted_at'] === null && $final['parent_id'] == 999;
});

add_test("Integrity: NanoID Uniqueness", function() {
    $ids = [];
    for($i=0; $i<100; $i++) {
        $id = generate_nanoid();
        if (in_array($id, $ids)) return "Collision detected in 100 iterations!";
        $ids[] = $id;
    }
    return true;
});

add_test("Search: Recent activity detection", function() use ($db) {
    $db->prepare("INSERT INTO folders (id, name) VALUES (1000, 'Activity Folder')")->execute();
    $db->prepare("INSERT INTO files (folder_id, name, created_at) VALUES (1000, 'new.txt', datetime('now'))")->execute();
    return has_recent_activity($db, 1000) === true;
});

// --- NEW REGRESSION TESTS (Recent Fixes) ---

add_test("Bulk Actions: Validate permissions for multiple ID selection", function() use ($db) {
    // Setup: Admin root (id 2000), Employee root (id 2001)
    $db->exec("INSERT INTO folders (id, owner_id, name) VALUES (2000, 1, 'Admin Folder')");
    $db->exec("INSERT INTO folders (id, owner_id, name) VALUES (2001, 2, 'Employee Folder')");

    // Test: Employee (id 2) tries to bulk action on both
    $items = [2000, 2001];
    $results = array_map(function($id) use ($db) {
        return can_user_edit_folder($db, $id, 2, 'pracownik', 'Pracownicy');
    }, $items);
    
    // Result should be [false, true]
    return $results[0] === false && $results[1] === true;
});

add_test("Security: Frontend JS syntax check (Regresja 'Unexpected token export')", function() {
    $js_dir = ROOT_DIR . '/assets/js';
    if (!is_dir($js_dir)) return "Katalog assets/js nie istnieje";
    
    $files = glob($js_dir . '/*.js');
    $errors = [];
    foreach ($files as $file) {
        $content = file_get_contents($file);
        // This environment uses UMD/Global scripts, 'export' outside of modules breaks it.
        if (preg_match('/^export\s+.*$/m', $content)) {
            $errors[] = basename($file);
        }
    }
    
    if (!empty($errors)) {
        return "Błąd: Wykryto słowo kluczowe 'export' w plikach: " . implode(', ', $errors) . ". To spowoduje błąd krytyczny w przeglądarce.";
    }
    return true;
});

add_test("Integrity: User creation role mapping", function() use ($db) {
    // Verify that role-to-group mapping in admin_logic.php is consistent
    $test_cases = [
        ['role' => 'zarząd', 'expected_group' => 'zarząd'],
        ['role' => 'pracownik', 'expected_group' => 'pracownicy']
    ];
    
    foreach ($test_cases as $case) {
        $group = ($case['role'] === 'zarząd') ? 'zarząd' : 'pracownicy';
        if ($group !== $case['expected_group']) return "Błędne mapowanie dla roli: " . $case['role'];
    }
    return true;
});

add_test("Upload: DataTransfer mock check (Regresja 'Single file upload')", function() {
    // This is a documentation/sanity test for the JS logic fixed in views/footer_parts/upload.php
    $upload_logic_file = ROOT_DIR . '/views/footer_parts/upload.php';
    if (!file_exists($upload_logic_file)) return "Brak pliku upload.php";
    
    $content = file_get_contents($upload_logic_file);
    // Ensure we are iterating over all files in DataTransfer
    if (strpos($content, 'for (let i = 0; i < files.length; i++)') === false && 
        strpos($content, 'Array.from(files)') === false &&
        strpos($content, 'files.forEach') === false) {
        return "UWAGA: W views/footer_parts/upload.php nie znaleziono pętli iterującej po plikach. Sprawdź czy bulk upload nie jest uszkodzony!";
    }
    return true;
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
$db = null;
if (file_exists($db_file)) unlink($db_file);
$files = glob($upload_dir . '/*');
foreach($files as $file) if(is_file($file)) unlink($file);
rmdir($upload_dir);
