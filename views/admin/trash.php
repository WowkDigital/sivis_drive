        <!-- Recycle Bin (Trash) -->
        <div id="trash" class="mt-8 bg-slate-800/80 p-6 rounded-3xl shadow-2xl border border-red-500/20 backdrop-blur-md">
            <h3 class="text-xl font-bold mb-6 flex items-center text-white">
                <div class="p-2.5 bg-red-500/20 rounded-xl mr-4">
                    <i data-lucide="trash-2" class="w-6 h-6 text-red-400"></i>
                </div>
                Kosz (Automatyczne usuwanie po 30 dniach)
                <span class="ml-auto text-xs font-bold bg-red-500/10 text-red-400 px-3 py-1.5 rounded-full border border-red-500/20 uppercase tracking-widest"><?= count($deleted_files) + count($deleted_folders) ?> element(ów)</span>
            </h3>
            
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
                    <tbody class="divide-y divide-slate-700/30">
                        <?php if (empty($deleted_files) && empty($deleted_folders)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center text-slate-500 italic bg-slate-900/20 rounded-xl">
                                    <div class="flex flex-col items-center gap-3">
                                        <i data-lucide="leaf" class="w-12 h-12 opacity-20"></i>
                                        Kosz jest pusty. Jak widać, wszystko jest na swoim miejscu! ♻️
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <!-- Files -->
                        <?php foreach ($deleted_files as $f): ?>
                        <tr class="hover:bg-slate-700/20 transition-all group">
                            <td class="px-4 py-4">
                                <div class="flex items-center">
                                    <div class="p-2 bg-slate-900 rounded-lg mr-3 shadow-inner">
                                        <i data-lucide="file" class="w-4 h-4 text-red-400/60"></i>
                                    </div>
                                    <div>
                                        <div class="text-sm font-bold text-slate-200"><?= htmlspecialchars($f['original_name']) ?></div>
                                        <div class="text-[10px] text-slate-500 font-mono"><?= round($f['size']/1024) ?> KB</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4 text-sm text-slate-400"><?= htmlspecialchars($f['u_email'] ?: 'Nieznany') ?></td>
                            <td class="px-4 py-4 text-xs text-slate-500 font-medium"><?= date('d.m.Y H:i', strtotime($f['deleted_at'])) ?></td>
                            <td class="px-4 py-4 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="download.php?id=<?= $f['id'] ?>" class="p-2 hover:bg-blue-500/20 text-blue-400 rounded-lg transition-all" title="Pobierz plik">
                                        <i data-lucide="download" class="w-5 h-5"></i>
                                    </a>
                                    <form method="post" class="inline">
                                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                        <input type="hidden" name="action" value="restore_item">
                                        <input type="hidden" name="type" value="file">
                                        <input type="hidden" name="item_id" value="<?= $f['id'] ?>">
                                        <button type="submit" class="p-2 hover:bg-emerald-500/20 text-emerald-400 rounded-lg transition-all" title="Przywróć">
                                            <i data-lucide="rotate-ccw" class="w-5 h-5"></i>
                                        </button>
                                    </form>
                                    <form method="post" class="inline" id="perm-del-file-<?= $f['id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                        <input type="hidden" name="action" value="delete_file">
                                        <input type="hidden" name="file_id" value="<?= $f['id'] ?>">
                                        <button type="button" onclick="showConfirmModal('Trwale usunąć plik?', 'Czy na pewno chcesz usunąć ten plik bezpowrotnie z serwera?', () => document.getElementById('perm-del-file-<?= $f['id'] ?>').submit(), 'red')" class="p-2 hover:bg-red-500/20 text-red-400 rounded-lg transition-all" title="Usuń trwale">
                                            <i data-lucide="trash-2" class="w-5 h-5"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <!-- Folders -->
                        <?php foreach ($deleted_folders as $f): ?>
                        <tr class="hover:bg-slate-700/20 transition-all group">
                            <td class="px-4 py-4">
                                <div class="flex items-center">
                                    <div class="p-2 bg-slate-900 rounded-lg mr-3 shadow-inner">
                                        <i data-lucide="folder" class="w-4 h-4 text-red-400/60"></i>
                                    </div>
                                    <div class="text-sm font-bold text-slate-200"><?= htmlspecialchars($f['name']) ?></div>
                                </div>
                            </td>
                            <td class="px-4 py-4 text-sm text-slate-400"><?= htmlspecialchars($f['u_email'] ?: 'System') ?></td>
                            <td class="px-4 py-4 text-xs text-slate-500 font-medium"><?= date('d.m.Y H:i', strtotime($f['deleted_at'])) ?></td>
                            <td class="px-4 py-4 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <form method="post" class="inline">
                                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                        <input type="hidden" name="action" value="restore_item">
                                        <input type="hidden" name="type" value="folder">
                                        <input type="hidden" name="item_id" value="<?= $f['id'] ?>">
                                        <button type="submit" class="p-2 hover:bg-emerald-500/20 text-emerald-400 rounded-lg transition-all" title="Przywróć">
                                            <i data-lucide="rotate-ccw" class="w-5 h-5"></i>
                                        </button>
                                    </form>
                                    <form method="post" class="inline" id="perm-del-folder-<?= $f['id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                        <input type="hidden" name="action" value="delete_folder">
                                        <input type="hidden" name="folder_id" value="<?= $f['id'] ?>">
                                        <button type="button" onclick="showConfirmModal('Trwale usunąć folder?', 'UWAGA: Spowoduje to bezpowrotne usunięcie folderu oraz całej jego zawartości z serwera!', () => document.getElementById('perm-del-folder-<?= $f['id'] ?>').submit(), 'red')" class="p-2 hover:bg-red-500/20 text-red-400 rounded-lg transition-all" title="Usuń trwale">
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
        </div>
