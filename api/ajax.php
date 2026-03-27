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
    $stmt = $db->prepare("SELECT f.*, (SELECT COUNT(*) FROM files WHERE folder_id = f.id AND deleted_at IS NULL) as file_count FROM folders f WHERE f.parent_id = ? AND f.deleted_at IS NULL ORDER BY f.name ASC");
    $stmt->execute([$fid]);
    $all_subfolders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get files
    $stmt = $db->prepare("SELECT * FROM files WHERE folder_id = ? AND deleted_at IS NULL ORDER BY created_at DESC");
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
        'folder_created_at' => !empty($folder['created_at']) ? date('d.m.Y', strtotime($folder['created_at'])) : 'Brak danych',
        'items' => $items,
        'has_more' => $has_more,
        'breadcrumbs' => $breadcrumbs,
        'can_edit' => (is_admin() || is_zarzad() || is_private_tree($db, $fid, $_SESSION['user_id'])),
        'is_private_tree' => is_private_tree($db, $fid, $_SESSION['user_id']),
        'user_role' => $_SESSION['role'] ?? 'pracownik',
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

if (isset($_GET['ajax_action']) && $_GET['ajax_action'] === 'rename_item' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        echo json_encode(['error' => 'Błąd CSRF']);
        exit;
    }

    $id = (int)$_POST['item_id'];
    $new_name = $_POST['new_name'];
    $type = $_POST['type'];

    if (empty($new_name)) {
        echo json_encode(['error' => 'Nazwa nie może być pusta']);
        exit;
    }

    if ($type === 'folder') {
        if (is_admin() || is_zarzad() || is_private_tree($db, $id, $_SESSION['user_id'])) {
            $stmt = $db->prepare("UPDATE folders SET name = ? WHERE id = ?");
            if ($stmt->execute([$new_name, $id])) {
                log_activity($db, $_SESSION['user_id'], 'RENAME_FOLDER', "Zmieniono nazwę folderu ID: $id na: $new_name (AJAX)");
                echo json_encode(['success' => true]);
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
                log_activity($db, $_SESSION['user_id'], 'RENAME_FILE', "Zmieniono nazwę pliku ID: $id na: $new_name (AJAX)");
                echo json_encode(['success' => true]);
                exit;
            }
        }
    }
    echo json_encode(['error' => 'Brak uprawnień lub błąd serwera']);
    exit;
}

if (isset($_GET['ajax_action']) && $_GET['ajax_action'] === 'create_folder' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        echo json_encode(['error' => 'Błąd CSRF']);
        exit;
    }

    $name = $_POST['name'];
    $parent_id = (int)$_POST['parent_id'];

    if (empty($name)) {
        echo json_encode(['error' => 'Nazwa nie może być pusta']);
        exit;
    }

    if (is_admin() || is_zarzad() || is_private_tree($db, $parent_id, $_SESSION['user_id'])) {
        $stmt = $db->prepare("SELECT owner_id FROM folders WHERE id = ?");
        $stmt->execute([$parent_id]);
        $owner_id = $stmt->fetchColumn();

        $stmt = $db->prepare("INSERT INTO folders (name, parent_id, owner_id) VALUES (?, ?, ?)");
        if ($stmt->execute([$name, $parent_id, $owner_id])) {
            log_activity($db, $_SESSION['user_id'], 'CREATE_FOLDER', "Utworzono folder: $name (AJAX)");
            echo json_encode(['success' => true, 'new_id' => $db->lastInsertId()]);
            exit;
        }
    }
    echo json_encode(['error' => 'Brak uprawnień lub błąd serwera']);
    exit;
}

if (isset($_GET['ajax_action']) && $_GET['ajax_action'] === 'create_shared_folder' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        echo json_encode(['error' => 'Błąd CSRF']);
        exit;
    }

    if (is_admin() || is_zarzad()) {
        $name = $_POST['name'];
        if (empty($name)) {
            echo json_encode(['error' => 'Nazwa nie może być pusta']);
            exit;
        }
        $stmt = $db->prepare("INSERT INTO folders (name, owner_id, access_groups) VALUES (?, NULL, 'zarząd,pracownicy')");
        if ($stmt->execute([$name])) {
            log_activity($db, $_SESSION['user_id'], 'CREATE_SHARED_FOLDER', "Utworzono folder udostępniony: $name (AJAX)");
            echo json_encode(['success' => true]);
            exit;
        }
    }
    echo json_encode(['error' => 'Brak uprawnień lub błąd serwera']);
    exit;
}

if (isset($_GET['ajax_action']) && $_GET['ajax_action'] === 'get_logs') {
    if (!is_admin()) {
        echo json_encode(['error' => 'Brak uprawnień']);
        exit;
    }

    $offset = (int)($_GET['offset'] ?? 0);
    $limit = (int)($_GET['limit'] ?? 30);

    $stmt = $db->prepare("SELECT l.*, u.email, u.display_name FROM logs l LEFT JOIN users u ON l.user_id = u.id ORDER BY l.created_at DESC LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format dates and prepare classes for UI
    foreach ($logs as &$l) {
        $l['formatted_date'] = date('d.m.Y H:i', strtotime($l['created_at']));
        $l['display_name'] = htmlspecialchars($l['display_name'] ?: 'System');
        $l['email'] = htmlspecialchars($l['email'] ?: '-');
        $l['details'] = htmlspecialchars($l['details']);
        $l['action'] = htmlspecialchars($l['action']);
        
        // Determine color class based on action
        $action = $l['action'];
        if (strpos($action, 'DELETE') !== false || strpos($action, 'TRASH') !== false) {
            $l['color_class'] = 'bg-red-500/10 text-red-100 border border-red-500/20';
        } elseif (strpos($action, 'RESTORE') !== false || strpos($action, 'SUCCESS') !== false) {
            $l['color_class'] = 'bg-emerald-500/10 text-emerald-300 border border-emerald-500/20';
        } elseif (strpos($action, 'ADMIN') !== false) {
            $l['color_class'] = 'bg-purple-500/10 text-purple-300 border border-purple-500/20';
        } elseif (strpos($action, 'UPLOAD') !== false || strpos($action, 'CREATE') !== false) {
            $l['color_class'] = 'bg-blue-500/10 text-blue-300 border border-blue-500/20';
        } elseif (strpos($action, 'MOVE') !== false || strpos($action, 'RENAME') !== false) {
            $l['color_class'] = 'bg-amber-500/10 text-amber-300 border border-amber-500/20';
        } elseif (strpos($action, 'LOGIN') !== false) {
            $l['color_class'] = 'bg-cyan-500/10 text-cyan-300 border border-cyan-500/20';
        } else {
            $l['color_class'] = 'bg-slate-500/10 text-slate-400 border border-slate-500/20';
        }
    }

    echo json_encode(['logs' => $logs, 'has_more' => count($logs) === $limit]);
    exit;
}
