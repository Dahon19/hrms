<x-ui.hero
    :eyebrow="$eyebrow ?? 'Attendance'"
    :title="'Attendance Console'"
    :subtitle="'Live attendance snapshot for ' . now()->format('l, F j, Y') . '.'"
    class="attendance-page-hero"
>
    <x-slot:actions>
        @isset ($actions)
            {!! $actions !!}
        @endisset
    </x-slot:actions>
</x-ui.hero>
