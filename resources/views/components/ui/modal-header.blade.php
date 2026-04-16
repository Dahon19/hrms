@props (['title', 'subtitle' => null, 'icon' => null, 'dismissible' => true, 'titleId' => null])
<div {{ $attributes->merge(['class' => 'modal-header hrms-modal-head']) }}>
    <div class="hrms-modal-head__main">
        @if (filled($icon))
            <span class="hrms-modal-head__icon" aria-hidden="true">
                <i class="{{ $icon }}"></i>
            </span>
        @endif
        <div class="hrms-modal-head__copy">
            <h5
                class="modal-title font-weight-bold mb-0"
                @if (filled($titleId)) id="{{ $titleId }}" @endif
            >
                {{ $title }}
            </h5>
            @if (filled($subtitle))
                <p class="modal-subtitle hrms-modal-head__subtitle mb-0">{{ $subtitle }}</p>
            @endif
            {{ $slot }}
        </div>
    </div>
    @if ($dismissible)
        <button
            type="button"
            class="hrms-modal-head__close"
            data-coreui-dismiss="modal"
            aria-label="Close"
        >
            <i class="cil-x" aria-hidden="true"></i>
        </button>
    @endif
</div>
