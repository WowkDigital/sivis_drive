<script>
    function showDownloadOverlay() {
        let overlay = document.getElementById('download-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'download-overlay';
            overlay.className = 'fixed inset-0 z-[200] bg-slate-900/80 backdrop-blur-md flex items-center justify-center';
            overlay.innerHTML = `
                <div class="bg-slate-800 border border-slate-700 rounded-3xl p-10 flex flex-col items-center gap-6 shadow-2xl max-w-sm w-full mx-4">
                    <div class="relative w-20 h-20">
                        <div class="absolute inset-0 rounded-full border-4 border-slate-700"></div>
                        <div class="absolute inset-0 rounded-full border-4 border-blue-500 border-t-transparent animate-spin"></div>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                        </div>
                    </div>
                    <div class="text-center">
                        <h3 class="text-xl font-bold text-white mb-1">Pakowanie plików...</h3>
                        <p class="text-slate-400 text-sm">Poczekaj chwilę, trwa kompresja do ZIP</p>
                    </div>
                    <div class="w-full bg-slate-900 rounded-full h-2 overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-blue-600 via-blue-400 to-blue-600 rounded-full animate-[shimmer_1.5s_ease-in-out_infinite]" style="width:100%; background-size:200% 100%; animation: shimmer 1.5s linear infinite; background-image: linear-gradient(90deg, #1d4ed8 0%, #60a5fa 50%, #1d4ed8 100%); background-size:200%;"></div>
                    </div>
                    <p id="download-overlay-dots" class="text-blue-400 font-mono text-sm font-bold tracking-widest">●●●</p>
                </div>
            `;
            document.body.appendChild(overlay);

            // Animated dots
            const dots = ['●○○', '●●○', '●●●', '○●●', '○○●'];
            let di = 0;
            overlay._dotsInterval = setInterval(() => {
                const el = document.getElementById('download-overlay-dots');
                if (el) el.textContent = dots[di++ % dots.length];
            }, 400);
        }
        overlay.style.display = 'flex';
    }

    function hideDownloadOverlay() {
        const overlay = document.getElementById('download-overlay');
        if (overlay) {
            clearInterval(overlay._dotsInterval);
            overlay.style.display = 'none';
        }
    }

    async function bulkDownload() {
        if (selectedItems.size === 0) return;
        const items = Array.from(selectedItems).join(',');

        showDownloadOverlay();
        try {
            const response = await fetch('download.php?items=' + encodeURIComponent(items));
            if (!response.ok) throw new Error('Server error');

            const blob = await response.blob();
            const contentDisposition = response.headers.get('Content-Disposition') || '';
            let filename = 'SivisDrive_pobrane.zip';
            const match = contentDisposition.match(/filename="?([^"]+)"?/);
            if (match) filename = match[1];

            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            setTimeout(() => { URL.revokeObjectURL(url); a.remove(); }, 1000);

            showToast('Pobieranie gotowe! 📦');
        } catch (e) {
            showToast('Błąd podczas pobierania.', 'error');
        } finally {
            hideDownloadOverlay();
        }
    }

    function bulkMove() {
        const items = [];
        selectedItems.forEach(key => {
            const [type, id] = key.split('-');
            items.push({id, type});
        });

        if (items.length === 0) return;

        openMoveModal(items, items.length === 1 ? 'zaznaczony element' : `${items.length} elementów`);
    }

    function bulkDelete() {
        const items = [];
        selectedItems.forEach(key => {
            const [type, id] = key.split('-');
            items.push({id, type});
        });

        if (items.length === 0) return;

        showConfirmModal('Usunąć zaznaczone?', `Czy na pewno chcesz usunąć ${items.length} zaznaczonych elementów? Tej operacji nie da się cofnąć!`, () => {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'index.php';
            
            const csrf = document.createElement('input');
            csrf.type = 'hidden';
            csrf.name = 'csrf_token';
            csrf.value = '<?= generate_csrf_token() ?>';
            form.appendChild(csrf);

            const action = document.createElement('input');
            action.type = 'hidden';
            action.name = 'action';
            action.value = 'delete_multiple';
            form.appendChild(action);

            const folderId = document.createElement('input');
            folderId.type = 'hidden';
            folderId.name = 'current_folder_id';
            folderId.value = currentFolderId;
            form.appendChild(folderId);

            const ids = document.createElement('input');
            ids.type = 'hidden';
            ids.name = 'item_ids';
            ids.value = items.map(i => i.id).join(',');
            form.appendChild(ids);

            const types = document.createElement('input');
            types.type = 'hidden';
            types.name = 'item_types';
            types.value = items.map(i => i.type).join(',');
            form.appendChild(types);

            document.body.appendChild(form);
            form.submit();
        }, 'red');
    }
</script>
