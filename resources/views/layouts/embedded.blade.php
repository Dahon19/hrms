<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Northeastern College | HRMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="hrms-user-id" content="{{ Auth::id() }}" />
    <meta name="hrms-base-url" content="{{ rtrim(url('/'), '/') }}" />
    <script>
        (function () {
            try {
                var userMeta = document.querySelector('meta[name="hrms-user-id"]');
                var userId = userMeta ? userMeta.getAttribute('content') : '';
                var themeKey = userId ? "hrms-theme-" + userId : "hrms-theme";
                var legacyThemeKey = "hrms-theme";
                var storedTheme = localStorage.getItem(themeKey);
                var legacyTheme = localStorage.getItem(legacyThemeKey);
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
                document.documentElement.classList.toggle("dark-mode", isDark);
            } catch (error) {
                // Ignore theme bootstrap failures in embedded views.
            }
        })();
    </script>
    <link
        rel="icon"
        type="image/webp"
        href="{{ asset('assets/img/Northeastern College.webp') }}"
    />
    <link rel="stylesheet" href="{{ asset('assets/css/coreui.min.css') }}" />
    <link
        rel="stylesheet"
        href="{{ asset('assets/plugins/fontawesome-free/css/all.min.css') }}"
    />
    @php
        $viteCss = [
            'resources/css/coreui-shell.css',
            'resources/css/ui-components.css',
            'resources/css/ui-tables.css',
            'resources/css/ui-hero.css',
            'resources/css/mobile-responsive.css',
            'resources/css/emerald-theme.css',
            'resources/css/toasts.css',
        ];

        if (request()->routeIs('documents.*') || request()->routeIs('employee-documents.*')) {
            $viteCss[] = 'resources/css/documents-index.css';
        }

        $viteCss[] = 'resources/css/dark-mode-consistency.css';
    @endphp
    @vite ($viteCss)
    @stack ('styles')
</head>
<body class="app-shell hrms-embedded" data-page="{{ request()->route()?->getName() }}">
    <div class="app-embedded-shell">
        @yield ('content')
    </div>

    <script src="{{ asset('assets/plugins/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/js/coreui.bundle.min.js') }}"></script>
    @php
        $viteJs = [
            'resources/js/app.js',
            'resources/js/flash-toasts.js',
            'resources/js/ui-interactions.js',
            'resources/js/datatables-init.js',
            'resources/js/notifications.js',
        ];

        if (request()->routeIs('documents.*') || request()->routeIs('employee-documents.*')) {
            $viteJs[] = 'resources/js/documents.js';
        }
    @endphp
    @vite ($viteJs)
    @stack ('scripts')
</body>
</html>
