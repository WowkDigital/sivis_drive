/**
 * Sivis Drive - Custom Modals System
 * Replaces native browser alert/confirm/prompt
 */

let actionModalCallback = null;

function showActionModal(title, message, icon = 'help-circle', color = 'blue', showInput = false, defaultValue = '', callback = null) {
    console.log('Showing action modal:', title);
    const modal = document.getElementById('action-modal');
    if (!modal) {
        console.error('Action modal element not found!');
        return;
    }

    const titleEl = document.getElementById('action-modal-title');
    const messageEl = document.getElementById('action-modal-message');
    const iconEl = document.getElementById('action-modal-icon');
    const iconCont = document.getElementById('action-modal-icon-container');
    const inputCont = document.getElementById('action-modal-input-container');
    const inputEl = document.getElementById('action-modal-input');
    const confirmBtn = document.getElementById('action-modal-confirm');

    if (titleEl) titleEl.innerText = title;
    if (messageEl) messageEl.innerText = message;
    actionModalCallback = callback;

    // Reset classes
    if (iconCont && confirmBtn) {
        iconCont.className = 'w-20 h-20 mx-auto rounded-full flex items-center justify-center mb-6 transition-colors shadow-inner';
        confirmBtn.className = 'sm:flex-1 w-full px-6 py-3.5 text-white font-bold rounded-2xl shadow-lg active:scale-95 transition-all order-1 sm:order-2 outline-none';

        if (color === 'red') {
            iconCont.classList.add('bg-red-500/10');
            if (iconEl) {
                iconEl.setAttribute('data-lucide', icon || 'trash-2');
                iconEl.className = 'w-10 h-10 text-red-400';
            }
            confirmBtn.classList.add('bg-red-600', 'hover:bg-red-500', 'shadow-red-600/20');
        } else if (color === 'orange') {
            iconCont.classList.add('bg-orange-500/10');
            if (iconEl) {
                iconEl.setAttribute('data-lucide', icon || 'alert-triangle');
                iconEl.className = 'w-10 h-10 text-orange-400';
            }
            confirmBtn.classList.add('bg-orange-600', 'hover:bg-orange-500', 'shadow-orange-600/20');
        } else {
            iconCont.classList.add('bg-blue-500/10');
            if (iconEl) {
                iconEl.setAttribute('data-lucide', icon || 'info');
                iconEl.className = 'w-10 h-10 text-blue-400';
            }
            confirmBtn.classList.add('bg-blue-600', 'hover:bg-blue-500', 'shadow-blue-600/20');
        }
    }

    if (showInput && inputCont && inputEl) {
        inputCont.classList.remove('hidden');
        inputEl.value = defaultValue;
        setTimeout(() => {
            inputEl.focus();
            inputEl.select();
        }, 50);
    } else if (inputCont) {
        inputCont.classList.add('hidden');
    }

    modal.classList.remove('hidden');
    // Force reflow
    void modal.offsetWidth;
    
    modal.classList.remove('opacity-0');
    const inner = modal.querySelector('div');
    if (inner) inner.classList.remove('scale-95');
    
    if (window.lucide) lucide.createIcons();
}

function closeActionModal() {
    const modal = document.getElementById('action-modal');
    if (!modal) return;
    modal.classList.add('opacity-0');
    modal.querySelector('div').classList.add('scale-95');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

function showConfirmModal(title, message, callback, color = 'blue', icon = null) {
    showActionModal(title, message, icon, color, false, '', callback);
}

function showPromptModal(title, defaultValue, callback) {
    showActionModal(title, '', 'edit-3', 'blue', true, defaultValue, callback);
}

// Global initialization
document.addEventListener('DOMContentLoaded', () => {
    const confirmBtn = document.getElementById('action-modal-confirm');
    const cancelBtn = document.getElementById('action-modal-cancel');
    const inputEl = document.getElementById('action-modal-input');

    if (confirmBtn) {
        confirmBtn.addEventListener('click', () => {
            const val = inputEl ? inputEl.value : null;
            closeActionModal();
            if (actionModalCallback) {
                actionModalCallback(val);
            }
        });
    }

    if (cancelBtn) cancelBtn.addEventListener('click', closeActionModal);
    
    // Close on Esc
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeActionModal();
    });
});
