@props([
    'color' => 'primary',
    'size' => null,
    'label' => 'Loading...',
    'centered' => false,
])

@php
    $spinnerClass = trim('spinner-border text-' . $color . ' ' . ($size ? ('spinner-border-' . $size) : ''));
@endphp

<div {{ $attributes->class(['hrms-spinner', 'hrms-spinner--centered' => $centered]) }}>
    <div class="{{ $spinnerClass }}" role="status" aria-live="polite" aria-label="{{ $label }}">
        <span class="visually-hidden">{{ $label }}</span>
    </div>
</div>
