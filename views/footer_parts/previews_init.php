<script>
    function openPreview(id, filename) {
        const modal = document.getElementById('preview-modal');
        const content = document.getElementById('preview-content');
        const title = document.getElementById('preview-title');
        
        if (!modal || !content || !title) return;

        title.innerText = filename;
        content.innerHTML = '<div class="flex items-center justify-center h-full"><i data-lucide="loader-2" class="w-12 h-12 text-blue-500 animate-spin"></i></div>';
        
        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            modal.querySelector('div').classList.remove('scale-95');
        }, 10);
        
        const ext = filename.split('.').pop().toLowerCase();
        const url = `download.php?id=${id}&action=view`;
        
        if (ext === 'pdf') {
            content.innerHTML = `<iframe src="${url}" class="w-full h-full border-0"></iframe>`;
        } else {
            content.innerHTML = `<div class="w-full h-full flex items-center justify-center p-4 sm:p-8"><img src="${url}" class="max-w-full max-h-full object-contain shadow-2xl"></div>`;
        }
        
        if (window.lucide) lucide.createIcons();
    }

    function closePreview() {
        const modal = document.getElementById('preview-modal');
        if (!modal) return;
        modal.classList.add('opacity-0');
        modal.querySelector('div').classList.add('scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
            document.getElementById('preview-content').innerHTML = '';
        }, 300);
    }

    function acceptCookies() {
        const now = new Date();
        const expires = new Date(now.getTime() + 365 * 24 * 60 * 60 * 1000);
        document.cookie = "cookie_consent=accepted; expires=" + expires.toUTCString() + "; path=/; SameSite=Lax";
        
        const banner = document.getElementById('cookie-consent-banner');
        if (banner) {
            banner.classList.add('translate-y-full', 'opacity-0');
            setTimeout(() => banner.remove(), 500);
        }
    }

    document.addEventListener('DOMContentLoaded', async () => {
        await fetchMoveTargets();
        if (currentFolderId) {
            loadFolder(currentFolderId, 0, true);
        }

        // Cookie Consent Check
        if (!document.cookie.split('; ').find(row => row.startsWith('cookie_consent='))) {
            setTimeout(() => {
                const banner = document.createElement('div');
                banner.id = 'cookie-consent-banner';
                banner.className = 'fixed bottom-6 left-6 right-6 md:left-auto md:max-w-md z-[100] bg-slate-800/95 backdrop-blur-xl border border-slate-700/60 p-6 rounded-3xl shadow-2xl flex flex-col gap-4 transform translate-y-full opacity-0 transition-all duration-700 ease-out sm:flex-row sm:items-center sm:gap-6';
                banner.innerHTML = `
                    <div class="p-3 bg-blue-500/10 rounded-2xl shrink-0 hidden sm:block">
                        <i data-lucide="cookie" class="w-8 h-8 text-blue-400"></i>
                    </div>
                    <div class="flex-1">
                        <h4 class="text-white font-bold text-lg mb-1 flex items-center gap-2">
                            <i data-lucide="cookie" class="w-5 h-5 text-blue-400 sm:hidden"></i>
                            Pliki Cookies
                        </h4>
                        <p class="text-slate-400 text-sm leading-relaxed">
                            Nasz system wykorzystuje pliki cookies, aby zapewnić bezpieczeństwo i najlepszą jakość korzystania z platformy Sivis Drive.
                        </p>
                    </div>
                    <button onclick="acceptCookies()" class="bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 px-6 rounded-2xl transition-all shadow-lg shadow-blue-600/20 active:scale-95 whitespace-nowrap">
                        Zgadzam się
                    </button>
                `;
                document.body.appendChild(banner);
                if (window.lucide) lucide.createIcons();
                setTimeout(() => banner.classList.remove('translate-y-full', 'opacity-0'), 100);
            }, 1000);
        }

        <?php if (isset($_SESSION['toast'])): ?>
            showToast("<?= htmlspecialchars($_SESSION['toast'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>");
            <?php unset($_SESSION['toast']); ?>
        <?php endif; ?>
    });
</script>
