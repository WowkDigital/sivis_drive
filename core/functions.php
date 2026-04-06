<?php
/**
 * Generate a NanoID-like secure random string
 */
function generate_nanoid($length = 21) {
    $alphabet = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $alphabetLength = strlen($alphabet);
    $id = '';
    for ($i = 0; $i < $length; $i++) {
        $id .= $alphabet[random_int(0, $alphabetLength - 1)];
    }
    return $id;
}
/**
 * Resolve folder ID (handles both internal ID and public NanoID)
 */
function resolve_folder_id($db, $id_raw) {
    if (!$id_raw) return 0;
    if (is_numeric($id_raw)) return (int)$id_raw;

    $stmt = $db->prepare("SELECT id FROM folders WHERE public_id = ?");
    $stmt->execute([$id_raw]);
    return (int)$stmt->fetchColumn() ?: 0;
}

/**
 * Check if a folder belongs to a user's private tree
 */
function is_private_tree($db, $folder_id, $user_id) {
    if (!$folder_id) return false;
    $curr = (int)$folder_id;
    while ($curr) {
        $stmt = $db->prepare("SELECT parent_id, owner_id FROM folders WHERE id = ?");
        $stmt->execute([$curr]);
        $folder = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$folder) return false;
        if ($folder['owner_id'] == $user_id) return true;
        if (!$folder['parent_id']) break;
        $curr = $folder['parent_id'];
    }
    return false;
}

/**
 * Check if a user can access a folder (recursive)
 */
function can_user_access_folder($db, $folder_id, $user_id, $user_role, $user_group) {
    if (!$folder_id) return false;
    if ($user_role === 'admin') return true;

    $curr = $folder_id;
    while ($curr) {
        $stmt = $db->prepare("
            SELECT f.parent_id, f.owner_id, f.access_groups, u.role as owner_role 
            FROM folders f 
            LEFT JOIN users u ON f.owner_id = u.id 
            WHERE f.id = ?
        ");
        $stmt->execute([$curr]);
        $folder = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$folder) return false;

        // Private folder check
        if ($folder['owner_id'] !== null) {
            // Own folder - always allow
            if ($folder['owner_id'] == $user_id) return true;
            
            // Zarząd viewing someone else
            if ($user_role === 'zarząd') {
                // Zarząd can see Employees, but NOT other Zarząd or Admin
                if ($folder['owner_role'] === 'pracownik') return true;
                return false; 
            }
            
            return false;
        }

        // Shared folder check (if restricted)
        if ($folder['access_groups'] && trim($folder['access_groups']) !== '') {
            $allowed = array_map(function($g) { return strtolower(trim($g)); }, explode(',', $folder['access_groups']));
            if (!in_array(strtolower($user_group), $allowed)) return false;
        }

        if (!$folder['parent_id']) break;
        $curr = $folder['parent_id'];
    }
    return true; // Default allowed for public root folders
}
/**
 * Check if a user can EDIT (Upload/Delete/Rename) a folder
 */
function can_user_edit_folder($db, $folder_id, $user_id, $user_role, $user_group) {
    if (!$folder_id) return false;
    if ($user_role === 'admin') return true;

    // First ensure they can even ACCESS it
    if (!can_user_access_folder($db, $folder_id, $user_id, $user_role, $user_group)) {
        return false;
    }

    // Zarząd can edit anything they can access (Employee trees or Shared folders)
    if ($user_role === 'zarząd') return true;
    
    // Regular employees can only edit their own private tree
    return is_private_tree($db, $folder_id, $user_id);
}

/**
 * Recursively delete a folder and its contents
 */
function delete_folder_recursive($db, $folder_id, $upload_dir) {
    // Get all children
    $stmt = $db->prepare("SELECT id FROM folders WHERE parent_id = ?");
    $stmt->execute([$folder_id]);
    $children = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($children as $child_id) {
        delete_folder_recursive($db, $child_id, $upload_dir);
    }
    
    // Delete files in this folder from disk
    $stmt = $db->prepare("SELECT name FROM files WHERE folder_id = ?");
    $stmt->execute([$folder_id]);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($files as $f) {
        @unlink($upload_dir . '/' . $f['name']);
    }
    
    // Delete from DB (even if soft-deleted)
    $db->prepare("DELETE FROM files WHERE folder_id = ?")->execute([$folder_id]);
    $db->prepare("DELETE FROM folders WHERE id = ?")->execute([$folder_id]);
}

/**
 * Recursively soft-delete a folder and its contents (move to Trash)
 */
function soft_delete_folder_recursive($db, $folder_id) {
    $now = date('Y-m-d H:i:s');
    
    // Soft-delete current folder
    $db->prepare("UPDATE folders SET deleted_at = ? WHERE id = ?")->execute([$now, $folder_id]);
    
    // Soft-delete files in this folder
    $db->prepare("UPDATE files SET deleted_at = ? WHERE folder_id = ?")->execute([$now, $folder_id]);
    
    // Recursively soft-delete subfolders
    $stmt = $db->prepare("SELECT id FROM folders WHERE parent_id = ?");
    $stmt->execute([$folder_id]);
    $children = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($children as $child_id) {
        soft_delete_folder_recursive($db, $child_id);
    }
}

