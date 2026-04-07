<?php
/**
 * Handle AJAX content loading
 */
require_once dirname(__DIR__) . '/core/auth.php';

if (isset($_GET['ajax_action']) && $_GET['ajax_action'] === 'get_folder_content') {
    $fid_raw = $_GET['folder_id'];
    $offset = (int)($_GET['offset'] ?? 0);
    $limit = 10;
    
    // Support both internal ID and NanoID
    $stmt = $db->prepare("SELECT * FROM folders WHERE id = ? OR public_id = ?");
    $stmt->execute([$fid_raw, $fid_raw]);
    $folder = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($folder) {
        $fid = (int)$folder['id'];
    }

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

    $breadcrumbs = [];
    $curr = $folder;
    while ($curr) {
        $breadcrumbs[] = ['id' => $curr['public_id'] ?: $curr['id'], 'name' => $curr['name']];
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
        'can_edit' => can_user_edit_folder($db, $fid, $_SESSION['user_id'], $role, $group),
        'is_private_tree' => is_private_tree($db, $fid, $_SESSION['user_id']),
        'user_role' => $_SESSION['role'] ?? 'pracownik',
        'total' => $total,
        'folder_id' => $fid,
        'active_folder_id' => $folder['public_id'] ?: $fid
    ]);
    exit;
}

if (isset($_GET['ajax_action']) && $_GET['ajax_action'] === 'get_move_targets') {
    $role = $_SESSION['role'] ?? 'pracownik';
    $group = get_user_group();
    $all = $db->query("SELECT * FROM folders ORDER BY CASE WHEN owner_id IS NULL THEN 0 ELSE 1 END, name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $accessible = [];
    
    // 1. Prepare a lookup map of internal ID -> public NanoID
    $id_map = [0 => null];
    foreach ($all as $f) {
        $id_map[(int)$f['id']] = $f['public_id'] ?: (int)$f['id'];
    }

    foreach ($all as $f) {
        if (can_user_access_folder($db, $f['id'], $_SESSION['user_id'], $role, $group)) {
            $accessible[] = [
                'id' => $f['public_id'] ?: $f['id'],
                'name' => $f['name'],
                'parent_id' => $f['parent_id'] ? ($id_map[(int)$f['parent_id']] ?? null) : null,
                'owner_id' => $f['owner_id']
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
        $role = $_SESSION['role'] ?? 'pracownik';
        $group = get_user_group();
        if (is_admin() || can_user_access_folder($db, $id, $_SESSION['user_id'], $role, $group)) {
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
        if (is_admin() || can_user_access_folder($db, $folder_id, $_SESSION['user_id'], $role, $group)) {
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

    $parent_id = resolve_folder_id($db, $_POST['parent_id']);
    $role = $_SESSION['role'] ?? 'pracownik';
    $group = get_user_group();

    if (can_user_edit_folder($db, $parent_id, $_SESSION['user_id'], $role, $group)) {
        $stmt = $db->prepare("SELECT owner_id FROM folders WHERE id = ?");
        $stmt->execute([$parent_id]);
        $owner_id = $stmt->fetchColumn();

        $stmt = $db->prepare("INSERT INTO folders (public_id, name, parent_id, owner_id) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([generate_nanoid(), $name, $parent_id, $owner_id])) {
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

if (isset($_GET['ajax_action']) && $_GET['ajax_action'] === 'run_tests') {
    if (!is_admin()) {
        echo json_encode(['error' => 'Brak uprawnień']);
        exit;
    }
    
    // The test script handles JSON output if called via web
    require_once ROOT_DIR . '/tests/permission_test.php';
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

if (isset($_GET['ajax_action']) && $_GET['ajax_action'] === 'get_trash') {
    if (!is_admin()) {
        echo json_encode(['error' => 'Brak uprawnień']);
        exit;
    }

    $offset = (int)($_GET['offset'] ?? 0);
    $limit = (int)($_GET['limit'] ?? 5);

    $q_files = "SELECT f.id, f.original_name as name, f.size, f.deleted_at, u.email as u_email, 'file' as type 
                FROM files f LEFT JOIN users u ON f.uploaded_by = u.id 
                WHERE f.deleted_at IS NOT NULL";
    $q_folders = "SELECT f.id, f.name, 0 as size, f.deleted_at, u.email as u_email, 'folder' as type 
                  FROM folders f LEFT JOIN users u ON f.owner_id = u.id 
                  WHERE f.deleted_at IS NOT NULL";

    $stmt = $db->prepare("($q_files) UNION ALL ($q_folders) ORDER BY deleted_at DESC LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as &$item) {
        $item['formatted_date'] = date('d.m.Y H:i', strtotime($item['deleted_at']));
        $item['formatted_size'] = $item['type'] === 'file' ? round($item['size'] / 1024) . ' KB' : '-';
        $item['u_email'] = htmlspecialchars($item['u_email'] ?: ($item['type'] === 'folder' ? 'System' : 'Nieznany'));
        $item['name'] = htmlspecialchars($item['name']);
    }

    echo json_encode(['items' => $items, 'has_more' => count($items) === $limit]);
    exit;
}
