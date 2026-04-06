<div class="bg-slate-800 rounded-3xl shadow-xl border border-slate-700/60 overflow-hidden mb-8">
    <div class="px-6 py-5 border-b border-slate-700/60 bg-slate-800/50 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center space-x-3">
            <div class="p-2 bg-blue-500/10 rounded-xl">
                <i data-lucide="settings-2" class="w-6 h-6 text-blue-400"></i>
            </div>
            <div>
                <h3 class="text-lg font-bold text-white">Ustawienia Systemowe</h3>
                <p class="text-xs text-slate-400">Konfiguracja globalnych funkcji systemu</p>
            </div>
        </div>
    </div>
    
    <div class="p-6">
        <form method="post" action="admin.php" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            <input type="hidden" name="action" value="update_settings">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- In-app preview -->
                <div class="flex items-start space-x-4 p-4 rounded-2xl bg-slate-900/30 border border-slate-700/30 hover:border-blue-500/30 transition-colors">
                    <div class="pt-1">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="in_app_preview" value="1" class="sr-only peer" <?= $in_app_preview_enabled ? 'checked' : '' ?>>
                            <div class="w-11 h-6 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>
                    <div>
                        <span class="block text-sm font-bold text-slate-200">Podgląd plików w aplikacji</span>
                        <span class="block text-xs text-slate-500 mt-1">Umożliwia wyświetlanie obrazów i plików PDF bezpośrednio w modalnym oknie, bez otwierania nowej karty.</span>
                    </div>
                </div>
                
                <!-- Enforce TOTP for Admin & Zarząd -->
                <div class="flex items-start space-x-4 p-4 rounded-2xl bg-slate-900/30 border border-slate-700/30 hover:border-emerald-500/30 transition-colors">
                    <div class="pt-1">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="enforce_2fa_admin" value="1" class="sr-only peer" <?= get_setting($db, 'enforce_2fa_admin', '0') == '1' ? 'checked' : '' ?>>
                            <div class="w-11 h-6 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                        </label>
                    </div>
                    <div>
                        <span class="block text-sm font-bold text-slate-200">Wymuszaj 2FA (TOTP) dla administracji i zarządu</span>
                        <span class="block text-xs text-slate-500 mt-1">Gdy włączone, użytkownicy o uprawnieniach administratora lub zarządu będą musieli używać 6-cyfrowego kodu przy logowaniu.</span>
                    </div>
                </div>

                <!-- Telegram Bot Notifications -->
                <div class="md:col-span-2 flex items-start space-x-4 p-6 rounded-2xl bg-slate-900/40 border border-slate-700/40 hover:border-sky-500/30 transition-colors">
                    <div class="p-3 bg-sky-500/10 rounded-xl">
                        <i data-lucide="send" class="w-6 h-6 text-sky-400"></i>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <span class="block text-sm font-bold text-slate-200">Powiadomienia Telegram</span>
                                <span class="block text-xs text-slate-500">Otrzymuj natychmiastowe powiadomienia na Telegramie gdy ktoś prosi o pomoc.</span>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="enable_contact_form" value="1" class="sr-only peer" <?= get_setting($db, 'enable_contact_form', '0') == '1' ? 'checked' : '' ?>>
                                <div class="w-11 h-6 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-sky-600"></div>
                            </label>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-1.5 ml-1">Bot Token</label>
                                <input type="text" name="telegram_bot_token" value="<?= htmlspecialchars(get_setting($db, 'telegram_bot_token', '')) ?>" class="w-full bg-slate-950/50 border border-slate-700 rounded-xl py-2 px-4 text-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500/50 transition-all placeholder:text-slate-600" placeholder="np. 12345678:ABC-DEF...">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-1.5 ml-1">Chat ID</label>
                                <input type="text" name="telegram_chat_id" value="<?= htmlspecialchars(get_setting($db, 'telegram_chat_id', '')) ?>" class="w-full bg-slate-950/50 border border-slate-700 rounded-xl py-2 px-4 text-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500/50 transition-all placeholder:text-slate-600" placeholder="np. 664433505">
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" name="action" value="test_telegram" class="inline-flex items-center space-x-2 px-4 py-2 bg-sky-500/10 hover:bg-sky-500/20 text-sky-400 rounded-xl font-bold text-xs transition-all border border-sky-500/20 active:scale-95">
                                <i data-lucide="send" class="w-3.5 h-3.5"></i>
                                <span>Wyślij wiadomość testową</span>
                            </button>
                        </div>
                    </div>
                </div>

            </div>
            
            <div class="flex justify-end pt-4 border-t border-slate-700/40">
                <button type="submit" class="flex items-center space-x-2 px-6 py-2.5 bg-blue-600 hover:bg-blue-500 text-white rounded-xl font-bold text-sm transition-all shadow-lg shadow-blue-500/20 active:scale-95">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    <span>Zapisz ustawienia</span>
                </button>
            </div>
        </form>
    </div>
</div>
