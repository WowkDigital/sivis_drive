<?php
require_once 'core/auth.php';
require_once 'core/functions.php';
require_once 'core/db.php';
require_admin();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        die("Błąd weryfikacji CSRF. Powrót <a href='admin.php'>tutaj</a>");
    }

    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_user') {
            $email = $_POST['email'];
            $display_name = $_POST['display_name'];
            $role = $_POST['role'];
            $group = ($role === 'zarząd') ? 'zarząd' : 'pracownicy';
            $password = generate_random_password(16);
            $hash = password_hash($password, PASSWORD_DEFAULT);
            
            try {
                $public_id = generate_nanoid();
                $stmt = $db->prepare('INSERT INTO users (public_id, email, password_hash, role, user_group, display_name) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$public_id, $email, $hash, $role, $group, $display_name]);
                $new_user_password = $password;
                $new_user_email = $email;
                $new_user_role = $role;
                
                log_activity($db, $_SESSION['user_id'], 'ADMIN_ADD_USER', "Utworzono użytkownika: $email ($role)");

                $message = "Użytkownik został pomyślnie utworzony.";
            } catch (Exception $e) {
                $message = "Błąd: " . $e->getMessage();
            }
        } elseif ($_POST['action'] === 'reset_password' && isset($_POST['user_id'])) {
            $uid = (int)$_POST['user_id'];
            $password = generate_random_password(16);
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $uid]);
            $new_user_password = $password;
            
            $stmt_email = $db->prepare('SELECT email FROM users WHERE id = ?');
            $stmt_email->execute([$uid]);
            $new_user_email = $stmt_email->fetchColumn();
            
            log_activity($db, $_SESSION['user_id'], 'ADMIN_RESET_PASSWORD', "Zresetowano hasło dla użytkownika ID: $uid");

            $message = "Hasło użytkownika zostało zresetowane.";
        } elseif ($_POST['action'] === 'add_folder') {
            $name = $_POST['name'];
            $access = $_POST['access_groups'];
            $public_id = generate_nanoid();
            $stmt = $db->prepare('INSERT INTO folders (public_id, name, access_groups) VALUES (?, ?, ?)');
            $stmt->execute([$public_id, $name, $access]);
            
            log_activity($db, $_SESSION['user_id'], 'ADMIN_ADD_SHARED_FOLDER', "Utworzono folder udostępniony: $name");

            $message = "Folder dodany.";
        } elseif ($_POST['action'] === 'delete_user') {
            $uid = (int)$_POST['user_id'];
            if ($uid === 1) {
                $message = "Błąd: Nie możesz usunąć głównego konta administratora (Konto Instalacyjne).";
            } elseif ($uid != $_SESSION['user_id']) {
                $upload_dir = __DIR__ . '/../uploads'; // Adjusting path for include
                
                // 1. Delete all root folders owned by user (recursive)
                $stmt = $db->prepare("SELECT id FROM folders WHERE owner_id = ? AND parent_id IS NULL");
                $stmt->execute([$uid]);
                $user_roots = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                foreach ($user_roots as $root_id) {
                    delete_folder_recursive($db, $root_id, $upload_dir);
                }
                
                // 2. Delete user
                $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
                $stmt->execute([$uid]);
                
                log_activity($db, $_SESSION['user_id'], 'ADMIN_DELETE_USER', "Usunięto użytkownika ID: $uid (oraz wszystkie jego pliki)");

                $message = "Użytkownik oraz jego wszystkie pliki i foldery zostały usunięte.";
            } else {
                $message = "Błąd: Nie możesz usunąć sam siebie!";
            }
        } elseif ($_POST['action'] === 'delete_folder') {
            $fid = (int)$_POST['folder_id'];
            $upload_dir = __DIR__ . '/../uploads';
            delete_folder_recursive($db, $fid, $upload_dir);
            
            log_activity($db, $_SESSION['user_id'], 'ADMIN_DELETE_SHARED_FOLDER', "Usunięto folder udostępniony ID: $fid");

            $message = "Folder i cała jego struktura zostały usunięte.";
        } elseif ($_POST['action'] === 'update_folder') {
            $fid = (int)$_POST['folder_id'];
            $access = $_POST['access_groups'];
            $stmt = $db->prepare('UPDATE folders SET access_groups = ? WHERE id = ?');
            $stmt->execute([$access, $fid]);
            $message = "Uprawnienia folderu zaktualizowane.";
        } elseif ($_POST['action'] === 'rename_folder') {
            $fid = (int)$_POST['folder_id'];
            $new_name = $_POST['new_name'];
            $stmt = $db->prepare('UPDATE folders SET name = ? WHERE id = ?');
            $stmt->execute([$new_name, $fid]);
            
            log_activity($db, $_SESSION['user_id'], 'ADMIN_RENAME_FOLDER', "Zmieniono nazwę folderu ID: $fid na: $new_name");

            $message = "Nazwa folderu zaktualizowana.";
        } elseif ($_POST['action'] === 'update_user_role') {
            $uid = (int)$_POST['user_id'];
            if ($uid === 1) {
                $message = "Błąd: Nie można zmienić roli głównego administratora.";
            } else {
                $role = $_POST['role'];
                $group = ($role === 'zarząd') ? 'zarząd' : 'pracownicy';
                
                $stmt = $db->prepare('UPDATE users SET role = ?, user_group = ? WHERE id = ?');
                $stmt->execute([$role, $group, $uid]);
                
                log_activity($db, $_SESSION['user_id'], 'ADMIN_UPDATE_USER_ROLE', "Zmieniono rolę użytkownika ID: $uid na: $role");

                $message = "Rola użytkownika zaktualizowana.";
            }
        } elseif ($_POST['action'] === 'update_user_name') {
            $uid = (int)$_POST['user_id'];
            $name = $_POST['display_name'];
            $stmt = $db->prepare('UPDATE users SET display_name = ? WHERE id = ?');
            $stmt->execute([$name, $uid]);
            
            // Sync private root folder name
            $stmt = $db->prepare("UPDATE folders SET name = ? WHERE owner_id = ? AND parent_id IS NULL");
            $stmt->execute(['Pliki ' . $name, $uid]);

            $message = "Nazwa wyświetlana zaktualizowana.";
        } elseif ($_POST['action'] === 'restore_item') {
            $id = (int)$_POST['item_id'];
            $type = $_POST['type'];

            // 1. Get/Create "przywrócone" folder
            $stmt = $db->prepare("SELECT id FROM folders WHERE name = 'przywrócone' AND parent_id IS NULL AND owner_id IS NULL LIMIT 1");
            $stmt->execute();
            $target_folder_id = $stmt->fetchColumn();
            
            if (!$target_folder_id) {
                // Create the folder
                $stmt = $db->prepare("INSERT INTO folders (public_id, name, parent_id, owner_id, access_groups) VALUES (?, 'przywrócone', NULL, NULL, 'zarząd')");
                $stmt->execute([generate_nanoid()]);
                $target_folder_id = $db->lastInsertId();
            }

            if ($type === 'folder') {
                $db->prepare("UPDATE folders SET deleted_at = NULL, parent_id = ? WHERE id = ?")->execute([$target_folder_id, $id]);
                log_activity($db, $_SESSION['user_id'], 'ADMIN_RESTORE_FOLDER', "Przywrócono folder ID: $id do 'przywrócone'");
            } else {
                $db->prepare("UPDATE files SET deleted_at = NULL, folder_id = ? WHERE id = ?")->execute([$target_folder_id, $id]);
                log_activity($db, $_SESSION['user_id'], 'ADMIN_RESTORE_FILE', "Przywrócono plik ID: $id do 'przywrócone'");
            }
            $message = "Element został przywrócony do folderu 'przywrócone'.";
        } elseif ($_POST['action'] === 'delete_file') {
            $fid = (int)$_POST['file_id'];
            $stmt = $db->prepare("SELECT name, original_name FROM files WHERE id = ?");
            $stmt->execute([$fid]);
            $finfo = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($finfo) {
                @unlink(__DIR__ . '/../uploads/' . $finfo['name']);
                $db->prepare("DELETE FROM files WHERE id = ?")->execute([$fid]);
                log_activity($db, $_SESSION['user_id'], 'ADMIN_DELETE_FILE_PERM', "Trwale usunięto plik: " . $finfo['original_name']);
                $message = "Plik został trwale usunięty.";
            }
        } elseif ($_POST['action'] === 'run_backup') {
            require_once 'core/backup_logic.php';
            // run_backup is called inside backup_logic.php only if specifically triggered
            // But we already modified backup_logic.php to not run on include.
            // So we call it here ONCE.
            if (run_backup($db)) {
                $message = "Backup został pomyślnie wykonany.";
            } else {
                $message = "Błąd: Backup jest już w toku lub wystąpił inny błąd.";
            }
        } elseif ($_POST['action'] === 'delete_backup') {
            $filename = basename($_POST['filename']);
            $backup_path = $data_dir . '/backups/' . $filename;
            if (file_exists($backup_path) && strpos($filename, 'backup_') === 0 && substr($filename, -4) === '.zip') {
                @unlink($backup_path);
                log_activity($db, $_SESSION['user_id'], 'ADMIN_DELETE_BACKUP', "Usunięto plik backupu: $filename");
                $message = "Plik backupu został usunięty.";
            }
        } elseif ($_POST['action'] === 'reset_maintenance') {
            clearstatcache();
            if (file_exists($data_dir . '/maintenance.flag')) @unlink($data_dir . '/maintenance.flag');
            if (file_exists($data_dir . '/backup.lock')) @unlink($data_dir . '/backup.lock');
            
            log_activity($db, $_SESSION['user_id'], 'ADMIN_RESET_MAINTENANCE', "Ręcznie przerwano przerwę techniczną");
            
            $_SESSION['toast'] = "Blokada została zdjęta. System powinien wrócić do normy.";
            header("Location: admin.php");
            exit;
        } elseif ($_POST['action'] === 'update_settings') {
            $in_app_preview = isset($_POST['in_app_preview']) ? '1' : '0';
            $enforce_2fa = isset($_POST['enforce_2fa_admin']) ? '1' : '0';
            $webhook_url = $_POST['admin_webhook_url'] ?? '';
            $tg_token = $_POST['telegram_bot_token'] ?? '';
            $tg_chat_id = $_POST['telegram_chat_id'] ?? '';
            
            $old_enforce = get_setting($db, 'enforce_2fa_admin', '0');
            
            set_setting($db, 'in_app_preview', $in_app_preview);
            set_setting($db, 'enforce_2fa_admin', $enforce_2fa);
            set_setting($db, 'admin_webhook_url', $webhook_url);
            set_setting($db, 'telegram_bot_token', $tg_token);
            set_setting($db, 'telegram_chat_id', $tg_chat_id);
            
            if ($old_enforce === '0' && $enforce_2fa === '1') {
                // Feature was just enabled, record timestamp
                set_setting($db, 'enforce_2fa_last_update', time());
                log_activity($db, $_SESSION['user_id'], 'ADMIN_ENABLE_2FA', "Włączono wymuszanie 2FA dla administracji i zarządu");
            } elseif ($old_enforce === '1' && $enforce_2fa === '0') {
                log_activity($db, $_SESSION['user_id'], 'ADMIN_DISABLE_2FA', "Wyłączono wymuszanie 2FA dla administracji i zarządu");
            }
            
            log_activity($db, $_SESSION['user_id'], 'ADMIN_UPDATE_SETTINGS', "Zaktualizowano ustawienia systemowe (Notyfikacje: D:" . (empty($webhook_url) ? 'N' : 'Y') . ", T:" . (empty($tg_token) ? 'N' : 'Y') . ")");
            $message = "Ustawienia zostały zapisane.";
        }


    }
}

