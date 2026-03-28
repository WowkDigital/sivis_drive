<script>
    function openMoveModal(items, description) {
        // items can be a single ID or an array of {id, type}
        const modal = document.getElementById('move-modal');
        const list = document.getElementById('modal-folder-list');
        if (!modal || !list) return;
        
        let idsInput = document.getElementById('modal-move-items-ids');
        if (!idsInput) {
            idsInput = document.createElement('input');
            idsInput.type = 'hidden';
            idsInput.name = 'item_ids';
            idsInput.id = 'modal-move-items-ids';
            const form = document.getElementById('modal-move-form');
            if (form) {
                form.appendChild(idsInput);
                
                // Add type input as well
                const typesInput = document.createElement('input');
                typesInput.type = 'hidden';
                typesInput.name = 'item_types';
                typesInput.id = 'modal-move-items-types';
                form.appendChild(typesInput);
                
                // Change action to bulk
                const actionInput = form.querySelector('input[name="action"]');
                if (actionInput) actionInput.value = 'move_multiple';
            }
        }

        const typeInput = document.getElementById('modal-move-items-types');

        if (Array.isArray(items)) {
            idsInput.value = items.map(i => i.id).join(',');
            if (typeInput) typeInput.value = items.map(i => i.type).join(',');
        } else {
            // Backward compatibility for single file move
            idsInput.value = items;
            if (typeInput) typeInput.value = 'file';
        }

        const filenameEl = document.getElementById('move-modal-filename');
        if (filenameEl) filenameEl.innerText = description;

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

        // FIND THE ROOT OF CURRENT FOLDER
        let currentRootId = null;
        let tempId = currentFolderId;
        while(tempId) {
            const f = moveTargets.find(x => x.id == tempId);
            if (!f) break;
            currentRootId = f.id;
            tempId = f.parent_id;
        }

        // SORT TREE: current root first, then items with owner_id == null (shared), then others
        tree.sort((a, b) => {
            if (a.id == currentRootId) return -1;
            if (b.id == currentRootId) return 1;
            if (a.owner_id === null && b.owner_id !== null) return -1;
            if (a.owner_id !== null && b.owner_id === null) return 1;
            return a.name.localeCompare(b.name);
        });

        const renderNodes = (nodes, depth = 0) => {
            let html = '';
            nodes.forEach(n => {
                const isCurrent = n.id == currentFolderId;
                const isSharedRoot = n.owner_id === null && n.parent_id === null;
                const isInCurrentTree = n.id == currentRootId;
                
                let bgClass = 'text-slate-400 hover:bg-slate-700 hover:text-white';
                if (isCurrent) {
                    bgClass = 'bg-purple-500/5 border border-purple-500/20 text-purple-400 cursor-default opacity-60';
                } else if (isSharedRoot) {
                    bgClass = 'bg-blue-500/5 border border-blue-500/10 text-blue-100 hover:bg-blue-500/10 hover:border-blue-500/30';
                } else if (isInCurrentTree && depth === 0) {
                    bgClass = 'bg-indigo-500/5 border border-indigo-500/10 text-indigo-100 hover:bg-indigo-500/10 hover:border-indigo-500/30';
                }

                html += `
                    <div class="group/folder px-2 py-1">
                        <button onclick="confirmMove(${n.id})" 
                                class="w-full text-left flex items-center px-4 py-3 rounded-2xl transition-all duration-200 ${bgClass}" 
                                ${isCurrent ? 'disabled' : ''}>
                            <div class="flex items-center min-w-0" style="margin-left: ${depth * 1.5}rem">
                                <div class="p-1.5 rounded-lg mr-3 ${isCurrent ? 'bg-purple-500/10' : (isSharedRoot ? 'bg-blue-500/20' : 'bg-slate-900 group-hover/folder:bg-slate-600')} transition-colors">
                                    <i data-lucide="${isSharedRoot ? 'share-2' : (isCurrent ? 'folder-open' : 'folder')}" class="w-4 h-4 ${isCurrent ? 'text-purple-400' : (isSharedRoot ? 'text-blue-400' : 'text-blue-400')}"></i>
                                </div>
                                <div class="flex flex-col min-w-0">
                                    <span class="truncate font-medium">${escHtml(n.name)}</span>
                                    ${isSharedRoot ? '<span class="text-[9px] uppercase font-extrabold text-blue-400 tracking-tighter opacity-70">Folder współdzielony</span>' : ''}
                                    ${isInCurrentTree && depth === 0 && !isSharedRoot ? '<span class="text-[9px] uppercase font-extrabold text-indigo-400 tracking-tighter opacity-70">Twoja aktualna lokalizacja</span>' : ''}
                                </div>
                                ${isCurrent ? '<span class="ml-auto text-[10px] uppercase font-bold tracking-widest opacity-50 pl-4 shrink-0">(To ten folder)</span>' : ''}
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
</script>
