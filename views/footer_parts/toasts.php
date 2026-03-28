<script>
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
</script>
