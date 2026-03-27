<?php
/**
 * Sivis Drive - Admin Backups Management
 */
?>
<section class="bg-slate-800 shadow-2xl rounded-3xl border border-slate-700/50 mt-10 mb-10 overflow-hidden">
    <div class="p-6 sm:p-8 border-b border-slate-700 bg-slate-800/50 flex flex-col sm:flex-row justify-between items-center gap-4">
        <div class="flex items-center gap-4">
            <div class="p-3 bg-blue-500/10 rounded-2xl">
                <i data-lucide="archive" class="w-7 h-7 text-blue-400"></i>
            </div>
            <div>
                <h3 class="text-2xl font-bold text-white tracking-tight">Kopie Zapasowe (Backup)</h3>
                <p class="text-slate-400 text-sm">Automatyczne i ręczne zarządzanie danymi</p>
            </div>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            <input type="hidden" name="action" value="run_backup">
            <button type="submit" class="px-6 py-3 bg-blue-600 hover:bg-blue-500 text-white font-bold rounded-2xl transition-all shadow-lg shadow-blue-600/20 active:scale-95 flex items-center gap-2.5">
                <i data-lucide="play-circle" class="w-5 h-5"></i> Wykonaj Backup Teraz
            </button>
        </form>
    </div>

    <div class="p-6 sm:p-8">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-slate-900/50 border border-slate-700/50 p-5 rounded-2xl">
                <div class="text-slate-500 text-xs font-bold uppercase tracking-widest mb-2 flex items-center gap-2">
                    <i data-lucide="calendar" class="w-3.5 h-3.5"></i> Częstotliwość
                </div>
                <div class="text-xl font-bold text-white">Manualny / CRON</div>
                <div class="text-slate-500 text-xs mt-1">Poprzez przycisk lub harmonogram serwera</div>
            </div>
            <div class="bg-slate-900/50 border border-slate-700/50 p-5 rounded-2xl">
                <div class="text-slate-500 text-xs font-bold uppercase tracking-widest mb-2 flex items-center gap-2">
                    <i data-lucide="history" class="w-3.5 h-3.5"></i> Retencja
                </div>
                <div class="text-xl font-bold text-white">7 Dni</div>
                <div class="text-slate-500 text-xs mt-1">Starsze pliki są usuwane</div>
            </div>
            <div class="bg-slate-900/50 border border-slate-700/50 p-5 rounded-2xl">
                <div class="text-slate-500 text-xs font-bold uppercase tracking-widest mb-2 flex items-center gap-2">
                    <i data-lucide="database" class="w-3.5 h-3.5"></i> Zawartość
                </div>
                <div class="text-xl font-bold text-white">Pełny ZIP</div>
                <div class="text-slate-500 text-xs mt-1">Baza + wszystkie pliki</div>
            </div>
        </div>

        <?php if (empty($backups)): ?>
            <div class="text-center py-12 bg-slate-900/30 rounded-3xl border border-dashed border-slate-700/50">
                <div class="p-4 bg-slate-800 rounded-full inline-block mb-4">
                    <i data-lucide="archive-x" class="w-10 h-10 text-slate-600"></i>
                </div>
                <h4 class="text-lg font-bold text-slate-300">Brak dostępnych kopii</h4>
                <p class="text-slate-500 text-sm max-w-xs mx-auto mt-1">Wykonaj pierwszy backup ręcznie lub poczekaj na automatyczny proces jutro.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto bg-slate-900/30 rounded-2xl border border-slate-700/50">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-slate-400 text-[10px] uppercase font-bold tracking-widest border-b border-slate-700/50">
                            <th class="px-6 py-4">Nazwa Pliku</th>
                            <th class="px-6 py-4">Data Utworzenia</th>
                            <th class="px-6 py-4">Rozmiar</th>
                            <th class="px-6 py-4 text-right">Akcje</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/40">
                        <?php foreach ($backups as $b): ?>
                            <tr class="hover:bg-slate-700/20 transition-colors group">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="p-2 bg-slate-800 rounded-lg group-hover:bg-blue-500/10 transition-colors">
                                            <i data-lucide="file-archive" class="w-4 h-4 text-slate-400 group-hover:text-blue-400"></i>
                                        </div>
                                        <span class="text-sm font-medium text-slate-200"><?= htmlspecialchars($b['filename']) ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-400">
                                    <?= $b['date'] ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-400">
                                    <?= round($b['size'] / (1024*1024), 2) ?> MB
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="download_backup.php?filename=<?= urlencode($b['filename']) ?>" target="_blank" class="p-2 bg-slate-800 hover:bg-blue-600 text-slate-400 hover:text-white rounded-lg transition-all" title="Pobierz">
                                            <i data-lucide="download" class="w-4 h-4"></i>
                                        </a>
                                        <form method="POST" class="inline" onsubmit="return confirm('Czy na pewno chcesz usunąć ten plik backupu?')">
                                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                            <input type="hidden" name="action" value="delete_backup">
                                            <input type="hidden" name="filename" value="<?= htmlspecialchars($b['filename']) ?>">
                                            <button type="submit" class="p-2 bg-slate-800 hover:bg-red-600 text-slate-400 hover:text-white rounded-lg transition-all" title="Usuń">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
