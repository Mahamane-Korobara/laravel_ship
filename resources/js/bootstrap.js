import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// CSRF token initial
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
if (csrfToken) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
}

window.Pusher = Pusher;

const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;

if (reverbKey) {
    // Determine the scheme (https or http)
    const isHttps = window.location.protocol === 'https:';
    const scheme = import.meta.env.VITE_REVERB_SCHEME ?? (isHttps ? 'https' : 'http');

    // Get host, default to current hostname
    const host = import.meta.env.VITE_REVERB_HOST ?? window.location.hostname;

    // Determine port based on scheme
    const defaultPort = scheme === 'https' ? 443 : 80;
    const port = import.meta.env.VITE_REVERB_PORT ?? defaultPort;

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: host,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
    });
}