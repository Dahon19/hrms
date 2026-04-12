@props ([
    'title' => null,
    'subtitle' => null,
    'responsive' => true,
])

@php
    $hasHeading = filled($title) || filled($subtitle) || isset($header);
    $hasControls = isset($controls) || isset($actions);
    $controlsContent = $controls ?? ($actions ?? '');
@endphp

<div {{ $attributes->merge(['class' => 'card shadow-sm mb-4 ui-table-card']) }}>
    @if ($hasHeading || $hasControls)
        <div class="ui-table-card__toolbar">
            @if ($hasHeading)
                <div class="ui-table-card__heading table-card-header">
                    @if (isset($header))
                        {{ $header }}
                    @else
                        <div class="ui-table-card__heading-copy">
                            @if (filled($title))
                                <h5 class="mb-1">{{ $title }}</h5>
                            @endif
                            @if (filled($subtitle))
                                <small>{{ $subtitle }}</small>
                            @endif
                        </div>
                    @endif
                </div>
            @endif
        </div>
    @endif

    @if ($hasControls)
        <div class="ui-table-card__controls ui-table-card__actions--toolbar">{{ $controlsContent }}</div>
    @endif

    <div class="card-body p-0 ui-table-card__body">
        @if ($responsive)
            <div class="table-responsive ui-table-card__table-wrap">
                {{ $slot }}
            </div>
        @else
            {{ $slot }}
        @endif
    </div>

    @if (isset($footer))
        <div class="card-footer hrms-list-footer ui-table-card__footer">
            {{ $footer }}
        </div>
    @endif
</div>
