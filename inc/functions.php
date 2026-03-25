<?php
/**
 * Helper functions for Sivis Drive
 */

/**
 * Check if a folder belongs to a user's private tree
 */
function is_private_tree($db, $folder_id, $user_id) {
    if (!$folder_id) return false;
    $curr = $folder_id;
    while ($curr) {
        $stmt = $db->prepare("SELECT parent_id, owner_id FROM folders WHERE id = ?");
        $stmt->execute([$curr]);
        $folder = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$folder) return false;
        if ($folder['owner_id'] == $user_id) return true;
        $curr = $folder['parent_id'];
    }
    return false;
}

/**
 * Get total stats for user's private tree
 */
function get_private_usage($db, $user_id) {
    // Get all folders in user's tree
    $folder_ids = [];
    $to_check = $db->query("SELECT id FROM folders WHERE owner_id = $user_id AND parent_id IS NULL")->fetchAll(PDO::FETCH_COLUMN);
    while (!empty($to_check)) {
        $curr = array_pop($to_check);
        $folder_ids[] = $curr;
        $children = $db->query("SELECT id FROM folders WHERE parent_id = $curr")->fetchAll(PDO::FETCH_COLUMN);
        $to_check = array_merge($to_check, $children);
    }
    
    if (empty($folder_ids)) return ['count' => 0, 'size' => 0];
    
    $ids_str = implode(',', $folder_ids);
    $stats = $db->query("SELECT COUNT(*) as count, SUM(size) as size FROM files WHERE folder_id IN ($ids_str)")->fetch(PDO::FETCH_ASSOC);
    return ['count' => (int)$stats['count'], 'size' => (int)$stats['size']];
}