/**
 * Permanently delete items from Trash older than 30 days
 */
function cleanup_garbage_collector($db, $upload_dir) {
    // Clean files
    $stmt = $db->prepare("SELECT id, name FROM files WHERE deleted_at IS NOT NULL AND deleted_at < datetime('now', '-30 days')");
    $stmt->execute();
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($files as $f) {
        @unlink($upload_dir . '/' . $f['name']);
        $db->prepare("DELETE FROM files WHERE id = ?")->execute([$f['id']]);
        log_activity($db, 0, 'GC_CLEANUP_FILE', "Automatycznie usunięto stary plik z kosza: " . $f['name']);
    }

    // Clean folders (where deleted_at is old)
    // We clean leaf folders first or just delete everything since we already unlinked files
    $stmt = $db->prepare("SELECT id, name FROM folders WHERE deleted_at IS NOT NULL AND deleted_at < datetime('now', '-30 days')");
    $stmt->execute();
    $folders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($folders as $fol) {
        $db->prepare("DELETE FROM folders WHERE id = ?")->execute([$fol['id']]);
        log_activity($db, 0, 'GC_CLEANUP_FOLDER', "Automatycznie usunięto stary folder z kosza: " . $fol['name']);
    }
}

/**
 * Get total stats for user's private tree
 */
function get_private_usage($db, $user_id) {
    // Get all folders in user's tree
    $folder_ids = [];
    $stmt = $db->prepare("SELECT id FROM folders WHERE owner_id = ? AND parent_id IS NULL");
    $stmt->execute([$user_id]);
    $to_check = $stmt->fetchAll(PDO::FETCH_COLUMN);

    while (!empty($to_check)) {
        $curr = array_pop($to_check);
        $folder_ids[] = $curr;
        $stmt = $db->prepare("SELECT id FROM folders WHERE parent_id = ?");
        $stmt->execute([$curr]);
        $children = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $to_check = array_merge($to_check, $children);
    }
    
    if (empty($folder_ids)) return ['count' => 0, 'size' => 0];
    
    $ids_str = implode(',', $folder_ids);
    $stats = $db->query("SELECT COUNT(*) as count, SUM(size) as size FROM files WHERE folder_id IN ($ids_str)")->fetch(PDO::FETCH_ASSOC);
    return ['count' => (int)$stats['count'], 'size' => (int)$stats['size']];
}

/**
 * Check if a folder tree has new files in the last 24 hours
 */
function has_recent_activity($db, $folder_id) {
    if (!$folder_id) return false;
    
    // Get all subfolders recursively
    $folder_ids = [$folder_id];
    $to_check = [$folder_id];
    while (!empty($to_check)) {
        $curr = array_pop($to_check);
        $stmt = $db->prepare("SELECT id FROM folders WHERE parent_id = ?");
        $stmt->execute([$curr]);
        $children = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($children as $cid) {
            $folder_ids[] = $cid;
            $to_check[] = $cid;
        }
    }
    
    $ids_str = implode(',', $folder_ids);
    $stmt = $db->prepare("SELECT COUNT(*) FROM files WHERE folder_id IN ($ids_str) AND created_at > datetime('now', '-1 day')");
    $stmt->execute();
    return (int)$stmt->fetchColumn() > 0;
}

/**
 * Log user activity
 */
function log_activity($db, $user_id, $action, $details = '') {
    try {
        $stmt = $db->prepare("INSERT INTO logs (user_id, action, details) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $action, $details]);
    } catch (Exception $e) {
        // Fail silently or handle
    }
}

/**
 * Get a value from the settings table
 */
function get_setting($db, $key, $default = null) {
    try {
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return ($val !== false) ? $val : $default;
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Update or insert a setting
 */
function set_setting($db, $key, $value) {
    try {
        $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value");
        $stmt->execute([$key, $value]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Send a notification to the admin via Telegram Bot if configured
 */
function send_admin_notification($db, $title, $message, $type = 'info') {
    $tg_token   = get_setting($db, 'telegram_bot_token', '');
    $tg_chat_id = get_setting($db, 'telegram_chat_id', '');

    // Always log to DB
    log_activity($db, 0, 'NOTIFICATION_SENT', "[$title] $message");

    if (empty($tg_token) || empty($tg_chat_id)) {
        return false;
    }

    $emoji = "ℹ️";
    if ($type === 'error')   $emoji = "❌";
    if ($type === 'warning') $emoji = "⚠️";
    if ($type === 'success') $emoji = "✅";

    $tg_text = "<b>$emoji Sivis Drive: $title</b>\n\n$message";
    $url = "https://api.telegram.org/bot{$tg_token}/sendMessage";

    $post_data = [
        'chat_id'    => $tg_chat_id,
        'text'       => $tg_text,
        'parse_mode' => 'HTML'
    ];

    $options = [
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\n",
            'content'       => json_encode($post_data),
            'ignore_errors' => true
        ]
    ];

    $context = stream_context_create($options);
    return @file_get_contents($url, false, $context) !== false;
}


