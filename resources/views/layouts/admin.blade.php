<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Northeastern College | HRMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="hrms-user-id" content="{{ Auth::id() }}" />
    <meta name="hrms-base-url" content="{{ rtrim(url('/'), '/') }}" />
    @php
        $hrmsRealtimeDriver = config('broadcasting.default');
        $hrmsRealtimeKey = env('PUSHER_APP_KEY') ?: env('REVERB_APP_KEY');
        $hrmsRealtimeEnabled = (bool) env('HRMS_REALTIME_ENABLED', false)
            && in_array($hrmsRealtimeDriver, ['reverb', 'pusher'], true)
            && filled($hrmsRealtimeKey);
        $hrmsRealtimeConfig = [
            'enabled' => $hrmsRealtimeEnabled,
            'driver' => $hrmsRealtimeDriver,
            'key' => $hrmsRealtimeEnabled ? $hrmsRealtimeKey : null,
            'cluster' => env('PUSHER_APP_CLUSTER', 'mt1'),
            'wsHost' => env('PUSHER_HOST', env('REVERB_HOST', request()->getHost())),
            'wsPort' => (int) env('PUSHER_PORT', env('REVERB_PORT', 443)),
            'wssPort' => (int) env('PUSHER_PORT', env('REVERB_PORT', 443)),
            'forceTLS' => (env('PUSHER_SCHEME', env('REVERB_SCHEME', 'https')) === 'https'),
            'enabledTransports' => ['ws', 'wss'],
            'authEndpoint' => url('/broadcasting/auth'),
        ];
    @endphp
    <script>
        window.hrmsRealtimeConfig = @json ($hrmsRealtimeConfig);
    </script>
    <script>
        (function () {
            try {
                var userMeta = document.querySelector('meta[name="hrms-user-id"]');
                var userId = userMeta ? userMeta.getAttribute('content') : '';
                var themeKey = userId ? "hrms-theme-" + userId : "hrms-theme";
                var legacyThemeKey = "hrms-theme";
                var compactKey = "hrms-compact";
                var densityKey = userId ? "hrms-density-" + userId : "hrms-density";
                var reducedMotionKey = userId ? "hrms-reduced-motion-" + userId : "hrms-reduced-motion";
                var storedTheme = localStorage.getItem(themeKey);
                var legacyTheme = localStorage.getItem(legacyThemeKey);
                var storedCompact = localStorage.getItem(compactKey);
                var storedDensity = localStorage.getItem(densityKey);
                var storedReducedMotion = localStorage.getItem(reducedMotionKey);
                var isCompact =
                    storedCompact === null ? true : storedCompact === "on";
                var prefersDark =
                    typeof window.matchMedia === "function" &&
                    window.matchMedia("(prefers-color-scheme: dark)").matches;
                var themeMode =
                    storedTheme === "dark" || storedTheme === "light" || storedTheme === "system"
                        ? storedTheme
                        : legacyTheme === "dark" || legacyTheme === "light" || legacyTheme === "system"
                            ? legacyTheme
                        : "system";
                if ((themeMode === "dark" || themeMode === "light" || themeMode === "system") && storedTheme === null && themeKey !== legacyThemeKey) {
                    localStorage.setItem(themeKey, themeMode);
                }
                var isDark = themeMode === "dark" || (themeMode === "system" && prefersDark);
                document.documentElement.classList.toggle(
                    "nav-compact",
                    isCompact,
                );
                document.documentElement.classList.toggle("dark-mode", isDark);
                document.documentElement.classList.toggle(
                    "hrms-density-compact",
                    storedDensity === "compact",
                );
                document.documentElement.classList.toggle(
                    "hrms-reduce-motion",
                    storedReducedMotion === "on",
                );
            } catch (error) {
                document.documentElement.classList.add("nav-compact");
            }
        })();
    </script>
    <link
        rel="icon"
        type="image/webp"
        href="{{ asset('assets/img/Northeastern College.webp') }}"
    />
    <link
        rel="shortcut icon"
        type="image/webp"
        href="{{ asset('assets/img/Northeastern College.webp') }}"
    />
    <link rel="stylesheet" href="{{ asset('assets/css/coreui.min.css') }}" />
    <link
        rel="stylesheet"
        href="{{ asset('assets/plugins/fontawesome-free/css/all.min.css') }}"
    />
    <style>
        /* Critical admin shell fallback in case built Vite CSS is unavailable in production. */
        body.app-shell {
            min-height: 100vh;
            overflow-x: hidden;
            background: #f3f4f7;
        }
        .wrapper.app-shell {
            display: block;
            min-height: 100vh;
        }
        .app-main {
            min-width: 0;
            min-height: 100vh;
            margin-left: 16rem;
        }
        .app-sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            display: flex;
            flex-direction: column;
            width: 16rem;
            height: 100vh;
            background: linear-gradient(180deg, #fbfdff 0%, #eef4ff 100%);
            border-right: 1px solid rgba(148, 163, 184, 0.24);
            box-shadow: 18px 0 34px rgba(148, 163, 184, 0.18), 4px 0 10px rgba(96, 165, 250, 0.1);
            z-index: 1035;
            color: #334155;
        }
        .app-sidebar .sidebar-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(196, 181, 253, 0.24);
        }
        .app-sidebar .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            width: 100%;
            padding: 0.4rem 1rem;
            color: #1e293b;
            text-decoration: none;
        }
        .sidebar-brand-mark {
            width: 2.15rem;
            height: 2.15rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.9rem;
            overflow: hidden;
            background: rgba(59, 130, 246, 0.1);
            flex: 0 0 auto;
        }
        .sidebar-brand-logo {
            display: block;
            width: 1.7rem;
            height: 1.7rem;
            max-width: 1.7rem;
            max-height: 1.7rem;
            object-fit: contain;
        }
        .sidebar-brand-copy {
            display: flex;
            flex-direction: column;
            line-height: 1.1;
        }
        .sidebar-brand-line {
            font-size: 1rem;
            font-weight: 700;
        }
        .sidebar-body {
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
            padding: 0.35rem 0 0.5rem;
        }
        .app-shell .nav-header {
            padding: 0.45rem 0.9rem 0.15rem;
            font-size: 0.62rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            color: #7b8ba5;
            text-transform: uppercase;
        }
        .app-shell .sidebar-nav .nav-link {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin: 0.12rem 0.75rem;
            padding: 0.42rem 0.6rem;
            border-radius: 0.7rem;
            color: #334155;
            text-decoration: none;
        }
        .app-shell .nav-group-items {
            display: none;
        }
        .app-shell .nav-group.show > .nav-group-items {
            display: block;
        }
        .app-header {
            position: sticky;
            top: 0;
            z-index: 1025;
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(12px);
        }
        .app-body {
            min-width: 0;
            padding-top: 1.5rem;
            padding-bottom: 2rem;
        }
        .app-content-container {
            width: 100%;
            max-width: none;
        }
        @media (max-width: 991.98px) {
            .wrapper.app-shell {
                display: block;
            }
            .app-main {
                margin-left: 0;
                width: 100%;
                max-width: 100%;
            }
            body.sidebar-collapse .app-main {
                margin-left: 0 !important;
                width: 100%;
                max-width: 100%;
            }
            .app-sidebar {
                position: fixed;
                inset: 0 auto 0 0;
                width: 16rem;
                transform: translateX(-100%);
                transition: transform 0.25s ease;
            }
            body.sidebar-open .app-sidebar {
                transform: translateX(0);
            }
        }
    </style>
    @php $viteCss = [ 'resources/css/coreui-shell.css', 'resources/css/ui-components.css', 'resources/css/ui-tables.css', 'resources/css/ui-hero.css', 'resources/css/mobile-responsive.css', 'resources/css/module-overrides.css', 'resources/css/toasts.css', ]; if (request()->routeIs('dashboard')) { $viteCss[] = 'resources/css/dashboard.css'; } if (request()->routeIs('attendance.*')) { $viteCss[] = 'resources/css/attendance-kiosk.css'; } if (request()->routeIs('attendance.calendar')) { $viteCss[] = 'resources/css/attendance-calendar.css'; } if (request()->routeIs('leaves.*') || request()->routeIs('leave-types.*')) { $viteCss[] = 'resources/css/leaves-index.css'; } if (request()->routeIs('leave-balances.*')) { $viteCss[] = 'resources/css/leave-balances.css'; } if (request()->routeIs('documents.*') || request()->routeIs('employee-documents.*')) { $viteCss[] = 'resources/css/documents-index.css'; } if (request()->routeIs('employees.*')) { $viteCss[] = 'resources/css/employees-index.css'; } if (request()->routeIs('departments.*')) { $viteCss[] = 'resources/css/departments-index.css'; } if (request()->routeIs('positions.*')) { $viteCss[] = 'resources/css/positions-index.css'; } if (request()->routeIs('job-postings.*')) { $viteCss[] = 'resources/css/job-postings-index.css'; } if (request()->routeIs('reports.*')) { $viteCss[] = 'resources/css/reports-index.css'; } if (request()->routeIs('idp.*')) { $viteCss[] = 'resources/css/idp-index.css'; } if (request()->routeIs('audit-logs.*')) { $viteCss[] = 'resources/css/audit-logs.css'; } if (request()->routeIs('offboarding.*')) { $viteCss[] = 'resources/css/offboarding.css'; } if (request()->routeIs('travel-orders.*')) { $viteCss[] = 'resources/css/travel-orders.css'; } if (request()->routeIs('spms.*')) { $viteCss[] = 'resources/css/spms-index.css'; } if (request()->routeIs('pds.*')) { $viteCss[] = 'resources/css/pds.css'; } if (request()->routeIs('eligibility.*') || request()->routeIs('rewards.eligibility.*')) { $viteCss[] = 'resources/css/eligibility-index.css'; } $viteCss[] = 'resources/css/dark-mode-consistency.css'; @endphp
    @vite ($viteCss)
    @stack ('styles')
