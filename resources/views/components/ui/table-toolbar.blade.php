@props ([
    'as' => 'form',
    'method' => 'GET',
    'action' => null,
    'searchName' => null,
    'searchLabel' => 'Search',
    'searchValue' => null,
    'searchPlaceholder' => 'Search',
    'filterName' => null,
    'filterLabel' => 'Filter',
    'filterOptions' => [],
    'filterValue' => null,
    'filterPlaceholder' => 'All records',
    'filterSearchable' => true,
    'filterAllowClear' => true,
    'submitLabel' => null,
])

@php
    $tag = strtolower((string) $as) === 'div' ? 'div' : 'form';
    $submitType = $tag === 'form' ? 'submit' : 'button';
    $toolbarAttributes = $attributes->merge(['class' => 'ui-toolbar ui-table-toolbar-form ui-table-standard-toolbar']);

    if ($tag === 'form') {
        $toolbarAttributes = $toolbarAttributes
            ->merge(['method' => strtoupper($method) === 'GET' ? 'GET' : 'POST'])
            ->merge(['action' => $action]);
    }
@endphp

<{{ $tag }} {{ $toolbarAttributes }}>
    @if ($tag === 'form' && strtoupper($method) !== 'GET')
        @csrf
    @endif

    <div class="ui-toolbar__grid ui-table-toolbar-grid">
        @if ($searchName)
            <div
                class="ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--search"
                data-toolbar-label="{{ $searchLabel }}"
            >
                <label
                    class="ui-toolbar__label"
                    for="toolbar-search-{{ $searchName }}"
                    >{{ $searchLabel }}</label
                >
                <input
                    id="toolbar-search-{{ $searchName }}"
                    type="search"
                    name="{{ $searchName }}"
                    value="{{ $searchValue }}"
                    class="form-control ui-toolbar__control ui-toolbar__control--search"
                    aria-label="Search"
                    placeholder="{{ $searchPlaceholder }}"
                />
            </div>
        @endif

        @if ($filterName)
            <div class="ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--filter" data-toolbar-label="{{ $filterLabel }}">
                <label
                    class="ui-toolbar__label"
                    for="toolbar-filter-{{ $filterName }}"
                    >{{ $filterLabel }}</label
                >
                <select
                    id="toolbar-filter-{{ $filterName }}"
                    name="{{ $filterName }}"
                    class="form-control ui-toolbar__control ui-toolbar__control--select {{ $filterSearchable ? 'select2bs4' : '' }}"
                    aria-label="Filter"
                    @if ($filterSearchable) data-toolbar-select2="1" @endif
                    @if ($filterSearchable) data-placeholder="{{ $filterPlaceholder }}" @endif
                    @if ($filterSearchable) data-allow-clear="{{ $filterAllowClear ? '1' : '0' }}" @endif
                >
                    <option value=""></option>
                    @foreach ($filterOptions as $value => $label)
                        <option
                            value="{{ $value }}"
                            @selected ((string) $filterValue === (string) $value)
                            >{{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif

        {{ $slot }}

        @if ($submitLabel)
            <div
                class="ui-toolbar__field ui-toolbar__field--action ui-table-toolbar-field ui-table-toolbar-field--action"
                data-toolbar-label=""
            >
                <x-ui.button
                    type="{{ $submitType }}"
                    variant="primary"
                    icon="cil-filter"
                    class="ui-toolbar__submit ui-table-standard-toolbar__submit"
                >
                    {{ $submitLabel }}
                </x-ui.button>
            </div>
        @endif
    </div>
</{{ $tag }}>
