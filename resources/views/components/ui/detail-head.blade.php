@props (['title', 'eyebrow' => null, 'subtitle' => null])
<div {{ $attributes->merge(['class' => 'hrms-detail-head']) }}>
    @if (filled($eyebrow))
        <div class="hrms-detail-head__eyebrow">{{ $eyebrow }}</div>
    @endif
    <div class="hrms-detail-head__title">{{ $title }}</div>
    @if (filled($subtitle))
        <div class="hrms-detail-head__subtitle">{{ $subtitle }}</div>
    @endif
    {{ $slot }}
</div>
