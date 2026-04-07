<script>
    async function handleFileUpload(files) {
        if (files.length === 0) return;
        
        const overlay = document.getElementById('upload-progress-overlay');
        const statusText = document.getElementById('upload-status-text');
        const progressBar = document.getElementById('upload-progress-bar');
        const percentText = document.getElementById('upload-percent-text');
        
        if (!overlay) { showToast('Błąd: strefa uploadu niedostępna', 'error'); return; }

        overlay.classList.remove('hidden', 'opacity-0');
        overlay.classList.add('pointer-events-auto');
        
        let totalFiles = files.length;
        let successFiles = 0;
        let failedFiles = 0;
        let currentFileIndex = 0;

        const uploadOneFile = (file) => {
            return new Promise((resolve) => {
                const formData = new FormData();
                formData.append('csrf_token', '<?= generate_csrf_token() ?>');
                formData.append('action', 'upload');
                formData.append('folder_id', currentFolderId);
                formData.append('file', file);
                const relPath = file.webkitRelativePath || file.fullPath || "";
                if (relPath) {
                    formData.append('relative_path', relPath);
                }
                
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'index.php', true);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                
                xhr.upload.onprogress = (e) => {
                    if (e.lengthComputable) {
                        const filePercent = Math.round((e.loaded / e.total) * 100);
                        if (statusText) statusText.innerText = `Przesyłanie (${currentFileIndex + 1}/${totalFiles}): ${file.name}`;
                        const overallPercent = Math.round(((currentFileIndex / totalFiles) * 100) + (filePercent / totalFiles));
                        if (progressBar) progressBar.style.width = overallPercent + '%';
                        if (percentText) percentText.innerText = overallPercent + '%';
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
                                failedFiles++;
                                showToast(data.error || `Błąd przy pliku: ${file.name}`, 'error');
                                resolve();
                            }
                        } catch (e) {
                            successFiles++;
                            resolve();
                        }
                    } else {
                        failedFiles++;
                        showToast(`Błąd serwera (${xhr.status}) dla: ${file.name}`, 'error');
                        resolve();
                    }
                };
                
                xhr.onerror = () => {
                    failedFiles++;
                    showToast(`Błąd połączenia dla: ${file.name}`, 'error');
                    resolve();
                };
                xhr.send(formData);
            });
        };

        try {
            for (let i = 0; i < files.length; i++) {
                currentFileIndex = i;
                await uploadOneFile(files[i]);
            }
            
            if (successFiles > 0) {
                if (statusText) statusText.innerText = `Zakończono! Wgrano ${successFiles} z ${totalFiles} plików. 🎉`;
                if (progressBar) {
                    progressBar.style.width = '100%';
                    progressBar.classList.replace('bg-blue-500', 'bg-emerald-500');
                }
            } else {
                if (statusText) statusText.innerText = `Nie wgrano żadnego pliku.`;
                if (progressBar) progressBar.classList.replace('bg-blue-500', 'bg-red-500');
            }
            
            setTimeout(() => {
                overlay.classList.add('opacity-0');
                setTimeout(() => {
                    overlay.classList.add('hidden');
                    overlay.classList.remove('pointer-events-auto');
                    if (progressBar) {
                        progressBar.style.width = '0%';
                        progressBar.classList.replace('bg-emerald-500', 'bg-blue-500');
                        progressBar.classList.replace('bg-red-500', 'bg-blue-500');
                    }
                    loadFolder(currentFolderId, 0, true);
                    if (window.refreshUsage) refreshUsage();
                    const fi = document.getElementById('file-input'); if(fi) fi.value = "";
                    const foi = document.getElementById('folder-input'); if(foi) foi.value = "";
                    if (successFiles > 0) showToast(`Wgrano ${successFiles} plików! 🎉`);
                }, 300);
            }, 1500);

        } catch (error) {
            showToast(String(error), 'error');
            overlay.classList.add('hidden', 'opacity-0');
            overlay.classList.remove('pointer-events-auto');
        }
    }

    function setupDragAndDrop() {
        const dropZone = document.getElementById('drop-zone');

        if (dropZone && !dropZone.dataset.setup) {
            dropZone.dataset.setup = "true";
            
            dropZone.addEventListener('click', () => {
                const fi = document.getElementById('file-input');
                if (fi) fi.click();
            });

            ['file-input', 'folder-input'].forEach(id => {
                const inp = document.getElementById(id);
                if (inp && !inp.dataset.setup) {
                    inp.dataset.setup = "true";
                    inp.addEventListener('change', () => {
                        if (inp.files.length > 0) handleFileUpload(inp.files);
                    });
                }
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

            async function traverseFileTree(item, path = "") {
                if (item.isFile) {
                    return new Promise((resolve) => {
                        item.file((file) => {
                            file.fullPath = path + item.name;
                            resolve([file]);
                        });
                    });
                } else if (item.isDirectory) {
                    let dirReader = item.createReader();
                    let entries = [];
                    let getEntries = async () => {
                        let results = await new Promise(resolve => dirReader.readEntries(resolve));
                        if (results.length) {
                            entries = entries.concat(results);
                            return getEntries();
                        }
                    };
                    await getEntries();
                    let files = [];
                    for (let entry of entries) {
                        files = files.concat(await traverseFileTree(entry, path + item.name + "/"));
                    }
                    return files;
                }
                return [];
            }

            dropZone.addEventListener('drop', async (e) => {
                const items = e.dataTransfer.items;
                if (items && items.length > 0) {
                    // Extract entries synchronously into an array first
                    const entries = [];
                    for (let i = 0; i < items.length; i++) {
                        let item = items[i].webkitGetAsEntry();
                        if (item) entries.push(item);
                    }
                    
                    let allFiles = [];
                    for (const entry of entries) {
                        allFiles = allFiles.concat(await traverseFileTree(entry));
                    }
                    if (allFiles.length > 0) handleFileUpload(allFiles);
                } else {
                    const files = e.dataTransfer.files;
                    if (files.length > 0) handleFileUpload(files);
                }
            }, false);
        }
    }
</script>
