<x-guest-layout>
    <style>
        @keyframes fadeScaleIn {
            0% { opacity: 0; transform: scale(0.95) translateY(20px); }
            100% { opacity: 1; transform: scale(1) translateY(0); }
        }

        @keyframes floatIdle {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-15px) rotate(2deg); }
        }

        @keyframes shimmerPulse {
            0%, 100% { opacity: 0.35; transform: scale(1); }
            50% { opacity: 0.6; transform: scale(1.08); }
        }

        @keyframes cardLiftIn {
            0% { opacity: 0; transform: translateY(34px) scale(0.98); }
            100% { opacity: 1; transform: translateY(0) scale(1); }
        }

        @keyframes fadeSlideIn {
            0% { opacity: 0; transform: translateY(12px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        .animate-fade-scale {
            animation: fadeScaleIn 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        .animate-float {
            animation: floatIdle 6s ease-in-out infinite;
        }

        .glass-panel {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .graph-grid-bg {
            background-image:
                linear-gradient(rgba(148, 163, 184, 0.08) 1px, transparent 1px),
                linear-gradient(90deg, rgba(148, 163, 184, 0.08) 1px, transparent 1px);
            background-size: 36px 36px;
        }

        .animate-shimmer-pulse {
            animation: shimmerPulse 7s ease-in-out infinite;
        }

        .animate-card-lift {
            animation: cardLiftIn 0.9s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        .animate-fade-slide {
            animation: fadeSlideIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        .login-form-panel {
            width: min(100%, 34rem);
        }

        .turnstile-shell {
            border: 1px solid rgba(229, 231, 235, 0.95);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(249, 250, 251, 0.98) 100%);
            box-shadow: 0 16px 36px -28px rgba(15, 23, 42, 0.28);
        }

        .turnstile-shell .cf-turnstile {
            display: block;
            width: 100%;
            max-width: 100%;
        }

        .turnstile-shell iframe {
            max-width: 100%;
        }

        @media (max-width: 640px) {
            .turnstile-shell {
                padding-left: 0.85rem;
                padding-right: 0.85rem;
            }
        }
    </style>

    <div class="flex h-screen w-full overflow-hidden bg-neutral-50 font-sans text-neutral-900 selection:bg-green-500 selection:text-white" x-data="{ showPassword: false, isLoading: false }">
        <div class="relative hidden w-1/2 flex-col justify-between overflow-hidden bg-neutral-900 px-12 py-16 text-white lg:flex">
            <div class="absolute inset-0 z-0 bg-[radial-gradient(circle_at_top_left,_rgba(74,222,128,0.22),_transparent_30%),radial-gradient(circle_at_bottom_right,_rgba(22,163,74,0.26),_transparent_28%),linear-gradient(135deg,_#07170f_0%,_#0f172a_48%,_#0b3b2a_100%)]"></div>
            <div class="graph-grid-bg absolute inset-0 z-0 opacity-40"></div>
            <div class="absolute inset-0 z-0 bg-[linear-gradient(to_right,rgba(255,255,255,0.015)_1px,transparent_1px),linear-gradient(to_bottom,rgba(255,255,255,0.015)_1px,transparent_1px)] [background-size:12px_12px] opacity-20"></div>
            <div class="absolute left-0 top-0 z-0 h-full w-full pointer-events-none opacity-20 mix-blend-screen">
                <div class="absolute -left-32 -top-32 h-[500px] w-[500px] rounded-full bg-green-500 blur-[100px] animate-float animate-shimmer-pulse"></div>
                <div class="absolute right-0 top-1/2 h-[400px] w-[400px] rounded-full bg-emerald-400 blur-[100px] animate-float animate-shimmer-pulse" style="animation-delay: -3s;"></div>
            </div>

            <div class="relative z-10 animate-fade-scale" style="animation-delay: 0.1s; opacity: 0;">
                <a href="{{ route('landing') }}" class="group mb-8 inline-flex items-center gap-4 focus:outline-none" aria-label="Return to landing page">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl border border-white/20 bg-white/10 shadow-xl backdrop-blur-md transition-all duration-300 group-hover:scale-105 group-hover:bg-white/20">
                        <img src="{{ asset('assets/img/Northeastern College.webp') }}" data-fallback="{{ asset('assets/dist/img/AdminLTELogo.png') }}" alt="Logo" class="h-8 w-8 object-contain" onerror="this.onerror=null;this.src=this.dataset.fallback;" />
                    </div>
                    <div>
                        <span class="block text-sm font-medium uppercase tracking-widest text-green-300">System Hub</span>
                        <span class="block text-xl font-bold tracking-wide text-white">NC HRMS</span>
                    </div>
                </a>

                <h1 class="mb-6 font-display text-5xl font-extrabold leading-[1.15] tracking-tight">
                    Powering<br />workforce<br />
                    <span class="bg-gradient-to-r from-emerald-300 to-green-500 bg-clip-text text-transparent">excellence.</span>
                </h1>
                <p class="max-w-md text-lg leading-relaxed text-neutral-300">
                    A unified workspace for employee management, streamlined attendance tracking, and transparent performance evaluation.
                </p>
            </div>

            <div class="relative z-10 space-y-5 animate-fade-scale" style="animation-delay: 0.3s; opacity: 0;">
                <div class="glass-panel flex items-center gap-4 rounded-2xl p-5 transition-all duration-500 hover:-translate-y-1 hover:bg-white/10 hover:shadow-[0_20px_40px_-25px_rgba(74,222,128,0.32)]">
                    <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-green-500/20 text-green-300">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-white">Centralized Profiles</h3>
                        <p class="mt-0.5 text-sm text-neutral-400">Access vital personnel records instantly.</p>
                    </div>
                </div>
            </div>

            <div class="relative z-10 text-xs font-medium text-neutral-500">
                &copy; {{ date('Y') }} Northeastern College. All rights reserved.
            </div>
        </div>

        <div class="relative flex w-full flex-col items-center justify-center bg-white px-6 sm:px-12 lg:w-1/2">
            <div class="login-form-panel animate-card-lift w-full" style="animation-delay: 0.2s; opacity: 0;">
                <div class="mb-10 text-center lg:hidden">
                    <div class="mb-4 inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-green-600 shadow-xl transition-transform duration-500 hover:scale-[1.04] hover:shadow-[0_24px_36px_-20px_rgba(22,163,74,0.5)]">
                        <img src="{{ asset('assets/img/Northeastern College.webp') }}" data-fallback="{{ asset('assets/dist/img/AdminLTELogo.png') }}" alt="Logo" class="h-10 w-10 object-contain brightness-0 invert" onerror="this.onerror=null;this.src=this.dataset.fallback;" />
                    </div>
                    <h2 class="text-3xl font-extrabold tracking-tight text-neutral-900">NC HRMS</h2>
                    <p class="mt-2 text-sm text-neutral-500">Sign in to your account</p>
                </div>

                <div class="mb-10 hidden lg:block">
                    <h2 class="text-3xl font-extrabold tracking-tight text-neutral-900">Welcome Back</h2>
                    <p class="mt-2 text-sm text-neutral-500">Enter your credentials to access the dashboard.</p>
                </div>

                @if (session('status'))
                    <div class="animate-fade-slide mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                        {{ session('status') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" class="space-y-6" @submit="isLoading = true">
                    @csrf

                    <div class="relative">
                        <label for="login" class="mb-1.5 block text-sm font-medium text-neutral-700">Email / Employee ID</label>
                        <div class="group relative">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-neutral-400 transition-all duration-300 pointer-events-none group-focus-within:scale-110 group-focus-within:text-green-500">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" /></svg>
                            </div>
                            <input id="login" type="text" name="login" value="{{ old('login') }}" required autofocus
                                class="block w-full rounded-xl border border-neutral-200 bg-neutral-50/50 py-3 pl-11 pr-4 text-neutral-900 shadow-[0_8px_18px_-14px_rgba(15,23,42,0.18)] transition-all duration-300 focus:-translate-y-0.5 focus:border-green-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-green-500/15 sm:text-sm @error('login') border-red-500 bg-red-50/30 focus:border-red-500 focus:ring-red-500/20 @enderror"
                                placeholder="name@college.edu.ph" autocomplete="username" />
                        </div>
                        @error ('login')
                            <p class="animate-fade-slide mt-1.5 text-sm font-medium text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="relative">
                        <label for="password" class="mb-1.5 block text-sm font-medium text-neutral-700">Password</label>
                        <div class="group relative">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-neutral-400 transition-all duration-300 pointer-events-none group-focus-within:scale-110 group-focus-within:text-green-500">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                            </div>
                            <input id="password" :type="showPassword ? 'text' : 'password'" name="password" required
                                class="block w-full rounded-xl border border-neutral-200 bg-neutral-50/50 py-3 pl-11 pr-12 text-neutral-900 shadow-[0_8px_18px_-14px_rgba(15,23,42,0.18)] transition-all duration-300 focus:-translate-y-0.5 focus:border-green-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-green-500/15 sm:text-sm @error('password') border-red-500 bg-red-50/30 focus:border-red-500 focus:ring-red-500/20 @enderror"
                                placeholder="********" autocomplete="current-password" />

                            <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-neutral-400 transition-all duration-300 hover:scale-110 hover:text-neutral-600 focus:outline-none">
                                <svg x-show="!showPassword" class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                <svg x-show="showPassword" x-cloak class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
                            </button>
                        </div>
                        @error ('password')
                            <p class="animate-fade-slide mt-1.5 text-sm font-medium text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mt-4 flex items-center justify-between">
                        <label for="remember_me" class="group flex cursor-pointer items-center">
                            <div class="relative mr-2 flex h-5 w-5 items-center justify-center">
                                <input id="remember_me" type="checkbox" name="remember" class="peer h-5 w-5 cursor-pointer appearance-none rounded-md border-2 border-neutral-300 transition-all duration-300 checked:border-green-600 checked:bg-green-600 hover:scale-105 focus:outline-none focus:ring-2 focus:ring-green-500/30" />
                                <svg class="pointer-events-none absolute h-3 w-3 text-white opacity-0 transition-opacity duration-200 peer-checked:opacity-100" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                            </div>
                            <span class="text-sm font-medium text-neutral-600 transition-colors group-hover:text-neutral-900">Remember me</span>
                        </label>

                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}" class="text-sm font-semibold text-green-600 transition-colors hover:text-green-500">
                                Forgot password?
                            </a>
                        @endif
                    </div>

                    @php
                        $turnstileSiteKey = config('services.turnstile.site_key');
                    @endphp
                    @if (filled($turnstileSiteKey))
                        <div class="space-y-2">
                            <div class="turnstile-shell rounded-2xl px-4 py-3">
                                <p class="mb-2 text-xs font-semibold uppercase tracking-[0.18em] text-neutral-400">Security Check</p>
                            <div
                                class="cf-turnstile"
                                data-sitekey="{{ $turnstileSiteKey }}"
                                data-theme="light"
                                data-size="flexible"
                            ></div>
                            </div>
                            @error('cf-turnstile-response')
                                <p class="animate-fade-slide mt-1.5 text-sm font-medium text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif

                    <button type="submit" :disabled="isLoading"
                        class="group relative flex w-full items-center justify-center overflow-hidden rounded-xl bg-green-600 px-4 py-3.5 font-semibold text-white shadow-[0_8px_20px_-6px_rgba(22,163,74,0.42)] transition-all duration-300 hover:-translate-y-0.5 hover:bg-green-700 hover:shadow-[0_16px_28px_-10px_rgba(22,163,74,0.42)] focus:outline-none focus:ring-4 focus:ring-green-500/30 disabled:cursor-not-allowed disabled:opacity-70"
                    >
                        <span class="pointer-events-none absolute inset-0 -translate-x-full bg-[linear-gradient(120deg,transparent,rgba(255,255,255,0.22),transparent)] transition-transform duration-700 group-hover:translate-x-full"></span>
                        <span x-show="!isLoading">Sign In</span>
                        <span x-show="isLoading" x-cloak class="flex items-center">
                            <svg class="-ml-1 mr-2 h-5 w-5 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Authenticating...
                        </span>
                    </button>
                </form>
            </div>

            <p class="absolute bottom-6 text-center text-xs font-medium text-neutral-400 lg:hidden">
                &copy; {{ date('Y') }} Northeastern College
            </p>
        </div>
    </div>
    @if (filled(config('services.turnstile.site_key')))
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endif
</x-guest-layout>
