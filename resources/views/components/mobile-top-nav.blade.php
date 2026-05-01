@props(['user'])

@php
    $hour = now()->hour;
    $greeting = 'Good day';
    if ($hour < 12) $greeting = 'Good morning';
    elseif ($hour < 18) $greeting = 'Good afternoon';
    else $greeting = 'Good evening';

    $firstName = $user->employee->first_name ?? (explode(' ', trim($user->name))[0] ?? 'User');
@endphp

<div class="mobile-top-nav d-lg-none" aria-label="Mobile top navigation">
    <div class="mobile-top-nav__content container-fluid px-3 h-100 d-flex align-items-center">
        <div class="mobile-top-nav__left d-flex align-items-center gap-2">
            <div class="mobile-top-nav__logo">
                <img 
                    src="{{ asset('assets/img/Northeastern%20College.webp') }}" 
                    alt="Logo" 
                    onerror="this.src='{{ asset('assets/dist/img/AdminLTELogo.png') }}'"
                >
            </div>
            <div class="mobile-top-nav__greet">
                <h6 class="mobile-top-nav__name">
                    <span class="mobile-top-nav__eyebrow">{{ $greeting }},</span> {{ $firstName }}
                </h6>
            </div>
        </div>
        
        <button 
            type="button" 
            class="mobile-top-nav__ai ms-auto" 
            data-chatbot-toggle 
            aria-label="Toggle AI Assistant"
        >
            <i class="cil-speech"></i>
            <span class="d-none d-sm-inline ms-1">Assistant</span>
        </button>
    </div>
</div>
