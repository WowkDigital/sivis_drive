<?php
require_once 'auth.php';
require_admin();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_user') {
            $email = $_POST['email'];
            $role = $_POST['role'];
            $group = $_POST['group'];
            $password = bin2hex(random_bytes(4)); // generate random 8 chars password
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
        }
    }
}

$users = $db->query("SELECT id, email, role, user_group FROM users")->fetchAll(PDO::FETCH_ASSOC);
$folders = $db->query("SELECT id, name, access_groups FROM folders")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administratora</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <nav class="bg-blue-600 text-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex items-center space-x-4">
                    <i data-lucide="settings" class="w-6 h-6"></i>
                    <span class="font-bold text-xl">Panel Administratora</span>
                </div>
                <div class="flex space-x-4 items-center">
                    <a href="index.php" class="hover:text-gray-200">Powrót do plików</a>
                    <a href="logout.php" class="hover:text-gray-200 flex items-center"><i data-lucide="log-out" class="w-4 h-4 mr-1"></i> Wyloguj</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 shadow-sm">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Add User -->
            <div class="bg-white p-6 rounded-lg shadow-md border border-gray-100">
                <h3 class="text-lg font-bold mb-4 flex items-center border-b pb-2 text-gray-800"><i data-lucide="user-plus" class="w-5 h-5 mr-2 text-blue-500"></i> Dodaj Użytkownika</h3>
                <form method="post">
                    <input type="hidden" name="action" value="add_user">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
                        <input type="email" name="email" required class="w-full border border-gray-300 rounded px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="mb-4 grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Rola</label>
                            <select name="role" class="w-full border border-gray-300 rounded px-3 py-1.5 outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                                <option value="pracownik">Pracownik</option>
                                <option value="zarząd">Zarząd</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Grupa Domyślna</label>
                            <select name="group" class="w-full border border-gray-300 rounded px-3 py-1.5 outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                                <option value="pracownicy">Pracownicy</option>
                                <option value="zarząd">Zarząd</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 w-full flex items-center justify-center font-medium"><i data-lucide="plus" class="w-4 h-4 mr-1"></i> Utwórz Konto</button>
                </form>
            </div>

            <!-- Add Folder -->
            <div class="bg-white p-6 rounded-lg shadow-md border border-gray-100">
                <h3 class="text-lg font-bold mb-4 flex items-center border-b pb-2 text-gray-800"><i data-lucide="folder-plus" class="w-5 h-5 mr-2 text-blue-500"></i> Dodaj Folder</h3>
                <form method="post">
                    <input type="hidden" name="action" value="add_folder">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nazwa</label>
                        <input type="text" name="name" required class="w-full border border-gray-300 rounded px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Dostęp dla grup</label>
                        <select name="access_groups" class="w-full border border-gray-300 rounded px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500">
                             <option value="pracownicy">Pracownicy</option>
                             <option value="zarząd">Zarząd</option>
                             <option value="zarząd,pracownicy">Obie grupy</option>
                        </select>
                    </div>
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 w-full flex items-center justify-center font-medium"><i data-lucide="plus" class="w-4 h-4 mr-1"></i> Utwórz</button>
                </form>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
            <!-- List Users -->
            <div class="bg-white p-6 rounded-lg shadow-md border border-gray-100">
                <h3 class="text-lg font-bold mb-4 flex items-center border-b pb-2 text-gray-800"><i data-lucide="users" class="w-5 h-5 mr-2 text-blue-500"></i> Zarządzaj Użytkownikami</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs text-gray-500 uppercase bg-gray-50 border-b">
                            <tr>
                                <th class="px-4 py-2">E-mail</th>
                                <th class="px-4 py-2">Rola / Grupa</th>
                                <th class="px-4 py-2 text-right">Akcja</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                            <tr class="bg-white border-b hover:bg-gray-50 border-gray-50">
                                <td class="px-4 py-3 font-medium text-gray-900"><?= htmlspecialchars($u['email']) ?></td>
                                <td class="px-4 py-3 text-gray-500">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-xs uppercase"><?= htmlspecialchars($u['role']) ?></span>
                                        <span class="text-xs italic"><?= htmlspecialchars($u['user_group']) ?></span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                    <form method="post" onsubmit="return confirm('Pewny?');" style="display:inline;">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button class="text-red-500 hover:text-red-700"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- List Folders -->
            <div class="bg-white p-6 rounded-lg shadow-md border border-gray-100">
                <h3 class="text-lg font-bold mb-4 flex items-center border-b pb-2 text-gray-800"><i data-lucide="folders" class="w-5 h-5 mr-2 text-blue-500"></i> Lista Folderów</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-gray-500">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50 border-b">
                            <tr>
                                <th class="px-4 py-2">Nazwa</th>
                                <th class="px-4 py-2">Grupy dostępu</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($folders as $f): ?>
                            <tr class="bg-white border-b hover:bg-gray-50">
                                <td class="px-4 py-2 font-medium text-gray-900 flex items-center"><i data-lucide="folder" class="w-4 h-4 mr-2 text-blue-400"></i> <?= htmlspecialchars($f['name']) ?></td>
                                <td class="px-4 py-2"><?= htmlspecialchars($f['access_groups']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
