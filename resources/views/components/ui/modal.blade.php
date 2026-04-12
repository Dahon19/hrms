@props([
    'id' => null,
    'size' => 'md',
    'centered' => true,
    'scrollable' => false,
    'dialogClass' => '',
    'contentClass' => '',
    'staticBackdrop' => false,
    'keyboard' => true,
])

@php
    $dialogClasses = collect([
        'modal-dialog',
        $size ? 'modal-' . $size : null,
        $centered ? 'modal-dialog-centered' : null,
        $scrollable ? 'modal-dialog-scrollable' : null,
        $dialogClass ?: null,
    ])->filter()->implode(' ');

    $contentClasses = collect([
        'modal-content',
        'hrms-modal-surface',
        $contentClass ?: null,
    ])->filter()->implode(' ');
@endphp

<div
    {{ $attributes->merge([
        'class' => 'modal fade',
        'id' => $id,
        'tabindex' => '-1',
        'role' => 'dialog',
        'aria-hidden' => 'true',
        'data-backdrop' => $staticBackdrop ? 'static' : null,
        'data-keyboard' => $keyboard ? 'true' : 'false',
        'data-coreui-backdrop' => $staticBackdrop ? 'static' : 'true',
        'data-coreui-keyboard' => $keyboard ? 'true' : 'false',
    ]) }}
>
    <div class="{{ $dialogClasses }}" role="document">
        <div class="{{ $contentClasses }}">
            {{ $slot }}
        </div>
    </div>
</div>
