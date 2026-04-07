<?php
require_once 'core/auth.php';
require_once 'core/functions.php';
require_login();

if (!isset($_GET['id']) && !isset($_GET['ids']) && !isset($_GET['items'])) {
    die("Brak pliku.");
}

$role = $_SESSION['role'] ?? 'pracownik';
$group = get_user_group();

if (isset($_GET['ids']) || isset($_GET['items'])) {
    $items_raw = isset($_GET['items']) ? explode(',', $_GET['items']) : explode(',', $_GET['ids']);
    
    if (empty($items_raw)) die("Brak wybranych plików.");

    $zip = new ZipArchive();
    $zip_name = 'SivisDrive_' . date('Y-m-d_H-i-s') . '.zip';
    $zip_path = sys_get_temp_dir() . '/' . $zip_name;

    if ($zip->open($zip_path, ZipArchive::CREATE) !== TRUE) {
        die("Błąd przy tworzeniu ZIP.");
    }

    function addFolderToZip($db, $fid, $zip, $base_path, $user_id, $role, $group) {
        $is_admin = ($role === 'admin');
        
        $sql_files = "SELECT * FROM files WHERE folder_id = ?" . ($is_admin ? "" : " AND deleted_at IS NULL");
        $stmt = $db->prepare($sql_files);
        $stmt->execute([$fid]);
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($files as $f) {
            $fpath = __DIR__ . '/uploads/' . $f['name'];
            if (file_exists($fpath)) {
                $zip->addFile($fpath, $base_path . $f['original_name']);
            }
        }
        
        $sql_fol = "SELECT * FROM folders WHERE parent_id = ?" . ($is_admin ? "" : " AND deleted_at IS NULL");
        $stmt = $db->prepare($sql_fol);
        $stmt->execute([$fid]);
        $subfolders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($subfolders as $sf) {
            if (can_user_access_folder($db, $sf['id'], $user_id, $role, $group)) {
                $zip->addEmptyDir($base_path . $sf['name']);
                addFolderToZip($db, $sf['id'], $zip, $base_path . $sf['name'] . '/', $user_id, $role, $group);
            }
        }
    }

    foreach ($items_raw as $item_key) {
        $parts = explode('-', $item_key);
        if (count($parts) === 2) {
            $type = $parts[0];
            $id = $parts[1];
        } else {
            // Fallback for old 'ids' param or unexpected format
            $type = 'file';
            $id = (int)$item_key;
        }

        if ($type === 'file') {
            $stmt = $db->prepare("SELECT * FROM files WHERE id = ? OR public_id = ?");
            $stmt->execute([$id, $id]);
            $file = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($file && can_user_access_folder($db, $file['folder_id'], $_SESSION['user_id'], $role, $group)) {
                // If it's deleted, only admin can download it
                if ($file['deleted_at'] !== null && !is_admin()) continue;
                $fpath = __DIR__ . '/uploads/' . $file['name'];
                if (file_exists($fpath)) {
                    $zip->addFile($fpath, $file['original_name']);
                }
            }
        } elseif ($type === 'folder') {
            $stmt = $db->prepare("SELECT * FROM folders WHERE id = ? OR public_id = ?");
            $stmt->execute([$id, $id]);
            $folder = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($folder && can_user_access_folder($db, $folder['id'], $_SESSION['user_id'], $role, $group)) {
                // If it's deleted, only admin can download it
                if ($folder['deleted_at'] !== null && !is_admin()) continue;
                $zip->addEmptyDir($folder['name']);
                addFolderToZip($db, $folder['id'], $zip, $folder['name'] . '/', $_SESSION['user_id'], $role, $group);
            }
        }
    }

    $zip->close();

    if (file_exists($zip_path)) {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zip_name . '"');
        header('Content-Length: ' . filesize($zip_path));
        readfile($zip_path);
        unlink($zip_path);
        exit;
    } else {
        die("Błąd przy pobieraniu paczki.");
    }

} else {
    $file_id_raw = $_GET['id'];
    $stmt = $db->prepare("SELECT f.* FROM files f WHERE f.id = ? OR f.public_id = ?");
    $stmt->execute([$file_id_raw, $file_id_raw]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$file) {
        die("Plik nie istnieje.");
    }

    // Check access
    if (!can_user_access_folder($db, $file['folder_id'], $_SESSION['user_id'], $role, $group)) {
        log_activity($db, $_SESSION['user_id'], 'SECURITY_ALERT', "Nieautoryzowana próba pobrania pliku ID: $file_id_raw");
        die("Brak dostępu do tego pliku.");
    }
    
    // Check if deleted
    if ($file['deleted_at'] !== null && !is_admin()) {
        die("Ten plik został usunięty.");
    }

    $filepath = __DIR__ . '/uploads/' . $file['name'];

    if (file_exists($filepath)) {
        $is_view = isset($_GET['action']) && $_GET['action'] === 'view';
        $ext = strtolower(pathinfo($file['original_name'], PATHINFO_EXTENSION));
        
        $viewable_types = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp'
        ];

        if ($is_view && isset($viewable_types[$ext])) {
            header('Content-Type: ' . $viewable_types[$ext]);
            header('Content-Disposition: inline; filename="' . basename($file['original_name']) . '"');
        } else {
            $info = pathinfo($file['original_name']);
            $formatted_name = $info['filename'] . '_' . date('Y-m-d_Hi') . (isset($info['extension']) ? '.' . $info['extension'] : '');
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $formatted_name . '"');
        }

        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    } else {
        die("Plik fizycznie nie istnieje na serwerze.");
    }
}
?>
