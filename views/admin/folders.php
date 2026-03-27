        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
            <!-- Add Folder -->
            <div class="bg-slate-800 p-6 rounded-2xl shadow-xl border border-slate-700">
                <h3 class="text-lg font-bold mb-5 flex items-center border-b border-slate-700 pb-3 text-slate-100">
                    <div class="p-1.5 bg-emerald-500/10 rounded-lg mr-3">
                        <i data-lucide="folder-plus" class="w-5 h-5 text-emerald-400"></i>
                    </div>
                    Dodaj Folder Udostępniony
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
                                <?php if (empty($folders)): ?>
                                    <tr>
                                        <td colspan="2" class="px-6 py-8 text-center text-slate-500 italic">Brak folderów udostępnionych.</td>
                                    </tr>
                                <?php endif; ?>
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
                                        <div class="flex items-center justify-end gap-2">
                                            <form method="post" class="inline flex items-center gap-2 mr-2">
                                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                                <input type="hidden" name="action" value="update_folder">
                                                <input type="hidden" name="folder_id" value="<?= $f['id'] ?>">
                                                <select name="access_groups" onchange="this.form.submit()" class="bg-slate-900 border border-slate-700 rounded-lg px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-400 focus:border-blue-500 outline-none transition-all cursor-pointer">
                                                    <option value="zarząd,pracownicy" <?= $f['access_groups'] == 'zarząd,pracownicy' ? 'selected' : '' ?>>Wszyscy</option>
                                                    <option value="zarząd" <?= $f['access_groups'] == 'zarząd' ? 'selected' : '' ?>>Zarząd</option>
                                                </select>
                                            </form>
                                            <form method="post" id="delete-folder-form-<?= $f['id'] ?>" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                                <input type="hidden" name="action" value="delete_folder">
                                                <input type="hidden" name="folder_id" value="<?= $f['id'] ?>">
                                                <button type="button" onclick="showConfirmModal('Trwale usunąć folder?', 'UWAGA: Spowoduje to bezpowrotne usunięcie folderu oraz całej jego zawartości z serwera!', () => document.getElementById('delete-folder-form-<?= $f['id'] ?>').submit(), 'red')" class="p-2 text-red-400 hover:text-red-300 bg-red-500/10 hover:bg-red-500/20 rounded-lg transition-all" title="Usuń folder">
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
            </div>
        </div>
