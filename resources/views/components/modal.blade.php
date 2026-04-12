@props([
    'id' => null,
    'title' => null,
    'subtitle' => null,
    'icon' => null,
    'size' => 'md',
    'centered' => true,
    'scrollable' => false,
    'dialogClass' => '',
    'contentClass' => '',
    'headerClass' => '',
    'staticBackdrop' => false,
    'keyboard' => true,
    'dismissible' => true,
    'titleId' => null,
])

<x-ui.modal
    :id="$id"
    :size="$size"
    :centered="$centered"
    :scrollable="$scrollable"
    :dialog-class="$dialogClass"
    :content-class="$contentClass"
    :static-backdrop="$staticBackdrop"
    :keyboard="$keyboard"
    {{ $attributes }}
>
    @if (filled($title))
        <x-ui.modal-header
            :title="$title"
            :subtitle="$subtitle"
            :icon="$icon"
            :dismissible="$dismissible"
            :title-id="$titleId"
            :class="$headerClass"
        />
    @endif

    @if (isset($body))
        <div class="modal-body">
            {{ $body }}
        </div>
    @else
        {{ $slot }}
    @endif

    @if (isset($footer))
        <x-ui.modal-footer>
            {{ $footer }}
        </x-ui.modal-footer>
    @endif
</x-ui.modal>
