        <!-- Recycle Bin (Trash) -->
        <div id="trash" class="mt-8 bg-slate-800/80 p-6 rounded-3xl shadow-2xl border border-red-500/20 backdrop-blur-md">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                <h3 class="text-xl font-bold flex items-center text-white">
                    <div class="p-2.5 bg-red-500/20 rounded-xl mr-4">
                        <i data-lucide="trash-2" class="w-6 h-6 text-red-400"></i>
                    </div>
                    Kosz (Automatyczne usuwanie po 30 dniach)
                    <span class="ml-4 text-xs font-bold bg-red-500/10 text-red-400 px-3 py-1.5 rounded-full border border-red-500/20 uppercase tracking-widest"><?= $total_trash ?> element(ów)</span>
                </h3>

                <?php if ($total_trash > 0): ?>
                <form method="post" id="empty-trash-form">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="action" value="empty_trash">
                    <button type="button" onclick="showConfirmModal('Opróżnić kosz?', 'Czy na pewno chcesz bezpowrotnie usunąć WSZYSTKO co znajduje się w koszu? Tej operacji nie da się cofnąć!', () => document.getElementById('empty-trash-form').submit(), 'red')" class="w-full sm:w-auto px-4 py-2 bg-red-600 hover:bg-red-500 text-white font-bold rounded-xl transition-all shadow-lg shadow-red-600/20 active:scale-95 flex items-center justify-center gap-2">
                        <i data-lucide="trash-2" class="w-4 h-4"></i> Opróżnij kosz
                    </button>
                </form>
                <?php endif; ?>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="border-b border-slate-700/50 text-left">
                            <th class="px-4 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Nazwa / Typ</th>
                            <th class="px-4 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Właściciel</th>
                            <th class="px-4 py-4 text-xs font-bold text-slate-500 uppercase tracking-widest">Data usunięcia</th>
                            <th class="px-4 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-widest">Akcje</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/30" id="trash-tbody">
                        <?php if (empty($deleted_items)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center text-slate-500 italic bg-slate-900/20 rounded-xl">
                                    <div class="flex flex-col items-center gap-3">
                                        <i data-lucide="leaf" class="w-12 h-12 opacity-20"></i>
                                        Kosz jest pusty. Jak widać, wszystko jest na swoim miejscu! ♻️
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($deleted_items as $item): ?>
                        <tr class="hover:bg-slate-700/20 transition-all group">
                            <td class="px-4 py-4">
                                <div class="flex items-center">
                                    <div class="p-2 bg-slate-900 rounded-lg mr-3 shadow-inner">
                                        <?php if ($item['type'] === 'file'): ?>
                                            <i data-lucide="file" class="w-4 h-4 text-red-400/60"></i>
                                        <?php else: ?>
                                            <i data-lucide="folder" class="w-4 h-4 text-red-400/60"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="text-sm font-bold text-slate-200"><?= htmlspecialchars($item['name']) ?></div>
                                        <?php if ($item['type'] === 'file'): ?>
                                            <div class="text-[10px] text-slate-500 font-mono"><?= round($item['size']/1024) ?> KB</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4 text-sm text-slate-400"><?= htmlspecialchars($item['u_email'] ?: ($item['type'] === 'folder' ? 'System' : 'Nieznany')) ?></td>
                            <td class="px-4 py-4 text-xs text-slate-500 font-medium"><?= date('d.m.Y H:i', strtotime($item['deleted_at'])) ?></td>
                            <td class="px-4 py-4 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <?php if ($item['type'] === 'file'): ?>
                                        <a href="download.php?id=<?= $item['id'] ?>" class="p-2 hover:bg-blue-500/20 text-blue-400 rounded-lg transition-all" title="Pobierz plik">
                                            <i data-lucide="download" class="w-5 h-5"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <form method="post" class="inline">
                                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                        <input type="hidden" name="action" value="restore_item">
                                        <input type="hidden" name="type" value="<?= $item['type'] ?>">
                                        <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                        <button type="submit" class="p-2 hover:bg-emerald-500/20 text-emerald-400 rounded-lg transition-all" title="Przywróć">
                                            <i data-lucide="rotate-ccw" class="w-5 h-5"></i>
                                        </button>
                                    </form>
                                    
                                    <form method="post" class="inline" id="perm-del-item-<?= $item['type'] ?>-<?= $item['id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                        <input type="hidden" name="action" value="<?= $item['type'] === 'file' ? 'delete_file' : 'delete_folder' ?>">
                                        <input type="hidden" name="<?= $item['type'] ?>_id" value="<?= $item['id'] ?>">
                                        <button type="button" onclick="showConfirmModal('Trwale usunąć?', 'Czy na pewno chcesz usunąć ten element bezpowrotnie z serwera?', () => document.getElementById('perm-del-item-<?= $item['type'] ?>-<?= $item['id'] ?>').submit(), 'red')" class="p-2 hover:bg-red-500/20 text-red-400 rounded-lg transition-all" title="Usuń trwale">
                                            <i data-lucide="trash-2" class="w-5 h-5"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_trash > $trash_limit): ?>
            <div id="load-more-trash-container" class="mt-8 text-center">
                <button id="load-more-trash" onclick="loadMoreTrash()" class="px-6 py-2.5 bg-slate-700 hover:bg-slate-600 text-slate-300 font-bold rounded-xl border border-slate-600 transition-all flex items-center mx-auto gap-2">
                    <i data-lucide="refresh-cw" class="w-4 h-4"></i> Załaduj więcej
                </button>
            </div>
            <?php endif; ?>
        </div>

        <script>
            let trashOffset = <?= $trash_limit ?>;
            function loadMoreTrash() {
                const btn = document.getElementById('load-more-trash');
                const icon = btn.querySelector('.lucide');
                const limit = 10;
                const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

                btn.disabled = true;
                if (icon) icon.classList.add('animate-spin');

                fetch(`api/ajax.php?ajax_action=get_trash&offset=${trashOffset}&limit=${limit}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.items && data.items.length > 0) {
                            const container = document.getElementById('trash-tbody');
                            
                            data.items.forEach(item => {
                                const tr = document.createElement('tr');
                                tr.className = 'hover:bg-slate-700/20 transition-all group opacity-0 translate-y-2';
                                
                                let downloadBtn = item.type === 'file' 
                                    ? `<a href="download.php?id=${item.id}" class="p-2 hover:bg-blue-500/20 text-blue-400 rounded-lg transition-all" title="Pobierz plik">
                                         <i data-lucide="download" class="w-5 h-5"></i>
                                       </a>` 
                                    : '';

                                tr.innerHTML = `
                                    <td class="px-4 py-4">
                                        <div class="flex items-center">
                                            <div class="p-2 bg-slate-900 rounded-lg mr-3 shadow-inner">
                                                <i data-lucide="${item.type === 'file' ? 'file' : 'folder'}" class="w-4 h-4 text-red-400/60"></i>
                                            </div>
                                            <div>
                                                <div class="text-sm font-bold text-slate-200">${item.name}</div>
                                                ${item.type === 'file' ? `<div class="text-[10px] text-slate-500 font-mono">${item.formatted_size}</div>` : ''}
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-slate-400">${item.u_email}</td>
                                    <td class="px-4 py-4 text-xs text-slate-500 font-medium">${item.formatted_date}</td>
                                    <td class="px-4 py-4 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            ${downloadBtn}
                                            <form method="post" class="inline">
                                                <input type="hidden" name="csrf_token" value="${csrfToken}">
                                                <input type="hidden" name="action" value="restore_item">
                                                <input type="hidden" name="type" value="${item.type}">
                                                <input type="hidden" name="item_id" value="${item.id}">
                                                <button type="submit" class="p-2 hover:bg-emerald-500/20 text-emerald-400 rounded-lg transition-all" title="Przywróć">
                                                    <i data-lucide="rotate-ccw" class="w-5 h-5"></i>
                                                </button>
                                            </form>
                                            <form method="post" class="inline" id="perm-del-item-${item.type}-${item.id}">
                                                <input type="hidden" name="csrf_token" value="${csrfToken}">
                                                <input type="hidden" name="action" value="${item.type === 'file' ? 'delete_file' : 'delete_folder'}">
                                                <input type="hidden" name="${item.type}_id" value="${item.id}">
                                                <button type="button" onclick="showConfirmModal('Trwale usunąć?', 'Czy na pewno chcesz usunąć ten element bezpowrotnie z serwera?', () => document.getElementById('perm-del-item-${item.type}-${item.id}').submit(), 'red')" class="p-2 hover:bg-red-500/20 text-red-400 rounded-lg transition-all" title="Usuń trwale">
                                                    <i data-lucide="trash-2" class="w-5 h-5"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                `;
                                container.appendChild(tr);
                                setTimeout(() => {
                                    tr.classList.remove('opacity-0', 'translate-y-2');
                                    tr.classList.add('transition-all', 'duration-500');
                                }, 50);
                            });

                            trashOffset += data.items.length;
                            if (!data.has_more) {
                                btn.parentElement.classList.add('hidden');
                            }
                        } else {
                            btn.parentElement.classList.add('hidden');
                        }
                        if(window.lucide) lucide.createIcons();
                    })
                    .catch(err => {
                        console.error(err);
                        if(window.showToast) showToast('Błąd ładowania kosza', 'error');
                    })
                    .finally(() => {
                        btn.disabled = false;
                        if (icon) icon.classList.remove('animate-spin');
                    });
            }
        </script>
