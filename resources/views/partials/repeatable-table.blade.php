@php
    $rows = is_array($rows) ? $rows : [];
    $dateFields = $dateFields ?? [];
    $selects = $selects ?? [];
    $isEditable = (($canSave ?? false) && !(($readOnly ?? false) === true));
    $displayRows = max(1, count($rows));
@endphp

<form
    method="POST"
    action="{{ $action }}"
    class="pds-section-form"
    data-pds-autosave="{{ $isEditable ? '1' : '0' }}"
    data-pds-section="{{ $sectionKey ?? '' }}"
>
    @csrf
    @method ('PUT')
    <input type="hidden" name="form_section" value="{{ $sectionKey ?? '' }}" />
    <fieldset @disabled (($readOnly ?? false) === true)>
        <div class="table-responsive">
            <table
                class="table table-sm table-hover align-middle mb-0 hrms-table pds-repeatable-table"
                @if ($isEditable)
                    data-pds-repeatable-table="1"
                    data-next-index="{{ $displayRows }}"
                @endif
            >
                <thead class="thead-light">
                    <tr>
                        @foreach ($headers as $header)
                            <th>{{ $header }}</th>
                        @endforeach
                        @if ($isEditable)
                            <th class="pds-repeatable-table__actions">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody data-pds-repeatable-body="1">
                    @for ($i = 0; $i < $displayRows; $i++)
                        <tr data-pds-repeatable-row="1" @if ($i === 0) data-pds-locked-row="1" @endif>
                            @foreach ($fields as $field)
                                <td>
                                    @php ($value = $rows[$i][$field] ?? '')
                                    @if ($loop->first)
                                        <input
                                            type="hidden"
                                            name="entries[{{ $i }}][id]"
                                            value="{{ $rows[$i]['id'] ?? '' }}"
                                        />
                                    @endif
                                    @if (isset($selects[$field]))
                                        <select
                                            name="entries[{{ $i }}][{{ $field }}]"
                                            class="form-control form-control-sm"
                                        >
                                            <option value="">-</option>
                                            @foreach ($selects[$field] as $optValue => $optLabel)
                                                <option
                                                    value="{{ $optValue }}"
                                                    @selected ((string) $value === (string) $optValue)
                                                    >{{ $optLabel }}
                                                </option>
                                            @endforeach
                                        </select>
                                    @elseif (isset($dateFields) && in_array($field, $dateFields, true))
                                        <input
                                            type="date"
                                            name="entries[{{ $i }}][{{ $field }}]"
                                            value="{{ $value ? \Illuminate\Support\Carbon::parse($value)->toDateString() : '' }}"
                                            class="form-control form-control-sm"
                                        />
                                    @else
                                        <input
                                            name="entries[{{ $i }}][{{ $field }}]"
                                            value="{{ $value }}"
                                            class="form-control form-control-sm"
                                        />
                                    @endif
                                </td>
                            @endforeach
                            @if ($isEditable)
                                <td class="text-center pds-repeatable-table__actions-cell">
                                    @if ($i === 0)
                                        <span class="pds-row-action-placeholder" aria-hidden="true"></span>
                                    @else
                                        <x-ui.button
                                            type="delete"
                                            size="sm"
                                            class="pds-repeatable-remove"
                                            data-pds-repeatable-remove="1"
                                            aria-label="Remove row"
                                            title="Remove row"
                                        />
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>
        @if ($isEditable)
            <template data-pds-repeatable-template="1">
                <tr data-pds-repeatable-row="1">
                    @foreach ($fields as $field)
                        <td>
                            @if ($loop->first)
                                <input
                                    type="hidden"
                                    name="entries[__INDEX__][id]"
                                    value=""
                                />
                            @endif
                            @if (isset($selects[$field]))
                                <select
                                    name="entries[__INDEX__][{{ $field }}]"
                                    class="form-control form-control-sm"
                                >
                                    <option value="">-</option>
                                    @foreach ($selects[$field] as $optValue => $optLabel)
                                        <option value="{{ $optValue }}">{{ $optLabel }}</option>
                                    @endforeach
                                </select>
                            @elseif (in_array($field, $dateFields, true))
                                <input
                                    type="date"
                                    name="entries[__INDEX__][{{ $field }}]"
                                    value=""
                                    class="form-control form-control-sm"
                                />
                            @else
                                <input
                                    name="entries[__INDEX__][{{ $field }}]"
                                    value=""
                                    class="form-control form-control-sm"
                                />
                            @endif
                        </td>
                    @endforeach
                    <td class="text-center pds-repeatable-table__actions-cell">
                        <button
                            type="button"
                            class="btn hrms-btn btn-sm btn-danger crud-btn-delete action-btn pds-repeatable-remove"
                            data-pds-repeatable-remove="1"
                            aria-label="Remove row"
                            title="Remove row"
                        >
                            <i class="cil-trash hrms-btn__icon" aria-hidden="true"></i>
                        </button>
                    </td>
                </tr>
            </template>
            <div class="mt-2 pds-repeatable-actions">
                <x-ui.button
                    type="button"
                    variant="outline-primary"
                    size="sm"
                    icon="cil-plus"
                    class="pds-repeatable-add"
                    data-pds-repeatable-add="1"
                    aria-label="Add row"
                    title="Add row"
                >
                    Add Field
                </x-ui.button>
            </div>
        @endif
    </fieldset>
    @if ($isEditable)
        <div class="pds-section-actions">
            <small
                class="text-muted mr-3 pds-autosave-status"
                data-pds-autosave-status="1"
            >
                Autosave ready.
            </small>
        </div>
    @endif
</form>
