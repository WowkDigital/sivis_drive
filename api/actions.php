<?php
/**
 * Handle POST actions (Uploads, Deletions, Folders)
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // Weryfikacja CSRF
    $csrf_token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        header("HTTP/1.1 403 Forbidden");
        die("Błąd weryfikacji CSRF. Proszę odświeżyć stronę.");
    }

    if ($_POST['action'] === 'upload' && isset($_FILES['file']) && isset($_POST['folder_id'])) {
        $folder_id = (int)$_POST['folder_id'];
        $can_edit_target = is_admin() || is_zarzad() || is_private_tree($db, $folder_id, $_SESSION['user_id']);
        
        $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
        $error_msg = null;

        if ($can_edit_target) {
            $file = $_FILES['file'];
            // If it's single file in array structure (traditional form with file[])
            if (is_array($file['name'])) {
                $file = [
                    'name' => $file['name'][0],
                    'type' => $file['type'][0],
                    'tmp_name' => $file['tmp_name'][0],
                    'error' => $file['error'][0],
                    'size' => $file['size'][0]
                ];
            }

            // Limit check for private folders
            if (is_private_tree($db, $folder_id, $_SESSION['user_id'])) {
                $usage = get_private_usage($db, $_SESSION['user_id']);
                if ($usage['count'] >= 500) {
                    $error_msg = "Błąd: Przekroczono limit 500 plików.";
                } elseif (($usage['size'] + $file['size']) > 500 * 1024 * 1024) {
                    $error_msg = "Błąd: Przekroczono limit miejsca (500MB).";
                }
            }

            if (empty($error_msg)) {
                // Handle relative path (folder upload)
                $current_target_folder_id = $folder_id;
                if (!empty($_POST['relative_path'])) {
                    $path_parts = explode('/', $_POST['relative_path']);
                    array_pop($path_parts); // Remove the filename from path
                    
                    foreach ($path_parts as $part) {
                        $part = trim($part);
                        if ($part === '') continue;
                        
                        $stmt_find = $db->prepare("SELECT id FROM folders WHERE name = ? AND parent_id = ?");
                        $stmt_find->execute([$part, $current_target_folder_id]);
                        $found_id = $stmt_find->fetchColumn();
                        
                        if ($found_id) {
                            $current_target_folder_id = $found_id;
                        } else {
                            $stmt_owner = $db->prepare("SELECT owner_id FROM folders WHERE id = ?");
                            $stmt_owner->execute([$current_target_folder_id]);
                            $p_owner = $stmt_owner->fetchColumn();
                            
                            $stmt_ins = $db->prepare("INSERT INTO folders (name, parent_id, owner_id) VALUES (?, ?, ?)");
                            $stmt_ins->execute([$part, $current_target_folder_id, $p_owner]);
                            $current_target_folder_id = $db->lastInsertId();
                        }
                    }
                }
                $folder_id = $current_target_folder_id;

                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $error_msg = "Błąd wysyłania (kod: " . $file['error'] . ").";
                } elseif ($file['size'] > 100 * 1024 * 1024) {
                    $error_msg = "Błąd: Plik jest za duży (max 100MB).";
                } else {
                    $forbidden_extensions = ['php', 'php3', 'php4', 'php5', 'php6', 'phtml', 'exe', 'bat', 'sh', 'cgi', 'pl', 'py', 'htaccess'];
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, $forbidden_extensions)) {
                        $error_msg = "Błąd: Niedozwolone rozszerzenie pliku.";
                    } else {
                        $unique_name = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9.\-_]/', '', basename($file['name']));
                        if (move_uploaded_file($file['tmp_name'], $upload_dir . '/' . $unique_name)) {
                            $stmt = $db->prepare('INSERT INTO files (folder_id, name, original_name, size, uploaded_by) VALUES (?, ?, ?, ?, ?)');
                            $stmt->execute([$folder_id, $unique_name, $file['name'], $file['size'], $_SESSION['user_id']]);
                            
                            log_activity($db, $_SESSION['user_id'], 'UPLOAD_FILE', "Wgrano plik: " . $file['name'] . " do folderu ID: $folder_id");

                            if ($is_ajax) {
                                echo json_encode(['success' => true]);
                                exit;
                            } else {
                                $_SESSION['toast'] = "Plik został dodany.";
                                header("Location: index.php?folder=" . $folder_id);
                                exit;
                            }
                        } else {
                            $error_msg = "Błąd zapisu pliku na serwerze.";
                        }
                    }
                }
            }
        } else {
            $error_msg = "Brak uprawnień do wgrywania plików do tej lokalizacji.";
        }

        if ($error_msg) {
            if ($is_ajax) {
                echo json_encode(['success' => false, 'error' => $error_msg]);
                exit;
            } else {
                $_SESSION['toast_error'] = $error_msg;
                header("Location: index.php?folder=" . $folder_id);
                exit;
            }
        }
    } elseif ($_POST['action'] === 'create_folder' && isset($_POST['name']) && isset($_POST['parent_id'])) {
        $name = $_POST['name'];
        $parent_id = (int)$_POST['parent_id'];
        
        // Check if user owns the parent tree or is admin/zarząd (for shared folders)
        $is_private = is_private_tree($db, $parent_id, $_SESSION['user_id']);
        $can_edit_parent = is_admin() || is_zarzad() || $is_private;

        if ($can_edit_parent) {
             // Inherit owner_id from parent
             $stmt = $db->prepare("SELECT owner_id FROM folders WHERE id = ?");
             $stmt->execute([$parent_id]);
             $owner_id = $stmt->fetchColumn();

             $stmt = $db->prepare("INSERT INTO folders (name, parent_id, owner_id) VALUES (?, ?, ?)");
             $stmt->execute([$name, $parent_id, $owner_id]);
             
             log_activity($db, $_SESSION['user_id'], 'CREATE_FOLDER', "Utworzono folder: $name (nadrzędny ID: $parent_id)");

             $_SESSION['toast'] = "Podfolder został utworzony.";
             header("Location: index.php?folder=" . $parent_id);
             exit;
        }
    } elseif ($_POST['action'] === 'create_shared_folder' && isset($_POST['name'])) {
        if (is_admin() || is_zarzad()) {
            $name = $_POST['name'];
            $stmt = $db->prepare("INSERT INTO folders (name, owner_id, access_groups) VALUES (?, NULL, 'zarząd,pracownicy')");
            $stmt->execute([$name]);
            $message = "Nowy folder udostępniony został utworzony.";
            header("Location: index.php");
            exit;
        }
    } elseif ($_POST['action'] === 'update_my_name' && isset($_POST['display_name'])) {
        $name = $_POST['display_name'];
        $uid = $_SESSION['user_id'];
        
        $stmt = $db->prepare('UPDATE users SET display_name = ? WHERE id = ?');
        $stmt->execute([$name, $uid]);
        
        $_SESSION['display_name'] = $name;

        // Sync private root folder name
        $stmt = $db->prepare("UPDATE folders SET name = ? WHERE owner_id = ? AND parent_id IS NULL");
        $stmt->execute(['Pliki ' . $name, $uid]);

        $message = "Twoja nazwa została zaktualizowana.";
    } elseif ($_POST['action'] === 'rename_item' && isset($_POST['item_id']) && isset($_POST['new_name']) && isset($_POST['type'])) {
        $id = (int)$_POST['item_id'];
        $new_name = $_POST['new_name'];
        $type = $_POST['type']; // 'file' or 'folder'
        
        if ($type === 'folder') {
            if (is_admin() || is_zarzad() || is_private_tree($db, $id, $_SESSION['user_id'])) {
                 $stmt = $db->prepare("UPDATE folders SET name = ? WHERE id = ?");
                 if ($stmt->execute([$new_name, $id])) {
                     // Get parent for better redirect
                     $st_p = $db->prepare("SELECT parent_id FROM folders WHERE id = ?");
                     $st_p->execute([$id]);
                     $p_id = $st_p->fetchColumn();
                     
                     log_activity($db, $_SESSION['user_id'], 'RENAME_FOLDER', "Zmieniono nazwę folderu ID: $id na: $new_name");
                     
                     $_SESSION['toast'] = "Nazwa folderu została zmieniona. ✔";
                     header("Location: index.php?folder=" . ($p_id ?: 0));
                     exit;
                 }
            }
        } else {
             $stmt = $db->prepare("SELECT folder_id FROM files WHERE id = ?");
             $stmt->execute([$id]);
             $folder_id = $stmt->fetchColumn();
             if (is_admin() || is_zarzad() || is_private_tree($db, $folder_id, $_SESSION['user_id'])) {
                 $stmt = $db->prepare("UPDATE files SET original_name = ? WHERE id = ?");
                 if ($stmt->execute([$new_name, $id])) {
                     log_activity($db, $_SESSION['user_id'], 'RENAME_FILE', "Zmieniono nazwę pliku ID: $id na: $new_name");
                     $_SESSION['toast'] = "Nazwa pliku została zmieniona. ✔";
                     header("Location: index.php?folder=" . $folder_id);
                     exit;
                 }
             }
        }
    } elseif ($_POST['action'] === 'delete_file' && isset($_POST['file_id'])) {
        $fid = (int)$_POST['file_id'];
        $stmt = $db->prepare("SELECT folder_id, name, original_name, deleted_at FROM files WHERE id = ?");
        $stmt->execute([$fid]);
        $file_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($file_info) {
             $can_edit_file = is_admin() || is_zarzad() || is_private_tree($db, $file_info['folder_id'], $_SESSION['user_id']);
             if ($can_edit_file) {
                if ($file_info['deleted_at'] !== null) {
                    // Item already in trash -> PERMANENT DELETE
                    @unlink($upload_dir . '/' . $file_info['name']);
                    $db->prepare("DELETE FROM files WHERE id = ?")->execute([$fid]);
                    log_activity($db, $_SESSION['user_id'], 'DELETE_FILE_PERM', "Trwale usunięto plik z kosza: " . ($file_info['original_name'] ?? 'Nieznany') . " (ID: $fid)");
                    $_SESSION['toast'] = "Plik został trwale usunięty.";
                } else {
                    // Move to trash
                    $db->prepare("UPDATE files SET deleted_at = datetime('now') WHERE id = ?")->execute([$fid]);
                    log_activity($db, $_SESSION['user_id'], 'TRASH_FILE', "Przeniesiono do kosza plik: " . ($file_info['original_name'] ?? 'Nieznany') . " (ID: $fid)");
                    $_SESSION['toast'] = "Plik został przeniesiony do kosza.";
                }

                header("Location: " . ($_SERVER['HTTP_REFERER'] ?: 'index.php'));
                exit;
             }
        }
    } elseif ($_POST['action'] === 'delete_folder' && isset($_POST['folder_id'])) {
        $fid = (int)$_POST['folder_id'];
        $stmt = $db->prepare("SELECT parent_id, name, deleted_at FROM folders WHERE id = ?");
        $stmt->execute([$fid]);
        $folder_info = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($folder_info && (is_admin() || is_zarzad() || is_private_tree($db, $fid, $_SESSION['user_id']))) {
            if ($folder_info['deleted_at'] !== null) {
                // Already in trash -> PERMANENT DELETE
                delete_folder_recursive($db, $fid, $upload_dir);
                log_activity($db, $_SESSION['user_id'], 'DELETE_FOLDER_PERM', "Trwale usunięto folder z kosza: " . $folder_info['name'] . " (ID: $fid)");
                $_SESSION['toast'] = "Folder został trwale usunięty.";
            } else {
                // Move to trash
                soft_delete_folder_recursive($db, $fid);
                log_activity($db, $_SESSION['user_id'], 'TRASH_FOLDER', "Przeniesiono do kosza folder: " . $folder_info['name'] . " (ID: $fid)");
                $_SESSION['toast'] = "Folder został przeniesiony do kosza.";
            }

            header("Location: " . ($_SERVER['HTTP_REFERER'] ?: 'index.php'));
            exit;
        }
    } elseif ($_POST['action'] === 'move_file' && isset($_POST['file_id']) && isset($_POST['new_folder_id'])) {
        $fid = (int)$_POST['file_id'];
        $new_folder_id = (int)$_POST['new_folder_id'];
        
        $stmt = $db->prepare("SELECT folder_id FROM files WHERE id = ?");
        $stmt->execute([$fid]);
        $old_folder_id = $stmt->fetchColumn();
        
        $role = $_SESSION['role'] ?? 'pracownik';
        $group = get_user_group();

        $can_move = can_user_access_folder($db, $old_folder_id, $_SESSION['user_id'], $role, $group) 
                 && can_user_access_folder($db, $new_folder_id, $_SESSION['user_id'], $role, $group);
        
        if ($can_move) {
            $db->prepare("UPDATE files SET folder_id = ? WHERE id = ?")->execute([$new_folder_id, $fid]);
            header("Location: index.php?folder=" . $new_folder_id);
            exit;
        }
    } elseif ($_POST['action'] === 'move_multiple' && isset($_POST['item_ids']) && isset($_POST['item_types']) && isset($_POST['new_folder_id'])) {
        $item_ids = explode(',', $_POST['item_ids']);
        $item_types = explode(',', $_POST['item_types']);
        $new_folder_id = (int)$_POST['new_folder_id'];
        
        $role = $_SESSION['role'] ?? 'pracownik';
        $group = get_user_group();

        if (can_user_access_folder($db, $new_folder_id, $_SESSION['user_id'], $role, $group)) {
            foreach ($item_ids as $index => $id) {
                $id = (int)$id;
                $type = $item_types[$index] ?? 'file';

                if ($type === 'folder') {
                    // Check if folder is not the target itself or a parent of target (to avoid cycles)
                    if ($id === $new_folder_id) continue;
                    
                    if (can_user_access_folder($db, $id, $_SESSION['user_id'], $role, $group)) {
                        $db->prepare("UPDATE folders SET parent_id = ? WHERE id = ?")->execute([$new_folder_id, $id]);
                        log_activity($db, $_SESSION['user_id'], 'MOVE_FOLDER', "Przeniesiono folder ID: $id do folderu ID: $new_folder_id");
                    }
                } else { // type === 'file'
                    $stmt = $db->prepare("SELECT folder_id FROM files WHERE id = ?");
                    $stmt->execute([$id]);
                    $old_folder_id = $stmt->fetchColumn();
                    
                    if (can_user_access_folder($db, $old_folder_id, $_SESSION['user_id'], $role, $group)) {
                        $db->prepare("UPDATE files SET folder_id = ? WHERE id = ?")->execute([$new_folder_id, $id]);
                        log_activity($db, $_SESSION['user_id'], 'MOVE_FILE', "Przeniesiono plik ID: $id do folderu ID: $new_folder_id");
                    }
                }
            }
            $_SESSION['toast'] = "Pomyślnie przeniesiono " . count($item_ids) . " elementów! 🚀";
            header("Location: index.php?folder=" . $new_folder_id);
            exit;
        }
    } elseif ($_POST['action'] === 'delete_multiple' && isset($_POST['item_ids']) && isset($_POST['item_types'])) {
        $item_ids = explode(',', $_POST['item_ids']);
        $item_types = explode(',', $_POST['item_types']);
        $folder_id = (int)($_POST['current_folder_id'] ?? 0);
        
        $trash_count = 0;
        foreach ($item_ids as $index => $id) {
            $id = (int)$id;
            $type = $item_types[$index] ?? 'file';

            if ($type === 'folder') {
                if (is_admin() || is_zarzad() || is_private_tree($db, $id, $_SESSION['user_id'])) {
                    soft_delete_folder_recursive($db, $id);
                    log_activity($db, $_SESSION['user_id'], 'TRASH_FOLDER', "Masowo przeniesiono do kosza folder ID: $id");
                    $trash_count++;
                }
            } else { // type === 'file'
                $stmt = $db->prepare("SELECT folder_id, name, original_name FROM files WHERE id = ?");
                $stmt->execute([$id]);
                $file_info = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($file_info) {
                    if (is_admin() || is_zarzad() || is_private_tree($db, $file_info['folder_id'], $_SESSION['user_id'])) {
                        $db->prepare("UPDATE files SET deleted_at = datetime('now') WHERE id = ?")->execute([$id]);
                        log_activity($db, $_SESSION['user_id'], 'TRASH_FILE', "Masowo przeniesiono do kosza plik: " . ($file_info['original_name'] ?? 'Nieznany') . " (ID: $id)");
                        $trash_count++;
                    }
                }
            }
        }
        $_SESSION['toast'] = "Pomyślnie przeniesiono $trash_count elementów do kosza! 🗑️";
        header("Location: index.php?folder=" . $folder_id);
        exit;
    } elseif ($_POST['action'] === 'restore_item' && isset($_POST['item_id']) && isset($_POST['type'])) {
        $id = (int)$_POST['item_id'];
        $type = $_POST['type'];

        if (is_admin()) {
            if ($type === 'folder') {
                $db->prepare("UPDATE folders SET deleted_at = NULL WHERE id = ?")->execute([$id]);
                log_activity($db, $_SESSION['user_id'], 'RESTORE_FOLDER', "Przywrócono folder ID: $id (Kosz)");
            } else {
                $db->prepare("UPDATE files SET deleted_at = NULL WHERE id = ?")->execute([$id]);
                log_activity($db, $_SESSION['user_id'], 'RESTORE_FILE', "Przywrócono plik ID: $id (Kosz)");
            }
            $_SESSION['toast'] = "Pomyślnie przywrócono element z kosza! ♻️";
            header("Location: admin.php");
            exit;
        }
    }
}


?>
