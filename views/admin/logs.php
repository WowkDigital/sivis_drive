        <!-- Recent Activity Log -->
        <div class="mt-8 bg-slate-800 p-6 rounded-2xl shadow-xl border border-slate-700">
            <h3 class="text-lg font-bold mb-5 flex items-center border-b border-slate-700 pb-3 text-slate-100">
                <div class="p-1.5 bg-orange-500/10 rounded-lg mr-3">
                    <i data-lucide="activity" class="w-5 h-5 text-orange-400"></i>
                </div>
                Ostatnie aktywności (Logi)
            </h3>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="border-b border-slate-700 text-left">
                            <th class="px-6 py-4 text-xs font-semibold text-slate-400 uppercase tracking-wider">Użytkownik</th>
                            <th class="px-6 py-4 text-xs font-semibold text-slate-400 uppercase tracking-wider">Akcja</th>
                            <th class="px-6 py-4 text-xs font-semibold text-slate-400 uppercase tracking-wider">Szczegóły</th>
                            <th class="px-6 py-4 text-xs font-semibold text-slate-400 uppercase tracking-wider">Data</th>
                        </tr>
                    </thead>
                    <tbody id="logs-tbody" class="divide-y divide-slate-700/50">
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center text-slate-500 italic">Brak zarejestrowanych aktywności.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($logs as $l): ?>
                        <tr class="hover:bg-slate-700/30 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-slate-200"><?= htmlspecialchars($l['display_name'] ?: 'System') ?></div>
                                <div class="text-xs text-slate-500"><?= htmlspecialchars($l['email'] ?: '-') ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                $action = $l['action'];
                                $colorClass = 'bg-slate-500/10 text-slate-400 border border-slate-500/10';
                                
                                if (strpos($action, 'DELETE') !== false || strpos($action, 'TRASH') !== false) {
                                    $colorClass = 'bg-red-500/10 text-red-100 border border-red-500/20';
                                } elseif (strpos($action, 'RESTORE') !== false || strpos($action, 'SUCCESS') !== false) {
                                    $colorClass = 'bg-emerald-500/10 text-emerald-300 border border-emerald-500/20';
                                } elseif (strpos($action, 'ADMIN') !== false) {
                                    $colorClass = 'bg-purple-500/10 text-purple-300 border border-purple-500/20';
                                } elseif (strpos($action, 'UPLOAD') !== false || strpos($action, 'CREATE') !== false) {
                                    $colorClass = 'bg-blue-500/10 text-blue-300 border border-blue-500/20';
                                } elseif (strpos($action, 'MOVE') !== false || strpos($action, 'RENAME') !== false) {
                                    $colorClass = 'bg-amber-500/10 text-amber-300 border border-amber-500/20';
                                } elseif (strpos($action, 'LOGIN') !== false) {
                                    $colorClass = 'bg-cyan-500/10 text-cyan-300 border border-cyan-500/20';
                                }
                                ?>
                                <span class="px-2 py-1 text-[10px] font-bold rounded uppercase tracking-tighter <?= $colorClass ?>">
                                    <?= htmlspecialchars($action) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-400">
                                <?= htmlspecialchars($l['details']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-500">
                                <?= date('d.m.Y H:i', strtotime($l['created_at'])) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-6 flex justify-center">
                <button id="load-more-logs" onclick="loadMoreLogs()" class="flex items-center gap-2 px-6 py-2.5 bg-slate-700 hover:bg-slate-600 text-slate-300 rounded-xl transition-all border border-slate-600/50 hover:border-slate-500 font-medium text-sm shadow-lg group">
                    <i data-lucide="refresh-cw" class="w-4 h-4 group-hover:rotate-180 transition-transform duration-500"></i>
                    Załaduj kolejne 30 logów
                </button>
            </div>
        </div>
