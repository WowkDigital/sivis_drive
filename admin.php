<?php
require_once 'auth.php';
require_admin();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_user') {
            $email = $_POST['email'];
            $role = $_POST['role'];
            $group = ($role === 'zarząd') ? 'zarząd' : 'pracownicy';
            $password = generate_random_password(16);
            $hash = password_hash($password, PASSWORD_DEFAULT);
            
            try {
                $stmt = $db->prepare('INSERT INTO users (email, password_hash, role, user_group) VALUES (?, ?, ?, ?)');
                $stmt->execute([$email, $hash, $role, $group]);
                $message = "Użytkownik dodany. Wygenerowane hasło: $password (Wyślij to pracownikowi)";
            } catch (Exception $e) {
                $message = "Błąd: " . $e->getMessage();
            }
        } elseif ($_POST['action'] === 'add_folder') {
            $name = $_POST['name'];
            $access = $_POST['access_groups'];
            $stmt = $db->prepare('INSERT INTO folders (name, access_groups) VALUES (?, ?)');
            $stmt->execute([$name, $access]);
            $message = "Folder dodany.";
        } elseif ($_POST['action'] === 'delete_user') {
            $uid = (int)$_POST['user_id'];
            if ($uid != $_SESSION['user_id']) {
                $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
                $stmt->execute([$uid]);
                $message = "Użytkownik usunięty.";
            } else {
                $message = "Błąd: Nie możesz usunąć sam siebie!";
            }
        } elseif ($_POST['action'] === 'delete_folder') {
            $fid = (int)$_POST['folder_id'];
            $upload_dir = __DIR__ . '/uploads';
            
            // Delete all files in this folder from disk
            $stmt = $db->prepare("SELECT name FROM files WHERE folder_id = ?");
            $stmt->execute([$fid]);
            $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($files as $f) {
                @unlink($upload_dir . '/' . $f['name']);
            }
            
            // Delete files from DB
            $stmt = $db->prepare("DELETE FROM files WHERE folder_id = ?");
            $stmt->execute([$fid]);
            
            // Delete folder from DB
            $stmt = $db->prepare("DELETE FROM folders WHERE id = ?");
            $stmt->execute([$fid]);
            
            $message = "Folder i jego zawartość zostały usunięte.";
        }
    }
}

