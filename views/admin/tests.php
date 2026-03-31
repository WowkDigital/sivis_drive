<?php
/**
 * Sivis Drive - Admin System Tests View
 */
?>
<section class="bg-slate-800 shadow-2xl rounded-3xl border border-slate-700/50 mt-10 mb-10 overflow-hidden">
    <div class="p-6 sm:p-8 border-b border-slate-700 bg-slate-800/50 flex flex-col sm:flex-row justify-between items-center gap-4">
        <div class="flex items-center gap-4">
            <div class="p-3 bg-purple-500/10 rounded-2xl">
                <i data-lucide="shield-check" class="w-7 h-7 text-purple-400"></i>
            </div>
            <div>
                <h3 class="text-2xl font-bold text-white tracking-tight">Testy Systemowe</h3>
                <p class="text-slate-400 text-sm">Weryfikacja integralności i uprawnień przed wdrożeniem</p>
            </div>
        </div>
        <button id="run-system-tests" onclick="runSystemTests()" class="px-6 py-3 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded-2xl transition-all shadow-lg shadow-purple-600/20 active:scale-95 flex items-center gap-2.5">
            <i data-lucide="play" class="w-5 h-5"></i> Uruchom Diagnostykę
        </button>
    </div>

    <div class="p-6 sm:p-8">
        <div id="test-results-container" class="hidden">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-slate-900/50 border border-slate-700/50 p-5 rounded-2xl">
                    <div class="text-slate-500 text-[10px] font-bold uppercase tracking-widest mb-2 flex items-center gap-2">
                        <i data-lucide="list" class="w-3.5 h-3.5 text-blue-400"></i> Łącznie testów
                    </div>
                    <div id="test-total" class="text-3xl font-bold text-white">0</div>
                </div>
                <div class="bg-slate-900/50 border border-emerald-500/20 p-5 rounded-2xl">
                    <div class="text-emerald-500/60 text-[10px] font-bold uppercase tracking-widest mb-2 flex items-center gap-2">
                        <i data-lucide="check-circle" class="w-3.5 h-3.5 text-emerald-400"></i> Zakończone sukcesem
                    </div>
                    <div id="test-passed" class="text-3xl font-bold text-emerald-400">0</div>
                </div>
                <div id="test-failed-card" class="bg-slate-900/50 border border-slate-700/50 p-5 rounded-2xl transition-colors">
                    <div class="text-slate-500 text-[10px] font-bold uppercase tracking-widest mb-2 flex items-center gap-2">
                        <i data-lucide="alert-triangle" class="w-3.5 h-3.5" id="test-failed-icon"></i> Błędy / Niepowodzenia
                    </div>
                    <div id="test-failed" class="text-3xl font-bold text-white">0</div>
                </div>
            </div>

            <div class="overflow-x-auto bg-slate-900/30 rounded-2xl border border-slate-700/50 max-h-[400px] overflow-y-auto custom-scrollbar">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-slate-400 text-[10px] uppercase font-bold tracking-widest border-b border-slate-700/50 sticky top-0 bg-slate-800 z-10">
                            <th class="px-6 py-4">Scenariusz Testowy</th>
                            <th class="px-6 py-4 text-center">Czas</th>
                            <th class="px-6 py-4 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody id="test-results-tbody" class="divide-y divide-slate-700/40">
                        <!-- Filled via AJAX -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Initial Empty State -->
        <div id="test-empty-state" class="text-center py-16 bg-slate-900/30 rounded-3xl border border-dashed border-slate-700/50">
            <div class="p-5 bg-slate-800 rounded-full inline-block mb-4">
                <i data-lucide="flask-conical" class="w-10 h-10 text-slate-600"></i>
            </div>
            <h4 class="text-lg font-bold text-slate-300">Gotowy do testów</h4>
            <p class="text-slate-500 text-sm max-w-xs mx-auto mt-1">Uruchom diagnostykę, aby sprawdzić czy system uprawnień i logika plików działają poprawnie.</p>
        </div>
        
        <!-- Loading State -->
        <div id="test-loading-state" class="hidden text-center py-20 bg-slate-900/30 rounded-3xl border border-slate-700/50">
            <div class="w-16 h-16 border-4 border-purple-500/20 border-t-purple-500 rounded-full animate-spin mx-auto mb-6"></div>
            <h4 class="text-lg font-bold text-slate-300 animate-pulse">Trwa wykonywanie testów...</h4>
            <p class="text-slate-500 text-sm">Symulowanie zachowań użytkowników i sprawdzanie bazy danych.</p>
        </div>
    </div>
