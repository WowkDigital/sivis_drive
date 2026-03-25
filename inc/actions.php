<?php
/**
 * Handle POST actions (Uploads, Deletions, Folders)
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'upload' && isset($_FILES['file']) && isset($_POST['folder_id'])) {
        $folder_id = (int)$_POST['folder_id'];
        $can_edit_target = is_admin() || is_zarzad() || is_private_tree($db, $folder_id, $_SESSION['user_id']);
        
        if ($can_edit_target) {
            $file = $_FILES['file'];
            // Limit check for private folders
            if (is_private_tree($db, $folder_id, $_SESSION['user_id'])) {
                $usage = get_private_usage($db, $_SESSION['user_id']);
                if ($usage['count'] >= 500) {
                    $message = "Błąd: Przekroczono limit 500 plików.";
                } elseif (($usage['size'] + $file['size']) > 500 * 1024 * 1024) {
                    $message = "Błąd: Przekroczono limit miejsca (500MB).";
                }
            }

            if (empty($message)) {
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $message = "Błąd wysyłania (kod: " . $file['error'] . ").";
                } elseif ($file['size'] > 100 * 1024 * 1024) {
                    $message = "Błąd: Plik jest za duży (max 100MB).";
                } else {
                    $unique_name = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9.\-_]/', '', basename($file['name']));
                    if (move_uploaded_file($file['tmp_name'], $upload_dir . '/' . $unique_name)) {
                        $stmt = $db->prepare('INSERT INTO files (folder_id, name, original_name, size, uploaded_by) VALUES (?, ?, ?, ?, ?)');
                        $stmt->execute([$folder_id, $unique_name, $file['name'], $file['size'], $_SESSION['user_id']]);
                        $message = "Plik został dodany.";
                        header("Location: index.php?folder=" . $folder_id);
                        exit;
                    }
                }
            }
        }
    } elseif ($_POST['action'] === 'create_folder' && isset($_POST['name']) && isset($_POST['parent_id'])) {
        $name = $_POST['name'];
        $parent_id = (int)$_POST['parent_id'];
        // Check if user owns the parent tree or is admin
        if (is_admin() || is_private_tree($db, $parent_id, $_SESSION['user_id'])) {
             $stmt = $db->prepare("INSERT INTO folders (name, parent_id) VALUES (?, ?)");
             $stmt->execute([$name, $parent_id]);
             $message = "Podfolder został utworzony.";
             header("Location: index.php?folder=" . $parent_id);
             exit;
        }
    } elseif ($_POST['action'] === 'delete_file' && isset($_POST['file_id'])) {
        $fid = (int)$_POST['file_id'];
        $stmt = $db->prepare("SELECT folder_id, name FROM files WHERE id = ?");
        $stmt->execute([$fid]);
        $file_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($file_info) {
             $can_edit_file = is_admin() || is_zarzad() || is_private_tree($db, $file_info['folder_id'], $_SESSION['user_id']);
             if ($can_edit_file) {
                @unlink($upload_dir . '/' . $file_info['name']);
                $db->prepare("DELETE FROM files WHERE id = ?")->execute([$fid]);
                $message = "Plik usunięty.";
                header("Location: " . $_SERVER['HTTP_REFERER']);
                exit;
             }
        }
    } elseif ($_POST['action'] === 'move_file' && isset($_POST['file_id']) && isset($_POST['new_folder_id'])) {
        $fid = (int)$_POST['file_id'];
        $new_folder_id = (int)$_POST['new_folder_id'];
        
        $stmt = $db->prepare("SELECT folder_id FROM files WHERE id = ?");
        $stmt->execute([$fid]);
        $old_folder_id = $stmt->fetchColumn();
        
        $can_move = (is_admin() || is_zarzad() || (is_private_tree($db, $old_folder_id, $_SESSION['user_id']) && is_private_tree($db, $new_folder_id, $_SESSION['user_id'])));
        
        if ($can_move) {
            $db->prepare("UPDATE files SET folder_id = ? WHERE id = ?")->execute([$new_folder_id, $fid]);
            header("Location: index.php?folder=" . $new_folder_id);
            exit;
        }
    }
}
