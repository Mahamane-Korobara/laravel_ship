import './bootstrap';

// Fix CSRF token après navigation wire:navigate (SPA mode)
document.addEventListener('livewire:navigated', () => {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (token) {
        window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
    }
});

// Fallback: si wire:navigate ne déclenche pas la navigation, on force la navigation classique.
document.addEventListener('click', (event) => {
    const link = event.target.closest('a[wire\\:navigate]');
    if (!link) return;
    if (event.button !== 0) return;
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    if (link.target && link.target !== '_self') return;

    let handled = false;
    const onNavigate = () => {
        handled = true;
    };

    document.addEventListener('livewire:navigating', onNavigate, { once: true });

    setTimeout(() => {
        if (!handled) {
            window.location.href = link.href;
        }
    }, 200);
});

document.addEventListener('livewire:init', () => {
    Livewire.on('notify', ({ message, type } = {}) => {
        window.dispatchEvent(new CustomEvent('ship-notify', { detail: { message, type } }));
    });
});
