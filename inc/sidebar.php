<div class="w-full lg:w-1/4 space-y-6">
    <!-- Section: Moje Foldery -->
    <div class="bg-slate-800 shadow-xl rounded-2xl p-5 border border-slate-700">
        <h2 class="text-xs font-bold mb-4 flex items-center text-slate-500 uppercase tracking-widest leading-none">
            Moje foldery
        </h2>
        <ul class="space-y-1.5">
            <?php foreach ($my_folders as $f): ?>
                <li>
                    <a href="javascript:void(0)" onclick="loadFolder(<?= $f['id'] ?>, 0, true)" id="folder-link-<?= $f['id'] ?>" class="folder-link flex items-center justify-between px-4 py-3 rounded-xl transition-all duration-200 <?= $f['id'] == $active_folder_id ? 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 font-medium active-folder' : 'text-slate-400 hover:bg-slate-700 hover:text-slate-200' ?>">
                        <div class="flex items-center min-w-0">
                            <i data-lucide="user" class="w-5 h-5 mr-3 shrink-0 opacity-70"></i>
                            <span class="truncate">Mój folder</span>
                        </div>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        
        <?php 
            $usage = get_private_usage($db, $_SESSION['user_id']);
            $perc_p = min(100, round(($usage['count'] / 500) * 100));
            $perc_s = min(100, round(($usage['size'] / (500*1024*1024)) * 100));
        ?>
        <div class="mt-6 pt-4 border-t border-slate-700/50 space-y-3">
            <div>
                <div class="flex justify-between text-[10px] uppercase font-bold text-slate-500 mb-1">
                    <span>Pliki (<?= $usage['count'] ?>/500)</span>
                    <span><?= $perc_p ?>%</span>
                </div>
                <div class="h-1 w-full bg-slate-900 rounded-full overflow-hidden">
                    <div class="h-full bg-blue-500" style="width: <?= $perc_p ?>%"></div>
                </div>
            </div>
            <div>
                <div class="flex justify-between text-[10px] uppercase font-bold text-slate-500 mb-1">
                    <span>Miejsce (<?= round($usage['size']/(1024*1024), 1) ?>MB/500MB)</span>
                    <span><?= $perc_s ?>%</span>
                </div>
                <div class="h-1 w-full bg-slate-900 rounded-full overflow-hidden">
                    <div class="h-full bg-emerald-500" style="width: <?= $perc_s ?>%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section: Udostępnione Foldery -->
    <div class="bg-slate-800 shadow-xl rounded-2xl p-5 border border-slate-700">
        <h2 class="text-xs font-bold mb-4 flex items-center justify-between text-slate-500 uppercase tracking-widest leading-none">
            <span>Udostępnione foldery</span>
            <?php if (is_admin() || is_zarzad()): ?>
                <button onclick="const n=prompt('Nazwa nowego folderu udostępnionego:'); if(n){document.getElementById('new_shared_folder_name').value=n; document.getElementById('new_shared_folder_form').submit();}" class="p-1 hover:text-emerald-400 transition-colors" title="Dodaj folder udostępniony">
                    <i data-lucide="plus-circle" class="w-4 h-4"></i>
                </button>
                <form id="new_shared_folder_form" method="post" class="hidden">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="action" value="create_shared_folder">
                    <input type="hidden" name="name" id="new_shared_folder_name">
                </form>
            <?php endif; ?>
        </h2>
        <ul class="space-y-1.5">
            <?php if (empty($shared_folders)): ?>
                <li class="text-slate-500 text-[11px] py-1 px-3">Brak udostępnionych folderów.</li>
            <?php endif; ?>
            <?php foreach ($shared_folders as $f): 
                $is_restricted = trim($f['access_groups']) === 'zarząd';
            ?>
                <li>
                    <a href="javascript:void(0)" onclick="loadFolder(<?= $f['id'] ?>, 0, true)" id="folder-link-<?= $f['id'] ?>" class="folder-link flex items-center justify-between px-4 py-3 rounded-xl transition-all duration-200 <?= $f['id'] == $active_folder_id ? 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 font-medium active-folder' : 'text-slate-400 hover:bg-slate-700 hover:text-slate-200' ?>">
                        <div class="flex items-center min-w-0">
                            <i data-lucide="folder" class="w-5 h-5 mr-3 shrink-0 opacity-70"></i>
                            <span class="truncate text-sm"><?= htmlspecialchars($f['name']) ?></span>
                        </div>
                        <div class="ml-2 shrink-0 opacity-60" title="<?= $is_restricted ? 'Tylko Zarząd' : 'Wszyscy' ?>">
                            <i data-lucide="<?= $is_restricted ? 'shield-check' : 'users' ?>" class="w-3.5 h-3.5"></i>
                        </div>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- Section: Foldery Pracowników (Zarząd) -->
    <?php if (!empty($employees_folders)): ?>
    <div class="bg-slate-800 shadow-xl rounded-2xl p-5 border border-slate-700">
        <h2 class="text-xs font-bold mb-4 flex items-center text-slate-500 uppercase tracking-widest leading-none">
            Foldery pracowników
        </h2>
        <ul class="space-y-1.5">
            <?php foreach ($employees_folders as $f): 
                $has_new = has_recent_activity($db, $f['id']);
            ?>
                <li>
                    <a href="javascript:void(0)" onclick="loadFolder(<?= $f['id'] ?>, 0, true)" id="folder-link-<?= $f['id'] ?>" class="folder-link flex items-center justify-between px-4 py-2.5 rounded-xl text-slate-400 hover:bg-slate-700 hover:text-slate-200 transition-all text-sm <?= $f['id'] == $active_folder_id ? 'bg-purple-500/10 border border-purple-500/20 text-purple-400 font-medium active-folder' : '' ?>">
                        <div class="flex items-center truncate">
                            <i data-lucide="book-user" class="w-4 h-4 mr-3 shrink-0"></i>
                            <span class="truncate"><?= htmlspecialchars($f['name']) ?></span>
                        </div>
                        <?php if ($has_new): ?>
                            <span class="flex h-2 w-2 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.8)] ml-2 shrink-0 animate-pulse" title="Nowe pliki w ciągu ost. 24h"></span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
</div>
