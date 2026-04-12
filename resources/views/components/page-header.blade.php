@props (['eyebrow' => null, 'title', 'subtitle' => null, 'class' => '', 'id' => null])
<x-ui.hero
    :eyebrow="$eyebrow"
    :title="$title"
    :subtitle="$subtitle"
    :class="'hrms-page-header ' . $class"
    :id="$id"
    {{ $attributes }}
>
    @isset ($actions)
        <x-slot:actions>
            {{ $actions }}
        </x-slot:actions>
    @endisset
</x-ui.hero>
