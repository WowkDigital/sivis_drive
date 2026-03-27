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

            <!-- List Users (Moved to be next to Add User) -->
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
                                                <button type="button" onclick="showConfirmModal('Zresetować hasło?', 'Czy na pewno chcesz zresetować hasło dla <?= htmlspecialchars($u['email']) ?>?\\nZostanie wygenerowane nowe, losowe hasło.', () => document.getElementById('reset-pass-form-<?= $u['id'] ?>').submit(), 'orange')" class="p-2 text-orange-400 hover:text-orange-300 bg-orange-500/10 hover:bg-orange-500/20 rounded-lg transition-all" title="Resetuj hasło">
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
        </div>
