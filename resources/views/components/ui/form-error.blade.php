@props([
    'field',
    'context' => null,
    'bag' => 'default',
    'tag' => 'div',
])

@php
    $message = \App\Support\FormValidation::firstError($field, $context, $bag);
@endphp

@if ($message)
    <{{ $tag }} {{ $attributes->merge(['class' => 'invalid-feedback d-block']) }}>
        {{ $message }}
    </{{ $tag }}>
@endif
