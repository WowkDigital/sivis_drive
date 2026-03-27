<?php
/**
 * Sivis Drive - Backup Logic
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$is_backup_script = true; // Bypasses maintenance exit in db.php

function run_backup($db) {
    global $data_dir;
    $root_dir = dirname(__DIR__);
    $backup_dir = $data_dir . '/backups';
    $flag_file = $data_dir . '/maintenance.flag';

    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0777, true);
    }

    // --- STEP 1: ENABLE MAINTENANCE ---
    $now = date('Y-m-d H:i:s');
    file_put_contents($flag_file, $now);
    
    // --- STEP 2: CREATE ZIP ---
    $zip_filename = 'backup_' . date('Y-m-d') . '.zip';
    $zip_path = $backup_dir . '/' . $zip_filename;
    
    // If ZIP exists for today, keep it or overwrite? User asked for daily. Overwrite if exists.
    if (file_exists($zip_path)) unlink($zip_path);

    $zip = new ZipArchive();
    if ($zip->open($zip_path, ZipArchive::CREATE) !== TRUE) {
        log_activity($db, 0, 'BACKUP_ERROR', "Nie udało się stworzyć archiwum ZIP: $zip_filename");
        @unlink($flag_file);
        return false;
    }

    // Files to include: /uploads, /data (excluding /data/backups), index.php etc.?
    // If the whole project is small, best to zip the whole folder structure or at least the core state.
    // The user asked for "pełen backup" - full backup.
    
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root_dir, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->isDir()) continue;
        
        $filePath = $file->getRealPath();
        $relativePath = substr($filePath, strlen($root_dir) + 1);
        
        // --- EXCLUSIONS ---
        // Don't zip existing backups or the installer
        if (strpos($relativePath, 'data/backups') === 0) continue;
        // Don't zip git stuff or vendor/node_modules if exists
        if (strpos($relativePath, '.git') === 0) continue;
        if (strpos($relativePath, '.gemini') === 0) continue; // Antigravity local files
        
        $zip->addFile($filePath, $relativePath);
    }
    
    $zip->close();

    // --- STEP 3: CLEANUP (Keep 7 days) ---
    $backups = glob($backup_dir . '/backup_*.zip');
    $retention_days = 7;
    $now_ts = time();
    foreach ($backups as $b) {
        if (filemtime($b) < ($now_ts - ($retention_days * 86400))) {
            @unlink($b);
            log_activity($db, 0, 'BACKUP_CLEANUP', "Usunięto stary backup: " . basename($b));
        }
    }

    // --- STEP 4: DISABLE MAINTENANCE ---
    @unlink($flag_file);
    
    log_activity($db, 0, 'BACKUP_SUCCESS', "Pomyślnie wykonano pełny backup: $zip_filename");
    
    // Save last backup timestamp
    file_put_contents($backup_dir . '/last_backup.txt', date('Y-m-d'));
    
    return true;
}

// Logic to check if we need a backup
$last_backup_file = $data_dir . '/backups/last_backup.txt';
$today = date('Y-m-d');
$last_backup = file_exists($last_backup_file) ? trim(file_get_contents($last_backup_file)) : '';

if (isset($_GET['force']) || (php_sapi_name() == 'cli') || ($last_backup !== $today)) {
    // Only run if not already running (maintenance flag check is not enough because we set it)
    // Use a lock file if needed, but for daily it should be fine.
    run_backup($db);
}
