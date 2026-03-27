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

    $role = $_SESSION['role'] ?? 'pracownik';
    $group = get_user_group();
    if (!can_user_access_folder($db, $fid, $_SESSION['user_id'], $role, $group)) {
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

    $is_private_root = ($folder['owner_id'] && !$folder['parent_id']);
    $folder_name_html = '';
    if ($is_private_root && strpos($folder['name'], 'Pliki ') === 0) {
        $user_part = htmlspecialchars(substr($folder['name'], 6));
        $folder_name_html = 'Pliki <span class="bg-gradient-to-r from-blue-400 to-indigo-400 bg-clip-text text-transparent ml-2 underline decoration-blue-500/30 underline-offset-8 select-none">' . $user_part . '</span>';
    }

    echo json_encode([
        'folder_name' => $folder['name'],
        'folder_name_html' => $folder_name_html,
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
    $role = $_SESSION['role'] ?? 'pracownik';
    $group = get_user_group();
    $all = $db->query("SELECT * FROM folders ORDER BY CASE WHEN owner_id IS NULL THEN 0 ELSE 1 END, name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $accessible = [];
    
    foreach ($all as $f) {
        if (can_user_access_folder($db, $f['id'], $_SESSION['user_id'], $role, $group)) {
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
