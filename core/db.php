<?php
$data_dir = dirname(__DIR__) . '/data';
if (!is_dir($data_dir)) {
    @mkdir($data_dir, 0777, true);
}
$db_file = $data_dir . '/database.sqlite';

// --- MAINTENANCE MODE ---
// Sprawdzamy czy nie jesteśmy w trakcie backupu lub czy użytkownik nie jest adminem
$is_login_page = strpos($_SERVER['PHP_SELF'], 'login.php') !== false;

if (file_exists($data_dir . '/maintenance.flag') && !isset($is_backup_script) && !$is_login_page) {
    // Check if session has admin role - if so, allow entry
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    
    if (!$is_admin) {
        require_once dirname(__DIR__) . '/views/maintenance.php';
        exit;
    }
}

$is_install_page = strpos($_SERVER['PHP_SELF'], 'install.php') !== false;
if (!file_exists($db_file) && file_exists(dirname(__DIR__) . '/install.php') && !$is_install_page) {
    header("Location: install.php");
    exit;
}


$db = new PDO("sqlite:$db_file");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$db->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    public_id TEXT UNIQUE,
    email TEXT UNIQUE,
    password_hash TEXT,
    role TEXT,
    user_group TEXT,
    display_name TEXT,
    last_login DATETIME,
    totp_secret TEXT,
    totp_enabled INTEGER DEFAULT 0
)");

// Migrate: Add columns if missing
try { @$db->exec("ALTER TABLE users ADD COLUMN public_id TEXT"); } catch (Exception $e) {}
try { @$db->exec("ALTER TABLE users ADD COLUMN last_login DATETIME"); } catch (Exception $e) {}
try { @$db->exec("ALTER TABLE users ADD COLUMN display_name TEXT"); } catch (Exception $e) {}
try { @$db->exec("ALTER TABLE users ADD COLUMN totp_secret TEXT"); } catch (Exception $e) {}
try { @$db->exec("ALTER TABLE users ADD COLUMN totp_enabled INTEGER DEFAULT 0"); } catch (Exception $e) {}

$db->exec("CREATE TABLE IF NOT EXISTS folders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    public_id TEXT UNIQUE,
    name TEXT,
    access_groups TEXT,
    parent_id INTEGER DEFAULT NULL,
    owner_id INTEGER DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(parent_id) REFERENCES folders(id),
    FOREIGN KEY(owner_id) REFERENCES users(id)
)");

// Migrate: Add columns if missing
try { @$db->exec("ALTER TABLE folders ADD COLUMN public_id TEXT"); } catch (Exception $e) {}
try { @$db->exec("ALTER TABLE folders ADD COLUMN parent_id INTEGER DEFAULT NULL"); } catch (Exception $e) {}
try { @$db->exec("ALTER TABLE folders ADD COLUMN owner_id INTEGER DEFAULT NULL"); } catch (Exception $e) {}
try { @$db->exec("ALTER TABLE folders ADD COLUMN deleted_at DATETIME DEFAULT NULL"); } catch (Exception $e) {}
try { @$db->exec("ALTER TABLE folders ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP"); } catch (Exception $e) {}

$db->exec("CREATE TABLE IF NOT EXISTS files (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    public_id TEXT UNIQUE,
    folder_id INTEGER,
    name TEXT,
    original_name TEXT,
    size INTEGER,
    uploaded_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    FOREIGN KEY(folder_id) REFERENCES folders(id)
)");
try { @$db->exec("ALTER TABLE files ADD COLUMN public_id TEXT"); } catch (Exception $e) {}
try { @$db->exec("ALTER TABLE files ADD COLUMN deleted_at DATETIME DEFAULT NULL"); } catch (Exception $e) {}

$db->exec("CREATE TABLE IF NOT EXISTS logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    action TEXT,
    details TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id) REFERENCES users(id)
)");

$db->exec("CREATE TABLE IF NOT EXISTS settings (
    setting_key TEXT PRIMARY KEY,
    setting_value TEXT
)");

$db->exec("CREATE TABLE IF NOT EXISTS user_2fa_trust (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    token TEXT UNIQUE,
    expires_at DATETIME,
    FOREIGN KEY(user_id) REFERENCES users(id)
)");

// Insert default settings if they don't exist
$stmt = $db->prepare("INSERT OR IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
$stmt->execute(['in_app_preview', '1']);
$stmt->execute(['enforce_2fa_admin', '0']);
$stmt->execute(['enforce_2fa_last_update', '0']);



