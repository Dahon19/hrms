@props (['eyebrow' => null, 'title', 'subtitle' => null])
<div
    {{ $attributes->merge(['class' => 'card mb-4 border-0 shadow-sm hero ui-hero-card hero-card']) }}
>
    <div class="card-body">
        <div class="hero-shell">
            <div class="hero-content hrms-page-header__content">
                @if ($eyebrow)
                    <p class="hero-eyebrow hrms-page-header__eyebrow">{{ $eyebrow }}</p>
                @endif
                <h4 class="hero-title">{{ $title }}</h4>
                @if ($subtitle)
                    <p class="hero-subtitle text-medium-emphasis">{{ $subtitle }}</p>
                @endif
            </div>
            @isset ($actions)
                <div class="hero-actions hrms-page-header__actions">
                    {{ $actions }}
                </div>
            @endisset
        </div>
    </div>
</div>
