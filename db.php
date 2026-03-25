<?php
$db_file = __DIR__ . '/database.sqlite';
if (!file_exists($db_file) && file_exists(__DIR__ . '/install.php')) {
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
    last_login DATETIME
)");

// Migrate: Add last_login to users if missing
try { @$db->exec("ALTER TABLE users ADD COLUMN last_login DATETIME"); } catch (Exception $e) {}

$db->exec("CREATE TABLE IF NOT EXISTS folders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT,
    access_groups TEXT
)");

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
