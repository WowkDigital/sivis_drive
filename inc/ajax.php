<?php
/**
 * Handle AJAX content loading
 */

if (isset($_GET['ajax_action']) && $_GET['ajax_action'] === 'get_folder_content') {
    $fid = (int)$_GET['folder_id'];
    $offset = (int)($_GET['offset'] ?? 0);
    $limit = 10;

    // Get folder info
    $stmt = $db->prepare("SELECT * FROM folders WHERE id = ?");
    $stmt->execute([$fid]);
    $folder = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$folder) {
        echo json_encode(['error' => 'Folder nie istnieje']);
        exit;
    }

    // Check permissions
    $can_access = is_admin() || is_zarzad() || is_private_tree($db, $fid, $_SESSION['user_id']);
    if (!$can_access) {
         echo json_encode(['error' => 'Brak uprawnień']);
         exit;
    }

    // Get subfolders (with file counts)
    $stmt = $db->prepare("SELECT f.*, (SELECT COUNT(*) FROM files WHERE folder_id = f.id) as file_count FROM folders f WHERE f.parent_id = ? ORDER BY f.name ASC");
    $stmt->execute([$fid]);
    $all_subfolders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get files
    $stmt = $db->prepare("SELECT * FROM files WHERE folder_id = ? ORDER BY created_at DESC");
    $stmt->execute([$fid]);
    $all_files = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Combine for pagination
    $combined = [];
    foreach ($all_subfolders as $sf) {
        $sf['is_folder'] = true;
        $combined[] = $sf;
    }
    foreach ($all_files as $f) {
        $f['is_folder'] = false;
        $combined[] = $f;
    }

    $total = count($combined);
    $items = array_slice($combined, $offset, $limit);
    $has_more = $total > ($offset + $limit);

    // Breadcrumbs for response
    $breadcrumbs = [];
    $curr = $folder;
    while ($curr) {
        $breadcrumbs[] = ['id' => $curr['id'], 'name' => $curr['name']];
        if (!$curr['parent_id']) break;
        $stmt = $db->prepare("SELECT * FROM folders WHERE id = ?");
        $stmt->execute([$curr['parent_id']]);
        $curr = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    $breadcrumbs = array_reverse($breadcrumbs);

    echo json_encode([
        'folder_name' => $folder['name'],
        'items' => $items,
        'has_more' => $has_more,
        'breadcrumbs' => $breadcrumbs,
        'can_edit' => (is_admin() || is_zarzad() || is_private_tree($db, $fid, $_SESSION['user_id'])),
        'total' => $total,
        'active_folder_id' => $fid
    ]);
    exit;
}

if (isset($_GET['ajax_action']) && $_GET['ajax_action'] === 'get_move_targets') {
    // Get all folders based on roles
    $all = $db->query("SELECT * FROM folders ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $accessible = [];
    
    foreach ($all as $f) {
        $can_see = false;
        if (is_admin()) {
            $can_see = true;
        } elseif (is_zarzad()) {
            $can_see = true; // Zarząd sees everything
        } else {
            // Private check
            if ($f['owner_id'] == $_SESSION['user_id'] || is_private_tree($db, $f['id'], $_SESSION['user_id'])) {
                $can_see = true;
            } elseif ($f['owner_id'] === null) {
                // Shared check
                $groups = array_map('trim', explode(',', $f['access_groups'] ?? ''));
                if (empty($f['access_groups']) || in_array(get_user_group(), $groups)) {
                    $can_see = true;
                }
            }
        }
        
        if ($can_see) {
            $accessible[] = [
                'id' => $f['id'],
                'name' => $f['name'],
                'parent_id' => $f['parent_id']
            ];
        }
    }
    
    echo json_encode($accessible);
    exit;
}
