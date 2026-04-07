<?php
/**
 * Sivis Drive - Main Entry Point
 * Refactored modular version
 */
require_once __DIR__ . '/core/auth.php';

require_login();

$message = '';
$upload_dir = __DIR__ . '/uploads';

// Garbage Collector (Cleanup trash older than 30 days)
cleanup_garbage_collector($db, $upload_dir);

$in_app_preview_enabled = get_setting($db, 'in_app_preview', '1') === '1';



// Ensure user has a private root folder
$stmt = $db->prepare("SELECT id FROM folders WHERE owner_id = ? AND parent_id IS NULL");
$stmt->execute([$_SESSION['user_id']]);
$user_private_root_id = $stmt->fetchColumn();

if (!$user_private_root_id) {
    if (empty($_SESSION['display_name'])) {
        $stmt = $db->prepare("SELECT display_name FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $_SESSION['display_name'] = $stmt->fetchColumn();
    }
    $name = !empty($_SESSION['display_name']) ? 'Pliki ' . $_SESSION['display_name'] : 'Moje pliki (' . $_SESSION['email'] . ')';
    $stmt = $db->prepare("INSERT INTO folders (public_id, name, owner_id) VALUES (?, ?, ?)");
    $stmt->execute([generate_nanoid(), $name, $_SESSION['user_id']]);
    $user_private_root_id = $db->lastInsertId();
}

// Sidebar folder fetching & Active folder determination
$shared_folders = [];
$my_folders = [];
$employees_folders = [];

$all_root_folders = $db->query("
    SELECT f.*, u.role as owner_role 
    FROM folders f 
    LEFT JOIN users u ON f.owner_id = u.id 
    WHERE f.parent_id IS NULL AND f.deleted_at IS NULL
")->fetchAll(PDO::FETCH_ASSOC);

$group = get_user_group();

foreach ($all_root_folders as $f) {
    if ($f['owner_id'] === null) { // Shared
        if (is_admin()) {
            $shared_folders[] = $f;
        } else {
            $access = array_map(function($g) { return strtolower(trim($g)); }, explode(',', $f['access_groups']));
            if (in_array(strtolower($group), $access) || empty($f['access_groups'])) $shared_folders[] = $f;
        }
    } elseif ($f['owner_id'] == $_SESSION['user_id']) { // Mine
        $my_folders[] = $f;
    } elseif (is_admin() || (is_zarzad() && $f['owner_role'] === 'pracownik')) { // Employee (visible to Zarząd/Admin)
        $employees_folders[] = $f;
    }
}

$get_f = $_GET['folder'] ?? null;
$active_folder_id = 0;
if ($get_f) {
    $stmt = $db->prepare("SELECT id FROM folders WHERE id = ? OR public_id = ?");
    $stmt->execute([$get_f, $get_f]);
    $active_folder_id = (int)$stmt->fetchColumn();
} else {
    $active_folder_id = ($shared_folders[0]['id'] ?? $my_folders[0]['id'] ?? 0);
}

$active_folder = null;
if ($active_folder_id) {
    $role = $_SESSION['role'] ?? 'pracownik';
    $group = get_user_group();
    if (!can_user_access_folder($db, $active_folder_id, $_SESSION['user_id'], $role, $group)) {
        header("Location: index.php");
        exit;
    }

    $stmt = $db->prepare("SELECT * FROM folders WHERE id = ?");
    $stmt->execute([$active_folder_id]);
    $active_folder = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Breadcrumbs
$breadcrumbs = [];
if ($active_folder) {
    $curr = $active_folder;
    while ($curr) {
        $breadcrumbs[] = $curr;
        if (!$curr['parent_id']) break;
        $stmt = $db->prepare("SELECT * FROM folders WHERE id = ?");
        $stmt->execute([$curr['parent_id']]);
        $curr = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    $breadcrumbs = array_reverse($breadcrumbs);
    $active_root_folder = $curr; // Store the root of the current branch
}

// Check permissions for current folder
$is_own_private = $active_folder_id && is_private_tree($db, $active_folder_id, $_SESSION['user_id']);
$can_edit = is_admin() || is_zarzad() || $is_own_private;

// Initial state for SSR (first 10 items)
$subfolders = [];
$files = [];
if ($active_folder) {
    $stmt = $db->prepare("SELECT f.*, (SELECT COUNT(*) FROM files WHERE folder_id = f.id AND deleted_at IS NULL) as file_count FROM folders f WHERE f.parent_id = ? AND f.deleted_at IS NULL ORDER BY f.name ASC");
    $stmt->execute([$active_folder_id]);
    $subfolders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("SELECT * FROM files WHERE folder_id = ? AND deleted_at IS NULL ORDER BY created_at DESC");
    $stmt->execute([$active_folder_id]);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle AJAX requests
require_once 'api/ajax.php';

// Handle POST actions
require_once 'api/actions.php';

// --- FRONTEND ---
if (isset($_SESSION['toast'])) {
    $message = $_SESSION['toast'];
    unset($_SESSION['toast']);
}
if (isset($_SESSION['toast_error'])) {
    $error_message = $_SESSION['toast_error'];
    unset($_SESSION['toast_error']);
}
require_once 'views/header.php';
?>
<div class="flex flex-col lg:flex-row gap-6">
    <!-- Sidebar / Folders -->
    <?php require_once 'views/sidebar.php'; ?>

    <!-- Content / Files -->
    <div class="w-full lg:w-3/4">
        <?php require_once 'views/drive.php'; ?>
    </div>
    </div>
    <?php require_once 'views/action_modal.php'; ?>
    <?php require_once 'views/footer.php'; ?>

