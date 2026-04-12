@extends ('layouts.admin')

@section ('content')
    <div class="container-fluid" id="pdsIndexPage">
        <x-page-header
            eyebrow="Records Management"
            title="Personal Data Sheets"
            subtitle="Review and maintain structured PDS records for employees."
        />

        <x-ui.table-card
            title="PDS Directory"
            class="hrms-list-card"
        >
            <x-slot:controls>
                @php $showPdsAdvancedFilters = filled($status ?? ''); @endphp
                <x-ui.table-toolbar
                    method="GET"
                    :action="route('pds.index')"
                    class="pds-index-toolbar pds-index-toolbar--surface"
                >
                    <div class="pds-toolbar-shell">
                        <div class="pds-toolbar-primary">
                            <div
                                class="pds-index-toolbar__field pds-index-toolbar__field--search ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--search"
                            >
                                <label class="form-label" for="pds_search"
                                    >Search</label
                                >
                                <input
                                    id="pds_search"
                                    type="text"
                                    name="search"
                                    value="{{ $search }}"
                                    class="form-control"
                                    placeholder="Name or employee ID"
                                />
                            </div>

                            @can ('manage-pds')
                                <div class="pds-toolbar-toggle-wrap">
                                    <label class="form-label pds-toolbar-toggle-label" for="pdsToolbarFiltersToggle"
                                        >Filters</label
                                    >
                                    <x-ui.button
                                        type="button"
                                        :variant="$showPdsAdvancedFilters ? 'primary' : 'outline-secondary'"
                                        size="sm"
                                        icon="cil-filter"
                                        id="pdsToolbarFiltersToggle"
                                        class="pds-toolbar-toggle"
                                        data-coreui-toggle="collapse"
                                        data-coreui-target="#pdsToolbarFiltersCollapse"
                                        aria-expanded="{{ $showPdsAdvancedFilters ? 'true' : 'false' }}"
                                        aria-controls="pdsToolbarFiltersCollapse"
                                    >
                                        Filters
                                    </x-ui.button>
                                </div>
                            @endcan
                        </div>

                        <div id="pdsToolbarFiltersCollapse" class="pds-toolbar-panel collapse {{ $showPdsAdvancedFilters ? 'show' : '' }}">
                            <div class="pds-toolbar-panel__body">
                                @can ('manage-pds')
                                    <div
                                        class="pds-index-toolbar__field pds-index-toolbar__field--select ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--filter"
                                    >
                                        <label class="form-label" for="pds_status"
                                            >Status</label
                                        >
                                        <select
                                            id="pds_status"
                                            name="status"
                                            class="form-control select2bs4"
                                            data-toolbar-select2="1"
                                            data-placeholder="All statuses"
                                            data-allow-clear="1"
                                        >
                                            <option value=""></option>
                                            <option
                                                value="draft"
                                                @selected (($status ?? '') === 'draft')
                                                >Draft
                                            </option>
                                            <option
                                                value="submitted"
                                                @selected (($status ?? '') === 'submitted')
                                                >Submitted
                                            </option>
                                            <option
                                                value="needs_correction"
                                                @selected (($status ?? '') === 'needs_correction')
                                                >Needs Correction
                                            </option>
                                            <option
                                                value="verified"
                                                @selected (($status ?? '') === 'verified')
                                                >Verified
                                            </option>
                                        </select>
                                    </div>
                                @endcan

                                <div
                                    class="pds-index-toolbar__field pds-index-toolbar__field--action ui-toolbar__field ui-toolbar__field--action ui-table-toolbar-field ui-table-toolbar-field--action"
                                >
                                    <x-ui.button type="submit" variant="primary" size="md">
                                        Apply
                                    </x-ui.button>
                                </div>
                            </div>
                        </div>
                    </div>
                </x-ui.table-toolbar>
            </x-slot:controls>

            <table
                class="table table-hover align-middle mb-0 hrms-list-table hrms-table"
            >
                <thead class="bg-light text-uppercase small font-weight-bold">
                    <tr>
                        <th class="pl-4 py-3">Employee</th>
                        <th class="py-3">Department</th>
                        <th class="py-3">Status</th>
                        <th class="py-3">Last Update</th>
                        <th class="py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($employees as $employee)
                        @php
                        $profile = $employee->pdsProfile;
                    @endphp
                        <tr>
                            <td class="pl-4">
                                <div class="font-weight-bold">
                                    {{ trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')) }}
                                </div>
                                <small class="text-muted"
                                    >#{{ $employee->employee_id }}</small
                                >
                            </td>
                            <td>
                                {{ $employee->department?->department ?? 'N/A' }}
                            </td>
                            <td>
                                @php
                                $profileStatus = $profile?->status ?? 'draft';
                            @endphp
                                <x-ui.status-badge
                                    class="text-uppercase"
                                    :status="$profileStatus"
                                    :text="str_replace('_', ' ', $profileStatus)"
                                    :variant="$profileStatus === 'verified' ? 'success' : 'warning'"
                                />
                            </td>
                            <td>
                                {{ optional($profile?->updated_at)->format('M d, Y h:i A') ?? 'N/A' }}
                            </td>
                            <td class="text-center">
                                <div
                                    class="crud-actions justify-content-center"
                                >
                                    <x-ui.button
                                        type="view"
                                        size="sm"
                                        href="{{ route('pds.show', $employee) }}"
                                        aria-label="Open PDS"
                                        title="Open PDS"
                                    />
                                    <x-ui.button
                                        type="view"
                                        size="sm"
                                        icon="cil-print"
                                        href="{{ route('pds.print', $employee) }}"
                                        target="_blank"
                                        rel="noopener"
                                        aria-label="Print PDS"
                                        title="Print PDS"
                                    />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-5">
                                No employee records found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <x-slot:footer>
                {{ $employees->links() }}
            </x-slot:footer>
        </x-ui.table-card>
    </div>
@endsection
