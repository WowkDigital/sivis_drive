<?php
require_once 'core/admin_logic.php';
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
    <script src="https://unpkg.com/lucide@0.418.0/dist/umd/lucide.min.js"></script>
    <script src="assets/js/modals.js"></script>
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
        <?php 
        if (isset($_SESSION['toast'])) {
            $message = $_SESSION['toast'];
            unset($_SESSION['toast']);
        }
        if (isset($_SESSION['toast_error'])) {
            $message = $_SESSION['toast_error']; // Reusing $message for simplicity in admin panel
            unset($_SESSION['toast_error']);
        }
        ?>
        <?php if ($message): ?>
            <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-6 py-4 rounded-2xl relative mb-6 backdrop-blur-sm shadow-xl flex items-center justify-between">
                <div class="flex items-center">
                    <i data-lucide="check-circle" class="w-5 h-5 mr-3"></i>
                    <span class="font-medium"><?= htmlspecialchars($message) ?></span>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($new_user_password) && isset($new_user_email)): ?>
            <div class="bg-blue-500/10 border border-blue-500/30 text-blue-100 px-6 py-6 rounded-2xl relative mb-8 backdrop-blur-md shadow-2xl ring-1 ring-blue-500/20">
                <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                    <div class="flex items-start">
                        <div class="p-3 bg-blue-500/20 rounded-xl mr-4 shrink-0">
                            <i data-lucide="user-check" class="w-8 h-8 text-blue-400"></i>
                        </div>
                        <div>
                            <h4 class="text-lg font-bold text-white mb-1">Dane Logowania</h4>
                            <p class="text-blue-300/80 text-sm">Przekaż te dane użytkownikowi. Po zamknięciu strony wygenerowane hasło nie będzie widoczne.</p>
                        </div>
                    </div>
                    <div class="flex flex-col gap-2 bg-slate-900/80 p-4 rounded-xl border border-blue-500/30 w-full md:w-auto min-w-[280px]">
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-slate-400 text-sm font-medium">Login:</span>
                            <code class="px-2 py-1 bg-slate-800 text-sm font-mono text-slate-300 rounded border border-slate-700" id="generated-email"><?= htmlspecialchars($new_user_email) ?></code>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-slate-400 text-sm font-medium">Hasło:</span>
                            <code class="px-2 py-1 bg-slate-800 text-sm font-mono text-blue-400 tracking-wider rounded border border-slate-700" id="generated-password"><?= htmlspecialchars($new_user_password) ?></code>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-slate-400 text-sm font-medium">Uprawnienia:</span>
                            <code class="px-2 py-1 bg-slate-800 text-[10px] uppercase font-black tracking-widest text-emerald-400 rounded border border-slate-700" id="generated-role"><?= htmlspecialchars($new_user_role) ?></code>
                        </div>
                        <button onclick="copyCredentialsToClipboard(this)" class="mt-2 w-full flex justify-center items-center gap-2 py-2 px-4 bg-blue-600 hover:bg-blue-500 text-white font-bold text-sm rounded-lg transition-all shadow-lg shadow-blue-500/20 active:scale-95 group" title="Kopiuj dane logowania">
                            <i data-lucide="copy" class="w-4 h-4 group-active:scale-90 transition-transform"></i> Skopiuj dane
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php require_once 'views/admin/stats.php'; ?>
        <?php require_once 'views/admin/users.php'; ?>

        <?php require_once 'views/admin/folders.php'; ?>
        <?php require_once 'views/admin/logs.php'; ?>
        
        <?php require_once 'views/admin/tests.php'; ?>
        <?php require_once 'views/admin/settings.php'; ?>
        
        <?php require_once 'views/admin/trash.php'; ?>
        <?php require_once 'views/admin/backups.php'; ?>

    </div>

    <?php require_once 'views/action_modal.php'; ?>

    <footer class="max-w-7xl mx-auto py-12 px-4 text-center border-t border-slate-800/60 mt-12 mb-8">
        <p class="text-slate-500 text-sm flex flex-col sm:flex-row items-center justify-center gap-2 sm:gap-4 opacity-70 hover:opacity-100 transition-all duration-300">
            <span class="flex items-center gap-1.5 font-medium">
                Made with <i data-lucide="heart" class="w-4 h-4 text-red-500 fill-red-500 animate-pulse"></i> by 
                <a href="https://wowk.digital" target="_blank" class="font-black text-slate-400 hover:text-blue-400 transition-all tracking-tight">Wowk Digital</a>
            </span>
            <span class="hidden sm:inline-block w-1.5 h-1.5 bg-slate-700 rounded-full"></span>
            <span class="font-bold text-[10px] uppercase tracking-[0.2em] text-slate-600">&copy; <?= date('Y') ?> Sivis Drive</span>
        </p>
    </footer>

    <script>
        function copyCredentialsToClipboard(btn) {
            const email = document.getElementById('generated-email').innerText;
            const password = document.getElementById('generated-password').innerText;
            const role = document.getElementById('generated-role').innerText;
            const loginUrl = window.location.origin + window.location.pathname.replace('admin.php', '');
            const textToCopy = `Adres portalu: ${loginUrl}\nLogin: ${email}\nHasło: ${password}\nUprawnienia: ${role}`;
            
            navigator.clipboard.writeText(textToCopy).then(() => {
                btn.innerHTML = `<i data-lucide="check" class="w-4 h-4"></i> Skopiowano!`;
                btn.classList.add('bg-emerald-600', 'shadow-emerald-500/20');
                btn.classList.remove('bg-blue-600', 'shadow-blue-500/20');

                lucide.createIcons();
                setTimeout(() => {
                    btn.innerHTML = `<i data-lucide="copy" class="w-4 h-4 group-active:scale-90 transition-transform"></i> Skopiuj dane`;
                    btn.classList.remove('bg-emerald-600', 'shadow-emerald-500/20');
                    btn.classList.add('bg-blue-600', 'shadow-blue-500/20');
                    lucide.createIcons();
                }, 2000);
            });
        }

        let logsOffset = 15;
        function loadMoreLogs() {
            const btn = document.getElementById('load-more-logs');
            const icon = btn.querySelector('.lucide, i, svg');
            const limit = 30;

            btn.disabled = true;
            if (icon) icon.classList.add('animate-spin');

            fetch(`api/ajax.php?ajax_action=get_logs&offset=${logsOffset}&limit=${limit}`)
                .then(res => res.json())
                .then(data => {
                    if (data.logs && data.logs.length > 0) {
                        const container = document.getElementById('logs-tbody');
                        
                        data.logs.forEach(l => {
                            const tr = document.createElement('tr');
                            tr.className = 'hover:bg-slate-700/30 transition-colors opacity-0 translate-y-2';
                            tr.innerHTML = `
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-slate-200">${l.display_name}</div>
                                    <div class="text-xs text-slate-500">${l.email}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-[10px] font-bold rounded uppercase tracking-tighter ${l.color_class}">
                                        ${l.action}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-400">
                                    ${l.details}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-500">
                                    ${l.formatted_date}
                                </td>
                            `;
                            container.appendChild(tr);
                            // Animate in
                            setTimeout(() => {
                                tr.classList.remove('opacity-0', 'translate-y-2');
                                tr.classList.add('transition-all', 'duration-500');
                            }, 50);
                        });

                        logsOffset += data.logs.length;
                        if (!data.has_more) {
                            btn.innerHTML = '<i data-lucide="check" class="w-4 h-4"></i> Wszystkie logi załadowane';
                            btn.classList.add('opacity-50', 'cursor-default');
                            btn.onclick = null;
                        }
                    } else {
                        btn.innerHTML = '<i data-lucide="check" class="w-4 h-4"></i> Wszystkie logi załadowane';
                        btn.onclick = null;
                    }
                    lucide.createIcons();
                })
                .catch(err => {
                    console.error(err);
                    showToast('Błąd podczas ładowania logów', 'error');
                })
                .finally(() => {
                    btn.disabled = false;
                    if (icon) icon.classList.remove('animate-spin');
                });
        }

        function showToast(message, type = 'success') {
            let container = document.getElementById('toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toast-container';
                container.className = 'fixed bottom-6 right-6 z-[100] flex flex-col gap-3 pointer-events-none';
                document.body.appendChild(container);
            }
            const toast = document.createElement('div');
            toast.className = `transform translate-x-full opacity-0 transition-all duration-500 ease-out flex items-center gap-3 px-6 py-4 rounded-2xl shadow-2xl border backdrop-blur-md pointer-events-auto min-w-[200px] mb-2
                ${type === 'success' ? 'bg-emerald-500/20 border-emerald-500/30 text-emerald-100' : 'bg-red-500/20 border-red-500/30 text-red-100'}`;
            const icon = type === 'success' ? 'check-circle' : 'alert-circle';
            toast.innerHTML = `
                <div class="p-1.5 rounded-lg ${type === 'success' ? 'bg-emerald-500/20' : 'bg-red-500/20'}">
                    <i data-lucide="${icon}" class="w-5 h-5"></i>
                </div>
                <span class="font-bold text-sm tracking-wide">${message}</span>
            `;
            container.appendChild(toast);
            if(window.lucide) lucide.createIcons();
            setTimeout(() => toast.classList.remove('translate-x-full', 'opacity-0'), 10);
            setTimeout(() => {
                toast.classList.add('translate-x-full', 'opacity-0');
                setTimeout(() => toast.remove(), 500);
            }, 3000);
        }

        lucide.createIcons();
    </script>
</body>
</html>
