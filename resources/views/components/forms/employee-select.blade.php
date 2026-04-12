@props ([
    'name' => 'employee_id',
    'id' => null,
    'selected' => null,
    'selectedText' => null,
    'placeholder' => 'Search',
    'required' => false,
    'includeArchived' => false,
    'allowClear' => true,
    'class' => '',
    'disabled' => false,
])

@php
    $inputId = $id ?: $name . '_' . uniqid();
    $resolvedSelected = old($name, $selected);
    $resolvedSelectedText = $selectedText;

    if (($resolvedSelectedText === null || $resolvedSelectedText === '') && $resolvedSelected) {
        $prefillEmployee = \App\Models\Employee::query()
            ->select(['id', 'first_name', 'middle_name', 'last_name', 'suffix'])
            ->find($resolvedSelected);

        if ($prefillEmployee) {
            $middleInitial = $prefillEmployee->middle_name
                ? ' ' . strtoupper(substr($prefillEmployee->middle_name, 0, 1)) . '.'
                : '';
            $suffix = $prefillEmployee->suffix ? ' ' . $prefillEmployee->suffix : '';

            $resolvedSelectedText = trim(
                $prefillEmployee->first_name . $middleInitial . ' ' . $prefillEmployee->last_name . $suffix,
            );
        }
    }
@endphp

<select
    id="{{ $inputId }}"
    name="{{ $name }}"
    class="form-control select2bs4 employee-select-control {{ $class }}"
    data-employee-select="1"
    data-placeholder="{{ $placeholder }}"
    data-allow-clear="{{ $allowClear ? '1' : '0' }}"
    data-include-archived="{{ $includeArchived ? '1' : '0' }}"
    data-search-url="{{ route('api.employees.search') }}"
    @if ($required) required @endif
    @if ($disabled) disabled @endif
    {{ $attributes->except(['class']) }}
>
    <option value="">{{ $placeholder }}</option>

    @if ($resolvedSelected)
        <option value="{{ $resolvedSelected }}" selected>
            {{ $resolvedSelectedText ?? 'Employee #' . $resolvedSelected }}
        </option>
    @endif
</select>
