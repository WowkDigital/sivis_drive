<?php
require_once 'auth.php';
if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['user_group'] = $user['user_group'];
        header('Location: index.php');
        exit;
    } else {
        $error = 'Nieprawidłowy email lub hasło.';
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logowanie - System Wymiany Plików</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-gray-50 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
        <div class="flex justify-center mb-6">
            <i data-lucide="folder-lock" class="w-12 h-12 text-blue-600"></i>
        </div>
        <h2 class="text-2xl font-bold text-center text-gray-800 mb-6">Logowanie</h2>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="email">Adres E-mail</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i data-lucide="mail" class="w-5 h-5 text-gray-400"></i>
                    </div>
                    <input class="shadow appearance-none border rounded w-full py-2 pl-10 pr-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="email" type="email" name="email" required placeholder="admin@admin.com">
                </div>
            </div>
            <div class="mb-6">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="password">Hasło</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i data-lucide="lock" class="w-5 h-5 text-gray-400"></i>
                    </div>
                    <input class="shadow appearance-none border rounded w-full py-2 pl-10 pr-3 text-gray-700 mb-3 leading-tight focus:outline-none focus:shadow-outline" id="password" type="password" name="password" required placeholder="admin123">
                </div>
            </div>
            <div class="flex items-center justify-between">
                <button class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 w-full px-4 rounded focus:outline-none focus:shadow-outline flex items-center justify-center" type="submit">
                    <i data-lucide="log-in" class="w-5 h-5 mr-2"></i> Zaloguj się
                </button>
            </div>
        </form>
    </div>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
