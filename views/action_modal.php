<div id="action-modal" class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-slate-900/80 backdrop-blur-sm hidden opacity-0 transition-opacity duration-300">
    <div class="bg-slate-800 w-full max-w-md rounded-3xl border border-slate-700 shadow-2xl overflow-hidden transform scale-95 transition-transform duration-300">
        <div class="p-8 text-center">
            <div id="action-modal-icon-container" class="w-20 h-20 mx-auto bg-blue-500/10 rounded-full flex items-center justify-center mb-6 transition-colors shadow-inner">
                <i id="action-modal-icon" data-lucide="help-circle" class="w-10 h-10 text-blue-400"></i>
            </div>
            <h3 id="action-modal-title" class="text-2xl font-bold text-white mb-2 tracking-tight">Potwierdzenie</h3>
            <p id="action-modal-message" class="text-slate-400 text-sm leading-relaxed mb-6"></p>
            
            <div id="action-modal-input-container" class="hidden mb-6">
                <input type="text" id="action-modal-input" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3.5 text-slate-200 outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition-all font-medium text-center shadow-lg" placeholder="Wpisz nazwę...">
            </div>

            <div class="flex flex-col sm:flex-row gap-3 mt-8">
                <button id="action-modal-cancel" class="sm:flex-1 w-full px-6 py-3.5 bg-slate-700/50 hover:bg-slate-700 text-slate-400 hover:text-slate-200 font-bold rounded-2xl transition-all border border-slate-700/50 order-2 sm:order-1 outline-none">Anuluj</button>
                <button id="action-modal-confirm" class="sm:flex-1 w-full px-6 py-3.5 bg-blue-600 hover:bg-blue-500 text-white font-bold rounded-2xl shadow-lg shadow-blue-600/20 active:scale-95 transition-all order-1 sm:order-2 outline-none">Potwierdź</button>
            </div>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div id="preview-modal" class="fixed inset-0 z-[120] flex items-center justify-center p-4 sm:p-10 bg-slate-900/95 backdrop-blur-xl hidden opacity-0 transition-opacity duration-300">
    <div class="bg-slate-800 w-full h-full max-w-6xl rounded-3xl border border-slate-700/50 shadow-[0_35px_100px_rgba(0,0,0,0.8)] flex flex-col overflow-hidden transform scale-95 transition-transform duration-300">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-slate-700/60 bg-slate-800/80 flex items-center justify-between shrink-0">
            <div class="flex items-center space-x-4 min-w-0">
                <div class="p-2 bg-blue-500/10 rounded-xl hidden sm:block">
                    <i data-lucide="eye" class="w-5 h-5 text-blue-400"></i>
                </div>
                <h3 id="preview-title" class="text-lg font-bold text-white truncate pr-4">Podgląd pliku</h3>
            </div>
            <div class="flex items-center space-x-2">
                <button onclick="closePreview()" class="group p-2 bg-slate-700/50 hover:bg-red-500/20 text-slate-400 hover:text-red-400 rounded-xl transition-all border border-slate-700/50 hover:border-red-500/30">
                    <i data-lucide="x" class="w-6 h-6 group-hover:scale-110 transition-transform"></i>
                </button>
            </div>
        </div>
        
        <!-- Content -->
        <div id="preview-content" class="flex-1 overflow-auto bg-slate-900/40 relative">
            <div class="flex items-center justify-center h-full">
                <i data-lucide="loader-2" class="w-12 h-12 text-blue-500 animate-spin"></i>
            </div>
        </div>
        
        <!-- Footer / Info -->
        <div class="px-6 py-3 border-t border-slate-700/60 bg-slate-800/50 text-[10px] text-slate-500 uppercase font-black tracking-widest flex items-center justify-between shrink-0">
            <div class="flex items-center gap-4">
                <span class="flex items-center gap-1.5"><i data-lucide="shield-check" class="w-3 h-3 text-emerald-500"></i> Bezpieczny podgląd</span>
                <span class="hidden sm:inline-block opacity-20 text-slate-400">|</span>
                <span class="hidden sm:inline-block">Sivis Drive Internal Preview</span>
            </div>
            <div class="hidden sm:block opacity-50">Wowk Digital &copy; <?= date('Y') ?></div>
        </div>
    </div>
</div>

