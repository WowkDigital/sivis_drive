<script>
    function applyCheckboxVisual(input, checked) {
        const box = input.parentElement.querySelector('.checkbox-box');
        const svg = input.parentElement.querySelector('.checkbox-box svg');
        if (!box) return;
        if (checked) {
            box.classList.add('bg-blue-600', 'border-blue-500', 'shadow-[0_0_8px_rgba(59,130,246,0.5)]');
            box.classList.remove('bg-slate-900', 'border-slate-700');
            if (svg) svg.classList.replace('opacity-0', 'opacity-100');
        } else {
            box.classList.remove('bg-blue-600', 'border-blue-500', 'shadow-[0_0_8px_rgba(59,130,246,0.5)]');
            box.classList.add('bg-slate-900', 'border-slate-700');
            if (svg) svg.classList.replace('opacity-100', 'opacity-0');
        }
    }

    function onCheckboxChange(input) {
        applyCheckboxVisual(input, input.checked);
        updateItemSelected(input.dataset.id, input.dataset.type, input.checked);
    }

    function toggleSelectAll(checkbox) {
        const table = document.getElementById('files-table');
        if (!table) return;
        const checkboxes = table.querySelectorAll('input[type="checkbox"].item-checkbox');
        checkboxes.forEach(cb => {
            cb.checked = checkbox.checked;
            applyCheckboxVisual(cb, cb.checked);
            updateItemSelected(cb.dataset.id, cb.dataset.type, cb.checked);
        });
        // Also update the select-all box visual
        applyCheckboxVisual(checkbox, checkbox.checked);
        updateBulkActionBar();
    }

    function updateItemSelected(id, type, selected) {
        const itemKey = `${type}-${id}`;
        const row = document.querySelector(`tr[data-key="${itemKey}"]`);
        if (selected) {
            selectedItems.add(itemKey);
            if (row) row.classList.add('bg-blue-500/10', 'border-blue-500/20');
        } else {
            selectedItems.delete(itemKey);
            if (row) row.classList.remove('bg-blue-500/10', 'border-blue-500/20');
        }
        updateBulkActionBar();
    }

    function updateBulkActionBar() {
        let bar = document.getElementById('bulk-action-bar');
        if (!bar) {
            bar = document.createElement('div');
            bar.id = 'bulk-action-bar';
            bar.className = 'fixed bottom-4 sm:bottom-8 left-1/2 -translate-x-1/2 z-[60] bg-slate-800/95 backdrop-blur-2xl border border-blue-500/40 px-4 sm:px-6 py-3 sm:py-4 rounded-2xl sm:rounded-3xl shadow-[0_20px_50px_rgba(0,0,0,0.5)] flex items-center justify-between gap-4 sm:gap-10 transition-all duration-500 cubic-bezier(0.34, 1.56, 0.64, 1) translate-y-32 opacity-0 pointer-events-none max-w-[95%] sm:max-w-none';
            bar.innerHTML = `
                <div class="flex items-center gap-3 sm:gap-5 border-r border-slate-700/50 pr-4 sm:pr-8 shrink-0">
                    <div class="bg-blue-500 shadow-[0_0_15px_rgba(59,130,246,0.5)] text-white p-2 sm:p-2.5 rounded-xl sm:rounded-2xl shrink-0">
                        <i data-lucide="check-square" class="w-4 h-4 sm:w-5 h-5"></i>
                    </div>
                    <div class="flex flex-col">
                        <div class="flex items-baseline gap-1">
                            <span id="bulk-count" class="text-xl sm:text-2xl font-black text-white leading-none">0</span>
                            <span class="text-[10px] sm:text-xs text-blue-400 font-bold uppercase tracking-wider">Wybrano</span>
                        </div>
                        <span class="text-[9px] sm:text-[10px] text-slate-500 uppercase tracking-widest font-bold mt-0.5 hidden sm:block text-nowrap">Elementów</span>
                    </div>
                </div>
                <div class="flex items-center gap-2 sm:gap-2.5">
                    <button onclick="bulkDownload()" class="p-2.5 sm:p-3.5 bg-blue-600 hover:bg-blue-500 text-white rounded-xl sm:rounded-2xl transition-all active:scale-90 shadow-lg shadow-blue-600/20" title="Pobierz wybrane">
                        <i data-lucide="download" class="w-5 h-5 sm:w-6 h-6"></i>
                    </button>
                    <button onclick="bulkMove()" class="p-2.5 sm:p-3.5 bg-slate-700 hover:bg-slate-600 text-white rounded-xl sm:rounded-2xl border border-slate-600 transition-all active:scale-90" title="Przenieś wybrane">
                        <i data-lucide="folder-input" class="w-5 h-5 sm:w-6 h-6"></i>
                    </button>
                    <button onclick="bulkDelete()" class="p-2.5 sm:p-3.5 bg-red-500/10 hover:bg-red-500 text-red-500 hover:text-white rounded-xl sm:rounded-2xl border border-red-500/30 transition-all active:scale-90 shadow-lg shadow-red-500/10" title="Usuń wybrane">
                        <i data-lucide="trash-2" class="w-5 h-5 sm:w-6 h-6"></i>
                    </button>
                    <div class="w-px h-8 bg-slate-700/50 mx-1 sm:mx-2 hidden sm:block"></div>
                    <button onclick="clearSelection()" class="p-2 sm:p-3 text-slate-500 hover:text-white hover:bg-slate-700 rounded-xl transition-all" title="Anuluj zaznaczenie">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
            `;
            document.body.appendChild(bar);
            lucide.createIcons();
        }

        const count = selectedItems.size;
        const countEl = document.getElementById('bulk-count');
        if (countEl) countEl.innerText = count;

        if (count > 0) {
            bar.classList.remove('translate-y-32', 'opacity-0', 'pointer-events-none');
        } else {
            bar.classList.add('translate-y-32', 'opacity-0', 'pointer-events-none');
        }
    }

    function clearSelection() {
        selectedItems.clear();
        const checkboxes = document.querySelectorAll('input[type="checkbox"]');
        checkboxes.forEach(cb => {
            cb.checked = false;
            applyCheckboxVisual(cb, false);
        });
        document.querySelectorAll('tr[data-key]').forEach(tr => tr.classList.remove('bg-blue-500/10', 'border-blue-500/20'));
        updateBulkActionBar();
    }
</script>