</section>

<script>
    async function runSystemTests() {
        const btn = document.getElementById('run-system-tests');
        const emptyState = document.getElementById('test-empty-state');
        const loadingState = document.getElementById('test-loading-state');
        const resultsContainer = document.getElementById('test-results-container');
        const tbody = document.getElementById('test-results-tbody');
        
        // UI Reset
        btn.disabled = true;
        btn.classList.add('opacity-50');
        emptyState.classList.add('hidden');
        resultsContainer.classList.add('hidden');
        loadingState.classList.remove('hidden');
        tbody.innerHTML = '';

        try {
            const response = await fetch('api/ajax.php?ajax_action=run_tests');
            const results = await response.json();

            if (results.error) {
                showToast(results.error, 'error');
                loadingState.classList.add('hidden');
                emptyState.classList.remove('hidden');
                btn.disabled = false;
                btn.classList.remove('opacity-50');
                return;
            }

            // Render results
            let passedCount = 0;
            let failedCount = 0;
            results.forEach(r => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-slate-700/20 transition-colors group';
                
                const isPass = r.status === 'PASS';
                if(isPass) passedCount++; else failedCount++;
                
                const statusColor = isPass ? 'text-emerald-400 bg-emerald-500/10' : 'text-red-400 bg-red-500/10';
                const statusIcon = isPass ? 'check-circle' : 'x-circle';

                tr.innerHTML = `
                    <td class="px-6 py-4">
                        <div class="flex flex-col">
                            <span class="text-sm font-bold text-slate-200">${r.name}</span>
                            ${r.message ? `<span class="text-[10px] text-red-400/80 mt-1 uppercase font-bold">${r.message}</span>` : ''}
                        </div>
                    </td>
                    <td class="px-6 py-4 text-center text-xs font-mono text-slate-500">
                        ${r.duration}ms
                    </td>
                    <td class="px-6 py-4 text-right">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-widest ${statusColor}">
                            <i data-lucide="${statusIcon}" class="w-3.5 h-3.5"></i> ${r.status}
                        </span>
                    </td>
                `;
                tbody.appendChild(tr);
            });

            // Update Cards
            document.getElementById('test-total').innerText = results.length;
            document.getElementById('test-passed').innerText = passedCount;
            document.getElementById('test-failed').innerText = failedCount;
            
            const failedCard = document.getElementById('test-failed-card');
            const failedIcon = document.getElementById('test-failed-icon');
            if (failedCount > 0) {
                failedCard.classList.add('border-red-500/30');
                document.getElementById('test-failed').classList.add('text-red-400');
                document.getElementById('test-failed').classList.remove('text-white');
                failedIcon.classList.add('text-red-400');
            } else {
                failedCard.classList.remove('border-red-500/30');
                document.getElementById('test-failed').classList.remove('text-red-400');
                document.getElementById('test-failed').classList.add('text-white');
                failedIcon.classList.remove('text-red-400');
            }

            loadingState.classList.add('hidden');
            resultsContainer.classList.remove('hidden');
            lucide.createIcons();
            showToast(failedCount === 0 ? "Wszystkie testy zakończone sukcesem! ✔" : `Testy zakończone z błędami (${failedCount})`, failedCount === 0 ? 'success' : 'error');

        } catch (e) {
            console.error(err);
            showToast('Błąd połączenia z serwerem testowym', 'error');
            loadingState.classList.add('hidden');
            emptyState.classList.remove('hidden');
        } finally {
            btn.disabled = false;
            btn.classList.remove('opacity-50');
        }
    }
</script>
<style>
.custom-scrollbar::-webkit-scrollbar { width: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: rgba(15, 23, 42, 0.5); }
.custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(51, 65, 85, 0.8); border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(71, 85, 105, 1); }
</style>
