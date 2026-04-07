<script>
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
            canEditCurrentFolder = !!data.can_edit;
            currentOffset = offset;

            // Update container border color
            const containerMain = document.getElementById('main-drive-container');
            if (containerMain) {
                containerMain.classList.remove('border-slate-700', 'border-emerald-500/40', 'border-blue-500/40', 'border-purple-500/30');
                containerMain.classList.remove('shadow-[0_0_20px_rgba(16,185,129,0.05)]', 'shadow-[0_0_20px_rgba(59,130,246,0.05)]', 'shadow-[0_0_20px_rgba(168,85,247,0.05)]');

                if (data.root_owner_id === null) {
                    containerMain.classList.add('border-emerald-500/40', 'shadow-[0_0_20px_rgba(16,185,129,0.05)]');
                } else if (data.root_owner_id == currentUserId) {
                    containerMain.classList.add('border-blue-500/40', 'shadow-[0_0_20px_rgba(59,130,246,0.05)]');
                } else {
                    containerMain.classList.add('border-purple-500/30', 'shadow-[0_0_20px_rgba(168,85,247,0.05)]');
                }
            }
            
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
                let bcHtml = '<i data-lucide="home" class="w-4 h-4 mr-2 text-blue-400"></i>';
                data.breadcrumbs.forEach((bc, i) => {
                    if (i > 0) bcHtml += '<i data-lucide="chevron-right" class="w-3.5 h-3.5 mx-1.5 opacity-50"></i>';
                    const isLast = i === data.breadcrumbs.length - 1;
                    bcHtml += `<a href="javascript:void(0)" onclick="loadFolder('${bc.id}', 0, true)" class="hover:text-blue-400 transition-colors ${isLast ? 'text-blue-300 font-bold bg-blue-500/10 px-2 py-0.5 rounded' : ''}">${escHtml(bc.name)}</a>`;
                });
                bcContainer.innerHTML = bcHtml;
            }
            
            const dateContainer = document.getElementById('current-folder-date');
            if (dateContainer && data.folder_created_at) {
                dateContainer.innerHTML = `<i data-lucide="clock" class="w-3.5 h-3.5 mr-1.5 opacity-70"></i> Utworzono: ${data.folder_created_at}`;
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
                        tr.onclick = () => loadFolder(item.public_id || item.id, 0, true);
                        tr.classList.add('cursor-pointer');
                        tr.innerHTML = `
                            ${checkboxHtml}
                            <td class="px-3 py-4 w-full min-w-0">
                                <div class="flex items-center min-w-0">
                                    <div class="p-2 bg-slate-900 rounded-lg mr-2 sm:mr-3 group-hover:bg-slate-800 transition-colors shrink-0">
                                        <i data-lucide="folder" class="w-5 h-5 text-blue-400"></i>
                                    </div>
                                    <div class="flex flex-col min-w-0 overflow-hidden">
                                        <span class="font-medium text-slate-200 pr-1 text-sm sm:text-base" title="${escHtml(item.name)}">${escHtml(item.name.length > 30 ? item.name.substring(0, 27) + '...' : item.name)}</span>
                                        <span class="text-[10px] text-slate-500 uppercase font-bold tracking-tight sm:hidden">${item.file_count} plików</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4 text-center text-[10px] text-slate-500 hidden sm:table-cell uppercase font-bold tracking-tight">${escHtml(String(item.file_count))} plików</td>
                            <td class="px-5 py-4 text-center text-sm text-slate-400 hidden md:table-cell whitespace-nowrap">${item.created_at ? new Date(item.created_at).toLocaleDateString('pl-PL', {day: '2-digit', month: '2-digit', year: 'numeric'}) : '-'}</td>
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
                        const dateStr = item.created_at ? new Date(item.created_at).toLocaleDateString('pl-PL', {day: '2-digit', month: '2-digit', year: 'numeric'}) : '-';

                        let previewBtn = '';
                        if (['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
                            if (inAppPreviewEnabled) {
                                previewBtn = `<button onclick="event.stopPropagation(); openPreview('${item.public_id || item.id}', '${escHtml(item.original_name)}')" class="p-1.5 sm:p-2 flex items-center justify-center bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/20 hover:text-emerald-300 rounded-lg transition-all duration-200 shadow-sm" title="Podgląd">
                                    <i data-lucide="eye" class="w-4 sm:w-4.5 h-4 sm:h-4.5"></i>
                                </button>`;
                            } else {
                                previewBtn = `<a href="download.php?id=${item.public_id || item.id}&action=view" target="_blank" class="p-1.5 sm:p-2 flex items-center justify-center bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/20 hover:text-emerald-300 rounded-lg transition-all duration-200 shadow-sm" title="Otwórz w nowej karcie"><i data-lucide="eye" class="w-4 sm:w-4.5 h-4 sm:h-4.5"></i></a>`;
                            }
                        }

                        let actions = `
                            <div class="flex items-center justify-end gap-1 sm:gap-2 shrink-0 relative">
                                <button id="actions-btn-${itemKey}" onclick="event.stopPropagation(); toggleActions('${itemKey}')" class="sm:hidden p-2 text-slate-400 hover:text-white bg-slate-900/50 rounded-xl transition-all" title="Opcje">
                                    <i data-lucide="more-vertical" class="w-5 h-5"></i>
                                </button>
                                <div id="actions-${itemKey}" class="hidden sm:flex items-center gap-1 sm:gap-2 shrink-0">
                                    ${previewBtn ? previewBtn.replace('p-1.5 sm:p-2', 'p-2 sm:p-2').replace('w-4 sm:w-4.5 h-4 sm:h-4.5', 'w-4.5 h-4.5 sm:w-4 sm:h-4') : ''}
                                    <a href="download.php?id=${item.public_id || item.id}" class="p-2 sm:p-2 flex items-center justify-center bg-blue-500/10 text-blue-400 hover:bg-blue-500/20 hover:text-blue-300 rounded-xl transition-all duration-200 shadow-sm" title="Pobierz">
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
                                        <span class="font-medium text-slate-200 pr-1 text-sm sm:text-base" title="${escHtml(item.original_name)}">${escHtml(item.original_name.length > 30 ? item.original_name.substring(0, 27) + '...' : item.original_name)}</span>
                                        <span class="text-[10px] sm:text-xs text-slate-500 sm:hidden mt-0.5 truncate shrink">${escHtml(sizeVal)} • ${escHtml(dateStr)}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4 whitespace-nowrap text-sm text-slate-400 hidden sm:table-cell text-xs text-center">${sizeVal}</td>
                            <td class="px-5 py-4 whitespace-nowrap text-sm text-slate-400 hidden md:table-cell text-xs text-center">${dateStr}</td>
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
            const activeLink = document.getElementById(`folder-link-${data.folder_id}`);
            if (activeLink) {
                activeLink.classList.add('active-folder');
                activeLink.classList.add('bg-emerald-500/10', 'text-emerald-400', 'font-medium');
            }

            // Update URL without reload
            const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?folder=' + data.active_folder_id;
            window.history.pushState({path:newUrl},'',newUrl);

            // Toggle drop-zone visibility
            const dropZone = document.getElementById('drop-zone');
            const uploadActions = document.getElementById('upload-actions');
            
            if (dropZone) {
                if (data.can_edit) {
                    dropZone.classList.remove('hidden');
                    if (uploadActions) uploadActions.classList.remove('hidden');
                } else {
                    dropZone.classList.add('hidden');
                    if (uploadActions) uploadActions.classList.add('hidden');
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
</script>
