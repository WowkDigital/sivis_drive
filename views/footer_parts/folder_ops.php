<script>
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
                    await fetchMoveTargets();
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
                await fetchMoveTargets();
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
</script>
