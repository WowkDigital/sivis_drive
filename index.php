<?php
/**
 * Sivis Drive - Main Entry Point
 * Refactored modular version
 */
require_once 'core/auth.php';
require_once 'core/functions.php';

require_login();

$message = '';
$upload_dir = __DIR__ . '/uploads';

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
    $stmt = $db->prepare("INSERT INTO folders (name, owner_id) VALUES (?, ?)");
    $stmt->execute([$name, $_SESSION['user_id']]);
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
require_once 'api/ajax.php';

// Handle POST actions
require_once 'api/actions.php';

// --- FRONTEND ---
if (isset($_SESSION['toast'])) {
    $message = $_SESSION['toast'];
    unset($_SESSION['toast']);
}
require_once 'views/header.php';
?>
<div class="flex flex-col lg:flex-row gap-6">
    <!-- Sidebar / Folders -->
    <?php require_once 'views/sidebar.php'; ?>

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

                    <div class="flex flex-col gap-4 mb-6 pb-4 border-b border-slate-700">
                        <?php 
                            $is_private_tree = $active_folder_id && is_private_tree($db, $active_folder_id, $_SESSION['user_id']);
                            $role = $_SESSION['role'] ?? 'pracownik';
                        ?>
                        <div id="private-notice" class="flex items-center gap-2 px-3 py-1.5 bg-indigo-500/5 border border-indigo-500/20 rounded-lg text-[10px] text-indigo-400 font-bold uppercase tracking-wider <?= ($is_private_tree && $role === 'pracownik') ? '' : 'hidden' ?>">
                            <i data-lucide="shield-check" class="w-4 h-4"></i>
                            <span>Informacja: Zawartość tego folderu jest widoczna dla Zarządu.</span>
                        </div>
                        <div class="flex items-center group">
                            <?php 
                                $is_private_root = ($active_folder['owner_id'] && !$active_folder['parent_id']);
                                $raw_name = htmlspecialchars($active_folder['name']);
                                if ($is_private_root && strpos($active_folder['name'], 'Pliki ') === 0) {
                                    $user_part = htmlspecialchars(substr($active_folder['name'], 6));
                                    $name_to_show = 'Pliki <span class="bg-gradient-to-r from-blue-400 to-indigo-400 bg-clip-text text-transparent ml-2 underline decoration-blue-500/30 underline-offset-8 select-none">' . $user_part . '</span>';
                                } else {
                                    $name_to_show = $raw_name;
                                }
                            ?>
                            <h2 id="current-folder-name" class="text-xl sm:text-2xl font-bold text-slate-100 flex items-center min-w-0 flex-1">
                                <div class="p-2 bg-blue-500/10 rounded-lg mr-2 sm:mr-3 shrink-0">
                                    <i data-lucide="folder-open" class="w-5 h-5 sm:w-6 sm:h-6 text-blue-400"></i>
                                </div>
                                <span class="truncate"><?= $name_to_show ?></span>
                            </h2>
                        </div>
                        <div class="flex items-center gap-3">
                            <button onclick="copyFolderLink(this)" class="p-2.5 bg-slate-800 hover:bg-slate-700 rounded-xl text-slate-400 hover:text-blue-400 border border-slate-700 transition-all hover:scale-105 active:scale-95" title="Kopiuj link do folderu">
                                <i data-lucide="share-2" class="w-5 h-5"></i>
                            </button>
                            <button id="new-folder-btn" onclick="showPromptModal('Nazwa podfolderu:', '', (n) => createNewFolder(n))" class="text-xs font-bold px-3 py-1.5 bg-slate-700 hover:bg-slate-600 rounded-lg text-slate-200 border border-slate-600 transition-all flex items-center hidden">
                                <i data-lucide="folder-plus" class="w-3.5 h-3.5 mr-1.5"></i> Nowy folder
                            </button>
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

                    <!-- Always present, visibility toggled via JS -->
                    <div id="drop-zone" class="bg-slate-900/50 p-10 rounded-3xl mt-10 border-2 border-dashed border-slate-700 hover:border-blue-500/50 hover:bg-blue-500/5 transition-all duration-300 group cursor-pointer text-center relative overflow-hidden hidden">
                        <!-- Uploading Overlay -->
                        <div id="upload-progress-overlay" class="absolute inset-0 bg-slate-900/90 backdrop-blur-md z-20 flex flex-col items-center justify-center p-8 opacity-0 pointer-events-none transition-opacity duration-300">
                            <div class="w-20 h-20 mb-6 relative">
                                <div class="absolute inset-0 rounded-full border-4 border-blue-500/20"></div>
                                <div id="progress-ring" class="absolute inset-0 rounded-full border-4 border-blue-500 border-t-transparent animate-spin"></div>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <i data-lucide="upload-cloud" class="w-8 h-8 text-blue-400"></i>
                                </div>
                            </div>
                            <h4 id="upload-status-text" class="text-xl font-bold text-white mb-2">Przygotowanie...</h4>
                            <div class="w-full max-w-xs bg-slate-700 h-2 rounded-full overflow-hidden mb-3">
                                <div id="upload-progress-bar" class="w-0 h-full bg-blue-500 transition-all duration-300 shadow-[0_0_10px_rgba(59,130,246,0.5)]"></div>
                            </div>
                            <p id="upload-percent-text" class="text-blue-400 font-mono text-sm font-bold">0%</p>
                        </div>

                        <form id="upload-form" method="post" enctype="multipart/form-data" class="flex flex-col items-center justify-center gap-5 w-full">
                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
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

    <!-- Move File Modal -->
    <div id="move-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/80 backdrop-blur-sm hidden opacity-0 transition-opacity duration-300">
        <div class="bg-slate-800 w-full max-w-lg rounded-3xl border border-slate-700 shadow-2xl overflow-hidden transform scale-95 transition-transform duration-300">
            <div class="p-6 border-b border-slate-700 flex justify-between items-center bg-slate-800/50">
                <div class="flex items-center">
                    <div class="p-2 bg-purple-500/10 rounded-xl mr-4">
                        <i data-lucide="folder-input" class="w-6 h-6 text-purple-400"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-white leading-tight">Przenieś plik</h3>
                        <p id="move-modal-filename" class="text-xs text-slate-500 truncate max-w-[200px]"></p>
                    </div>
                </div>
                <button onclick="closeMoveModal()" class="p-2 hover:bg-slate-700 rounded-xl text-slate-400 hover:text-white transition-all">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            
            <div class="p-2 max-h-[400px] overflow-y-auto" id="modal-folder-list">
                <!-- Sorted Tree via JS -->
            </div>
            
            <form id="modal-move-form" method="post" class="hidden">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="move_file">
                <input type="hidden" name="file_id" id="modal-move-file-id">
                <input type="hidden" name="new_folder_id" id="modal-move-new-folder-id">
            </form>

            <div class="p-4 bg-slate-900/30 border-t border-slate-700 flex justify-end gap-3">
                <button onclick="closeMoveModal()" class="px-5 py-2.5 text-sm font-bold text-slate-400 hover:text-slate-200 transition-colors">Anuluj</button>
            </div>
        </div>
    </div>
    <?php require_once 'views/action_modal.php'; ?>
    <?php require_once 'views/footer.php'; ?>
