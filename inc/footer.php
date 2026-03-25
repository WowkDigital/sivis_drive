    <footer class="max-w-7xl mx-auto py-8 px-4 text-center">
        <p class="text-slate-500 text-sm flex items-center justify-center gap-1.5">
            Made with <i data-lucide="heart" class="w-4 h-4 text-red-500 fill-red-500"></i> by <span class="font-bold text-slate-400">WowkDigital</span>
        </p>
    </footer>

    <script>
        let currentFolderId = <?= $active_folder_id ?: 0 ?>;
        let currentOffset = 0;
        const limit = 10;
        let moveTargets = [];

        function initIcons() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }

        async function fetchMoveTargets() {
            try {
                const res = await fetch('index.php?ajax_action=get_move_targets');
                const data = await res.json();
                
                // Build a simple tree with indentation
                const tree = [];
                const map = {};
                data.forEach(f => map[f.id] = {...f, children: []});
                data.forEach(f => {
                    if (f.parent_id && map[f.parent_id]) {
                        map[f.parent_id].children.push(map[f.id]);
                    } else {
                        tree.push(map[f.id]);
                    }
                });

                const flatted = [];
                const flatten = (nodes, depth = 0) => {
                    nodes.forEach(n => {
                        flatted.push({id: n.id, name: "  ".repeat(depth) + (depth > 0 ? "┕ " : "") + n.name});
                        flatten(n.children, depth + 1);
                    });
                };
                flatten(tree);
                moveTargets = flatted;
            } catch (e) {
                console.error('Error fetching targets:', e);
            }
        }

        function copyFolderLink(btn) {
            const url = window.location.href;
            navigator.clipboard.writeText(url).then(() => {
                const icon = btn.querySelector('i');
                const originalIcon = icon.getAttribute('data-lucide');
                icon.setAttribute('data-lucide', 'check');
                btn.classList.add('text-emerald-400', 'border-emerald-500/50', 'bg-emerald-500/5');
                initIcons();
                setTimeout(() => {
                    icon.setAttribute('data-lucide', originalIcon);
                    btn.classList.remove('text-emerald-400', 'border-emerald-500/50', 'bg-emerald-500/5');
                    initIcons();
                }, 2000);
            });
        }

        async function loadFolder(folderId, offset = 0, clear = false) {
            if (clear) {
                currentOffset = 0;
                const tbody = document.getElementById('files-tbody');
                if (tbody) tbody.innerHTML = '';
                document.getElementById('load-more-container')?.classList.add('hidden');
            }

            try {
                const response = await fetch(`index.php?ajax_action=get_folder_content&folder_id=${folderId}&offset=${offset}`);
                const data = await response.json();

                if (data.error) {
                    alert(data.error);
                    return;
                }

                currentFolderId = folderId;
                currentOffset = offset;
                
                // Update folder name and badge
                const folderNameEl = document.getElementById('current-folder-name');
                if (folderNameEl) {
                    folderNameEl.innerHTML = `
                        <div class="p-2 bg-blue-500/10 rounded-lg mr-3">
                            <i data-lucide="folder-open" class="w-6 h-6 text-blue-400"></i>
                        </div>
                        ${data.folder_name}
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
                        bcHtml += `<a href="javascript:void(0)" onclick="loadFolder(${bc.id}, 0, true)" class="hover:text-blue-400 transition-colors ${isLast ? 'text-slate-300 font-bold' : ''}">${bc.name}</a>`;
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
                                    <table class="w-full">
                                        <thead>
                                            <tr class="border-b border-slate-700 text-left">
                                                <th class="px-5 py-4 text-xs font-semibold text-slate-400 uppercase tracking-wider">Nazwa</th>
                                                <th class="px-5 py-4 text-xs font-semibold text-slate-400 uppercase tracking-wider hidden sm:table-cell w-32 text-center text-center">Rozmiar / Typ</th>
                                                <th class="px-5 py-4 text-xs font-semibold text-slate-400 uppercase tracking-wider hidden md:table-cell w-40 text-center text-center">Data</th>
                                                <th class="px-5 py-4 text-right text-xs font-semibold text-slate-400 uppercase tracking-wider w-px whitespace-nowrap">Akcje</th>
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
                        tr.className = 'hover:bg-slate-700/30 transition-colors group';
                        
                        if (item.is_folder) {
                            tr.onclick = () => loadFolder(item.id, 0, true);
                            tr.classList.add('cursor-pointer');
                            tr.innerHTML = `
                                <td class="px-3 py-4">
                                    <div class="flex items-center">
                                        <div class="p-2 bg-slate-900 rounded-lg mr-3 group-hover:bg-slate-800 transition-colors shrink-0">
                                            <i data-lucide="folder" class="w-5 h-5 text-blue-400"></i>
                                        </div>
                                        <div class="flex flex-col">
                                            <span class="font-medium text-slate-200">${item.name}</span>
                                            <span class="text-[10px] text-slate-500 uppercase font-bold tracking-tight sm:hidden">${item.file_count} plików</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-center text-[10px] text-slate-500 hidden sm:table-cell uppercase font-bold tracking-tight">${item.file_count} plików</td>
                                <td class="px-5 py-4 text-center text-sm text-slate-400 hidden md:table-cell">-</td>
                                <td class="px-3 py-4 text-right">
                                    <a href="javascript:void(0)" class="p-2 inline-flex items-center justify-center bg-slate-700/50 text-slate-400 hover:text-white rounded-lg transition-all">
                                        <i data-lucide="chevron-right" class="w-4 h-4"></i>
                                    </a>
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
                                previewBtn = `<a href="download.php?id=${item.id}&action=view" target="_blank" class="p-2 flex items-center justify-center bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/20 hover:text-emerald-300 rounded-lg transition-all duration-200 shadow-sm" title="Podgląd"><i data-lucide="eye" class="w-4.5 h-4.5"></i></a>`;
                            }

                            let moveOptions = '<option value="" disabled selected>Przenieś do...</option>';
                            moveTargets.forEach(t => {
                                if (t.id != currentFolderId) {
                                    moveOptions += `<option value="${t.id}">${t.name}</option>`;
                                }
                            });

                            let actions = `
                                <div class="flex items-center justify-end gap-1.5 sm:gap-2 shrink-0">
                                    ${previewBtn}
                                    <a href="download.php?id=${item.id}" class="p-2 flex items-center justify-center bg-blue-500/10 text-blue-400 hover:bg-blue-500/20 hover:text-blue-300 rounded-lg transition-all duration-200 shadow-sm" title="Pobierz"><i data-lucide="download" class="w-4.5 h-4.5"></i></a>
                                    ${data.can_edit ? `
                                        <div class="relative group/move shrink-0">
                                            <form method="post" class="m-0">
                                                <input type="hidden" name="action" value="move_file">
                                                <input type="hidden" name="file_id" value="${item.id}">
                                                <div class="p-2 flex items-center justify-center bg-slate-700/50 group-hover/move:bg-orange-500/20 group-hover/move:text-orange-300 rounded-lg transition-all duration-200 border border-transparent group-hover/move:border-orange-500/30">
                                                    <i data-lucide="folder-input" class="w-4.5 h-4.5"></i>
                                                </div>
                                                <select name="new_folder_id" onchange="this.form.submit()" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" title="Przenieś plik">
                                                    ${moveOptions}
                                                </select>
                                            </form>
                                        </div>
                                        <form method="post" onsubmit="return confirm('Czy na pewno chcesz usunąć ten plik?');" class="inline m-0 shrink-0">
                                            <input type="hidden" name="action" value="delete_file">
                                            <input type="hidden" name="file_id" value="${item.id}">
                                            <button type="submit" title="Usuń" class="p-2 text-red-500/50 hover:text-red-400 bg-red-500/5 hover:bg-red-500/10 rounded-lg transition-all duration-200 flex items-center justify-center">
                                                <i data-lucide="trash-2" class="w-4.5 h-4.5"></i>
                                            </button>
                                        </form>
                                    ` : ''}
                                </div>
                            `;

                            tr.innerHTML = `
                                <td class="px-3 py-4 min-w-0 max-w-0 w-full overflow-hidden">
                                    <div class="flex items-center min-w-0">
                                        <div class="p-2 bg-slate-900 rounded-lg mr-2 sm:mr-3 group-hover:bg-slate-800 transition-colors shrink-0">
                                            <i data-lucide="${icon}" class="w-5 h-5 ${iconColor}"></i>
                                        </div>
                                        <div class="flex flex-col min-w-0 overflow-hidden">
                                            <span class="font-medium text-slate-200 truncate pr-1 shrink text-sm sm:text-base">${item.original_name}</span>
                                            <span class="text-[10px] sm:text-xs text-slate-500 sm:hidden mt-0.5 truncate shrink">${sizeVal} • ${dateStr}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap text-sm text-slate-400 hidden sm:table-cell text-xs text-center">${sizeVal}</td>
                                <td class="px-5 py-4 whitespace-nowrap text-sm text-slate-400 hidden md:table-cell text-xs text-center">${dateTimeStr}</td>
                                <td class="px-3 py-4 whitespace-nowrap text-right text-sm shrink-0 w-px">${actions}</td>
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
                    activeLink.classList.add('bg-blue-500/10', 'text-blue-400', 'font-medium');
                }

                // Update URL without reload
                const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?folder=' + folderId;
                window.history.pushState({path:newUrl},'',newUrl);

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
            const uploadForm = document.getElementById('upload-form');

            if (dropZone && fileInput && !dropZone.dataset.setup) {
                dropZone.dataset.setup = "true";
                dropZone.addEventListener('click', () => fileInput.click());
                fileInput.addEventListener('change', () => { if (fileInput.files.length > 0) uploadForm.submit(); });

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
                    fileInput.files = files;
                    if (files.length > 0) uploadForm.submit();
                }, false);
            }
        }

        document.addEventListener('DOMContentLoaded', async () => {
            await fetchMoveTargets();
            if (currentFolderId) {
                loadFolder(currentFolderId, 0, true);
            }
        });
    </script>
</body>
</html>
