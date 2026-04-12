<x-guest-layout>
    <style>
        @keyframes gridPulse {
            0%, 100% { opacity: 0.3; transform: scale(1); }
            50% { opacity: 0.55; transform: scale(1.04); }
        }

        @keyframes riseIn {
            0% { opacity: 0; transform: translateY(28px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        .login-rise {
            animation: riseIn 0.9s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        .grid-pulse {
            animation: gridPulse 8s ease-in-out infinite;
        }
    </style>

    <div class="relative min-h-screen overflow-hidden bg-slate-950 text-slate-100 selection:bg-sky-400 selection:text-slate-950" x-data="{ showPassword: false, isLoading: false }">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(56,189,248,0.24),_transparent_34%),radial-gradient(circle_at_bottom_right,_rgba(37,99,235,0.28),_transparent_30%),linear-gradient(135deg,_#020617_0%,_#0f172a_52%,_#082f49_100%)]"></div>
        <div class="grid-pulse absolute inset-0 opacity-40 [background-image:linear-gradient(rgba(148,163,184,0.10)_1px,transparent_1px),linear-gradient(90deg,rgba(148,163,184,0.10)_1px,transparent_1px)] [background-size:36px_36px]"></div>

        <div class="relative mx-auto flex min-h-screen max-w-7xl flex-col px-6 py-8 lg:px-10">
            <div class="mb-8 flex items-center justify-between login-rise" style="animation-delay: 0.05s; opacity: 0;">
                <a href="{{ route('landing') }}" class="inline-flex items-center gap-4">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl border border-white/10 bg-white/10 shadow-[0_18px_50px_-20px_rgba(56,189,248,0.85)] backdrop-blur-xl">
                        <img src="{{ asset('assets/img/Northeastern College.webp') }}" data-fallback="{{ asset('assets/dist/img/AdminLTELogo.png') }}" alt="Logo" class="h-8 w-8 object-contain" onerror="this.onerror=null;this.src=this.dataset.fallback;" />
                    </div>
                    <div>
                        <span class="block text-xs font-semibold uppercase tracking-[0.35em] text-sky-300/90">Northeastern College</span>
                        <span class="block text-lg font-semibold text-white">HRMS Access Portal</span>
                    </div>
                </a>

                <div class="hidden rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-medium text-slate-300 backdrop-blur lg:block">
                    Live workspace for HR, attendance, and employee records
                </div>
            </div>

            <div class="flex flex-1 items-center">
                <div class="grid w-full gap-8 lg:grid-cols-[1.15fr_0.85fr]">
                    <section class="flex flex-col justify-center login-rise" style="animation-delay: 0.15s; opacity: 0;">
                        <div class="max-w-2xl">
                            <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-sky-400/20 bg-sky-400/10 px-4 py-2 text-sm font-medium text-sky-200 backdrop-blur">
                                <span class="h-2 w-2 rounded-full bg-sky-300"></span>
                                Testing redesign build
                            </div>

                            <h1 class="max-w-3xl text-5xl font-black leading-[1.02] tracking-[-0.04em] text-white sm:text-6xl xl:text-7xl">
                                A sharper login screen
                                <span class="block bg-gradient-to-r from-sky-300 via-blue-200 to-blue-500 bg-clip-text text-transparent">
                                    for deployment verification.
                                </span>
                            </h1>

                            <p class="mt-6 max-w-xl text-lg leading-8 text-slate-300">
                                This test layout is intentionally more distinct so you can confirm whether the production server is using the latest Blade template and compiled assets.
                            </p>
                        </div>

                        <div class="mt-10 grid gap-4 sm:grid-cols-3">
                            <div class="rounded-3xl border border-white/10 bg-white/5 p-5 backdrop-blur-xl">
                                <div class="text-sm font-semibold text-sky-300">Unified Access</div>
                                <p class="mt-2 text-sm leading-6 text-slate-300">Attendance, documents, leave, and employee records in one workspace.</p>
                            </div>
                            <div class="rounded-3xl border border-white/10 bg-white/5 p-5 backdrop-blur-xl">
                                <div class="text-sm font-semibold text-sky-300">Role Aware</div>
                                <p class="mt-2 text-sm leading-6 text-slate-300">Different landing behavior for admins, heads, HR staff, and employees.</p>
                            </div>
                            <div class="rounded-3xl border border-white/10 bg-white/5 p-5 backdrop-blur-xl">
                                <div class="text-sm font-semibold text-sky-300">Traceable</div>
                                <p class="mt-2 text-sm leading-6 text-slate-300">A dramatic visual change makes deployment issues easier to spot immediately.</p>
                            </div>
                        </div>
                    </section>

                    <section class="flex items-center justify-center login-rise" style="animation-delay: 0.28s; opacity: 0;">
                        <div class="w-full max-w-md rounded-[2rem] border border-white/10 bg-white/95 p-6 text-slate-900 shadow-[0_30px_80px_-28px_rgba(2,132,199,0.55)] backdrop-blur-2xl sm:p-8">
                            <div class="mb-8">
                                <div class="mb-4 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-sky-500 to-blue-700 text-white shadow-lg shadow-sky-500/30">
                                    <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 11c1.657 0 3-1.79 3-4s-1.343-4-3-4-3 1.79-3 4 1.343 4 3 4zm-8 0c1.657 0 3-1.79 3-4S9.657 3 8 3 5 4.79 5 7s1.343 4 3 4zm8 2c-2.21 0-4 1.79-4 4v2h8v-2c0-2.21-1.79-4-4-4zM8 13c-2.21 0-4 1.79-4 4v2h8v-2c0-2.21-1.79-4-4-4z" />
                                    </svg>
                                </div>
                                <h2 class="text-3xl font-black tracking-tight text-slate-950">Sign in</h2>
                                <p class="mt-2 text-sm leading-6 text-slate-500">
                                    Use your email address or employee ID to enter the HRMS dashboard.
                                </p>
                            </div>

                            @if (session('status'))
                                <div class="mb-5 rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm font-medium text-sky-700">
                                    {{ session('status') }}
                                </div>
                            @endif

                            <form method="POST" action="{{ route('login') }}" class="space-y-5" @submit="isLoading = true">
                                @csrf

                                <div>
                                    <label for="login" class="mb-2 block text-sm font-semibold text-slate-700">Email / Employee ID</label>
                                    <div class="group flex items-center rounded-2xl border border-slate-200 bg-slate-50 px-4 transition-all duration-300 focus-within:border-sky-500 focus-within:bg-white focus-within:ring-4 focus-within:ring-sky-500/15 @error('login') border-red-400 focus-within:border-red-500 focus-within:ring-red-500/15 @enderror">
                                        <svg class="h-5 w-5 text-slate-400 transition-colors group-focus-within:text-sky-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
                                        </svg>
                                        <input id="login" type="text" name="login" value="{{ old('login') }}" required autofocus
                                            class="w-full border-0 bg-transparent px-3 py-4 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-0"
                                            placeholder="name@college.edu.ph" autocomplete="username" />
                                    </div>
                                    @error ('login')
                                        <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="password" class="mb-2 block text-sm font-semibold text-slate-700">Password</label>
                                    <div class="group flex items-center rounded-2xl border border-slate-200 bg-slate-50 px-4 transition-all duration-300 focus-within:border-sky-500 focus-within:bg-white focus-within:ring-4 focus-within:ring-sky-500/15 @error('password') border-red-400 focus-within:border-red-500 focus-within:ring-red-500/15 @enderror">
                                        <svg class="h-5 w-5 text-slate-400 transition-colors group-focus-within:text-sky-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                        </svg>
                                        <input id="password" :type="showPassword ? 'text' : 'password'" name="password" required
                                            class="w-full border-0 bg-transparent px-3 py-4 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-0"
                                            placeholder="Enter your password" autocomplete="current-password" />
                                        <button type="button" @click="showPassword = !showPassword" class="text-slate-400 transition-colors hover:text-slate-600">
                                            <svg x-show="!showPassword" class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            <svg x-show="showPassword" x-cloak class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                            </svg>
                                        </button>
                                    </div>
                                    @error ('password')
                                        <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="flex items-center justify-between gap-4">
                                    <label for="remember_me" class="inline-flex items-center gap-3 text-sm font-medium text-slate-600">
                                        <input id="remember_me" type="checkbox" name="remember" class="h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                                        Remember me
                                    </label>

                                    @if (Route::has('password.request'))
                                        <a href="{{ route('password.request') }}" class="text-sm font-semibold text-sky-700 transition-colors hover:text-sky-500">
                                            Forgot password?
                                        </a>
                                    @endif
                                </div>

                                <button type="submit" :disabled="isLoading"
                                    class="relative flex w-full items-center justify-center rounded-2xl bg-gradient-to-r from-sky-500 via-blue-600 to-blue-800 px-4 py-4 text-sm font-bold uppercase tracking-[0.25em] text-white shadow-[0_20px_40px_-18px_rgba(37,99,235,0.7)] transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[0_24px_44px_-16px_rgba(14,165,233,0.7)] focus:outline-none focus:ring-4 focus:ring-sky-500/25 disabled:cursor-not-allowed disabled:opacity-70"
                                >
                                    <span x-show="!isLoading">Access HRMS</span>
                                    <span x-show="isLoading" x-cloak class="flex items-center">
                                        <svg class="-ml-1 mr-2 h-5 w-5 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Signing In
                                    </span>
                                </button>
                            </form>

                            <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs leading-6 text-slate-500">
                                Test marker: if this card layout, headline, and blue gradient button do not appear in production, the live server is serving stale views or stale Vite assets.
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            <div class="mt-8 text-center text-xs font-medium text-slate-400 login-rise" style="animation-delay: 0.36s; opacity: 0;">
                &copy; {{ date('Y') }} Northeastern College HRMS
            </div>
        </div>
    </div>
</x-guest-layout>
