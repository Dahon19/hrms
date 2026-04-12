@props (['title' => null, 'eyebrow' => null, 'description' => null])
<section {{ $attributes->merge(['class' => 'hrms-form-section']) }}>
    @if (filled($eyebrow) || filled($title) || filled($description))
        <div class="hrms-form-section__header">
            @if (filled($eyebrow))
                <div class="hrms-form-section__eyebrow">{{ $eyebrow }}</div>
            @endif
            @if (filled($title))
                <div class="hrms-form-section__title form-section-title">
                    {{ $title }}
                </div>
            @endif
            @if (filled($description))
                <div class="hrms-form-section__text">{{ $description }}</div>
            @endif
        </div>
    @endif
    {{ $slot }}
</section>
