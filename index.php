<?php
/**
 * Sivis Drive - Main Entry Point
 * Refactored modular version
 */
require_once 'auth.php';
require_once 'inc/functions.php';

require_login();

$message = '';
$upload_dir = __DIR__ . '/uploads';

// Ensure user has a private root folder
$stmt = $db->prepare("SELECT id FROM folders WHERE owner_id = ? AND parent_id IS NULL");
$stmt->execute([$_SESSION['user_id']]);
$user_private_root_id = $stmt->fetchColumn();

if (!$user_private_root_id) {
    $stmt = $db->prepare("INSERT INTO folders (name, owner_id) VALUES (?, ?)");
    $stmt->execute(['Moje pliki (' . $_SESSION['email'] . ')', $_SESSION['user_id']]);
    $user_private_root_id = $db->lastInsertId();
}

// Sidebar folder fetching & Active folder determination
$shared_folders = [];
$my_folders = [];
$employees_folders = [];

$all_root_folders = $db->query("SELECT * FROM folders WHERE parent_id IS NULL")->fetchAll(PDO::FETCH_ASSOC);
$group = get_user_group();

foreach ($all_root_folders as $f) {
    if ($f['owner_id'] === null) { // Shared
        if (is_admin()) {
            $shared_folders[] = $f;
        } else {
            $access = array_map('trim', explode(',', $f['access_groups']));
            if (in_array(trim($group), $access) || empty($f['access_groups'])) $shared_folders[] = $f;
        }
    } elseif ($f['owner_id'] == $_SESSION['user_id']) { // Mine
        $my_folders[] = $f;
    } elseif (is_zarzad() || is_admin()) { // Employee (visible to Zarząd/Admin)
        $employees_folders[] = $f;
    }
}

$active_folder_id = isset($_GET['folder']) ? (int)$_GET['folder'] : ($shared_folders[0]['id'] ?? $my_folders[0]['id'] ?? 0);
$active_folder = null;
if ($active_folder_id) {
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
}

// Check permissions for current folder
$is_own_private = $active_folder_id && is_private_tree($db, $active_folder_id, $_SESSION['user_id']);
$can_edit = is_admin() || is_zarzad() || $is_own_private;

