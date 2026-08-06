<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    @php ($isLoginPage = request()->routeIs('login'))
    <title>
        {{ $isLoginPage ? 'HRMS' : 'Northeastern College' }} |
        @yield ('title', 'Auth')
    </title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
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
    <link
        rel="stylesheet"
        href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback"
    />
    <link rel="stylesheet" href="{{ asset('assets/css/coreui.min.css') }}" />
    @vite ([
        'resources/css/toasts.css',
        'resources/css/futuristic-theme.css',
        'resources/js/flash-toasts.js',
    ])
    @stack ('styles')
</head>
<body class="bg-light" data-page="{{ request()->route()?->getName() }}">
    <div class="login-box">
        <div class="card card-outline card-primary shadow">
            <div class="card-header text-center">
                <a href="{{ url('/') }}" class="h1"
                    ><b>Northeastern College</b> HRMS</a
                >
                @hasSection ('subtitle')
                    <p class="text-muted small mt-2 mb-0">@yield ('subtitle')</p>
                @endif
            </div>
            <div class="card-body">@yield ('content')</div>
        </div>
    </div>
    <x-toast />
    <script src="{{ asset('assets/plugins/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/js/coreui.bundle.min.js') }}"></script>
    @stack ('scripts')
</body>
</html>
