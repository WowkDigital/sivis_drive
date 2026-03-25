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
                    <span class="text-sm text-slate-400 hidden sm:flex items-center gap-2">
                        Zalogowano: 
                        <span class="font-semibold text-slate-200"><?= htmlspecialchars($_SESSION['display_name'] ?: $_SESSION['email']) ?></span>
                        <button onclick="const n=prompt('Zmień wyświetlaną nazwę:', '<?= htmlspecialchars($_SESSION['display_name'] ?? '') ?>'); if(n){document.getElementById('update_my_name_val').value=n; document.getElementById('update_my_name_form').submit();}" class="p-1 hover:text-blue-400 transition-colors" title="Edytuj profil">
                            <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                        </button>
                        <form id="update_my_name_form" method="post" class="hidden">
                            <input type="hidden" name="action" value="update_my_name">
                            <input type="hidden" name="display_name" id="update_my_name_val">
                        </form>
                    </span>
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
        <?php if (isset($message) && $message): ?>
            <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-xl relative mb-6 backdrop-blur-sm" role="alert">
                <span class="block sm:inline"><?= htmlspecialchars($message) ?></span>
            </div>
        <?php endif; ?>
