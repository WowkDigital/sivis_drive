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
