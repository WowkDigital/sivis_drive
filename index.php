<?php
require_once 'auth.php';
require_login();

$message = '';
$upload_dir = __DIR__ . '/uploads';

// Upload & Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'upload' && isset($_FILES['file']) && isset($_POST['folder_id']) && can_manage_files()) {
        $folder_id = (int)$_POST['folder_id'];
        $file = $_FILES['file'];
        
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors = [
                UPLOAD_ERR_INI_SIZE => 'Plik przekracza limit wielkości (upload_max_filesize w php.ini).',
                UPLOAD_ERR_FORM_SIZE => 'Plik przekracza limit wielkości formularza.',
                UPLOAD_ERR_PARTIAL => 'Plik został wysłany tylko częściowo.',
                UPLOAD_ERR_NO_FILE => 'Nie wybrano pliku.',
                UPLOAD_ERR_NO_TMP_DIR => 'Brak folderu tymczasowego na serwerze.',
                UPLOAD_ERR_CANT_WRITE => 'Błąd zapisu pliku na dysku (brak uprawnień?).',
                UPLOAD_ERR_EXTENSION => 'Rozszerzenie PHP zatrzymało wysyłanie pliku.'
            ];
            $message = "Błąd PHP: " . ($errors[$file['error']] ?? 'Nieznany błąd (' . $file['error'] . ').');
        } elseif ($file['size'] > 100 * 1024 * 1024) {
            $message = "Błąd: Plik jest za duży (max 100MB).";
        } else {
            if (!is_writable($upload_dir)) {
                $message = "Błąd: Brak uprawnień do zapisu w folderze uploads.";
            } else {
                $unique_name = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9.\-_]/', '', basename($file['name']));
                $dest = $upload_dir . '/' . $unique_name;
                
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $stmt = $db->prepare('INSERT INTO files (folder_id, name, original_name, size, uploaded_by) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute([$folder_id, $unique_name, $file['name'], $file['size'], $_SESSION['user_id']]);
                    $message = "Plik został dodany.";
                } else {
                    $message = "Błąd podczas przenoszenia pliku z folderu tymczasowego. Sprawdź logi serwera.";
                }
            }
        }
    } elseif ($_POST['action'] === 'delete_file' && isset($_POST['file_id']) && can_manage_files()) {
        $fid = (int)$_POST['file_id'];
        $stmt = $db->prepare("SELECT name FROM files WHERE id = ?");
        $stmt->execute([$fid]);
        $fname = $stmt->fetchColumn();
        if ($fname) {
            @unlink($upload_dir . '/' . $fname);
            $stmt = $db->prepare("DELETE FROM files WHERE id = ?");
            $stmt->execute([$fid]);
            $message = "Plik został usunięty.";
        }
    } elseif ($_POST['action'] === 'move_file' && isset($_POST['file_id']) && isset($_POST['new_folder_id']) && can_manage_files()) {
        $fid = (int)$_POST['file_id'];
        $new_folder_id = (int)$_POST['new_folder_id'];
        $stmt = $db->prepare("UPDATE files SET folder_id = ? WHERE id = ?");
        $stmt->execute([$new_folder_id, $fid]);
        $message = "Plik został przeniesiony.";
    }
}

// Fetch accessible folders
$folders = [];
$group = get_user_group();
$all_folders = $db->query("SELECT * FROM folders")->fetchAll(PDO::FETCH_ASSOC);

foreach ($all_folders as $f) {
    if (is_admin()) {
        $folders[] = $f;
    } else {
        $access = array_map('trim', explode(',', $f['access_groups']));
        if (in_array(trim($group), $access) || empty($f['access_groups'])) {
            $folders[] = $f;
        }
    }
}

$active_folder_id = isset($_GET['folder']) ? (int)$_GET['folder'] : (count($folders) > 0 ? $folders[0]['id'] : 0);
$active_folder = null;
foreach ($folders as $f) {
    if ($f['id'] == $active_folder_id) {
        $active_folder = $f;
        break;
    }
}