</head>
<body
    class="app-shell"
    data-page="{{ request()->route()?->getName() }}"
    data-show-password-change-notice="{{ session('show_password_change_notice') ? '1' : '0' }}"
>
    <script>
        if (document.documentElement.classList.contains("nav-compact")) {
            document.body.classList.add("nav-compact");
        }
    </script>
    <div class="wrapper app-shell">
        @php $authUser = Auth::user(); $avatarLetter = strtoupper(substr($authUser->employee->first_name ?? $authUser->name ?? 'U', 0, 1)); $topbarRole = $authUser->isAdmin() ? 'Administrator' : (optional(optional($authUser->employee)->position)->name ?: 'User'); $avatarSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200">' . '<rect width="100%" height="100%" fill="#e9ecef"/>' . '<text x="50%" y="50%" dy=".35em" text-anchor="middle" font-family="Arial, sans-serif" font-size="96" fill="#007bff">' . e($avatarLetter) . '</text></svg>'; $avatarFallback = 'data:image/svg+xml;base64,' . base64_encode($avatarSvg); $avatarUrl = null; if (!empty($authUser->avatar)) { $parts = explode('/', $authUser->avatar); $folder = $parts[0] ?? null; $subfolder = $parts[1] ?? null; $filename = $parts[2] ?? null; if ($folder && $subfolder && $filename) { $avatarUrl = route('storage.file', [ 'folder' => $folder, 'subfolder' => $subfolder, 'filename' => $filename, ]); } } @endphp
        <x-sidebar :user="$authUser" />
        <div class="app-main d-flex flex-column min-vh-100">
            @include ('components.header', [ 'authUser' => $authUser, 'avatarUrl' => $avatarUrl, 'avatarFallback' => $avatarFallback, 'topbarRole' => $topbarRole, ])
            <div class="body app-body flex-grow-1 px-3">
                <div class="container-fluid app-content-container">
                    @yield ('content')
                </div>
            </div>
            <x-footer />
        </div>
    </div>
    <x-mobile-bottom-nav :user="$authUser" />
    <form
        id="logout-form"
        action="{{ route('logout') }}"
        method="POST"
        class="d-none"
    >
        @csrf
    </form>
    <x-ui.modal
        id="transactionConfirmModal"
        size="sm"
        class="hrms-confirm-modal"
    >
                <x-ui.modal-header title="Confirm Action">
                    <span id="transactionConfirmTitle" class="d-none">Confirm Action</span>
                </x-ui.modal-header>
                <div class="modal-body">
                    <p class="mb-0" id="transactionConfirmMessage">Proceed with this action?</p>
                </div>
                <x-ui.modal-footer>
                    <x-ui.button
                        type="button"
                        variant="cancel"
                        icon="cil-x"
                        data-coreui-dismiss="modal"
                        id="transactionConfirmCancel"
                    >
                        Cancel
                    </x-ui.button>
                    <x-ui.button
                        type="button"
                        variant="primary"
                        icon="cil-check"
                        id="transactionConfirmProceed"
                    >
                        Proceed
                    </x-ui.button>
                </x-ui.modal-footer>
    </x-ui.modal>
    <x-ui.modal id="logoutConfirmModal">
                <x-ui.modal-header
                    title="Confirm Logout"
                />
                <div class="modal-body">Confirm logout?</div>
                <x-ui.modal-footer>
                    <x-ui.button
                        type="button"
                        variant="cancel"
                        icon="cil-x"
                        data-coreui-dismiss="modal"
                    >
                        Cancel
                    </x-ui.button>
                    <x-ui.button
                        type="button"
                        variant="danger"
                        icon="cil-account-logout"
                        onclick="document.getElementById('logout-form').submit()"
                    >
                        Log Out
                    </x-ui.button>
                </x-ui.modal-footer>
    </x-ui.modal>
    <x-ui.modal
        id="settingsModal"
        size="lg"
        dialogClass="settings-modal-dialog"
        contentClass="settings-modal-surface"
    >
                <x-ui.modal-header
                    title="Preferences"
                    subtitle="Personalize the appearance and workspace density. Changes are applied immediately."
                    class="border-0 pb-0"
                />
                <div class="modal-body settings-modal-body">
                    <div class="settings-overview">
                        <div class="settings-overview__main">
                            <div class="settings-overview__icon">
                                <i class="cil-settings"></i>
                            </div>
                            <div class="settings-overview__copy">
                                <span class="settings-overview__eyebrow">Workspace setup</span>
                                <h6 class="settings-overview__title">Display preferences</h6>
                                <p class="settings-overview__text mb-0">
                                    Adjust theme, navigation density, and screen spacing for your account.
                                </p>
                            </div>
                        </div>
                        <span class="badge badge-light border settings-overview__badge">Auto-saved</span>
                    </div>

                    <div class="settings-section settings-section--stacked mb-0">
                        <div class="settings-section__header">
                            <h6 class="settings-section-title mb-1">Appearance</h6>
                            <p class="settings-section-text mb-0">
                                Keep the interface readable and consistent across desktop and mobile layouts.
                            </p>
                        </div>

                        <div class="settings-card settings-card--feature p-3 bg-light">
                            <div class="settings-card__content">
                                <div class="settings-card__icon bg-warning-soft rounded p-3">
                                    <i class="cil-moon fs-5"></i>
                                </div>
                                <div class="settings-card__meta">
                                    <h6 class="mb-1 fw-bold">Theme</h6>
                                    <p class="mb-0 text-muted">
                                        Choose whether the interface follows the system theme or stays fixed.
                                    </p>
                                </div>
                            </div>
                            <div class="settings-card__control">
                                <label class="visually-hidden" for="themeModeSelect">Theme</label>
                                <select
                                    class="form-select form-select-sm settings-card__select"
                                    id="themeModeSelect"
                                >
                                    <option value="system">System</option>
                                    <option value="light">Light</option>
                                    <option value="dark">Dark</option>
                                </select>
                            </div>
                        </div>

                        <div class="settings-card settings-card--feature p-3 bg-light">
                            <div class="settings-card__content">
                                <div class="settings-card__icon bg-info-soft rounded p-3">
                                    <i class="cil-fullscreen-exit fs-5"></i>
                                </div>
                                <div class="settings-card__meta">
                                    <h6 class="mb-1 fw-bold">Compact navigation</h6>
                                    <p class="mb-0 text-muted">
                                        Reduce sidebar spacing for a tighter layout and quicker scanning.
                                    </p>
                                </div>
                            </div>
                            <div class="settings-card__control settings-card__control--switch">
                                <div class="form-check form-switch m-0 settings-switch">
                                    <input
                                        type="checkbox"
                                        class="form-check-input"
                                        id="sidebarCompactSwitch"
                                        aria-label="Toggle compact navigation"
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="settings-card settings-card--feature p-3 bg-light">
                            <div class="settings-card__content">
                                <div class="settings-card__icon bg-primary-soft rounded p-3">
                                    <i class="cil-settings fs-5"></i>
                                </div>
                                <div class="settings-card__meta">
                                    <h6 class="mb-1 fw-bold">Interface density</h6>
                                    <p class="mb-0 text-muted">
                                        Control how compact tables, cards, toolbars, and page headers feel.
                                    </p>
                                </div>
                            </div>
                            <div class="settings-card__control">
                                <label class="visually-hidden" for="interfaceDensitySelect">Interface Density</label>
                                <select
                                    class="form-select form-select-sm settings-card__select"
                                    id="interfaceDensitySelect"
                                >
                                    <option value="comfortable">Comfortable</option>
                                    <option value="compact">Compact</option>
                                </select>
                            </div>
                        </div>
                        {{-- <div class="settings-card p-3 bg-light">
                            <div class="d-flex align-items-center">
                                <div class="settings-card__icon bg-success-soft rounded p-3 me-3">
                                    <i class="cil-media-pause fs-5"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0 fw-bold">Reduced Motion</h6>
                                    <small class="text-muted"
                                        >Minimize animations and transitions across the shell</small
                                    >
                                </div>
                                <div class="form-check form-switch m-0">
                                    <input
                                        type="checkbox"
                                        class="form-check-input"
                                        id="reduceMotionSwitch"
                                    />
                                    <label
                                        class="form-check-label"
                                        for="reduceMotionSwitch"
                                    ></label>
                                </div>
                            </div> --}}
                        </div>
                    </div>
                </div>
    </x-ui.modal>
    <script src="{{ asset('assets/plugins/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/js/coreui.bundle.min.js') }}"></script>
    @php $viteJs = [ 'resources/js/app.js', 'resources/js/flash-toasts.js', 'resources/js/ui-interactions.js', 'resources/js/datatables-init.js', 'resources/js/notifications.js', 'resources/js/profile.js', ]; if (request()->routeIs('dashboard')) { $viteJs[] = 'resources/js/dashboard-ui.js'; $viteJs[] = 'resources/js/dashboard-report-hub.js'; } if (request()->routeIs('attendance.*')) { $viteJs[] = 'resources/js/attendance.js'; } if (request()->routeIs('attendance.calendar')) { $viteJs[] = 'resources/js/attendance-calendar.js'; } if (request()->routeIs('audit-logs.*')) { $viteJs[] = 'resources/js/audit-logs.js'; } if (request()->routeIs('documents.*') || request()->routeIs('employee-documents.*')) { $viteJs[] = 'resources/js/documents.js'; } if (request()->routeIs('departments.*')) { $viteJs[] = 'resources/js/departments.js'; } if (request()->routeIs('employees.*')) { $viteJs[] = 'resources/js/employees.js'; } if (request()->routeIs('positions.*')) { $viteJs[] = 'resources/js/positions.js'; } if (request()->routeIs('leave-types.*')) { $viteJs[] = 'resources/js/leave-types.js'; } if (request()->routeIs('leaves.*') || request()->routeIs('leave-balances.*')) { $viteJs[] = 'resources/js/leaves.js'; } if (request()->routeIs('reports.*')) { $viteJs[] = 'resources/js/reports-index.js'; } if (request()->routeIs('job-postings.index')) { $viteJs[] = 'resources/js/job-postings-index.js'; } if (request()->routeIs('job-postings.applicants')) { $viteJs[] = 'resources/js/job-postings-applicants.js'; } if (request()->routeIs('rewards.*') || request()->routeIs('eligibility.*') || request()->routeIs('rewards.eligibility.*')) { $viteJs[] = 'resources/js/rewards.js'; } if (request()->routeIs('eligibility.*') || request()->routeIs('rewards.eligibility.*')) { $viteJs[] = 'resources/js/eligibility.js'; } if (request()->routeIs('spms.*')) { $viteJs[] = 'resources/js/spms.js'; } if (request()->routeIs('idp.*')) { $viteJs[] = 'resources/js/idp.js'; } if (request()->routeIs('pds.*')) { $viteJs[] = 'resources/js/pds.js'; } if (request()->routeIs('offboarding.*')) { $viteJs[] = 'resources/js/offboarding.js'; } @endphp
    @vite ($viteJs)
    <x-toast />
    @stack ('scripts')
    @include ('profile.partials.edit-modal', ['profileUser' => $authUser])
    <x-ui.modal id="firstLoginPasswordNoticeModal">
                <x-ui.modal-header
                    title="Password Reminder"
                />
                <div class="modal-body">
                    <p class="mb-2">This is the first login for this account.</p>
                    <p class="mb-0 text-muted">For security, update the password from Profile > Edit Profile.</p>
                </div>
                <x-ui.modal-footer>
                    <x-ui.button
                        type="button"
                        variant="cancel"
                        icon="cil-x"
                        data-coreui-dismiss="modal"
                    >
                        Later
                    </x-ui.button>
                    <x-ui.button
                        type="button"
                        variant="primary"
                        icon="cil-pencil"
                        id="openProfileEditFromNotice"
                    >
                        Change Password
                    </x-ui.button>
                </x-ui.modal-footer>
    </x-ui.modal>
    <x-ui.modal id="filePreviewModal" size="xl">
                <x-ui.modal-header title="File Preview" />
                <div class="modal-body p-0">
                    <iframe
                        title="File Preview"
                        src="about:blank"
                        loading="lazy"
                        style="width: 100%; height: 75vh; border: 0"
                    ></iframe>
                </div>
    </x-ui.modal>
    <x-ui.modal id="mobileNotificationsModal">
                <x-ui.modal-header
                    title="Notifications"
                />
                <div class="modal-body p-0">
                    <div class="px-3 py-2 border-bottom d-flex align-items-center justify-content-between">
                        <span class="text-muted small fw-bold">Recent updates</span>
                        <x-ui.button
                            type="button"
                            variant="view"
                            icon="cil-check"
                            class="hrms-mark-all-read-mobile"
                        >
                            Mark all as read
                        </x-ui.button>
                    </div>
                    <div
                        id="hrmsMobileNotificationList"
                        class="hrms-notification-list"
                    >
                        <div class="px-3 py-3 text-muted small text-center">
                            No notifications yet.
                        </div>
                    </div>
                    <div class="hrms-notification-footer">
                        <button
                            type="button"
                            class="btn btn-light btn-sm hrms-notification-expand d-none"
                        >
                            See all notifications
                        </button>
                    </div>
                </div>
    </x-ui.modal>
</body>
</html>
