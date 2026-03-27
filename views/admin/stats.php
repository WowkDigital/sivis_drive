        <!-- Stats Dashboard -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-slate-800 p-6 rounded-2xl border border-slate-700 shadow-xl flex items-center">
                <div class="p-4 bg-blue-500/10 rounded-2xl mr-4">
                    <i data-lucide="files" class="w-8 h-8 text-blue-400"></i>
                </div>
                <div>
                    <p class="text-slate-500 text-sm font-medium uppercase tracking-wider">Wszystkie pliki</p>
                    <h4 class="text-3xl font-bold text-white"><?= $total_files ?></h4>
                </div>
            </div>
            <div class="bg-slate-800 p-6 rounded-2xl border border-slate-700 shadow-xl flex items-center">
                <div class="p-4 bg-emerald-500/10 rounded-2xl mr-4">
                    <i data-lucide="hard-drive" class="w-8 h-8 text-emerald-400"></i>
                </div>
                <div>
                    <p class="text-slate-500 text-sm font-medium uppercase tracking-wider">Zajęte miejsce</p>
                    <h4 class="text-3xl font-bold text-white"><?= $formatted_size ?></h4>
                </div>
            </div>
            <div class="bg-slate-800 p-6 rounded-2xl border border-slate-700 shadow-xl flex items-center">
                <div class="p-4 bg-purple-500/10 rounded-2xl mr-4">
                    <i data-lucide="history" class="w-8 h-8 text-purple-400"></i>
                </div>
                <div>
                    <p class="text-slate-500 text-sm font-medium uppercase tracking-wider">Ost. logowanie admina</p>
                    <h4 class="text-lg font-bold text-white"><?= $last_admin ? date('d.m.y H:i', strtotime($last_admin)) : 'Brak danych' ?></h4>
                </div>
            </div>
        </div>
