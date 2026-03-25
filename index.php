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
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sivis Drive</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <nav class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex items-center space-x-2 text-blue-600">
                    <i data-lucide="cloud" class="w-8 h-8"></i>
                    <span class="font-bold text-xl text-gray-800">Sivis Drive</span>
                </div>
                <div class="flex space-x-4 items-center">
                    <span class="text-sm text-gray-500 mr-4">Zalogowany jako: <span class="font-semibold text-gray-700"><?= htmlspecialchars($_SESSION['email']) ?></span></span>
                    <?php if (is_admin()): ?>
                        <a href="admin.php" class="text-blue-600 hover:text-blue-800 font-medium flex items-center">
                            <i data-lucide="shield-check" class="w-4 h-4 mr-1"></i> Admin
                        </a>
                    <?php endif; ?>
                    <a href="logout.php" class="text-red-600 hover:text-red-800 flex items-center bg-red-50 px-3 py-1 rounded">
                        <i data-lucide="log-out" class="w-4 h-4 mr-1"></i> Wyloguj
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4 shadow-sm" role="alert">
                <span class="block sm:inline"><?= htmlspecialchars($message) ?></span>
            </div>
        <?php endif; ?>

        <div class="flex flex-col md:flex-row gap-6">
            <!-- Sidebar / Folders -->
            <div class="w-full md:w-1/4">
                <div class="bg-white shadow-md rounded-lg p-4 border border-gray-100">
                    <h2 class="text-lg font-bold mb-4 flex items-center text-gray-800 border-b pb-2"><i data-lucide="folders" class="w-5 h-5 mr-2 text-blue-500"></i> Moje Foldery</h2>
                    <ul class="space-y-1">
                        <?php if (empty($folders)): ?>
                            <li class="text-gray-500 text-sm py-2 px-3">Brak dostępu do folderów.</li>
                        <?php endif; ?>
                        <?php foreach ($folders as $f): ?>
                            <li>
                                <a href="?folder=<?= $f['id'] ?>" class="flex items-center px-3 py-2 rounded-md transition-colors <?= $f['id'] == $active_folder_id ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100' ?>">
                                    <i data-lucide="<?= $f['id'] == $active_folder_id ? 'folder-open' : 'folder' ?>" class="w-4 h-4 mr-2 <?= $f['id'] == $active_folder_id ? 'text-blue-600' : 'text-gray-400' ?>"></i>
                                    <?= htmlspecialchars($f['name']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Content / Files -->
            <div class="w-full md:w-3/4">
                <div class="bg-white shadow-md rounded-lg p-6 border border-gray-100">
                    <?php if ($active_folder): ?>
                        <div class="flex justify-between items-center mb-6 pb-2 border-b">
                            <h2 class="text-xl font-bold text-gray-800 flex items-center"><i data-lucide="folder-open" class="w-6 h-6 mr-2 text-blue-500"></i> <?= htmlspecialchars($active_folder['name']) ?></h2>
                            <span class="text-sm text-gray-500"><?= count($files) ?> plików</span>
                        </div>

                        <?php if (can_manage_files()): ?>
                            <!-- Upload form for admin and zarząd -->
                            <div class="bg-gray-50 p-4 rounded-lg mb-6 border border-dashed border-gray-300">
                                <form method="post" enctype="multipart/form-data" class="flex items-center space-x-4">
                                    <input type="hidden" name="action" value="upload">
                                    <input type="hidden" name="folder_id" value="<?= $active_folder['id'] ?>">
                                    <i data-lucide="upload-cloud" class="w-8 h-8 text-gray-400 ml-2"></i>
                                    <input type="file" name="file" required class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
                                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded shadow flex items-center whitespace-nowrap">
                                        <i data-lucide="upload" class="w-4 h-4 mr-2"></i> Wgraj Plik
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>

                        <!-- File list -->
                        <?php if (empty($files)): ?>
                            <div class="text-center py-12 text-gray-500 flex flex-col items-center">
                                <i data-lucide="file-question" class="w-12 h-12 text-gray-300 mb-3"></i>
                                <p>Ten folder jest pusty.</p>
                            </div>
                        <?php else: ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm text-left">
                                    <thead class="text-xs text-gray-500 uppercase bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 font-medium">Nazwa pliku</th>
                                            <th class="px-4 py-3 font-medium">Rozmiar</th>
                                            <th class="px-4 py-3 font-medium">Data dodania</th>
                                            <th class="px-4 py-3 text-right font-medium">Akcja</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <?php foreach ($files as $file): 
                                            // Determine icon based on simple extension check
                                            $ext = strtolower(pathinfo($file['original_name'], PATHINFO_EXTENSION));
                                            $icon = 'file';
                                            $iconColor = 'text-gray-400';
                                            if ($ext === 'pdf') { $icon = 'file-text'; $iconColor = 'text-red-500'; }
                                            elseif (in_array($ext, ['doc', 'docx'])) { $icon = 'file-type-2'; $iconColor = 'text-blue-600'; }
                                            
                                            $size_val = $file['size'] > 1024*1024 ? round($file['size']/(1024*1024), 2) . ' MB' : round($file['size']/1024) . ' KB';
                                        ?>
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-4 py-3 flex items-center font-medium text-gray-800">
                                                <i data-lucide="<?= $icon ?>" class="w-5 h-5 mr-3 <?= $iconColor ?>"></i>
                                                <?= htmlspecialchars($file['original_name']) ?>
                                            </td>
                                            <td class="px-4 py-3 text-gray-500"><?= $size_val ?></td>
                                            <td class="px-4 py-3 text-gray-500"><?= date('d.m.Y H:i', strtotime($file['created_at'])) ?></td>
                                            <td class="px-4 py-3 text-right flex items-center justify-end space-x-2">
                                                <a href="download.php?id=<?= $file['id'] ?>" class="inline-flex items-center text-blue-600 hover:text-blue-800 bg-blue-50 hover:bg-blue-100 px-3 py-1.5 rounded-md font-medium transition-colors">
                                                    <i data-lucide="download" class="w-4 h-4 mr-1"></i> Pobierz
                                                </a>
                                                <?php if (can_manage_files()): ?>
                                                <form method="post" onsubmit="return confirm('Pewny?');" style="display:inline;">
                                                    <input type="hidden" name="action" value="delete_file">
                                                    <input type="hidden" name="file_id" value="<?= $file['id'] ?>">
                                                    <button type="submit" class="text-red-500 hover:text-red-700 p-1.5 rounded-md bg-red-50 hover:bg-red-100">
                                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <div class="text-center py-12 text-gray-500 flex flex-col items-center">
                            <i data-lucide="folder-search" class="w-16 h-16 text-gray-300 mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-800 mb-2">Wybierz folder</h3>
                            <p class="text-gray-500">Wybierz folder z menu po lewej stronie, aby zobaczyć jego zawartość.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
