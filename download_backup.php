<?php
/**
 * Sivis Drive - Secure Backup Download
 */
require_once 'core/auth.php';
require_once 'core/db.php';
require_admin();

if (isset($_GET['filename'])) {
    $filename = basename($_GET['filename']);
    $file_path = __DIR__ . '/data/backups/' . $filename;
    
    if (file_exists($file_path) && strpos($filename, 'backup_') === 0 && substr($filename, -4) === '.zip') {
        header('Content-Description: File Transfer');
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file_path));
        readfile($file_path);
        exit;
    }
}
die("Błąd: Plik nie istnieje lub brak uprawnień.");
