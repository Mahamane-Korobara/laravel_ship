@php
$sessionToasts = [];
if (session('success')) {
$sessionToasts[] = ['type' => 'success', 'message' => session('success')];
}
if (session('error')) {
$sessionToasts[] = ['type' => 'error', 'message' => session('error')];
}
if (session('warning')) {
$sessionToasts[] = ['type' => 'warning', 'message' => session('warning')];
}
if (session('info')) {
$sessionToasts[] = ['type' => 'info', 'message' => session('info')];
}
@endphp

<div
    x-data="shipToasts()"
    x-init="init()"
    class="fixed right-4 top-4 z-[60] flex w-[92vw] max-w-sm flex-col gap-2 sm:right-6 sm:top-6">
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-transition.opacity.duration.200ms
            class="rounded-xl border px-3 py-2 text-sm shadow-lg backdrop-blur"
            :class="toast.type === 'success'
                ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-200'
                : toast.type === 'error'
                    ? 'border-rose-500/30 bg-rose-500/10 text-rose-200'
                    : toast.type === 'warning'
                        ? 'border-amber-500/30 bg-amber-500/10 text-amber-200'
                        : 'border-slate-600/40 bg-slate-900/80 text-slate-200'">
            <div class="flex items-start justify-between gap-3">
                <div class="leading-snug" x-text="toast.message"></div>
                <button class="text-xs text-slate-400 hover:text-white" @click="remove(toast.id)">✕</button>
            </div>
        </div>
    </template>
</div>

@if (!empty($sessionToasts))
{{-- On injecte les données dans une balise script séparée ou via un attribut data --}}
<script id="session-toasts-data" type="application/json">
    @json($sessionToasts)
</script>
<script>
    window.addEventListener('load', () => {
        const dataElement = document.getElementById('session-toasts-data');
        if (dataElement) {
            const toasts = JSON.parse(dataElement.textContent);
            toasts.forEach(toast => {
                window.dispatchEvent(new CustomEvent('ship-notify', {
                    detail: toast
                }));
            });
        }
    });
</script>
@endif

@once
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('shipToasts', () => ({
            toasts: [],
            recentMessages: new Set(),
            init() {
                window.addEventListener('ship-notify', (event) => {
                    this.pushNotification(event.detail || {});
                });
            },
            pushNotification({
                message = '',
                type = 'info',
                timeout = 5500
            }) {
                if (!message) return;

                const msgKey = `${type}:${message}`;
                if (this.recentMessages.has(msgKey)) return;

                this.recentMessages.add(msgKey);
                setTimeout(() => this.recentMessages.delete(msgKey), 500);

                const id = Date.now() + Math.random();
                this.toasts.push({
                    id,
                    message,
                    type
                });

                const finalTimeout = Math.max(timeout, 5500);
                setTimeout(() => this.remove(id), finalTimeout);
            },
            remove(id) {
                this.toasts = this.toasts.filter(t => t.id !== id);
            },
        }));
    });
</script>
@endonce