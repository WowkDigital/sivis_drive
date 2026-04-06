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
    $lock_file = $data_dir . '/backup.lock';
    $flag_file = $data_dir . '/maintenance.flag';

    // --- STEP 0: LOCK CHECK ---
    if (file_exists($lock_file)) {
        // Check if lock is old (> 30 mins) - maybe process crashed
        if (time() - filemtime($lock_file) < 1800) {
            return false; // Already running
        }
    }
    file_put_contents($lock_file, getmypid());

    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0777, true);
    }

    // --- STEP 1: ENABLE MAINTENANCE ---
    $now = date('Y-m-d H:i:s');
    file_put_contents($flag_file, $now);
    
    // --- STEP 2: CREATE ZIP ---
    // Every backup gets a unique timestamp to prevent overwriting within the same day
    $zip_filename = 'backup_' . date('Y-m-d_H-i') . '.zip';
    $zip_path = $backup_dir . '/' . $zip_filename;
    
    // We don't unlink existing daily backups anymore, as each has a unique time suffix.

    if (!class_exists('ZipArchive')) {
        $temp_backup_dir = $data_dir . '/tmp_backup_' . time();
        
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // --- WINDOWS FALLBACK (PowerShell + xcopy) ---
            mkdir($temp_backup_dir, 0777, true);
            
            // xcopy /C continues even if errors occur (like locked files)
            // xcopy /E /I /H /Y /C are standard flags for complete copy
            exec("xcopy uploads \"$temp_backup_dir\\uploads\" /E /I /H /Y /C 2>&1");
            exec("xcopy core \"$temp_backup_dir\\core\" /E /I /H /Y /C 2>&1");
            exec("xcopy api \"$temp_backup_dir\\api\" /E /I /H /Y /C 2>&1");
            exec("xcopy views \"$temp_backup_dir\\views\" /E /I /H /Y /C 2>&1");
            exec("xcopy assets \"$temp_backup_dir\\assets\" /E /I /H /Y /C 2>&1");
            @copy("data/database.sqlite", "$temp_backup_dir/database.sqlite");
            @copy("index.php", "$temp_backup_dir/index.php");
            @copy("admin.php", "$temp_backup_dir/admin.php");

            $cmd = "powershell -ExecutionPolicy Bypass -Command \"Compress-Archive -Path '$temp_backup_dir\\*' -DestinationPath '$zip_path'\" 2>&1";
            exec($cmd, $output, $return_var);
            
            // Cleanup temp folder on Windows
            exec("rd /s /q \"$temp_backup_dir\"");

            if ($return_var === 0 && file_exists($zip_path)) {
                // Success
            } else {
                log_activity($db, 0, 'BACKUP_ERROR', "Błąd fallbacku Win (Kod: $return_var): " . implode(' ', array_slice($output, -1)));
                @unlink($flag_file); @unlink($lock_file); return false;
            }
        } else {
            // --- LINUX FALLBACK (zip command) ---
            // On linux we can usually zip directly, even if files are open
            $cmd = "zip -r " . escapeshellarg($zip_path) . " uploads core api views assets data/database.sqlite *.php *.md -x 'data/backups/*' 2>&1";
            exec($cmd, $output, $return_var);
            
            if ($return_var === 0 && file_exists($zip_path)) {
                // Success
            } else {
                log_activity($db, 0, 'BACKUP_ERROR', "Błąd fallbacku Linux (Kod: $return_var): " . implode(' ', array_slice($output, -1)));
                @unlink($flag_file); @unlink($lock_file); return false;
            }
        }
    } else {
        // --- STANDARD ZipArchive LOGIC ---
        $zip = new ZipArchive();
        if ($zip->open($zip_path, ZipArchive::CREATE) !== TRUE) {
            log_activity($db, 0, 'BACKUP_ERROR', "Nie udało się stworzyć archiwum ZIP: $zip_filename");
            @unlink($flag_file);
            @unlink($lock_file);
            return false;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root_dir, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->isDir()) continue;
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($root_dir) + 1);
            if (strpos($relativePath, 'data/backups') === 0) continue;
            if (strpos($relativePath, '.git') === 0) continue;
            if (strpos($relativePath, '.gemini') === 0) continue; 
            $zip->addFile($filePath, $relativePath);
        }
        $zip->close();
    }

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
    @unlink($lock_file);
    
    log_activity($db, 0, 'BACKUP_SUCCESS', "Pomyślnie wykonano pełny backup: $zip_filename");
    
    // Save last backup timestamp
    file_put_contents($backup_dir . '/last_backup.txt', date('Y-m-d'));
    
    return true;
}

function auto_backup_if_needed($db) {
    global $data_dir;
    $last_backup_file = $data_dir . '/backups/last_backup.txt';
    $today = date('Y-m-d');
    $last_backup = file_exists($last_backup_file) ? trim(file_get_contents($last_backup_file)) : '';

    if ($last_backup !== $today) {
        run_backup($db);
    }
}

// Ensure run_backup is not called twice if included in admin_logic.php
// We only run if NOT included or explicitly triggered.
if (php_sapi_name() == 'cli' || (isset($_GET['force']) && isset($is_backup_script) && $is_backup_script)) {
    run_backup($db);
}