$users = $db->query("SELECT id, email, role, user_group FROM users")->fetchAll(PDO::FETCH_ASSOC);
$folders = $db->query("SELECT id, name, access_groups FROM folders")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pl" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administratora - Sivis Drive</title>
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
                <div class="flex items-center space-x-3 text-emerald-400">
                    <div class="p-2 bg-emerald-500/10 rounded-xl">
                        <i data-lucide="settings" class="w-8 h-8"></i>
                    </div>
                    <span class="font-bold text-2xl tracking-tight text-white">Panel Administratora</span>
                </div>
                <div class="flex flex-wrap justify-center gap-3 items-center">
                    <a href="index.php" class="text-blue-400 hover:text-blue-300 hover:bg-slate-700 px-4 py-2 rounded-lg font-medium flex items-center transition-colors">
                        <i data-lucide="folder-open" class="w-4 h-4 mr-1.5"></i> Powrót do plików
                    </a>
                    <a href="logout.php" class="text-red-400 hover:text-red-300 flex items-center bg-red-500/10 hover:bg-red-500/20 px-4 py-2 rounded-lg font-medium transition-colors">
                        <i data-lucide="log-out" class="w-4 h-4 mr-1.5"></i> Wyloguj
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <?php if ($message): ?>
            <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-xl relative mb-6 backdrop-blur-sm shadow-lg">
                <span class="block sm:inline"><?= htmlspecialchars($message) ?></span>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Add User -->
            <div class="bg-slate-800 p-6 rounded-2xl shadow-xl border border-slate-700">
                <h3 class="text-lg font-bold mb-5 flex items-center border-b border-slate-700 pb-3 text-slate-100">
                    <div class="p-1.5 bg-blue-500/10 rounded-lg mr-3">
                        <i data-lucide="user-plus" class="w-5 h-5 text-blue-400"></i>
                    </div>
                    Dodaj Użytkownika
                </h3>
                <form method="post">
                    <input type="hidden" name="action" value="add_user">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-400 mb-1.5">Adres E-mail</label>
                        <input type="email" name="email" required placeholder="jan@firma.pl" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-4 py-2.5 text-slate-200 outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition-all placeholder-slate-600">
                    </div>
                    <div class="mb-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-400 mb-1.5">Rola</label>
                            <div class="relative">
                                <select name="role" class="w-full appearance-none bg-slate-900 border border-slate-700 rounded-lg px-4 py-2.5 text-slate-200 outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition-all cursor-pointer">
                                    <option value="pracownik">Pracownik</option>
                                    <option value="zarząd">Zarząd</option>
                                    <option value="admin">Administrator</option>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-slate-400">
                                    <i data-lucide="chevron-down" class="w-4 h-4"></i>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-400 mb-1.5">Uprawnienia</label>
                            <div class="relative">
                                <span class="block w-full bg-slate-900/50 border border-slate-700/50 rounded-lg px-4 py-2.5 text-slate-500 text-sm italic">
                                    Automatycznie wg. wybranej roli
                                </span>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-3 rounded-xl shadow-lg shadow-blue-500/20 hover:shadow-blue-500/40 w-full flex items-center justify-center font-medium transition-all duration-200">
                        <i data-lucide="plus-circle" class="w-5 h-5 mr-2"></i> Utwórz Konto
                    </button>
                </form>
            </div>

            <!-- Add Folder -->
            <div class="bg-slate-800 p-6 rounded-2xl shadow-xl border border-slate-700">
                <h3 class="text-lg font-bold mb-5 flex items-center border-b border-slate-700 pb-3 text-slate-100">
                    <div class="p-1.5 bg-emerald-500/10 rounded-lg mr-3">
                        <i data-lucide="folder-plus" class="w-5 h-5 text-emerald-400"></i>
                    </div>
                    Dodaj Folder
                </h3>
                <form method="post">
                    <input type="hidden" name="action" value="add_folder">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-400 mb-1.5">Nazwa folderu</label>
                        <input type="text" name="name" required placeholder="Np. Dokumenty HR" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-4 py-2.5 text-slate-200 outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition-all placeholder-slate-600">
                    </div>
                    <div class="mb-5">
                        <label class="block text-sm font-medium text-slate-400 mb-1.5">Dostęp dla grup</label>
                        <div class="relative">
                            <select name="access_groups" class="w-full appearance-none bg-slate-900 border border-slate-700 rounded-lg px-4 py-2.5 text-slate-200 outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition-all cursor-pointer">
                                 <option value="zarząd,pracownicy">Zarząd i Pracownicy (Wszyscy)</option>
                                 <option value="zarząd">Tylko Zarząd</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-slate-400">
                                <i data-lucide="chevron-down" class="w-4 h-4"></i>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-3 rounded-xl shadow-lg shadow-emerald-500/20 hover:shadow-emerald-500/40 w-full flex items-center justify-center font-medium transition-all duration-200">
                        <i data-lucide="plus-circle" class="w-5 h-5 mr-2"></i> Utwórz Folder
                    </button>
                </form>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
            <!-- List Users -->
            <div class="bg-slate-800 p-6 rounded-2xl shadow-xl border border-slate-700">
                <h3 class="text-lg font-bold mb-5 flex items-center border-b border-slate-700 pb-3 text-slate-100">
                    <div class="p-1.5 bg-purple-500/10 rounded-lg mr-3">
                        <i data-lucide="users" class="w-5 h-5 text-purple-400"></i>
                    </div>
                    Zarządzaj Użytkownikami
                </h3>
                <div class="overflow-x-auto -mx-6 sm:mx-0">
                    <div class="inline-block min-w-full align-middle">
                        <table class="min-w-full">
                            <thead>
                                <tr class="border-b border-slate-700 text-left">
                                    <th class="px-6 py-4 text-xs font-semibold text-slate-400 uppercase tracking-wider">E-mail</th>
                                    <th class="px-6 py-4 text-xs font-semibold text-slate-400 uppercase tracking-wider hidden sm:table-cell">Rola / Grupa</th>
                                    <th class="px-6 py-4 text-right text-xs font-semibold text-slate-400 uppercase tracking-wider">Akcja</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700/50">
                                <?php foreach ($users as $u): ?>
                                <tr class="hover:bg-slate-700/30 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="font-medium text-slate-200"><?= htmlspecialchars($u['email']) ?></div>
                                        <div class="text-xs text-slate-500 sm:hidden mt-1">
                                            Role: <span class="font-semibold text-slate-400"><?= htmlspecialchars($u['role']) ?></span> 
                                            (<span class="italic"><?= htmlspecialchars($u['user_group']) ?></span>)
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap hidden sm:table-cell">
                                        <div class="flex flex-col">
                                            <span class="font-bold text-xs uppercase tracking-wider text-slate-300"><?= htmlspecialchars($u['role']) ?></span>
                                            <span class="text-xs italic text-slate-500 mt-1"><i data-lucide="users" class="w-3 h-3 inline pb-0.5"></i> <?= htmlspecialchars($u['user_group']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                        <form method="post" onsubmit="return confirm('Trwale usunąć tego użytkownika?');" class="inline">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <button type="submit" class="p-2 text-red-400 hover:text-red-300 bg-red-500/10 hover:bg-red-500/20 rounded-lg transition-all" title="Usuń użytkownika">
                                                <i data-lucide="user-minus" class="w-5 h-5"></i>
                                            </button>
                                        </form>
                                        <?php else: ?>
                                            <span class="text-xs text-slate-500 px-2 py-1 bg-slate-900 rounded border border-slate-700">To Ty</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- List Folders -->
            <div class="bg-slate-800 p-6 rounded-2xl shadow-xl border border-slate-700">
                <h3 class="text-lg font-bold mb-5 flex items-center border-b border-slate-700 pb-3 text-slate-100">
                    <div class="p-1.5 bg-orange-500/10 rounded-lg mr-3">
                        <i data-lucide="folders" class="w-5 h-5 text-orange-400"></i>
                    </div>
                    Lista Folderów
                </h3>
                <div class="overflow-x-auto -mx-6 sm:mx-0">
                    <div class="inline-block min-w-full align-middle">
                        <table class="min-w-full">
                            <thead>
                                <tr class="border-b border-slate-700 text-left">
                                    <th class="px-6 py-4 text-xs font-semibold text-slate-400 uppercase tracking-wider">Nazwa</th>
                                    <th class="px-6 py-4 text-xs font-semibold text-slate-400 uppercase tracking-wider">Dostęp</th>
                                    <th class="px-6 py-4 text-right text-xs font-semibold text-slate-400 uppercase tracking-wider">Akcja</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700/50">
                                <?php foreach ($folders as $f): ?>
                                <tr class="hover:bg-slate-700/30 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center font-medium text-slate-200">
                                            <div class="p-2 bg-slate-900 rounded-lg mr-3">
                                                <i data-lucide="folder" class="w-4 h-4 text-blue-400"></i>
                                            </div>
                                            <?= htmlspecialchars($f['name']) ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex flex-wrap gap-2">
                                            <?php 
                                            $ag = array_filter(array_map('trim', explode(',', $f['access_groups'])));
                                            if (empty($ag)): ?>
                                                <span class="px-2.5 py-1 rounded-md text-xs font-medium bg-slate-700 text-slate-300 border border-slate-600">Brak określonych (Wszyscy)</span>
                                            <?php else: 
                                                foreach($ag as $g): ?>
                                                <span class="px-2.5 py-1 rounded-md text-xs font-medium bg-slate-900 border border-slate-700 text-slate-300">
                                                    <?= htmlspecialchars($g) ?>
                                                </span>
                                            <?php endforeach; endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <footer class="max-w-7xl mx-auto py-12 px-4 text-center">
        <p class="text-slate-600 text-xs flex items-center justify-center gap-1.5">
            Made with <i data-lucide="heart" class="w-3 h-3 text-red-500 fill-red-500"></i> by <span class="font-bold text-slate-500">WowkDigital</span>
        </p>
    </footer>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
