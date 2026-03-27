    <footer class="max-w-7xl mx-auto py-12 px-4 text-center border-t border-slate-800/60 mt-8">
        <p class="text-slate-500 text-sm flex items-center justify-center gap-1.5 opacity-80 hover:opacity-100 transition-opacity">
            Made with <i data-lucide="heart" class="w-4 h-4 text-red-500 fill-red-500"></i> by <span class="font-bold text-slate-400">WowkDigital</span>
        </p>
    </footer>
    </div> <!-- Close the wrapper div started in header.php -->


    <style>
        @keyframes shimmer {
            0%   { background-position: 200% center; }
            100% { background-position: -200% center; }
        }
        /* Mobile actions expansion - inline in row, absolute to prevent horizontal scroll */
        .mobile-actions-active {
            display: flex !important;
            position: absolute;
            right: 110%; /* Place to the left of the trigger button */
            top: 50%;
            transform: translateY(-50%);
            background: rgba(30, 41, 59, 1); /* solid slate-800 to cover text below */
            padding: 0.25rem 0.5rem;
            border-radius: 0.75rem;
            border: 1px solid #334155; /* slate-700 */
            gap: 0.5rem !important;
            z-index: 40;
            box-shadow: -10px 0 20px rgba(15, 23, 42, 0.5);
            white-space: nowrap;
        }
    </style>
    <script>
        let currentFolderId = <?= $active_folder_id ?: 0 ?>;
        let currentOffset = 0;
        const limit = 10;
        let moveTargets = [];
        let selectedItems = new Set(); // Stores objects {id: 1, type: 'file'}

        function escHtml(str) {
            const d = document.createElement('div');
            d.appendChild(document.createTextNode(str ?? ''));
            return d.innerHTML;
        }

        function initIcons() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }

        function toggleActions(key) {
            const container = document.getElementById(`actions-${key}`);
            if (!container) return;
            
            // Close other open menus
            document.querySelectorAll('[id^="actions-"].mobile-actions-active').forEach(el => {
                if (el.id !== `actions-${key}`) {
                    el.classList.remove('mobile-actions-active');
                }
            });

            container.classList.toggle('mobile-actions-active');
        }

        // Close menus on click outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('[id^="actions-"]') && !e.target.closest('button[id^="actions-btn-"]')) {
                document.querySelectorAll('[id^="actions-"].mobile-actions-active').forEach(el => {
                    el.classList.remove('mobile-actions-active');
                });
            }
        });

        function showToast(message, type = 'success') {
            let container = document.getElementById('toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toast-container';
                container.className = 'fixed bottom-6 right-6 z-[100] flex flex-col gap-3 pointer-events-none';
                document.body.appendChild(container);
            }

            const toast = document.createElement('div');
            toast.className = `transform translate-x-full opacity-0 transition-all duration-500 ease-out flex items-center gap-3 px-6 py-4 rounded-2xl shadow-2xl border backdrop-blur-md pointer-events-auto min-w-[200px] mb-2
                ${type === 'success' ? 'bg-emerald-500/20 border-emerald-500/30 text-emerald-100' : 'bg-red-500/20 border-red-500/30 text-red-100'}`;
            
            const icon = type === 'success' ? 'check-circle' : 'alert-circle';
            toast.innerHTML = `
                <div class="p-1.5 rounded-lg ${type === 'success' ? 'bg-emerald-500/20' : 'bg-red-500/20'}">
                    <i data-lucide="${icon}" class="w-5 h-5"></i>
                </div>
                <span class="font-bold text-sm tracking-wide">${message}</span>
            `;

            container.appendChild(toast);
            lucide.createIcons();

            // Animate In
            setTimeout(() => {
                toast.classList.remove('translate-x-full', 'opacity-0');
            }, 10);

            // Animate Out
            setTimeout(() => {
                toast.classList.add('translate-x-full', 'opacity-0');
                setTimeout(() => toast.remove(), 500);
            }, 3000);
        }

        async function fetchMoveTargets() {
            try {
                const res = await fetch('index.php?ajax_action=get_move_targets');
                moveTargets = await res.json();
            } catch (e) {
                console.error('Error fetching targets:', e);
            }
        }

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
            document.getElementById('bulk-count').innerText = count;

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

        function openMoveModal(items, description) {
            // items can be a single ID or an array of {id, type}
            const modal = document.getElementById('move-modal');
            const list = document.getElementById('modal-folder-list');
            
            let idsInput = document.getElementById('modal-move-items-ids');
            if (!idsInput) {
                idsInput = document.createElement('input');
                idsInput.type = 'hidden';
                idsInput.name = 'item_ids';
                idsInput.id = 'modal-move-items-ids';
                document.getElementById('modal-move-form').appendChild(idsInput);
                
                // Add type input as well
                const typesInput = document.createElement('input');
                typesInput.type = 'hidden';
                typesInput.name = 'item_types';
                typesInput.id = 'modal-move-items-types';
                document.getElementById('modal-move-form').appendChild(typesInput);
                
                // Change action to bulk
                document.querySelector('#modal-move-form input[name="action"]').value = 'move_multiple';
            }

            if (Array.isArray(items)) {
                idsInput.value = items.map(i => i.id).join(',');
                document.getElementById('modal-move-items-types').value = items.map(i => i.type).join(',');
            } else {
                // Backward compatibility for single file move
                idsInput.value = items;
                document.getElementById('modal-move-items-types').value = 'file';
            }

            document.getElementById('move-modal-filename').innerText = description;

            // Render Tree nicely
            const tree = [];
            const map = {};
            moveTargets.forEach(f => map[f.id] = {...f, children: []});
            moveTargets.forEach(f => {
                if (f.parent_id && map[f.parent_id]) {
                    map[f.parent_id].children.push(map[f.id]);
                } else {
                    tree.push(map[f.id]);
                }
            });

            const renderNodes = (nodes, depth = 0) => {
                let html = '';
                nodes.forEach(n => {
                    const isCurrent = n.id == currentFolderId;
                    html += `
                        <div class="group/folder px-2 py-1">
                            <button onclick="confirmMove(${n.id})" 
                                    class="w-full text-left flex items-center px-4 py-3 rounded-2xl transition-all duration-200 
                                    ${isCurrent ? 'bg-purple-500/5 border border-purple-500/20 text-purple-400 cursor-default opacity-50' : 'text-slate-400 hover:bg-slate-700 hover:text-white'}" 
                                    ${isCurrent ? 'disabled' : ''}>
                                <div class="flex items-center min-w-0" style="margin-left: ${depth * 1.5}rem">
                                    <div class="p-1.5 rounded-lg mr-3 ${isCurrent ? 'bg-purple-500/10' : 'bg-slate-900 group-hover/folder:bg-slate-600'} transition-colors">
                                        <i data-lucide="folder" class="w-4 h-4 ${isCurrent ? 'text-purple-400' : 'text-blue-400'}"></i>
                                    </div>
                                    <span class="truncate font-medium">${escHtml(n.name)}</span>
                                    ${isCurrent ? '<span class="ml-auto text-[10px] uppercase font-bold tracking-widest opacity-50">(Tu są elementy)</span>' : ''}
                                </div>
                            </button>
                            ${n.children.length > 0 ? renderNodes(n.children, depth + 1) : ''}
                        </div>
                    `;
                });
                return html;
            };

            list.innerHTML = renderNodes(tree);
            lucide.createIcons();
            
            modal.classList.remove('hidden');
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                modal.querySelector('div').classList.remove('scale-95');
            }, 10);
        }

        function closeMoveModal() {
            const modal = document.getElementById('move-modal');
            if (!modal) return;
            modal.classList.add('opacity-0');
            modal.querySelector('div').classList.add('scale-95');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }

        function confirmMove(folderId) {
            document.getElementById('modal-move-new-folder-id').value = folderId;
            document.getElementById('modal-move-form').submit();
        }

        function renameItem(id, type, currentName) {
            let extension = '';
            let displayName = currentName;
            
            if (type === 'file') {
                const lastDotIndex = currentName.lastIndexOf('.');
                if (lastDotIndex > 0) {
                    displayName = currentName.substring(0, lastDotIndex);
                    extension = currentName.substring(lastDotIndex);
                }
            }

            showPromptModal('Zmień nazwę:', displayName, async (newName) => {
                if (!newName || newName === displayName) return;
                
                const finalName = type === 'file' ? newName + extension : newName;
                
                const formData = new FormData();
                formData.append('item_id', id);
                formData.append('new_name', finalName);
                formData.append('type', type);
                formData.append('csrf_token', '<?= generate_csrf_token() ?>');

                try {
                    const response = await fetch('index.php?ajax_action=rename_item', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();
                    if (data.success) {
                        showToast("Nazwa została zmieniona! ✔");
                        loadFolder(currentFolderId, 0, true);
                    } else {
                        showToast(data.error || "Błąd zmiany nazwy", "error");
                    }
                } catch (e) {
                    showToast("Błąd połączenia", "error");
                }
            });
        }

        async function createNewFolder(name) {
            if (!name) return;
            const formData = new FormData();
            formData.append('name', name);
            formData.append('parent_id', currentFolderId);
            formData.append('csrf_token', '<?= generate_csrf_token() ?>');

            try {
                const response = await fetch('index.php?ajax_action=create_folder', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    showToast("Folder został utworzony! 📁");
                    loadFolder(currentFolderId, 0, true);
                } else {
                    showToast(data.error || "Błąd tworzenia folderu", "error");
                }
            } catch (e) {
                showToast("Błąd połączenia", "error");
            }
        }

        async function createSharedFolder(name) {
            if (!name) return;
            const formData = new FormData();
            formData.append('name', name);
            formData.append('csrf_token', '<?= generate_csrf_token() ?>');

            try {
                const response = await fetch('index.php?ajax_action=create_shared_folder', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    showToast("Nowy folder udostępniony został utworzony! 🌎");
                    location.reload(); // Sidebar needs update, simple reload for now or AJAX update sidebar
                } else {
                    showToast(data.error || "Błąd tworzenia folderu", "error");
                }
            } catch (e) {
                showToast("Błąd połączenia", "error");
            }
        }

        function copyFolderLink(btn) {
            const url = window.location.href;
            navigator.clipboard.writeText(url).then(() => {
                const icon = btn.querySelector('i') || btn.querySelector('svg');
                if (!icon) return;
                
                const originalData = icon.getAttribute('data-lucide') || icon.getAttribute('data-lucide-original');

                // Animate to success state
                icon.setAttribute('data-lucide', 'check');
                if (window.lucide) lucide.createIcons();
                
                btn.classList.add(
                    'text-emerald-400', 'border-emerald-500/40',
                    'bg-emerald-500/10', 'scale-110', 'shadow-[0_0_12px_rgba(52,211,153,0.3)]'
                );
                btn.classList.remove('text-slate-400', 'border-slate-700');
                initIcons();

                showToast('Link skopiowany do schowka! 📋');

                setTimeout(() => {
                    icon.setAttribute('data-lucide', originalData);
                    btn.classList.remove(
                        'text-emerald-400', 'border-emerald-500/40',
                        'bg-emerald-500/10', 'scale-110', 'shadow-[0_0_12px_rgba(52,211,153,0.3)]'
                    );
                    btn.classList.add('text-slate-400');
                    initIcons();
                }, 2000);
            });
        }

        async function handleFileUpload(files) {
            if (files.length === 0) return;
            
            const overlay = document.getElementById('upload-progress-overlay');
            const statusText = document.getElementById('upload-status-text');
            const progressBar = document.getElementById('upload-progress-bar');
            const percentText = document.getElementById('upload-percent-text');
            
            overlay.classList.remove('hidden', 'opacity-0');
            overlay.classList.add('pointer-events-auto');
            
            let totalFiles = files.length;
            let successFiles = 0;
            let currentFileIndex = 0;

            const uploadOneFile = (file) => {
                return new Promise((resolve, reject) => {
                    const formData = new FormData();
                    formData.append('csrf_token', '<?= generate_csrf_token() ?>');
                    formData.append('action', 'upload');
                    formData.append('folder_id', currentFolderId);
                    formData.append('file', file);
                    
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', 'index.php', true);
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                    
                    xhr.upload.onprogress = (e) => {
                        if (e.lengthComputable) {
                            const filePercent = Math.round((e.loaded / e.total) * 100);
                            statusText.innerText = `Przesyłanie (${currentFileIndex + 1}/${totalFiles}): ${file.name}`;
                            // Overall progress (approximate, since files might have different sizes)
                            const overallPercent = Math.round(((currentFileIndex / totalFiles) * 100) + (filePercent / totalFiles));
                            progressBar.style.width = overallPercent + '%';
                            percentText.innerText = overallPercent + '%';
                        }
                    };
                    
                    xhr.onload = () => {
                        if (xhr.status === 200) {
                            try {
                                const data = JSON.parse(xhr.responseText);
                                if (data.success) {
                                    successFiles++;
                                    resolve();
                                } else {
                                    reject(data.error || `Błąd przy pliku: ${file.name}`);
                                }
                            } catch (e) {
                                // If not JSON, assume success if status is 200 (backward compatibility)
                                successFiles++;
                                resolve();
                            }
                        } else {
                            reject(`Nie udało się wysłać pliku: ${file.name} (Status: ${xhr.status})`);
                        }
                    };
                    
                    xhr.onerror = () => reject('Błąd połączenia.');
                    xhr.send(formData);
                });
            };

            try {
                for (let i = 0; i < files.length; i++) {
                    currentFileIndex = i;
                    await uploadOneFile(files[i]);
                }
                
                statusText.innerText = `Zakończono! Wgrano ${successFiles} plików. 🎉`;
                progressBar.style.width = '100%';
                progressBar.classList.replace('bg-blue-500', 'bg-emerald-500');
                
                setTimeout(() => {
                    overlay.classList.add('opacity-0');
                    setTimeout(() => {
                        overlay.classList.add('hidden');
                        overlay.classList.remove('pointer-events-auto');
                        progressBar.style.width = '0%';
                        progressBar.classList.replace('bg-emerald-500', 'bg-blue-500');
                        loadFolder(currentFolderId, 0, true);
                    }, 300);
                }, 1500);

            } catch (error) {
                showToast(error, 'error');
                overlay.classList.add('hidden', 'opacity-0');
            }
        }

        async function loadFolder(folderId, offset = 0, clear = false) {
            if (clear) {
                currentOffset = 0;
                const tbody = document.getElementById('files-tbody');
                if (tbody) tbody.innerHTML = '';
                document.getElementById('load-more-container')?.classList.add('hidden');
                selectedItems.clear();
                updateBulkActionBar();
            }

            try {
                const response = await fetch(`index.php?ajax_action=get_folder_content&folder_id=${folderId}&offset=${offset}`);
                const data = await response.json();

                if (data.error) {
                    showToast(data.error, 'error');
                    return;
                }

                currentFolderId = folderId;
                currentOffset = offset;
                
                // Update folder name and badge
                const folderNameEl = document.getElementById('current-folder-name');
                if (folderNameEl) {
                    folderNameEl.classList.add('min-w-0', 'flex-1');
                    folderNameEl.innerHTML = `
                        <div class="p-2 bg-blue-500/10 rounded-lg mr-2 sm:mr-3 shrink-0">
                            <i data-lucide="folder-open" class="w-5 h-5 sm:w-6 sm:h-6 text-blue-400"></i>
                        </div>
                        <span class="truncate">${data.folder_name_html || escHtml(data.folder_name)}</span>
                    `;
                }
                
                const badge = document.getElementById('file-count-badge');
                if (badge) badge.innerText = `${data.total} elementów`;

                // Update Breadcrumbs
                const bcContainer = document.getElementById('breadcrumbs-container');
                if (bcContainer) {
                    let bcHtml = '<i data-lucide="home" class="w-3.5 h-3.5 mr-2"></i>';
                    data.breadcrumbs.forEach((bc, i) => {
                        if (i > 0) bcHtml += '<i data-lucide="chevron-right" class="w-3 h-3 mx-1 opacity-50"></i>';
                        const isLast = i === data.breadcrumbs.length - 1;
                        bcHtml += `<a href="javascript:void(0)" onclick="loadFolder(${bc.id}, 0, true)" class="hover:text-blue-400 transition-colors ${isLast ? 'text-slate-300 font-bold' : ''}">${escHtml(bc.name)}</a>`;
                    });
                    bcContainer.innerHTML = bcHtml;
                }

                // Render items
                const container = document.getElementById('items-container');
                if (!container) return;

                if (data.items.length > 0 || offset > 0) {
                    let tbody = document.getElementById('files-tbody');
                    
                    // If table doesn't exist, create it
                    if (!tbody) {
                        container.innerHTML = `
                            <div class="overflow-x-auto -mx-5 sm:mx-0">
                                <div class="inline-block min-w-full align-middle">
                                    <table id="files-table" class="w-full">
                                        <thead>
                                            <tr class="border-b border-slate-700 text-left">
                                                <th class="px-2 sm:px-3 py-4 w-10 sm:w-12 text-center">
                                                     <label class="flex items-center justify-center cursor-pointer mx-auto w-8 h-8">
                                                         <input type="checkbox" onclick="toggleSelectAll(this)" class="sr-only">
                                                         <div class="checkbox-box h-5 w-5 sm:h-6 sm:w-6 rounded-lg bg-slate-900 border-2 border-slate-700 transition-all duration-150 flex items-center justify-center hover:border-slate-500">
                                                             <svg class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-white opacity-0 transition-opacity duration-150" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                                                 <polyline points="20 6 9 17 4 12"></polyline>
                                                             </svg>
                                                         </div>
                                                     </label>
                                                 </th>
                                                <th class="px-3 sm:px-5 py-4 text-xs font-semibold text-slate-400 uppercase tracking-wider">Nazwa</th>
                                                <th class="px-5 py-4 text-xs font-semibold text-slate-400 uppercase tracking-wider hidden sm:table-cell w-32 text-center">Rozmiar / Typ</th>
                                                <th class="px-5 py-4 text-xs font-semibold text-slate-400 uppercase tracking-wider hidden md:table-cell w-40 text-center">Data</th>
                                                <th class="px-3 sm:px-5 py-4 text-right text-xs font-semibold text-slate-400 uppercase tracking-wider w-px whitespace-nowrap">Akcje</th>
                                            </tr>
                                        </thead>
                                        <tbody id="files-tbody" class="divide-y divide-slate-700/50"></tbody>
                                    </table>
                                </div>
                            </div>
                        `;
                        tbody = document.getElementById('files-tbody');
                    }

                    const emptyState = document.getElementById('empty-state');
                    if (emptyState) emptyState.remove();

                    data.items.forEach(item => {
                        const tr = document.createElement('tr');
                        const itemType = item.is_folder ? 'folder' : 'file';
                        const itemKey = `${itemType}-${item.id}`;
                        const isSelected = selectedItems.has(itemKey);
                        
                        tr.dataset.key = itemKey;
                        tr.className = 'hover:bg-slate-700/30 transition-colors group ' + (isSelected ? 'bg-blue-500/10 border-blue-500/20' : '');
                        
                        const checkboxHtml = `
                            <td class="px-2 sm:px-3 py-4 w-10 sm:w-12 text-center" onclick="event.stopPropagation()">
                                <label class="flex items-center justify-center cursor-pointer mx-auto w-8 h-8">
                                    <input type="checkbox" 
                                        class="item-checkbox sr-only"
                                        data-id="${item.id}" data-type="${itemType}"
                                        ${isSelected ? 'checked' : ''}
                                        onchange="onCheckboxChange(this)">
                                    <div class="checkbox-box h-5 w-5 sm:h-6 sm:w-6 rounded-lg border-2 transition-all duration-150 flex items-center justify-center
                                        ${isSelected
                                            ? 'bg-blue-600 border-blue-500 shadow-[0_0_8px_rgba(59,130,246,0.5)]'
                                            : 'bg-slate-900 border-slate-700 hover:border-slate-500'}"
                                    >
                                        <svg class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-white transition-opacity duration-150 ${isSelected ? 'opacity-100' : 'opacity-0'}"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="20 6 9 17 4 12"></polyline>
                                        </svg>
                                    </div>
                                </label>
                            </td>
                        `;

                        if (item.is_folder) {
                            tr.onclick = () => loadFolder(item.id, 0, true);
                            tr.classList.add('cursor-pointer');
                            tr.innerHTML = `
                                ${checkboxHtml}
                                <td class="px-3 py-4 w-full min-w-0">
                                    <div class="flex items-center min-w-0">
                                        <div class="p-2 bg-slate-900 rounded-lg mr-2 sm:mr-3 group-hover:bg-slate-800 transition-colors shrink-0">
                                            <i data-lucide="folder" class="w-5 h-5 text-blue-400"></i>
                                        </div>
                                        <div class="flex flex-col min-w-0 overflow-hidden">
                                            <span class="font-medium text-slate-200 truncate pr-1 text-sm sm:text-base">${escHtml(item.name)}</span>
                                            <span class="text-[10px] text-slate-500 uppercase font-bold tracking-tight sm:hidden">${item.file_count} plików</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-center text-[10px] text-slate-500 hidden sm:table-cell uppercase font-bold tracking-tight">${escHtml(String(item.file_count))} plików</td>
                                <td class="px-5 py-4 text-center text-sm text-slate-400 hidden md:table-cell">-</td>
                                <td class="px-2 sm:px-3 py-4 text-right">
                                    <div class="flex items-center justify-end gap-1 sm:gap-2 shrink-0 relative">
                                        <button id="actions-btn-${itemKey}" onclick="event.stopPropagation(); toggleActions('${itemKey}')" class="sm:hidden p-2 text-slate-400 hover:text-white bg-slate-900/50 rounded-xl transition-all" title="Opcje">
                                            <i data-lucide="more-vertical" class="w-5 h-5"></i>
                                        </button>
                                        <div id="actions-${itemKey}" class="hidden sm:flex items-center gap-1 sm:gap-2 shrink-0">
                                            ${data.can_edit ? `
                                                <button onclick="event.stopPropagation(); renameItem(${item.id}, 'folder', this.getAttribute('data-name'))" data-name="${escHtml(item.name)}" class="p-2 sm:p-2 flex items-center justify-center bg-yellow-500/10 text-yellow-500/70 hover:text-yellow-400 hover:bg-yellow-500/20 rounded-xl transition-all" title="Zmień nazwę">
                                                    <i data-lucide="edit-3" class="w-4.5 h-4.5 sm:w-4 sm:h-4"></i>
                                                </button>
                                                <form method="post" id="delete-folder-form-${item.id}" class="inline m-0 shrink-0" onclick="event.stopPropagation()">
                                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                                    <input type="hidden" name="action" value="delete_folder">
                                                    <input type="hidden" name="folder_id" value="${item.id}">
                                                    <button type="button" onclick="showConfirmModal('Usunąć folder?', 'Czy na pewno chcesz usunąć ten folder wraz z CAŁĄ zawartością?', () => document.getElementById('delete-folder-form-${item.id}').submit(), 'red')" title="Usuń folder" class="p-2 sm:p-2 text-red-500/50 hover:text-red-400 bg-red-500/5 hover:bg-red-500/10 rounded-xl transition-all flex items-center justify-center">
                                                        <i data-lucide="trash-2" class="w-4.5 h-4.5 sm:w-4 sm:h-4"></i>
                                                    </button>
                                                </form>
                                            ` : ''}
                                            <a href="javascript:void(0)" class="p-2 sm:p-2 inline-flex items-center justify-center bg-slate-700/50 text-slate-400 hover:text-white rounded-xl transition-all">
                                                <i data-lucide="chevron-right" class="w-4.5 h-4.5 sm:w-4 sm:h-4"></i>
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            `;
                        } else {
                            const ext = item.original_name.split('.').pop().toLowerCase();
                            let icon = 'file';
                            let iconColor = 'text-slate-400';
                            if (ext === 'pdf') { icon = 'file-text'; iconColor = 'text-red-400'; }
                            else if (['doc', 'docx'].includes(ext)) { icon = 'file-type-2'; iconColor = 'text-blue-400'; }
                            else if (['jpg', 'png', 'jpeg', 'gif', 'webp'].includes(ext)) { icon = 'image'; iconColor = 'text-emerald-400'; }
                            
                            const sizeVal = item.size > 1024*1024 ? (item.size/(1024*1024)).toFixed(2) + ' MB' : Math.round(item.size/1024) + ' KB';
                            const dateStr = new Date(item.created_at).toLocaleDateString('pl-PL', {day: '2-digit', month: '2-digit', year: 'numeric'});
                            const dateTimeStr = new Date(item.created_at).toLocaleString('pl-PL', {day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'});

                            let previewBtn = '';
                            if (['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
                                previewBtn = `<a href="download.php?id=${item.id}&action=view" target="_blank" class="p-1.5 sm:p-2 flex items-center justify-center bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/20 hover:text-emerald-300 rounded-lg transition-all duration-200 shadow-sm" title="Podgląd"><i data-lucide="eye" class="w-4 sm:w-4.5 h-4 sm:h-4.5"></i></a>`;
                            }

                            let actions = `
                                <div class="flex items-center justify-end gap-1 sm:gap-2 shrink-0 relative">
                                    <button id="actions-btn-${itemKey}" onclick="event.stopPropagation(); toggleActions('${itemKey}')" class="sm:hidden p-2 text-slate-400 hover:text-white bg-slate-900/50 rounded-xl transition-all" title="Opcje">
                                        <i data-lucide="more-vertical" class="w-5 h-5"></i>
                                    </button>
                                    <div id="actions-${itemKey}" class="hidden sm:flex items-center gap-1 sm:gap-2 shrink-0">
                                        ${previewBtn ? previewBtn.replace('p-1.5 sm:p-2', 'p-2 sm:p-2').replace('w-4 sm:w-4.5 h-4 sm:h-4.5', 'w-4.5 h-4.5 sm:w-4 sm:h-4') : ''}
                                        <a href="download.php?id=${item.id}" class="p-2 sm:p-2 flex items-center justify-center bg-blue-500/10 text-blue-400 hover:bg-blue-500/20 hover:text-blue-300 rounded-xl transition-all duration-200 shadow-sm" title="Pobierz">
                                            <i data-lucide="download" class="w-4.5 h-4.5 sm:w-4 sm:h-4"></i>
                                        </a>
                                        ${data.can_edit ? `
                                            <button onclick="renameItem(${item.id}, 'file', this.getAttribute('data-name'))" data-name="${escHtml(item.original_name)}" class="p-2 sm:p-2 flex items-center justify-center bg-yellow-500/10 text-yellow-500/70 hover:text-yellow-400 hover:bg-yellow-500/20 rounded-xl transition-all duration-200 border border-transparent hover:border-yellow-500/30" title="Zmień nazwę">
                                                <i data-lucide="edit-3" class="w-4.5 h-4.5 sm:w-4 sm:h-4"></i>
                                            </button>
                                            <button onclick="openMoveModal(${item.id}, this.getAttribute('data-name'))" data-name="${escHtml(item.original_name)}" class="p-2 sm:p-2 flex items-center justify-center bg-purple-500/10 text-purple-400 hover:text-purple-300 hover:bg-purple-500/20 rounded-xl transition-all duration-200 border border-transparent hover:border-purple-500/30" title="Przenieś plik">
                                                <i data-lucide="folder-input" class="w-4.5 h-4.5 sm:w-4 sm:h-4"></i>
                                            </button>
                                            <form method="post" id="delete-file-form-${item.id}" class="inline m-0 shrink-0" onclick="event.stopPropagation()">
                                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                                <input type="hidden" name="action" value="delete_file">
                                                <input type="hidden" name="file_id" value="${item.id}">
                                                <button type="button" onclick="showConfirmModal('Usunąć plik?', 'Czy na pewno chcesz trwale usunąć ten plik?', () => document.getElementById('delete-file-form-${item.id}').submit(), 'red')" title="Usuń" class="p-2 sm:p-2 text-red-500/50 hover:text-red-400 bg-red-500/5 hover:bg-red-500/10 rounded-xl transition-all duration-200 flex items-center justify-center">
                                                    <i data-lucide="trash-2" class="w-4.5 h-4.5 sm:w-4 sm:h-4"></i>
                                                </button>
                                            </form>
                                        ` : ''}
                                    </div>
                                </div>
                            `;

                            tr.innerHTML = `
                                ${checkboxHtml}
                                <td class="px-2 sm:px-3 py-4 min-w-0 w-full overflow-hidden">
                                    <div class="flex items-center min-w-0">
                                        <div class="p-2 bg-slate-900 rounded-lg mr-2 sm:mr-3 group-hover:bg-slate-800 transition-colors shrink-0">
                                            <i data-lucide="${icon}" class="w-5 h-5 ${iconColor}"></i>
                                        </div>
                                        <div class="flex flex-col min-w-0 overflow-hidden">
                                            <span class="font-medium text-slate-200 truncate pr-1 text-sm sm:text-base">${escHtml(item.original_name)}</span>
                                            <span class="text-[10px] sm:text-xs text-slate-500 sm:hidden mt-0.5 truncate shrink">${escHtml(sizeVal)} • ${escHtml(dateStr)}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap text-sm text-slate-400 hidden sm:table-cell text-xs text-center">${sizeVal}</td>
                                <td class="px-5 py-4 whitespace-nowrap text-sm text-slate-400 hidden md:table-cell text-xs text-center">${dateTimeStr}</td>
                                <td class="px-2 sm:px-3 py-4 whitespace-nowrap text-right text-sm shrink-0 w-px">${actions}</td>
                            `;
                        }
                        tbody.appendChild(tr);
                    });
                } else {
                    container.innerHTML = `
                        <div id="empty-state" class="text-center py-16 flex flex-col items-center">
                            <div class="bg-slate-900 p-4 rounded-full mb-4 ring-1 ring-slate-700">
                                <i data-lucide="file-question" class="w-10 h-10 text-slate-500"></i>
                            </div>
                            <h3 class="text-lg font-medium text-slate-300">Folder jest pusty</h3>
                            <p class="text-slate-500 mt-2">Brak plików i podfolderów w tej lokalizacji.</p>
                        </div>
                    `;
                }

                // Update pagination
                const loadMoreContainer = document.getElementById('load-more-container');
                if (data.has_more) {
                    loadMoreContainer?.classList.remove('hidden');
                } else {
                    loadMoreContainer?.classList.add('hidden');
                }

                // Update upload form current folder
                const uploadFolderId = document.getElementById('upload-folder-id');
                if (uploadFolderId) uploadFolderId.value = folderId;
                
                const deleteFolderParentId = document.getElementById('new_folder_parent_id');
                if (deleteFolderParentId) deleteFolderParentId.value = folderId;

                // Update sidebar active state
                document.querySelectorAll('.folder-link').forEach(l => l.classList.remove('active-folder', 'bg-emerald-500/10', 'border-emerald-500/20', 'text-emerald-400', 'bg-blue-500/10', 'border-blue-500/20', 'text-blue-400', 'bg-purple-500/10', 'border-purple-500/20', 'text-purple-400'));
                const activeLink = document.getElementById(`folder-link-${folderId}`);
                if (activeLink) {
                    activeLink.classList.add('active-folder');
                    activeLink.classList.add('bg-emerald-500/10', 'text-emerald-400', 'font-medium');
                }

                // Update URL without reload
                const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?folder=' + folderId;
                window.history.pushState({path:newUrl},'',newUrl);

                // Toggle drop-zone visibility
                const dropZone = document.getElementById('drop-zone');
                if (dropZone) {
                    if (data.can_edit) {
                        dropZone.classList.remove('hidden');
                    } else {
                        dropZone.classList.add('hidden');
                    }
                }

                // Toggle new-folder-btn (visible to anyone who can edit)
                const newFolderBtn = document.getElementById('new-folder-btn');
                if (newFolderBtn) {
                    if (data.can_edit) {
                        newFolderBtn.classList.remove('hidden');
                    } else {
                        newFolderBtn.classList.add('hidden');
                    }
                }
                
                // Toggle private notice
                const privateNotice = document.getElementById('private-notice');
                if (privateNotice) {
                    if (data.is_private_tree && data.user_role === 'pracownik') {
                        privateNotice.classList.remove('hidden');
                    } else {
                        privateNotice.classList.add('hidden');
                    }
                }

                initIcons();
                setupDragAndDrop();

            } catch (error) {
                console.error('Error loading folder:', error);
            }
        }

        document.getElementById('load-more-btn')?.addEventListener('click', () => {
            loadFolder(currentFolderId, currentOffset + limit);
        });

        function setupDragAndDrop() {
            const dropZone = document.getElementById('drop-zone');
            const fileInput = document.getElementById('file-input');

            if (dropZone && fileInput && !dropZone.dataset.setup) {
                dropZone.dataset.setup = "true";
                dropZone.addEventListener('click', () => fileInput.click());
                fileInput.addEventListener('change', () => { 
                    if (fileInput.files.length > 0) handleFileUpload(fileInput.files);
                });

                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                    dropZone.addEventListener(eventName, e => { e.preventDefault(); e.stopPropagation(); }, false);
                });

                ['dragenter', 'dragover'].forEach(eventName => {
                    dropZone.addEventListener(eventName, () => dropZone.classList.add('border-blue-500', 'bg-blue-500/5'), false);
                });

                ['dragleave', 'drop'].forEach(eventName => {
                    dropZone.addEventListener(eventName, () => dropZone.classList.remove('border-blue-500', 'bg-blue-500/5'), false);
                });

                dropZone.addEventListener('drop', e => {
                    const files = e.dataTransfer.files;
                    if (files.length > 0) handleFileUpload(files);
                }, false);
            }
        }

        document.addEventListener('DOMContentLoaded', async () => {
            await fetchMoveTargets();
            if (currentFolderId) {
                loadFolder(currentFolderId, 0, true);
            }

            <?php if (isset($_SESSION['toast'])): ?>
                showToast("<?= htmlspecialchars($_SESSION['toast'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>");
                <?php unset($_SESSION['toast']); ?>
            <?php endif; ?>
        });
    </script>
</body>
</html>
