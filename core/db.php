<?php
$data_dir = dirname(__DIR__) . '/data';
if (!is_dir($data_dir)) {
    @mkdir($data_dir, 0777, true);
}
$db_file = $data_dir . '/database.sqlite';

if (!file_exists($db_file) && file_exists(dirname(__DIR__) . '/install.php')) {
    header("Location: install.php");
    exit;
}

$db = new PDO("sqlite:$db_file");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$db->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT UNIQUE,
    password_hash TEXT,
    role TEXT,
    user_group TEXT,
    display_name TEXT,
    last_login DATETIME
)");

// Migrate: Add columns if missing
try { @$db->exec("ALTER TABLE users ADD COLUMN last_login DATETIME"); } catch (Exception $e) {}
try { @$db->exec("ALTER TABLE users ADD COLUMN display_name TEXT"); } catch (Exception $e) {}

$db->exec("CREATE TABLE IF NOT EXISTS folders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT,
    access_groups TEXT,
    parent_id INTEGER DEFAULT NULL,
    owner_id INTEGER DEFAULT NULL,
    FOREIGN KEY(parent_id) REFERENCES folders(id),
    FOREIGN KEY(owner_id) REFERENCES users(id)
)");

// Migrate: Add parent_id and owner_id if missing
try { @$db->exec("ALTER TABLE folders ADD COLUMN parent_id INTEGER DEFAULT NULL"); } catch (Exception $e) {}
try { @$db->exec("ALTER TABLE folders ADD COLUMN owner_id INTEGER DEFAULT NULL"); } catch (Exception $e) {}

$db->exec("CREATE TABLE IF NOT EXISTS files (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    folder_id INTEGER,
    name TEXT,
    original_name TEXT,
    size INTEGER,
    uploaded_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(folder_id) REFERENCES folders(id)
)");

$db->exec("CREATE TABLE IF NOT EXISTS logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    action TEXT,
    details TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id) REFERENCES users(id)
)");
