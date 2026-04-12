(function () {
    if (window.__HRMS_NOTIFICATIONS_INITIALIZED) return;
    window.__HRMS_NOTIFICATIONS_INITIALIZED = true;

    const userMeta = document.querySelector('meta[name="hrms-user-id"]');
    const userId = Number(userMeta?.getAttribute('content') || 0);
    if (!userId) return;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const baseUrl = document.querySelector('meta[name="hrms-base-url"]')?.getAttribute('content') || window.location.origin;
    const badgeEls = Array.from(document.querySelectorAll('#hrmsNotificationBadge'));
    const listEls = Array.from(document.querySelectorAll('#hrmsNotificationList, #hrmsMobileNotificationList'));
    const openTriggers = Array.from(document.querySelectorAll('#hrmsNotificationBell, [data-mobile-notification-open="1"]'));
    const markAllButtons = Array.from(document.querySelectorAll('.hrms-mark-all-read, .hrms-mark-all-read-mobile'));
    const expandButtons = Array.from(document.querySelectorAll('.hrms-notification-expand'));
    if (!listEls.length || !markAllButtons.length) return;

    const DEFAULT_LIMIT = 10;
    const MAX_LIMIT = 500;

    const ENDPOINTS = {
        index: (limit) => `/notifications?limit=${encodeURIComponent(limit)}`,
        unreadCount: '/notifications/unread-count',
        markRead: (id) => `/notifications/${encodeURIComponent(id)}/read`,
        markAll: '/notifications/read-all',
    };

    let notifications = [];
    let unreadCount = 0;
    let hasLoaded = false;
    let currentLimit = DEFAULT_LIMIT;
    let totalCount = 0;
    let hasMore = false;

    const loadingMarkup = `
        <div class="px-3 py-4 text-center">
            <div class="spinner-border spinner-border-sm text-primary" role="status" aria-label="Loading notifications">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    `;

    const typeColor = (type) => {
        const value = String(type || 'info').toLowerCase();
        if (value === 'success') return 'text-success';
        if (value === 'warning') return 'text-warning';
        if (value === 'error') return 'text-danger';
        return 'text-info';
    };

    const escapeHtml = (value) => String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const toRelativeTime = (isoString) => {
        if (!isoString) return '';
        const then = new Date(isoString).getTime();
        const now = Date.now();
        const diffSec = Math.max(1, Math.floor((now - then) / 1000));

        if (diffSec < 60) return `${diffSec}s ago`;
        if (diffSec < 3600) return `${Math.floor(diffSec / 60)}m ago`;
        if (diffSec < 86400) return `${Math.floor(diffSec / 3600)}h ago`;
        return `${Math.floor(diffSec / 86400)}d ago`;
    };

    const resolveActionUrl = (rawUrl) => {
        const value = String(rawUrl || '').trim();
        if (!value) return '';

        try {
            const base = new URL(baseUrl);
            const appBasePath = String(base.pathname || '/').replace(/\/+$/, '') || '/';
            const isAppAtRoot = appBasePath === '/';

            const normalizePathToApp = (inputPath) => {
                let path = String(inputPath || '');
                if (!path.startsWith('/')) {
                    path = `/${path}`;
                }

                if (!isAppAtRoot && !path.startsWith(`${appBasePath}/`) && path !== appBasePath) {
                    path = `${appBasePath}${path}`;
                }

                return path.replace(/\/{2,}/g, '/');
            };

            if (/^https?:\/\//i.test(value)) {
                const absolute = new URL(value);
                if (absolute.origin === window.location.origin) {
                    return absolute.toString();
                }

                const remappedPath = normalizePathToApp(absolute.pathname);
                return `${window.location.origin}${remappedPath}${absolute.search}${absolute.hash}`;
            }

            if (value.startsWith('/')) {
                const remappedPath = normalizePathToApp(value);
                return `${window.location.origin}${remappedPath}`;
            }

            const remappedPath = normalizePathToApp(value);
            return `${window.location.origin}${remappedPath}`;
        } catch (error) {
            return value;
        }
    };

    const updateBadge = () => {
        badgeEls.forEach((badgeEl) => {
            if (unreadCount > 0) {
                badgeEl.textContent = String(unreadCount > 99 ? '99+' : unreadCount);
                badgeEl.classList.remove('d-none');
                return;
            }

            badgeEl.classList.add('d-none');
            badgeEl.textContent = '0';
        });
    };

    const updateExpandButtons = () => {
        if (!expandButtons.length) return;

        expandButtons.forEach((button) => {
            button.disabled = false;
            button.textContent = 'See all notifications';
            if (hasMore) {
                button.classList.remove('d-none');
                return;
            }
            button.classList.add('d-none');
        });
    };

    const updateList = () => {
        const markup = !notifications.length
            ? '<div class="px-3 py-3 text-muted small text-center">No notifications yet.</div>'
            : notifications.map((item) => {
            const unreadClass = item.read ? '' : 'is-unread';
            const actionUrl = resolveActionUrl(item.redirect_url || item.action_url);
            const clickableClass = actionUrl ? 'is-clickable' : '';
            const actionAttr = actionUrl ? `data-action-url="${escapeHtml(actionUrl)}"` : '';
            return `
                <div class="hrms-notification-item ${unreadClass} ${clickableClass}" data-id="${escapeHtml(item.id)}" ${actionAttr} ${actionUrl ? 'tabindex="0" role="button"' : ''}>
                    <div class="hrms-notification-item-header">
                        <p class="hrms-notification-title ${typeColor(item.type)}">${escapeHtml(item.title)}</p>
                        ${item.read ? '' : '<span class="badge badge-primary badge-pill">New</span>'}
                    </div>
                    <p class="hrms-notification-message">${escapeHtml(item.message)}</p>
                    <div class="hrms-notification-meta">
                        <span>${escapeHtml(item.module || 'general')} | ${escapeHtml(toRelativeTime(item.created_at))}</span>
                        <div class="hrms-notification-actions">
                            ${item.read ? '' : '<button type="button" class="btn btn-link btn-sm p-0 hrms-mark-read">Mark read</button>'}
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        listEls.forEach((listEl) => {
            listEl.innerHTML = markup;
        });

        updateExpandButtons();
    };

    const showLoading = () => {
        listEls.forEach((listEl) => {
            listEl.innerHTML = loadingMarkup;
        });
    };

    const safeJson = async (response) => {
        try {
            return await response.json();
        } catch (error) {
            return null;
        }
    };

    const request = async (url, options = {}) => {
        const response = await fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                ...options.headers,
            },
            ...options,
        });

        const data = await safeJson(response);
        if (!response.ok) {
            throw new Error(data?.message || 'Request failed.');
        }

        return data || {};
    };

    const fetchNotifications = async (limit = currentLimit) => {
        currentLimit = Math.max(1, Math.min(Number(limit) || DEFAULT_LIMIT, MAX_LIMIT));
        showLoading();
        const data = await request(ENDPOINTS.index(currentLimit));
        notifications = Array.isArray(data.notifications) ? data.notifications : [];
        unreadCount = Number(data.unread_count || 0);
        totalCount = Math.max(
            notifications.length,
            Number(data.total_count || 0)
        );
        hasMore = Boolean(data.has_more) && notifications.length < totalCount;
        hasLoaded = true;
        updateBadge();
        updateList();
    };

    const markRead = async (id) => {
        const data = await request(ENDPOINTS.markRead(id), { method: 'POST' });
        notifications = notifications.map((item) => item.id === id ? { ...item, read: true, read_at: new Date().toISOString() } : item);
        unreadCount = Number(data.unread_count || 0);
        updateBadge();
        updateList();
    };

    const markAllRead = async () => {
        const data = await request(ENDPOINTS.markAll, { method: 'POST' });
        notifications = notifications.map((item) => ({ ...item, read: true, read_at: item.read_at || new Date().toISOString() }));
        unreadCount = Number(data.unread_count || 0);
        updateBadge();
        updateList();
        if (typeof window.showToast === 'function') {
            window.showToast('success', 'All notifications marked as read.');
        }
    };

    const pushRealtimeNotification = (notification) => {
        if (!notification || !notification.id) return;

        const wasFullyExpanded = currentLimit > DEFAULT_LIMIT && currentLimit >= totalCount;
        if (wasFullyExpanded) {
            currentLimit = Math.min(MAX_LIMIT, currentLimit + 1);
        }

        const maxItems = currentLimit > DEFAULT_LIMIT ? currentLimit : DEFAULT_LIMIT;
        notifications = [notification, ...notifications.filter((item) => item.id !== notification.id)].slice(0, maxItems);
        totalCount = Math.max(totalCount + 1, notifications.length);
        hasMore = notifications.length < totalCount;
        unreadCount += notification.read ? 0 : 1;
        updateBadge();
        updateList();

        if (typeof window.showToast === 'function') {
            window.showToast(notification.type || 'info', notification.message || notification.title || 'New notification');
        }
    };

    let fallbackPollingStarted = false;

    const startFallbackPolling = () => {
        if (fallbackPollingStarted) {
            return;
        }

        fallbackPollingStarted = true;
        window.setInterval(async () => {
            try {
                const data = await request(ENDPOINTS.unreadCount);
                const nextUnreadCount = Number(data.unread_count || 0);
                if (!hasLoaded || nextUnreadCount !== unreadCount) {
                    await fetchNotifications();
                } else {
                    unreadCount = nextUnreadCount;
                    updateBadge();
                }
            } catch (error) {
                // Intentionally ignore background polling errors.
            }
        }, 15000);
    };

    const subscribeWithPusher = async () => {
        const config = window.hrmsRealtimeConfig || {};
        if (!config.enabled || !config.key) return false;

        try {
            const { default: Pusher } = await import('pusher-js');
            const pusher = new Pusher(config.key, {
                cluster: config.cluster || 'mt1',
                wsHost: config.wsHost,
                wsPort: Number(config.wsPort || 80),
                wssPort: Number(config.wssPort || 443),
                forceTLS: Boolean(config.forceTLS),
                enabledTransports: Array.isArray(config.enabledTransports) ? config.enabledTransports : ['ws', 'wss'],
                authEndpoint: config.authEndpoint || '/broadcasting/auth',
                auth: {
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                },
            });

            let fallbackStarted = false;
            const fallbackToPolling = () => {
                if (fallbackStarted) return;
                fallbackStarted = true;

                try {
                    pusher.disconnect();
                } catch (error) {
                    // Ignore disconnect errors during fallback.
                }

                startFallbackPolling();
            };

            const channel = pusher.subscribe(`private-users.${userId}.notifications`);
            channel.bind('hrms.notification.created', (eventPayload) => {
                const notification = eventPayload?.notification || null;
                pushRealtimeNotification(notification);
            });

            return await new Promise((resolve) => {
                let settled = false;

                const finish = (subscribed) => {
                    if (settled) return;
                    settled = true;
                    resolve(subscribed);
                };

                const fail = () => {
                    fallbackToPolling();
                    finish(false);
                };

                pusher.connection.bind('connected', () => finish(true));
                pusher.connection.bind('unavailable', fail);
                pusher.connection.bind('failed', fail);
                pusher.connection.bind('error', fail);

                window.setTimeout(() => {
                    if (settled) return;
                    if (pusher.connection.state !== 'connected') {
                        fail();
                    }
                }, 4000);
            });
        } catch (error) {
            return false;
        }
    };

    openTriggers.forEach((trigger) => {
        trigger.addEventListener('click', () => {
            if (!hasLoaded) {
                fetchNotifications(currentLimit).catch(() => {
                    updateList();
                });
                return;
            }

            fetchNotifications(currentLimit).catch(() => {
                // Keep stale list if refresh fails.
            });
        });
    });

    const navigateToItemAction = async (item) => {
        if (!item) return;

        const actionUrl = String(item.getAttribute('data-action-url') || '').trim();
        if (!actionUrl) return;

        const id = String(item.getAttribute('data-id') || '').trim();
        const isUnread = item.classList.contains('is-unread');
        if (id && isUnread) {
            try {
                await markRead(id);
            } catch (error) {
                // Ignore mark-read failure and continue navigation.
            }
        }

        window.location.assign(actionUrl);
    };

    listEls.forEach((listEl) => {
        listEl.addEventListener('click', (event) => {
            const btn = event.target instanceof HTMLElement ? event.target.closest('.hrms-mark-read') : null;
            if (btn) {
                event.preventDefault();
                event.stopPropagation();
                const item = btn.closest('.hrms-notification-item');
                const id = item?.getAttribute('data-id');
                if (!id) return;

                markRead(id).catch(() => {
                    if (typeof window.showToast === 'function') {
                        window.showToast('error', 'Unable to mark notification as read.');
                    }
                });
                return;
            }

            const item = event.target instanceof HTMLElement ? event.target.closest('.hrms-notification-item') : null;
            if (!item) return;

            event.preventDefault();
            navigateToItemAction(item);
        });
    });

    listEls.forEach((listEl) => {
        listEl.addEventListener('keydown', (event) => {
            if (!(event.target instanceof HTMLElement)) return;
            const item = event.target.closest('.hrms-notification-item');
            if (!item) return;

            if (event.key !== 'Enter' && event.key !== ' ') return;
            event.preventDefault();
            navigateToItemAction(item);
        });
    });

    markAllButtons.forEach((markAllBtn) => {
        markAllBtn.addEventListener('click', (event) => {
            event.preventDefault();
            markAllRead().catch(() => {
                if (typeof window.showToast === 'function') {
                    window.showToast('error', 'Unable to mark all notifications as read.');
                }
            });
        });
    });

    expandButtons.forEach((expandButton) => {
        expandButton.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            if (!hasMore) return;

            expandButton.disabled = true;
            expandButton.textContent = 'Loading...';

            const targetLimit = Math.max(totalCount, currentLimit + DEFAULT_LIMIT);
            fetchNotifications(targetLimit).catch(() => {
                if (typeof window.showToast === 'function') {
                    window.showToast('error', 'Unable to load all notifications.');
                }
            }).finally(() => {
                expandButton.disabled = false;
                expandButton.textContent = 'See all notifications';
            });
        });
    });

    fetchNotifications(currentLimit).catch(() => {
        updateList();
    });

    subscribeWithPusher().then((subscribed) => {
        if (!subscribed) {
            startFallbackPolling();
        }
    });
})();