// Initial state for SSR (first 10 items)
$subfolders = [];
$files = [];
if ($active_folder) {
    $stmt = $db->prepare("SELECT f.*, (SELECT COUNT(*) FROM files WHERE folder_id = f.id) as file_count FROM folders f WHERE f.parent_id = ? ORDER BY f.name ASC");
    $stmt->execute([$active_folder_id]);
    $subfolders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("SELECT * FROM files WHERE folder_id = ? ORDER BY created_at DESC");
    $stmt->execute([$active_folder_id]);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle AJAX requests
require_once 'inc/ajax.php';

// Handle POST actions
require_once 'inc/actions.php';

// --- FRONTEND ---
require_once 'inc/header.php';
?>
<div class="flex flex-col lg:flex-row gap-6">
    <!-- Sidebar / Folders -->
    <?php require_once 'inc/sidebar.php'; ?>

    <!-- Content / Files -->
    <div class="w-full lg:w-3/4">
        <div class="bg-slate-800 shadow-xl rounded-2xl p-5 sm:p-6 border border-slate-700 min-h-[500px]">
            <div id="folder-content-wrapper">
                <?php if ($active_folder): ?>
                    <div id="breadcrumbs-container" class="mb-4 flex items-center text-xs text-slate-500 bg-slate-900/40 p-2 rounded-lg border border-slate-700/50">
                        <i data-lucide="home" class="w-3.5 h-3.5 mr-2"></i>
                        <?php foreach ($breadcrumbs as $i => $bc): ?>
                            <?php if ($i > 0): ?><i data-lucide="chevron-right" class="w-3 h-3 mx-1 opacity-50"></i><?php endif; ?>
                            <a href="javascript:void(0)" onclick="loadFolder(<?= $bc['id'] ?>, 0, true)" class="hover:text-blue-400 transition-colors <?= $i === count($breadcrumbs)-1 ? 'text-slate-300 font-bold' : '' ?>">
                                <?= htmlspecialchars($bc['name']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-6 pb-4 border-b border-slate-700">
                        <h2 id="current-folder-name" class="text-2xl font-bold text-slate-100 flex items-center">
                            <div class="p-2 bg-blue-500/10 rounded-lg mr-3">
                                <i data-lucide="folder-open" class="w-6 h-6 text-blue-400"></i>
                            </div>
                            <?= htmlspecialchars($active_folder['name']) ?>
                        </h2>
                        <div class="flex items-center gap-3">
                            <?php if ($can_edit): ?>
                            <button onclick="const n=prompt('Nazwa podfolderu:'); if(n){document.getElementById('new_folder_name').value=n; document.getElementById('new_folder_form').submit();}" class="text-xs font-bold px-3 py-1.5 bg-slate-700 hover:bg-slate-600 rounded-lg text-slate-200 border border-slate-600 transition-all flex items-center">
                                <i data-lucide="folder-plus" class="w-3.5 h-3.5 mr-1.5"></i> Nowy folder
                            </button>
                            <form id="new_folder_form" method="post" class="hidden">
                                <input type="hidden" name="action" value="create_folder">
                                <input type="hidden" name="name" id="new_folder_name">
                                <input type="hidden" name="parent_id" id="new_folder_parent_id" value="<?= $active_folder_id ?>">
                            </form>
                            <?php endif; ?>
                            <span id="file-count-badge" class="text-sm font-medium px-3 py-1 bg-slate-900 rounded-full text-slate-400 border border-slate-700"><?= count($files) + count($subfolders) ?> elementów</span>
                        </div>
                    </div>

                    <!-- File list container -->
                    <div id="items-container">
                         <!-- Loaded via AJAX on load -->
                    </div>

                    <div id="load-more-container" class="mt-8 text-center hidden">
                        <button id="load-more-btn" class="px-6 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold rounded-xl border border-slate-700 transition-all flex items-center mx-auto">
                            <i data-lucide="refresh-cw" class="w-4 h-4 mr-2"></i> Załaduj więcej
                        </button>
                    </div>

                    <?php if ($can_edit): ?>
                        <div id="drop-zone" class="bg-slate-900/50 p-12 rounded-3xl mt-12 border-2 border-dashed border-slate-700 hover:border-blue-500/50 hover:bg-blue-500/5 transition-all duration-300 group cursor-pointer text-center">
                            <form id="upload-form" method="post" enctype="multipart/form-data" class="flex flex-col items-center justify-center gap-5 w-full">
                                <input type="hidden" name="action" value="upload">
                                <input type="hidden" name="folder_id" id="upload-folder-id" value="<?= $active_folder['id'] ?>">
                                
                                <div class="p-6 bg-blue-500/10 rounded-2xl group-hover:scale-110 group-hover:bg-blue-500/20 transition-all duration-300">
                                    <i data-lucide="upload-cloud" class="w-16 h-16 text-blue-400"></i>
                                </div>
                                
                                <div>
                                    <h3 class="text-2xl font-bold text-slate-100 mb-2">Przeciągnij pliki tutaj 🚀</h3>
                                    <p class="text-slate-400">lub <span class="text-blue-400 font-semibold group-hover:underline">kliknij</span>, aby wybrać dokumenty z komputera 📁</p>
                                </div>

                                <input type="file" name="file" id="file-input" required class="hidden">
                                
                                <div class="text-xs text-slate-500 mt-2 uppercase tracking-widest font-bold">Max: 100MB • PDF, JPG, PNG, DOCX</div>
                            </form>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="text-center py-20 flex flex-col items-center">
                        <div class="bg-slate-900 border border-slate-700 p-5 rounded-full mb-5 shadow-inner">
                            <i data-lucide="folder-search" class="w-12 h-12 text-slate-500"></i>
                        </div>
                        <h3 class="text-xl font-bold text-slate-200 mb-2">Wybierz folder</h3>
                        <p class="text-slate-400 max-w-sm mx-auto">Wybierz folder z nawigacji obok, aby zobaczyć jego zawartość lub wgrać nowe pliki dokumentów.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require_once 'inc/footer.php'; ?>