// Data Fetching
$users = $db->query("SELECT id, email, role, user_group, display_name, last_login FROM users")->fetchAll(PDO::FETCH_ASSOC);
$folders = $db->query("SELECT id, name, access_groups FROM folders WHERE owner_id IS NULL AND parent_id IS NULL AND deleted_at IS NULL")->fetchAll(PDO::FETCH_ASSOC);
$deleted_files = $db->query("SELECT f.*, u.email as u_email FROM files f LEFT JOIN users u ON f.uploaded_by = u.id WHERE f.deleted_at IS NOT NULL ORDER BY f.deleted_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$deleted_folders = $db->query("SELECT f.*, u.email as u_email FROM folders f LEFT JOIN users u ON f.owner_id = u.id WHERE f.deleted_at IS NOT NULL ORDER BY f.deleted_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$logs = $db->query("SELECT l.*, u.email, u.display_name FROM logs l LEFT JOIN users u ON l.user_id = u.id ORDER BY l.created_at DESC LIMIT 15")->fetchAll(PDO::FETCH_ASSOC);

// Stats
$total_files = $db->query("SELECT COUNT(*) FROM files WHERE deleted_at IS NULL")->fetchColumn();
$total_size = $db->query("SELECT SUM(size) FROM files WHERE deleted_at IS NULL")->fetchColumn() ?: 0;
$last_admin = $db->query("SELECT MAX(last_login) FROM users WHERE role = 'admin'")->fetchColumn();

// Backups list
$backups_list = glob($data_dir . '/backups/backup_*.zip');
usort($backups_list, function($a, $b) { return filemtime($b) - filemtime($a); });
$backups = [];
foreach ($backups_list as $b) {
    $backups[] = [
        'filename' => basename($b),
        'size' => filesize($b),
        'date' => date('d.m.Y H:i', filemtime($b))
    ];
}

$formatted_size = $total_size > 1024*1024*1024 
    ? round($total_size/(1024*1024*1024), 2) . ' GB' 
    : ($total_size > 1024*1024 
        ? round($total_size/(1024*1024), 2) . ' MB' 
        : round($total_size/1024) . ' KB');

$in_app_preview_enabled = get_setting($db, 'in_app_preview', '1') === '1';

