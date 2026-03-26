<?php
require_once 'auth.php';
require_once 'inc/functions.php';
require_login();

if (!isset($_GET['id']) && !isset($_GET['ids'])) {
    die("Brak pliku.");
}

$role = $_SESSION['role'] ?? 'pracownik';
$group = get_user_group();

if (isset($_GET['ids'])) {
    $ids = array_filter(array_map('intval', explode(',', $_GET['ids'])));
    
    if (empty($ids)) die("Brak wybranych plików.");

    $zip = new ZipArchive();
    $zip_name = 'SivisDrive_' . date('Y-m-d_H-i-s') . '.zip';
    $zip_path = sys_get_temp_dir() . '/' . $zip_name;

    if ($zip->open($zip_path, ZipArchive::CREATE) !== TRUE) {
        die("Błąd przy tworzeniu ZIP.");
    }

    function addFolderToZip($db, $fid, $zip, $base_path, $user_id, $role, $group) {
        // Add files
        $stmt = $db->prepare("SELECT * FROM files WHERE folder_id = ?");
        $stmt->execute([$fid]);
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($files as $f) {
            $fpath = __DIR__ . '/uploads/' . $f['name'];
            if (file_exists($fpath)) {
                $zip->addFile($fpath, $base_path . $f['original_name']);
            }
        }
        
        // Add subfolders
        $stmt = $db->prepare("SELECT * FROM folders WHERE parent_id = ?");
        $stmt->execute([$fid]);
        $subfolders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($subfolders as $sf) {
            if (can_user_access_folder($db, $sf['id'], $user_id, $role, $group)) {
                $zip->addEmptyDir($base_path . $sf['name']);
                addFolderToZip($db, $sf['id'], $zip, $base_path . $sf['name'] . '/', $user_id, $role, $group);
            }
        }
    }

    // We don't know if ID is file or folder from the plain list, so we check both tables or use a combined approach
    // But my JS only sent file IDs. I'll update JS to send objects or I check DB here.
    
    foreach ($ids as $id) {
        // Check if it's a file
        $stmt = $db->prepare("SELECT * FROM files WHERE id = ?");
        $stmt->execute([$id]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($file && can_user_access_folder($db, $file['folder_id'], $_SESSION['user_id'], $role, $group)) {
            $fpath = __DIR__ . '/uploads/' . $file['name'];
            if (file_exists($fpath)) {
                $zip->addFile($fpath, $file['original_name']);
            }
            continue;
        }

        // Check if it's a folder (only if not found as file or if we want to support both with same IDs - unlikely in this DB schema but possible)
        $stmt = $db->prepare("SELECT * FROM folders WHERE id = ?");
        $stmt->execute([$id]);
        $folder = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($folder && can_user_access_folder($db, $folder['id'], $_SESSION['user_id'], $role, $group)) {
            $zip->addEmptyDir($folder['name']);
            addFolderToZip($db, $folder['id'], $zip, $folder['name'] . '/', $_SESSION['user_id'], $role, $group);
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
    $file_id = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT f.*, fol.access_groups, fol.owner_id FROM files f JOIN folders fol ON f.folder_id = fol.id WHERE f.id = ?");
    $stmt->execute([$file_id]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$file) {
        die("Plik nie istnieje.");
    }

    // Check access
    if (!can_user_access_folder($db, $file['folder_id'], $_SESSION['user_id'], $role, $group)) {
        die("Brak dostępu do tego pliku.");
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
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file['original_name']) . '"');
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
