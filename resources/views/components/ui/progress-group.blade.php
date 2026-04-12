@props([
    'icon' => 'cil-chart-pie',
    'label',
    'value',
    'meta' => null,
    'percent' => 0,
    'color' => 'primary',
])

@php
    $clampedPercent = max(0, min(100, (int) $percent));
@endphp

<div {{ $attributes->class('progress-group hrms-progress-group') }}>
    <div class="progress-group-header align-items-end">
        <i class="{{ $icon }} progress-group-icon me-2"></i>
        <div>{{ $label }}</div>
        <div class="ms-auto font-weight-bold me-2">{{ $value }}</div>
        @if ($meta)
            <div class="text-muted small">{{ $meta }}</div>
        @endif
    </div>
    <div class="progress-group-bars">
        <div class="progress progress-thin">
            <div
                class="progress-bar bg-{{ $color }}"
                role="progressbar"
                style="width: {{ $clampedPercent }}%"
                aria-valuenow="{{ $clampedPercent }}"
                aria-valuemin="0"
                aria-valuemax="100"
            ></div>
        </div>
    </div>
</div>
