    <footer class="max-w-7xl mx-auto py-12 px-4 text-center border-t border-slate-800/60 mt-12 mb-8">
        <p class="text-slate-500 text-sm flex flex-col sm:flex-row items-center justify-center gap-2 sm:gap-4 opacity-70 hover:opacity-100 transition-all duration-300">
            <span class="flex items-center gap-1.5 font-medium">
                Made with <i data-lucide="heart" class="w-4 h-4 text-red-500 fill-red-500 animate-pulse"></i> by 
                <a href="https://wowk.digital" target="_blank" class="font-black text-slate-400 hover:text-blue-400 transition-colors tracking-tight">Wowk Digital</a>
            </span>
            <span class="hidden sm:inline-block w-1.5 h-1.5 bg-slate-700 rounded-full"></span>
            <span class="font-bold text-[10px] uppercase tracking-[0.2em] text-slate-600">&copy; <?= date('Y') ?> Sivis Drive</span>
        </p>
    </footer>
    </div> <!-- Close the wrapper div started in header.php -->

    <?php require_once 'footer_parts/styles.php'; ?>

    <script>
        /**
         * Sivis Drive Core Configuration
         */
        const inAppPreviewEnabled = <?= isset($in_app_preview_enabled) && $in_app_preview_enabled ? 'true' : 'false' ?>;
        let canEditCurrentFolder = <?= isset($can_edit) && $can_edit ? 'true' : 'false' ?>;
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

        async function fetchMoveTargets() {
            try {
                const res = await fetch('index.php?ajax_action=get_move_targets');
                moveTargets = await res.json();
            } catch (e) {
                console.error('Error fetching targets:', e);
            }
        }

        async function refreshUsage() {
            try {
                const res = await fetch('api/ajax.php?ajax_action=get_usage');
                const usage = await res.json();
                
                const count = usage.count || 0;
                const size = usage.size || 0;
                const perc_p = Math.min(100, Math.round((count / 500) * 100));
                const perc_s = Math.min(100, Math.round((size / (500 * 1024 * 1024)) * 100));
                
                const elCount = document.getElementById('usage-count');
                const elCountPerc = document.getElementById('usage-count-perc');
                const elCountBar = document.getElementById('usage-count-bar');
                const elSize = document.getElementById('usage-size');
                const elSizePerc = document.getElementById('usage-size-perc');
                const elSizeBar = document.getElementById('usage-size-bar');
                
                if (elCount) elCount.innerText = `Pliki (${count}/500)`;
                if (elCountPerc) elCountPerc.innerText = `${perc_p}%`;
                if (elCountBar) elCountBar.style.width = `${perc_p}%`;
                
                const mb = (size / (1024 * 1024)).toFixed(1);
                if (elSize) elSize.innerText = `Miejsce (${mb}MB/500MB)`;
                if (elSizePerc) elSizePerc.innerText = `${perc_s}%`;
                if (elSizeBar) elSizeBar.style.width = `${perc_s}%`;
            } catch (e) {
                console.error('Error refreshing usage:', e);
            }
        }
    </script>

    <?php 
        // Logic splits
        require_once 'footer_parts/toasts.php';
        require_once 'footer_parts/bulk_actions.php';
        require_once 'footer_parts/bulk_operations.php';
        require_once 'footer_parts/move_logic.php';
        require_once 'footer_parts/folder_ops.php';
        require_once 'footer_parts/upload.php';
        require_once 'footer_parts/drive_logic.php';
        require_once 'footer_parts/previews_init.php';
    ?>

</body>
</html>