$files = [];
if ($active_folder) {
    $stmt = $db->prepare("SELECT * FROM files WHERE folder_id = ? ORDER BY created_at DESC");
    $stmt->execute([$active_folder_id]);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="pl" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sivis Drive</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        slate: {
                            800: '#1e293b',
                            900: '#0f172a',
                        }
                    }
                }
            }
        }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-slate-900 text-slate-200 min-h-screen">
    <nav class="bg-slate-800 shadow-lg border-b border-slate-700">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between md:h-16 py-4 md:py-0 items-center gap-4 md:gap-0">
                <div class="flex items-center space-x-3 text-blue-400">
                    <div class="p-2 bg-blue-500/10 rounded-xl">
                        <i data-lucide="cloud" class="w-8 h-8"></i>
                    </div>
                    <span class="font-bold text-2xl tracking-tight text-white">Sivis Drive</span>
                </div>
                <div class="flex flex-wrap justify-center gap-3 items-center">
                    <span class="text-sm text-slate-400 hidden sm:inline">Zalogowano: <span class="font-semibold text-slate-200"><?= htmlspecialchars($_SESSION['email']) ?></span></span>
                    <?php if (is_admin()): ?>
                        <a href="admin.php" class="text-blue-400 hover:text-blue-300 hover:bg-slate-700 px-3 py-2 rounded-lg font-medium flex items-center transition-colors">
                            <i data-lucide="shield-check" class="w-4 h-4 mr-1.5"></i> Admin
                        </a>
                    <?php endif; ?>
                    <a href="logout.php" class="text-red-400 hover:text-red-300 flex items-center bg-red-500/10 hover:bg-red-500/20 px-4 py-2 rounded-lg font-medium transition-colors">
                        <i data-lucide="log-out" class="w-4 h-4 mr-1.5"></i> Wyloguj
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <?php if ($message): ?>
            <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-xl relative mb-6 backdrop-blur-sm" role="alert">
                <span class="block sm:inline"><?= htmlspecialchars($message) ?></span>
            </div>
        <?php endif; ?>

        <div class="flex flex-col lg:flex-row gap-6">
            <!-- Sidebar / Folders -->
            <div class="w-full lg:w-1/4">
                <div class="bg-slate-800 shadow-xl rounded-2xl p-5 border border-slate-700">
                    <h2 class="text-lg font-bold mb-4 flex items-center text-slate-100"><i data-lucide="folders" class="w-5 h-5 mr-2 text-blue-400"></i> Moje Foldery</h2>
                    <ul class="space-y-1.5">
                        <?php if (empty($folders)): ?>
                            <li class="text-slate-500 text-sm py-2 px-3 bg-slate-900/50 rounded-lg">Brak dostępu do folderów.</li>
                        <?php endif; ?>
                        <?php foreach ($folders as $f): 
                            $is_restricted = trim($f['access_groups']) === 'zarząd';
                        ?>
                            <li>
                                <a href="?folder=<?= $f['id'] ?>" class="flex items-center justify-between px-4 py-3 rounded-xl transition-all duration-200 <?= $f['id'] == $active_folder_id ? 'bg-blue-500/10 border border-blue-500/20 text-blue-400 font-medium' : 'text-slate-400 hover:bg-slate-700 hover:text-slate-200' ?>">
                                    <div class="flex items-center min-w-0">
                                        <i data-lucide="<?= $f['id'] == $active_folder_id ? 'folder-open' : 'folder' ?>" class="w-5 h-5 mr-3 shrink-0 <?= $f['id'] == $active_folder_id ? 'text-blue-400' : 'text-slate-500' ?>"></i>
                                        <span class="truncate"><?= htmlspecialchars($f['name']) ?></span>
                                    </div>
                                    <div class="ml-2 shrink-0 opacity-60 hover:opacity-100 transition-opacity" title="<?= $is_restricted ? 'Tylko Zarząd' : 'Wszyscy pracownicy' ?>">
                                        <i data-lucide="<?= $is_restricted ? 'shield-check' : 'users' ?>" class="w-3.5 h-3.5 <?= $is_restricted ? 'text-orange-400' : 'text-slate-500' ?>"></i>
                                    </div>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Content / Files -->
            <div class="w-full lg:w-3/4">
                <div class="bg-slate-800 shadow-xl rounded-2xl p-5 sm:p-6 border border-slate-700 min-h-[500px]">
                    <?php if ($active_folder): ?>
                        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-6 pb-4 border-b border-slate-700">
                            <h2 class="text-2xl font-bold text-slate-100 flex items-center">
                                <div class="p-2 bg-blue-500/10 rounded-lg mr-3">
                                    <i data-lucide="folder-open" class="w-6 h-6 text-blue-400"></i>
                                </div>
                                <?= htmlspecialchars($active_folder['name']) ?>
                            </h2>
                            <span class="text-sm font-medium px-3 py-1 bg-slate-900 rounded-full text-slate-400 border border-slate-700"><?= count($files) ?> plików</span>
                        </div>

                        <?php if (can_manage_files()): ?>
                            <!-- Upload form for admin and zarząd -->
                            <div id="drop-zone" class="bg-slate-900/50 p-12 rounded-3xl mb-8 border-2 border-dashed border-slate-700 hover:border-blue-500/50 hover:bg-blue-500/5 transition-all duration-300 group cursor-pointer text-center">
                                <form id="upload-form" method="post" enctype="multipart/form-data" class="flex flex-col items-center justify-center gap-5 w-full">
                                    <input type="hidden" name="action" value="upload">
                                    <input type="hidden" name="folder_id" value="<?= $active_folder['id'] ?>">
                                    
                                    <div class="p-6 bg-blue-500/10 rounded-2xl group-hover:scale-110 group-hover:bg-blue-500/20 transition-all duration-300">
                                        <i data-lucide="upload-cloud" class="w-16 h-16 text-blue-400"></i>
                                    </div>
                                    
                                    <div>
                                        <h3 class="text-2xl font-bold text-slate-100 mb-2">Przeciągnij pliki tutaj 🚀</h3>
                                        <p class="text-slate-400">lub <span class="text-blue-400 font-semibold group-hover:underline">kliknij</span>, aby wybrać dokumenty z komputera 📁</p>
                                    </div>

                                    <input type="file" name="file" id="file-input" required class="hidden">
                                    
                                    <div class="text-xs text-slate-500 mt-2 uppercase tracking-widest font-bold">Max: 100MB • PDF, DOCX, JPG, PNG</div>
                                </form>
                            </div>
                        <?php endif; ?>

                        <!-- File list -->
                        <?php if (empty($files)): ?>
                            <div class="text-center py-16 flex flex-col items-center">
                                <div class="bg-slate-900 p-4 rounded-full mb-4 ring-1 ring-slate-700">
                                    <i data-lucide="file-question" class="w-10 h-10 text-slate-500"></i>
                                </div>
                                <h3 class="text-lg font-medium text-slate-300">Folder jest pusty</h3>
                                <p class="text-slate-500 mt-2">Brak plików w tym folderze.</p>
                            </div>
                        <?php else: ?>
                            <div class="overflow-x-auto -mx-5 sm:mx-0">
                                <div class="inline-block min-w-full align-middle">
                                    <table class="w-full">
                                        <thead>
                                            <tr class="border-b border-slate-700 text-left">
                                                <th class="px-5 py-4 text-xs font-semibold text-slate-400 uppercase tracking-wider">Nazwa pliku</th>
                                                <th class="px-5 py-4 text-xs font-semibold text-slate-400 uppercase tracking-wider hidden sm:table-cell w-32">Rozmiar</th>
                                                <th class="px-5 py-4 text-xs font-semibold text-slate-400 uppercase tracking-wider hidden md:table-cell w-40">Data</th>
                                                <th class="px-5 py-4 text-right text-xs font-semibold text-slate-400 uppercase tracking-wider w-px whitespace-nowrap">Akcje</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-700/50">
                                            <?php foreach ($files as $file): 
                                                $ext = strtolower(pathinfo($file['original_name'], PATHINFO_EXTENSION));
                                                $icon = 'file';
                                                $iconColor = 'text-slate-400';
                                                if ($ext === 'pdf') { $icon = 'file-text'; $iconColor = 'text-red-400'; }
                                                elseif (in_array($ext, ['doc', 'docx'])) { $icon = 'file-type-2'; $iconColor = 'text-blue-400'; }
                                                elseif (in_array($ext, ['jpg', 'png', 'jpeg', 'gif', 'webp'])) { $icon = 'image'; $iconColor = 'text-emerald-400'; }
                                                
                                                $size_val = $file['size'] > 1024*1024 ? round($file['size']/(1024*1024), 2) . ' MB' : round($file['size']/1024) . ' KB';
                                            ?>
                                            <tr class="hover:bg-slate-700/30 transition-colors group">
                                                <td class="px-3 py-4 min-w-0 max-w-0 w-full overflow-hidden">
                                                    <div class="flex items-center min-w-0">
                                                        <div class="p-2 bg-slate-900 rounded-lg mr-2 sm:mr-3 group-hover:bg-slate-800 transition-colors shrink-0">
                                                            <i data-lucide="<?= $icon ?>" class="w-5 h-5 <?= $iconColor ?>"></i>
                                                        </div>
                                                        <div class="flex flex-col min-w-0 overflow-hidden">
                                                            <span class="font-medium text-slate-200 truncate pr-1 shrink text-sm sm:text-base"><?= htmlspecialchars($file['original_name']) ?></span>
                                                            <span class="text-[10px] sm:text-xs text-slate-500 sm:hidden mt-0.5 truncate shrink"><?= $size_val ?> • <?= date('d.m.y', strtotime($file['created_at'])) ?></span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-5 py-4 whitespace-nowrap text-sm text-slate-400 hidden sm:table-cell text-xs"><?= $size_val ?></td>
                                                <td class="px-5 py-4 whitespace-nowrap text-sm text-slate-400 hidden md:table-cell text-xs"><?= date('d.m.Y H:i', strtotime($file['created_at'])) ?></td>
                                                <td class="px-3 py-4 whitespace-nowrap text-right text-sm shrink-0 w-px">
                                                    <div class="flex items-center justify-end gap-1.5 sm:gap-2 shrink-0">
                                                        <?php 
                                                            $viewable = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
                                                            if (in_array($ext, $viewable)): 
                                                        ?>
                                                            <a href="download.php?id=<?= $file['id'] ?>&action=view" target="_blank" class="p-2 flex items-center justify-center bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/20 hover:text-emerald-300 rounded-lg transition-all duration-200 shadow-sm" title="Podgląd">
                                                                <i data-lucide="eye" class="w-4.5 h-4.5"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        <a href="download.php?id=<?= $file['id'] ?>" class="p-2 flex items-center justify-center bg-blue-500/10 text-blue-400 hover:bg-blue-500/20 hover:text-blue-300 rounded-lg transition-all duration-200 shadow-sm" title="Pobierz">
                                                            <i data-lucide="download" class="w-4.5 h-4.5"></i>
                                                        </a>
                                                        <?php if (can_manage_files()): ?>
                                                        <div class="relative group/move shrink-0">
                                                            <form method="post" class="m-0">
                                                                <input type="hidden" name="action" value="move_file">
                                                                <input type="hidden" name="file_id" value="<?= $file['id'] ?>">
                                                                <div class="p-2 flex items-center justify-center bg-orange-500/10 text-orange-400 group-hover/move:bg-orange-500/20 group-hover/move:text-orange-300 rounded-lg transition-all duration-200">
                                                                    <i data-lucide="folder-input" class="w-4.5 h-4.5"></i>
                                                                </div>
                                                                <select name="new_folder_id" onchange="this.form.submit()" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" title="Przenieś plik">
                                                                    <option value="" disabled selected>Przenieś do...</option>
                                                                    <?php foreach ($folders as $fold): if ($fold['id'] != $active_folder_id): ?>
                                                                    <option value="<?= $fold['id'] ?>"><?= htmlspecialchars($fold['name']) ?></option>
                                                                    <?php endif; endforeach; ?>
                                                                </select>
                                                            </form>
                                                        </div>
                                                        <form method="post" onsubmit="return confirm('Czy na pewno chcesz usunąć ten plik?');" class="inline m-0 shrink-0">
                                                            <input type="hidden" name="action" value="delete_file">
                                                            <input type="hidden" name="file_id" value="<?= $file['id'] ?>">
                                                            <button type="submit" title="Usuń" class="p-2 text-red-400 hover:text-red-300 bg-red-500/10 hover:bg-red-500/20 rounded-lg transition-all duration-200 flex items-center justify-center">
                                                                <i data-lucide="trash-2" class="w-4.5 h-4.5"></i>
                                                            </button>
                                                        </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
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
    <footer class="max-w-7xl mx-auto py-8 px-4 text-center">
        <p class="text-slate-500 text-sm flex items-center justify-center gap-1.5">
            Made with <i data-lucide="heart" class="w-4 h-4 text-red-500 fill-red-500"></i> by <span class="font-bold text-slate-400">WowkDigital</span>
        </p>
    </footer>

    <script>
        lucide.createIcons();

        // Drag and Drop Logic
        const dropZone = document.getElementById('drop-zone');
        const fileInput = document.getElementById('file-input');
        const uploadForm = document.getElementById('upload-form');

        if (dropZone && fileInput) {
            // Click to upload
            dropZone.addEventListener('click', () => fileInput.click());
            
            // Auto submit when file selected via dialog
            fileInput.addEventListener('change', () => {
                if (fileInput.files.length > 0) {
                    uploadForm.submit();
                }
            });

            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, e => {
                    e.preventDefault();
                    e.stopPropagation();
                }, false);
            });

            ['dragenter', 'dragover'].forEach(eventName => {
                dropZone.addEventListener(eventName, () => {
                    dropZone.classList.add('border-blue-500', 'bg-blue-500/5');
                }, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, () => {
                    dropZone.classList.remove('border-blue-500', 'bg-blue-500/5');
                }, false);
            });

            dropZone.addEventListener('drop', e => {
                const dt = e.dataTransfer;
                const files = dt.files;
                fileInput.files = files;
                
                // Optional: Auto-submit on drop
                if (files.length > 0) {
                    uploadForm.submit();
                }
            }, false);
        }
    </script>
</body>
</html>
