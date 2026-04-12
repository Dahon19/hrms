import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

window.Pusher = Pusher;

const realtimeConfig = window.hrmsRealtimeConfig || {};
const realtimeEnabled = Boolean(realtimeConfig.enabled);
const reverbKey = realtimeEnabled
    ? (realtimeConfig.key || import.meta.env.VITE_REVERB_APP_KEY)
    : null;

if (realtimeEnabled && reverbKey) {
    const forceTLS = Boolean(
        realtimeConfig.forceTLS ??
        ((import.meta.env.VITE_REVERB_SCHEME || 'http') === 'https')
    );

    try {
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: reverbKey,
            wsHost: realtimeConfig.wsHost || import.meta.env.VITE_REVERB_HOST || window.location.hostname,
            wsPort: Number(realtimeConfig.wsPort || import.meta.env.VITE_REVERB_PORT || 8080),
            wssPort: Number(realtimeConfig.wssPort || import.meta.env.VITE_REVERB_PORT || 443),
            forceTLS,
            enabledTransports: realtimeConfig.enabledTransports || ['ws', 'wss'],
            authEndpoint: realtimeConfig.authEndpoint || '/broadcasting/auth',
        });
    } catch (error) {
        console.error('Echo initialization failed.', error);
    }
}


