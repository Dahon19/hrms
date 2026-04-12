@props (['label', 'value' => null, 'meta' => null, 'valueClass' => null])
<div {{ $attributes->merge(['class' => 'hrms-detail-field']) }}>
    <div class="hrms-detail-field__label">{{ $label }}</div>
    @if ($slot->isNotEmpty())
        <div @class (['hrms-detail-field__value', $valueClass])
            >{{ $slot }}
        </div>
    @elseif (filled($value))
        <div @class (['hrms-detail-field__value', $valueClass])
            >{{ $value }}
        </div>
    @endif
    @if (filled($meta))
        <div class="hrms-detail-field__meta">{{ $meta }}</div>
    @endif
</div>
