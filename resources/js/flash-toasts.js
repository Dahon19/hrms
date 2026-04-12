(function () {
    if (window.__HRMS_TOASTS_INITIALIZED) return;
    window.__HRMS_TOASTS_INITIALIZED = true;

    const container = document.getElementById('toast-container');
    const source = document.getElementById('flash-messages-data');
    if (!container) return;

    const MAX_VISIBLE = 3;
    const DEFAULT_DURATION = 5000;
    const queue = [];
    const visible = [];
    const dedupe = new Map();

    const ICONS = {
        success: 'cil-check-circle',
        error: 'cil-warning',
        warning: 'cil-warning',
        info: 'cil-info',
    };

    const TITLES = {
        success: 'Success',
        error: 'Error',
        warning: 'Warning',
        info: 'Info',
    };

    const normalizeType = (type) => {
        const value = String(type || 'info').toLowerCase();
        if (value === 'danger') return 'error';
        return ['success', 'error', 'warning', 'info'].includes(value) ? value : 'info';
    };

    const sanitizeMessage = (message) => String(message || '').trim();
    const escapeHtml = (value) => {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    const scheduleDedupeCleanup = () => {
        const now = Date.now();
        dedupe.forEach((ts, key) => {
            if (now - ts > 20000) dedupe.delete(key);
        });
    };

    const removeToast = (toast) => {
        if (!toast) return;
        toast.classList.remove('is-visible');
        toast.classList.add('is-leaving');
        window.setTimeout(() => {
            toast.remove();
            const index = visible.indexOf(toast);
            if (index >= 0) visible.splice(index, 1);
            flushQueue();
        }, 220);
    };

    const startTimer = (toast, progressEl, duration) => {
        const startedAt = Date.now();
        let remaining = duration;
        let timer = null;

        const run = () => {
            progressEl.style.transitionDuration = `${remaining}ms`;
            progressEl.style.transform = 'scaleX(0)';
            timer = window.setTimeout(() => removeToast(toast), remaining);
        };

        const pause = () => {
            if (!timer) return;
            window.clearTimeout(timer);
            timer = null;
            const elapsed = Date.now() - startedAt;
            remaining = Math.max(duration - elapsed, 0);
            const ratio = duration > 0 ? remaining / duration : 0;
            progressEl.style.transitionDuration = '0ms';
            progressEl.style.transform = `scaleX(${ratio})`;
        };

        const resume = () => {
            if (timer || remaining <= 0) return;
            requestAnimationFrame(run);
        };

        toast.addEventListener('mouseenter', pause);
        toast.addEventListener('mouseleave', resume);
        run();
    };

    const createToastElement = (payload) => {
        const type = normalizeType(payload.type);
        const message = sanitizeMessage(payload.message);
        const details = Array.isArray(payload.details) ? payload.details.filter(Boolean) : [];

        const toast = document.createElement('section');
        toast.className = `hrms-toast hrms-toast--${type}`;
        toast.setAttribute('role', 'status');
        toast.setAttribute('aria-live', type === 'error' ? 'assertive' : 'polite');
        toast.setAttribute('tabindex', '0');

        const safeTitle = TITLES[type];
        const iconClass = ICONS[type];

        const detailsHtml = details.length
            ? `
                <details class="hrms-toast-details">
                    <summary>Show details (${details.length})</summary>
                    <ul>${details.map((item) => `<li>${escapeHtml(item)}</li>`).join('')}</ul>
                </details>
            `
            : '';

        toast.innerHTML = `
            <div class="hrms-toast-header">
                <div class="hrms-toast-title">
                    <i class="hrms-toast-icon ${iconClass}" aria-hidden="true"></i>
                    <span>${safeTitle}</span>
                </div>
                <button type="button" class="hrms-toast-close" aria-label="Dismiss notification">
                    <i class="cil-x" aria-hidden="true"></i>
                </button>
            </div>
            <div class="hrms-toast-body">
                <p class="hrms-toast-message">${escapeHtml(message)}</p>
                ${detailsHtml}
            </div>
            <div class="hrms-toast-progress"></div>
        `;

        const closeBtn = toast.querySelector('.hrms-toast-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => removeToast(toast));
        }

        toast.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                removeToast(toast);
            }
        });

        container.appendChild(toast);
        requestAnimationFrame(() => toast.classList.add('is-visible'));

        const progressEl = toast.querySelector('.hrms-toast-progress');
        if (progressEl) {
            startTimer(toast, progressEl, payload.duration || DEFAULT_DURATION);
        }

        return toast;
    };

    const flushQueue = () => {
        while (queue.length && visible.length < MAX_VISIBLE) {
            const next = queue.shift();
            const toastEl = createToastElement(next);
            visible.push(toastEl);
        }
    };

    const pushToast = (payload) => {
        const type = normalizeType(payload.type);
        const message = sanitizeMessage(payload.message);
        if (!message) return;

        scheduleDedupeCleanup();
        const dedupeKey = `${type}|${message}`;
        if (dedupe.has(dedupeKey)) return;
        dedupe.set(dedupeKey, Date.now());

        queue.push({
            type,
            message,
            details: payload.details || [],
            duration: payload.duration || DEFAULT_DURATION,
        });
        flushQueue();
    };

    const parseFlashPayload = () => {
        if (!source) return [];
        const encoded = source.getAttribute('data-messages-b64') || '';
        if (!encoded) return [];

        try {
            const parsed = JSON.parse(window.atob(encoded));
            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            return [];
        }
    };

    const toastFromAxiosResponse = (response) => {
        const data = response && response.data ? response.data : null;
        if (!data || typeof data !== 'object') return;

        const status = normalizeType(data.status || '');
        const message = sanitizeMessage(data.message || '');
        if (!message) return;
        pushToast({ type: status, message });
    };

    const toastFromErrorResponse = (statusCode, data) => {
        if (statusCode === 422 && data?.errors) {
            return;
        }

        if (data?.message) {
            pushToast({
                type: statusCode >= 500 ? 'error' : 'warning',
                message: String(data.message),
            });
            return;
        }

        if (statusCode >= 500) {
            pushToast({
                type: 'error',
                message: 'A server error occurred. Please try again.',
            });
        }
    };

    const hookAxios = () => {
        if (!window.axios || !window.axios.interceptors) return;
        window.axios.interceptors.response.use(
            (response) => {
                toastFromAxiosResponse(response);
                return response;
            },
            (error) => {
                const response = error && error.response ? error.response : null;
                if (response) {
                    toastFromErrorResponse(response.status, response.data || {});
                } else {
                    pushToast({ type: 'error', message: 'Network error. Please check your connection.' });
                }
                return Promise.reject(error);
            }
        );
    };

    const hookFetch = () => {
        if (typeof window.fetch !== 'function') return;
        const nativeFetch = window.fetch.bind(window);

        window.fetch = async (...args) => {
            const response = await nativeFetch(...args);
            const contentType = String(response.headers.get('content-type') || '');
            if (!contentType.includes('application/json')) return response;

            try {
                const cloned = response.clone();
                const data = await cloned.json();
                if (response.ok) {
                    if (data && typeof data === 'object' && data.status && data.message) {
                        pushToast({
                            type: normalizeType(data.status),
                            message: String(data.message),
                        });
                    }
                } else {
                    toastFromErrorResponse(response.status, data || {});
                }
            } catch (error) {
                // Ignore invalid JSON parsing from cloned response.
            }

            return response;
        };
    };

    window.showToast = function (type, message, options = {}) {
        pushToast({
            type: normalizeType(type),
            message,
            details: options.details || [],
            duration: options.duration || DEFAULT_DURATION,
        });
    };

    parseFlashPayload().forEach((item) => {
        pushToast({
            type: item.type,
            message: item.message,
            details: item.details || [],
        });
    });

    hookAxios();
    hookFetch();
})();


