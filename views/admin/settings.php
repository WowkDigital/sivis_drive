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
                
                <!-- Placeholder for future settings -->
                <div class="flex items-start space-x-4 p-4 rounded-2xl bg-slate-900/10 border border-slate-700/10 opacity-50">
                    <div class="pt-1">
                        <i data-lucide="lock" class="w-5 h-5 text-slate-600"></i>
                    </div>
                    <div>
                        <span class="block text-sm font-bold text-slate-500">Więcej opcji wkrótce</span>
                        <span class="block text-xs text-slate-600 mt-1">Tu pojawią się dodatkowe ustawienia konfiguracji systemu.</span>
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
