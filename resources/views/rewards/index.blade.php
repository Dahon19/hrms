@extends ('layouts.admin')

@section ('content')
    <div
        class="container-fluid"
        id="rewardsIndexPage"
    >
        <x-page-header
            eyebrow="Recognition & Rewards"
            title="Recognition Records"
            subtitle="Review assigned recognition records. New recognition assignments and award title setup are managed from the Eligibility Dashboard."
        >
        </x-page-header>

        @can ('manage-rewards')
            <div class="row mb-3">
                <div class="col-md-3">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <small class="text-muted text-uppercase"
                                >Eligible Employees</small
                            >
                            <div class="h4 font-weight-bold mb-0">
                                {{ (int) ($summary['eligible_employees'] ?? 0) }}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <small class="text-muted text-uppercase"
                                >Total Rewards Issued</small
                            >
                            <div class="h4 font-weight-bold mb-0">
                                {{ (int) ($summary['total_rewards'] ?? 0) }}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <small class="text-muted text-uppercase"
                                >Attendance-Based</small
                            >
                            <div class="h4 font-weight-bold mb-0">
                                {{ (int) (collect($summary['by_type'] ?? [])->firstWhere('award_type', 'attendance')->total ?? 0) }}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <small class="text-muted text-uppercase"
                                >Performance-Based</small
                            >
                            <div class="h4 font-weight-bold mb-0">
                                {{ (int) (collect($summary['by_type'] ?? [])->firstWhere('award_type', 'performance')->total ?? 0) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-lg-6 mb-3 mb-lg-0">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white border-0 pb-0">
                            <h6 class="mb-0 font-weight-bold text-dark">
                                Rewards Issued per Department
                            </h6>
                        </div>
                        <div class="card-body pt-2">
                            @forelse (($summary['by_department'] ?? collect()) as $row)
                                <div
                                    class="d-flex justify-content-between border-bottom py-1"
                                >
                                    <span>{{ $row->department_name }}</span>
                                    <strong>{{ (int) $row->total }}</strong>
                                </div>
                            @empty
                                <small class="rewards-record__meta"
                                    >No reward records yet.</small
                                >
                            @endforelse
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white border-0 pb-0">
                            <h6 class="mb-0 font-weight-bold text-dark">
                                Milestone Distribution
                            </h6>
                        </div>
                        <div class="card-body pt-2">
                            @forelse (($summary['by_milestone'] ?? collect()) as $row)
                                <div
                                    class="d-flex justify-content-between border-bottom py-1"
                                >
                                    <span
                                        >{{ str_replace('_', ' ', ucfirst((string) $row->milestone_type)) }}</span
                                    >
                                    <strong>{{ (int) $row->total }}</strong>
                                </div>
                            @empty
                                <small class="rewards-record__meta"
                                    >No milestone records yet.</small
                                >
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        @endcan

        <x-ui.table-card
            title="Recognition Log"
            subtitle="Assigned recognition records."
            class="hrms-list-card"
        >
            <x-slot:controls>
                @php $showRewardsAdvancedFilters = filled($type ?? ''); @endphp
                <x-ui.table-toolbar
                    method="GET"
                    :action="route('rewards.index')"
                    class="rewards-filter-form rewards-index-toolbar mb-0"
                >
                    <div class="rewards-toolbar-shell">
                        <div class="rewards-toolbar-primary">
                            <div
                                class="ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--search rewards-toolbar-field rewards-toolbar-field--search"
                            >
                                <label class="form-label" for="rewardsSearchField"
                                    >Search</label
                                >
                                <input
                                    id="rewardsSearchField"
                                    type="search"
                                    name="search"
                                    value="{{ $search }}"
                                    class="form-control form-control-sm"
                                    placeholder="Employee or award"
                                />
                            </div>
                            <div class="rewards-toolbar-toggle-wrap">
                                <label class="form-label rewards-toolbar-toggle-label" for="rewardsToolbarFiltersToggle"
                                    >Filters</label
                                >
                                <x-ui.button
                                    type="button"
                                    :variant="$showRewardsAdvancedFilters ? 'primary' : 'outline-secondary'"
                                    size="sm"
                                    icon="cil-filter"
                                    id="rewardsToolbarFiltersToggle"
                                    class="rewards-toolbar-toggle"
                                    data-coreui-toggle="collapse"
                                    data-coreui-target="#rewardsToolbarFiltersCollapse"
                                    aria-expanded="{{ $showRewardsAdvancedFilters ? 'true' : 'false' }}"
                                    aria-controls="rewardsToolbarFiltersCollapse"
                                >
                                    Filters
                                </x-ui.button>
                            </div>
                        </div>

                        <div id="rewardsToolbarFiltersCollapse" class="rewards-toolbar-panel collapse {{ $showRewardsAdvancedFilters ? 'show' : '' }}">
                            <div class="rewards-toolbar-grid">
                                <div
                                    class="ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--filter rewards-toolbar-field"
                                >
                                    <label class="form-label" for="rewardsTypeFilter"
                                        >Type</label
                                    >
                                    <select
                                        name="type"
                                        id="rewardsTypeFilter"
                                        class="form-control form-control-sm rewards-type-select select2bs4"
                                        data-toolbar-select2="1"
                                        data-placeholder="All types"
                                        data-allow-clear="1"
                                    >
                                        <option value=""></option>
                                        <option
                                            value="tenure"
                                            {{ $type === 'tenure' ? 'selected' : '' }}
                                            >Tenure
                                        </option>
                                        <option
                                            value="attendance"
                                            {{ $type === 'attendance' ? 'selected' : '' }}
                                            >Attendance
                                        </option>
                                        <option
                                            value="performance"
                                            {{ $type === 'performance' ? 'selected' : '' }}
                                            >Performance
                                        </option>
                                        <option
                                            value="special"
                                            {{ $type === 'special' ? 'selected' : '' }}
                                            >Special
                                        </option>
                                    </select>
                                </div>
                                <div
                                    class="ui-toolbar__field ui-toolbar__field--action ui-table-toolbar-field ui-table-toolbar-field--action rewards-toolbar-field rewards-toolbar-field--action"
                                >
                                    <x-ui.button type="submit" variant="primary" size="sm">
                                        Apply
                                    </x-ui.button>
                                </div>
                            </div>
                        </div>
                    </div>
                </x-ui.table-toolbar>
            </x-slot:controls>
            <table class="table hrms-table" data-dt-search="0">
                <thead class="bg-light text-uppercase small font-weight-bold">
                    <tr>
                        <th class="ps-4 py-3">Employee</th>
                        <th>Award</th>
                        <th class="text-center">Type</th>
                        <th class="text-center">Date</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        @php
                            $badgeVariant = match ($record->award_type) {
                                'tenure' => 'primary',
                                'attendance' => 'success',
                                'performance' => 'info',
                                'special' => 'warning',
                                default => 'secondary',
                            };
                            $employee = $record->employee;
                        @endphp
                        <tr
                            class="rewards-record-row"
                            data-search="{{ trim(collect([trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')), $record->award_title])->filter()->implode(' | ')) }}"
                        >
                            <td class="ps-4">
                                <div class="rewards-record__employee">
                                    {{ trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')) }}
                                </div>
                                <small class="rewards-record__meta">
                                    #{{ $employee->employee_id ?? 'N/A' }} /
                                    {{ $employee->department?->department ?? 'No Department' }}
                                </small>
                            </td>
                            <td>
                                <div class="rewards-record__title">
                                    {{ $record->award_title }}
                                </div>
                                <small class="rewards-record__remarks">
                                    {{ $record->remarks ?: 'No remarks provided.' }}
                                </small>
                            </td>
                            <td class="text-center rewards-record__type-cell">
                                <x-ui.status-badge
                                    class="text-uppercase px-3 py-2"
                                    :status="$record->award_type"
                                    :text="str_replace('_', ' ', $record->award_type)"
                                    :variant="$badgeVariant"
                                />
                            </td>
                            <td class="text-center rewards-record__date-cell">
                                <span class="rewards-record__date">
                                    {{ optional($record->award_date)->format('M d, Y') ?: 'No date' }}
                                </span>
                            </td>
                            <td class="text-center rewards-record__actions">
                                <div
                                    class="crud-actions justify-content-center"
                                >
                                    <x-ui.button
                                        type="view"
                                        size="sm"
                                        href="{{ route('rewards.show', $employee) }}"
                                        aria-label="View Recognition Record"
                                        title="View Recognition Record"
                                    />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <div class="rewards-empty-state">
                                    <i class="cil-star"></i>
                                    <h6>No recognition records yet</h6>
                                    <p class="mb-0">Assigned awards will appear here after recognition is recorded.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </x-ui.table-card>
    </div>
@endsection
