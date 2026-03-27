<?php
require_once 'core/auth.php';
require_once 'core/functions.php';
require_admin();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        die("Błąd weryfikacji CSRF. Powrót <a href='admin.php'>tutaj</a>");
    }

    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_user') {
            $email = $_POST['email'];
            $display_name = $_POST['display_name'];
            $role = $_POST['role'];
            $group = ($role === 'zarząd') ? 'zarząd' : 'pracownicy';
            $password = generate_random_password(16);
            $hash = password_hash($password, PASSWORD_DEFAULT);
            
            try {
                $stmt = $db->prepare('INSERT INTO users (email, password_hash, role, user_group, display_name) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$email, $hash, $role, $group, $display_name]);
                $new_user_password = $password;
                
                log_activity($db, $_SESSION['user_id'], 'ADMIN_ADD_USER', "Utworzono użytkownika: $email ($role)");

                $message = "Użytkownik został pomyślnie utworzony.";
            } catch (Exception $e) {
                $message = "Błąd: " . $e->getMessage();
            }
        } elseif ($_POST['action'] === 'reset_password' && isset($_POST['user_id'])) {
            $uid = (int)$_POST['user_id'];
            $password = generate_random_password(16);
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $uid]);
            $new_user_password = $password;
            
            log_activity($db, $_SESSION['user_id'], 'ADMIN_RESET_PASSWORD', "Zresetowano hasło dla użytkownika ID: $uid");

            $message = "Hasło użytkownika zostało zresetowane.";
        } elseif ($_POST['action'] === 'add_folder') {
            $name = $_POST['name'];
            $access = $_POST['access_groups'];
            $stmt = $db->prepare('INSERT INTO folders (name, access_groups) VALUES (?, ?)');
            $stmt->execute([$name, $access]);
            
            log_activity($db, $_SESSION['user_id'], 'ADMIN_ADD_SHARED_FOLDER', "Utworzono folder udostępniony: $name");

            $message = "Folder dodany.";
        } elseif ($_POST['action'] === 'delete_user') {
            $uid = (int)$_POST['user_id'];
            if ($uid != $_SESSION['user_id']) {
                $upload_dir = __DIR__ . '/uploads';
                
                // 1. Delete all root folders owned by user (recursive)
                $stmt = $db->prepare("SELECT id FROM folders WHERE owner_id = ? AND parent_id IS NULL");
                $stmt->execute([$uid]);
                $user_roots = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                foreach ($user_roots as $root_id) {
                    delete_folder_recursive($db, $root_id, $upload_dir);
                }
                
                // 2. Delete user
                $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
                $stmt->execute([$uid]);
                
                log_activity($db, $_SESSION['user_id'], 'ADMIN_DELETE_USER', "Usunięto użytkownika ID: $uid (oraz wszystkie jego pliki)");

                $message = "Użytkownik oraz jego wszystkie pliki i foldery zostały usunięte.";
            } else {
                $message = "Błąd: Nie możesz usunąć sam siebie!";
            }
        } elseif ($_POST['action'] === 'delete_folder') {
            $fid = (int)$_POST['folder_id'];
            $upload_dir = __DIR__ . '/uploads';
            delete_folder_recursive($db, $fid, $upload_dir);
            
            log_activity($db, $_SESSION['user_id'], 'ADMIN_DELETE_SHARED_FOLDER', "Usunięto folder udostępniony ID: $fid");

            $message = "Folder i cała jego struktura zostały usunięte.";
        } elseif ($_POST['action'] === 'update_folder') {
            $fid = (int)$_POST['folder_id'];
            $access = $_POST['access_groups'];
            $stmt = $db->prepare('UPDATE folders SET access_groups = ? WHERE id = ?');
            $stmt->execute([$access, $fid]);
            $message = "Uprawnienia folderu zaktualizowane.";
        } elseif ($_POST['action'] === 'rename_folder') {
            $fid = (int)$_POST['folder_id'];
            $new_name = $_POST['new_name'];
            $stmt = $db->prepare('UPDATE folders SET name = ? WHERE id = ?');
            $stmt->execute([$new_name, $fid]);
            
            log_activity($db, $_SESSION['user_id'], 'ADMIN_RENAME_FOLDER', "Zmieniono nazwę folderu ID: $fid na: $new_name");

            $message = "Nazwa folderu zaktualizowana.";
        } elseif ($_POST['action'] === 'update_user_role') {
            $uid = (int)$_POST['user_id'];
            $role = $_POST['role'];
            $group = ($role === 'zarząd') ? 'zarząd' : 'pracownicy';
            
            $stmt = $db->prepare('UPDATE users SET role = ?, user_group = ? WHERE id = ?');
            $stmt->execute([$role, $group, $uid]);
            
            log_activity($db, $_SESSION['user_id'], 'ADMIN_UPDATE_USER_ROLE', "Zmieniono rolę użytkownika ID: $uid na: $role");

            $message = "Rola użytkownika zaktualizowana.";
        } elseif ($_POST['action'] === 'update_user_name') {
            $uid = (int)$_POST['user_id'];
            $name = $_POST['display_name'];
            $stmt = $db->prepare('UPDATE users SET display_name = ? WHERE id = ?');
            $stmt->execute([$name, $uid]);
            
            // Sync private root folder name
            $stmt = $db->prepare("UPDATE folders SET name = ? WHERE owner_id = ? AND parent_id IS NULL");
            $stmt->execute(['Pliki ' . $name, $uid]);

            $message = "Nazwa wyświetlana zaktualizowana.";
        }
    }
}

$users = $db->query("SELECT id, email, role, user_group, display_name, last_login FROM users")->fetchAll(PDO::FETCH_ASSOC);
$folders = $db->query("SELECT id, name, access_groups FROM folders WHERE owner_id IS NULL AND parent_id IS NULL")->fetchAll(PDO::FETCH_ASSOC);

$logs = $db->query("SELECT l.*, u.email, u.display_name FROM logs l LEFT JOIN users u ON l.user_id = u.id ORDER BY l.created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

// Stats
$total_files = $db->query("SELECT COUNT(*) FROM files")->fetchColumn();
$total_size = $db->query("SELECT SUM(size) FROM files")->fetchColumn() ?: 0;
$last_admin = $db->query("SELECT MAX(last_login) FROM users WHERE role = 'admin'")->fetchColumn();

$formatted_size = $total_size > 1024*1024*1024 
    ? round($total_size/(1024*1024*1024), 2) . ' GB' 
    : ($total_size > 1024*1024 
        ? round($total_size/(1024*1024), 2) . ' MB' 
        : round($total_size/1024) . ' KB');
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
            <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-6 py-4 rounded-2xl relative mb-6 backdrop-blur-sm shadow-xl flex items-center justify-between">
                <div class="flex items-center">
                    <i data-lucide="check-circle" class="w-5 h-5 mr-3"></i>
                    <span class="font-medium"><?= htmlspecialchars($message) ?></span>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($new_user_password)): ?>
            <div class="bg-blue-500/10 border border-blue-500/30 text-blue-100 px-6 py-6 rounded-2xl relative mb-8 backdrop-blur-md shadow-2xl ring-1 ring-blue-500/20">
                <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                    <div class="flex items-start">
                        <div class="p-3 bg-blue-500/20 rounded-xl mr-4 shrink-0">
                            <i data-lucide="key" class="w-8 h-8 text-blue-400"></i>
                        </div>
                        <div>
                            <h4 class="text-lg font-bold text-white mb-1">Wygenerowane Hasło</h4>
                            <p class="text-blue-300/80 text-sm">Przekaż to hasło użytkownikowi. Po zamknięciu tego komunikatu nie będzie ono widoczne.</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 bg-slate-900/80 p-2 rounded-xl border border-blue-500/30 animate-pulse-subtle">
                        <code class="px-3 py-1.5 text-xl font-mono text-blue-400 tracking-wider" id="generated-password"><?= htmlspecialchars($new_user_password) ?></code>
                        <button onclick="copyPasswordToClipboard(this)" class="p-2 bg-blue-600 hover:bg-blue-500 text-white rounded-lg transition-all shadow-lg active:scale-95 group" title="Kopiuj hasło">
                            <i data-lucide="copy" class="w-5 h-5 group-active:scale-90 transition-transform"></i>
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Stats Dashboard -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-slate-800 p-6 rounded-2xl border border-slate-700 shadow-xl flex items-center">
                <div class="p-4 bg-blue-500/10 rounded-2xl mr-4">
                    <i data-lucide="files" class="w-8 h-8 text-blue-400"></i>
                </div>
                <div>
                    <p class="text-slate-500 text-sm font-medium uppercase tracking-wider">Wszystkie pliki</p>
                    <h4 class="text-3xl font-bold text-white"><?= $total_files ?></h4>
                </div>
            </div>
            <div class="bg-slate-800 p-6 rounded-2xl border border-slate-700 shadow-xl flex items-center">
                <div class="p-4 bg-emerald-500/10 rounded-2xl mr-4">
                    <i data-lucide="hard-drive" class="w-8 h-8 text-emerald-400"></i>
                </div>
                <div>
                    <p class="text-slate-500 text-sm font-medium uppercase tracking-wider">Zajęte miejsce</p>
                    <h4 class="text-3xl font-bold text-white"><?= $formatted_size ?></h4>
                </div>
            </div>
            <div class="bg-slate-800 p-6 rounded-2xl border border-slate-700 shadow-xl flex items-center">
                <div class="p-4 bg-purple-500/10 rounded-2xl mr-4">
                    <i data-lucide="history" class="w-8 h-8 text-purple-400"></i>
                </div>
                <div>
                    <p class="text-slate-500 text-sm font-medium uppercase tracking-wider">Ost. logowanie admina</p>
                    <h4 class="text-lg font-bold text-white"><?= $last_admin ? date('d.m.y H:i', strtotime($last_admin)) : 'Brak danych' ?></h4>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Add User -->
            <div class="bg-slate-800 p-6 rounded-2xl shadow-xl border border-slate-700">
                <h3 class="text-lg font-bold mb-5 flex items-center border-b border-slate-700 pb-3 text-slate-100">
                    <div class="p-1.5 bg-blue-500/10 rounded-lg mr-3">
                        <i data-lucide="user-plus" class="w-5 h-5 text-blue-400"></i>
                    </div>
                    Dodaj Użytkownika
                </h3>
                <form method="post"><input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="action" value="add_user">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-400 mb-1.5">Adres E-mail</label>
                        <input type="email" name="email" required placeholder="jan@firma.pl" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-4 py-2.5 text-slate-200 outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition-all placeholder-slate-600">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-400 mb-1.5">Imię i Nazwisko</label>
                        <input type="text" name="display_name" required placeholder="Jan Kowalski" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-4 py-2.5 text-slate-200 outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition-all placeholder-slate-600">
                    </div>
                    <div class="mb-5">
                        <label class="block text-sm font-medium text-slate-400 mb-1.5">Rola</label>
                        <div class="relative">
                            <select name="role" class="w-full appearance-none bg-slate-900 border border-slate-700 rounded-lg px-4 py-2.5 text-slate-200 outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition-all cursor-pointer">
                                <option value="pracownik">Pracownik</option>
                                <option value="zarząd">Zarząd</option>
                                <option value="admin">Administrator</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-slate-500">
                                <i data-lucide="chevron-down" class="w-4 h-4"></i>
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
                <form method="post"><input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
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
                                        <form method="post" class="flex flex-col gap-1"><input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                            <input type="hidden" name="action" value="update_user_name">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <div class="relative group/name">
                                                <input type="text" name="display_name" value="<?= htmlspecialchars($u['display_name'] ?? '') ?>" class="bg-transparent border-none text-slate-200 font-medium p-0 focus:ring-0 w-full" placeholder="Brak nazwy...">
                                                <button type="submit" class="absolute -right-6 top-1/2 -translate-y-1/2 opacity-0 group-hover/name:opacity-100 text-emerald-400 hover:text-emerald-300 transition-opacity">
                                                    <i data-lucide="save" class="w-4 h-4"></i>
                                                </button>
                                            </div>
                                            <div class="text-xs text-slate-500"><?= htmlspecialchars($u['email']) ?></div>
                                        </form>
                                        <div class="text-xs text-slate-500 sm:hidden mt-2">
                                            Role: <span class="font-semibold text-slate-400"><?= htmlspecialchars($u['role']) ?></span> 
                                            (<span class="italic"><?= htmlspecialchars($u['user_group']) ?></span>)
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap hidden sm:table-cell">
                                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                        <form method="post" class="flex items-center gap-2"><input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                            <input type="hidden" name="action" value="update_user_role">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <div class="relative">
                                                <select name="role" onchange="this.form.submit()" class="appearance-none bg-slate-900 border border-slate-700 rounded-lg pl-3 pr-8 py-1.5 text-xs font-bold uppercase tracking-wider text-slate-300 focus:border-blue-500 outline-none transition-all cursor-pointer">
                                                    <option value="pracownik" <?= $u['role'] == 'pracownik' ? 'selected' : '' ?>>Pracownik</option>
                                                    <option value="zarząd" <?= $u['role'] == 'zarząd' ? 'selected' : '' ?>>Zarząd</option>
                                                    <option value="admin" <?= $u['role'] == 'admin' ? 'selected' : '' ?>>Administrator</option>
                                                </select>
                                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-slate-500">
                                                    <i data-lucide="chevron-down" class="w-3 h-3"></i>
                                                </div>
                                            </div>
                                        </form>
                                        <?php else: ?>
                                            <span class="font-bold text-xs uppercase tracking-wider text-slate-500 italic">Administrator (To Ty)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                        <div class="flex items-center justify-end gap-2">
                                            <form method="post" id="reset-pass-form-<?= $u['id'] ?>" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                                <input type="hidden" name="action" value="reset_password">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <button type="button" onclick="showConfirmModal('Zresetować hasło?', 'Czy na pewno chcesz zresetować hasło dla <?= htmlspecialchars($u['email']) ?>?\nZostanie wygenerowane nowe, losowe hasło.', () => document.getElementById('reset-pass-form-<?= $u['id'] ?>').submit(), 'orange')" class="p-2 text-orange-400 hover:text-orange-300 bg-orange-500/10 hover:bg-orange-500/20 rounded-lg transition-all" title="Resetuj hasło">
                                                    <i data-lucide="refresh-cw" class="w-5 h-5"></i>
                                                </button>
                                            </form>
                                            <form method="post" id="delete-user-form-<?= $u['id'] ?>" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <button type="button" onclick="showConfirmModal('Trwale usunąć użytkownika?', 'UWAGA: Spowoduje to również usunięcie WSZYSTKICH plików i folderów tego pracownika.', () => document.getElementById('delete-user-form-<?= $u['id'] ?>').submit(), 'red')" class="p-2 text-red-400 hover:text-red-300 bg-red-500/10 hover:bg-red-500/20 rounded-lg transition-all" title="Usuń użytkownika">
                                                    <i data-lucide="user-minus" class="w-5 h-5"></i>
                                                </button>
                                            </form>
                                        </div>
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
                    <div class="p-1.5 bg-blue-500/10 rounded-lg mr-3">
                        <i data-lucide="share-2" class="w-5 h-5 text-blue-400"></i>
                    </div>
                    Udostępnione foldery
                </h3>
                <div class="overflow-x-auto -mx-6 sm:mx-0">
                    <div class="inline-block min-w-full align-middle">
                        <table class="min-w-full">
                            <thead>
                                <tr class="border-b border-slate-700 text-left">
                                    <th class="px-6 py-4 text-xs font-semibold text-slate-400 uppercase tracking-wider">Nazwa folderu udostępnionego</th>
                                    <th class="px-6 py-4 text-right text-xs font-semibold text-slate-400 uppercase tracking-wider">Akcja</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700/50">
                                <?php foreach ($folders as $f): ?>
                                <tr class="hover:bg-slate-700/30 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <form method="post" class="flex items-center"><input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                            <input type="hidden" name="action" value="rename_folder">
                                            <input type="hidden" name="folder_id" value="<?= $f['id'] ?>">
                                            <div class="flex items-center font-medium text-slate-200 w-full max-w-xs">
                                                <div class="p-2 bg-slate-900 rounded-lg mr-3 shrink-0">
                                                    <i data-lucide="folder" class="w-4 h-4 text-blue-400"></i>
                                                </div>
                                                <div class="relative flex items-center w-full">
                                                    <input type="text" name="new_name" value="<?= htmlspecialchars($f['name']) ?>" required class="w-full bg-slate-900 border border-slate-700 rounded-lg pl-3 pr-8 py-1.5 text-sm text-slate-200 outline-none focus:ring-1 focus:ring-blue-500/50 focus:border-blue-500 transition-all">
                                                    <button type="submit" title="Zapisz nazwę" class="absolute right-1 p-1 text-slate-400 hover:text-emerald-400 transition-colors">
                                                        <i data-lucide="save" class="w-4 h-4"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        <form method="post" id="delete-folder-form-<?= $f['id'] ?>" class="inline-flex gap-2">
                                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                            <input type="hidden" name="action" value="delete_folder">
                                            <input type="hidden" name="folder_id" value="<?= $f['id'] ?>">
                                            <button type="button" onclick="showConfirmModal('Usunąć folder udostępniony?', 'UWAGA: Usunięcie folderu spowoduje TRWAŁE usunięcie WSZYSTKICH plików i podfolderów w nim zawartych.', () => document.getElementById('delete-folder-form-<?= $f['id'] ?>').submit(), 'red')" class="p-2 text-red-400 hover:text-red-300 bg-red-500/10 hover:bg-red-500/20 rounded-lg transition-all" title="Usuń folder z zawartością">
                                                <i data-lucide="trash-2" class="w-5 h-5"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity Log -->
        <div class="mt-8 bg-slate-800 p-6 rounded-2xl shadow-xl border border-slate-700">
            <h3 class="text-lg font-bold mb-5 flex items-center border-b border-slate-700 pb-3 text-slate-100">
                <div class="p-1.5 bg-orange-500/10 rounded-lg mr-3">
                    <i data-lucide="activity" class="w-5 h-5 text-orange-400"></i>
                </div>
                Ostatnie aktywności (Logi)
            </h3>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="border-b border-slate-700 text-left">
                            <th class="px-6 py-4 text-xs font-semibold text-slate-400 uppercase tracking-wider">Użytkownik</th>
                            <th class="px-6 py-4 text-xs font-semibold text-slate-400 uppercase tracking-wider">Akcja</th>
                            <th class="px-6 py-4 text-xs font-semibold text-slate-400 uppercase tracking-wider">Szczegóły</th>
                            <th class="px-6 py-4 text-xs font-semibold text-slate-400 uppercase tracking-wider">Data</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50">
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center text-slate-500 italic">Brak zarejestrowanych aktywności.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($logs as $l): ?>
                        <tr class="hover:bg-slate-700/30 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-slate-200"><?= htmlspecialchars($l['display_name'] ?: 'System') ?></div>
                                <div class="text-xs text-slate-500"><?= htmlspecialchars($l['email'] ?: '-') ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-[10px] font-bold rounded uppercase tracking-tighter 
                                    <?= strpos($l['action'], 'DELETE') !== false ? 'bg-red-500/10 text-red-400' : (strpos($l['action'], 'ADMIN') !== false ? 'bg-purple-500/10 text-purple-400' : 'bg-blue-500/10 text-blue-400') ?>">
                                    <?= htmlspecialchars($l['action']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-400">
                                <?= htmlspecialchars($l['details']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-500">
                                <?= date('d.m.Y H:i', strtotime($l['created_at'])) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php require_once 'views/action_modal.php'; ?>
    <footer class="max-w-7xl mx-auto py-12 px-4 text-center">
        <p class="text-slate-600 text-xs flex items-center justify-center gap-1.5">
            Made with <i data-lucide="heart" class="w-3 h-3 text-red-500 fill-red-500"></i> by <span class="font-bold text-slate-500">WowkDigital</span>
        </p>
    </footer>

    <script src="assets/js/modals.js"></script>
    <script>
        function showToast(message) {
            let container = document.getElementById('toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toast-container';
                container.className = 'fixed bottom-6 right-6 z-[100] flex flex-col gap-3 pointer-events-none';
                document.body.appendChild(container);
            }
            const toast = document.createElement('div');
            toast.className = `transform translate-x-full opacity-0 transition-all duration-500 ease-out flex items-center gap-3 px-6 py-4 rounded-2xl shadow-2xl border backdrop-blur-md pointer-events-auto min-w-[200px] mb-2 bg-emerald-500/20 border-emerald-500/30 text-emerald-100`;
            toast.innerHTML = `<div class="p-1.5 rounded-lg bg-emerald-500/20"><i data-lucide="check-circle" class="w-5 h-5"></i></div><span class="font-bold text-sm tracking-wide">${message}</span>`;
            container.appendChild(toast);
            lucide.createIcons();
            setTimeout(() => toast.classList.remove('translate-x-full', 'opacity-0'), 10);
            setTimeout(() => {
                toast.classList.add('translate-x-full', 'opacity-0');
                setTimeout(() => toast.remove(), 500);
            }, 3000);
        }

        function copyPasswordToClipboard(btn) {
            const password = document.getElementById('generated-password').innerText;
            navigator.clipboard.writeText(password).then(() => {
                const icon = btn.querySelector('i');
                const originalIcon = icon.getAttribute('data-lucide');
                
                showToast('Hasło skopiowane do schowka! 🔑');

                icon.setAttribute('data-lucide', 'check');
                btn.classList.add('bg-emerald-600');
                btn.classList.remove('bg-blue-600');

                lucide.createIcons();
                setTimeout(() => {
                    icon.setAttribute('data-lucide', originalIcon);
                    btn.classList.remove('bg-emerald-600');
                    btn.classList.add('bg-blue-600');
                    lucide.createIcons();
                }, 2000);
            });
        }
        lucide.createIcons();
    </script>
</body>
</html>

