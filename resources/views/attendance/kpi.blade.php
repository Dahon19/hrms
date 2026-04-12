@extends ('layouts.admin')

@section ('content')
    @php
        $monthOptions = [
            1 => 'January',
            2 => 'February',
            3 => 'March',
            4 => 'April',
            5 => 'May',
            6 => 'June',
            7 => 'July',
            8 => 'August',
            9 => 'September',
            10 => 'October',
            11 => 'November',
            12 => 'December',
        ];
    @endphp
    <div class="container-fluid pt-4" id="attendanceKpiPage">
        <x-page-header
            eyebrow="Attendance KPI"
            title="Monthly Attendance Incentive Basis"
            subtitle="Automated monthly attendance scoring for SPMS and recognition eligibility."
        >
            <x-slot:actions>
                <x-ui.button
                    type="export"
                    variant="outline-light"
                    :href="route('attendance.kpi.export', ['month' => $month, 'year' => $year, 'department_id' => $selectedDepartmentId])"
                >
                    Export
                </x-ui.button>
                @if ($canManage)
                    <x-ui.button
                        variant="outline-light"
                        icon="cil-settings"
                        data-toggle="modal"
                        data-target="#attendanceKpiConfigModal"
                    >
                        Configure KPI
                    </x-ui.button>
                @endif
            </x-slot:actions>
        </x-page-header>

        <div class="row">
            <div class="col-md-3 mb-3">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <div class="text-muted text-uppercase small">
                            Outstanding (5)
                        </div>
                        <div class="h4 font-weight-bold mb-0">
                            {{ (int) ($summary['outstanding'] ?? 0) }}
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <div class="text-muted text-uppercase small">
                            Very Satisfactory (4)
                        </div>
                        <div class="h4 font-weight-bold mb-0">
                            {{ (int) ($summary['very_satisfactory'] ?? 0) }}
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <div class="text-muted text-uppercase small">
                            Eligible for Incentive
                        </div>
                        <div class="h4 font-weight-bold mb-0">
                            {{ (int) ($summary['eligible'] ?? 0) }}
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <div class="text-muted text-uppercase small">
                            Active KPI Target
                        </div>
                        <div class="h4 font-weight-bold mb-0">
                            {{ number_format((float) ($kpi?->target_percentage ?? 100), 2) }}%
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <x-ui.table-card
            title="KPI Score List"
            subtitle="Monthly attendance scoring records."
            class="border-0 hrms-list-card"
        >
            <x-slot:controls>
                <x-ui.table-toolbar
                    method="GET"
                    :action="route('attendance.kpi.index')"
                    class="attendance-kpi-toolbar"
                >
                    <div class="ui-toolbar__field ui-table-toolbar-field">
                        <label class="small text-muted mb-1 d-block"
                            >Month</label
                        >
                        <select
                            name="month"
                            class="form-control form-control-sm select2bs4"
                            data-toolbar-select2="1"
                        >
                            @foreach ($monthOptions as $monthValue => $monthLabel)
                                <option value="{{ $monthValue }}" @selected((int) $month === (int) $monthValue)>
                                    {{ $monthLabel }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ui-toolbar__field ui-table-toolbar-field">
                        <label class="small text-muted mb-1 d-block"
                            >Year</label
                        >
                        <input
                            type="number"
                            min="2000"
                            max="2100"
                            name="year"
                            class="form-control form-control-sm"
                            value="{{ $year }}"
                        />
                    </div>
                    <div class="ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--filter">
                        <label class="small text-muted mb-1 d-block"
                            >Department</label
                        >
                        <select
                            name="department_id"
                            class="form-control form-control-sm select2bs4"
                            data-toolbar-select2="1"
                            data-placeholder="All departments"
                            data-allow-clear="1"
                        >
                            <option value=""></option>
                            @foreach ($departments as $department)
                                <option
                                    value="{{ $department->id }}"
                                    {{ (int) $selectedDepartmentId === (int) $department->id ? 'selected' : '' }}
                                >
                                    {{ $department->department }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div
                        class="ui-toolbar__field ui-toolbar__field--action ui-table-toolbar-field ui-table-toolbar-field--action"
                    >
                        <x-ui.button
                            type="submit"
                            variant="primary"
                            size="sm"
                            icon="cil-filter"
                        >
                            Apply
                        </x-ui.button>
                    </div>
                    @if ($canManage)
                        <div
                            class="ui-toolbar__field ui-toolbar__field--action ui-table-toolbar-field ui-table-toolbar-field--action"
                        >
                            <form
                                method="POST"
                                action="{{ route('attendance.kpi.compute') }}"
                            >
                                @csrf
                                <input
                                    type="hidden"
                                    name="month"
                                    value="{{ $month }}"
                                />
                                <input
                                    type="hidden"
                                    name="year"
                                    value="{{ $year }}"
                                />
                                <x-ui.button
                                    type="submit"
                                    variant="btn-outline-info"
                                    size="sm"
                                    icon="cil-calculator"
                                >
                                    Compute
                                </x-ui.button>
                            </form>
                        </div>
                        <div
                            class="ui-toolbar__field ui-toolbar__field--action ui-table-toolbar-field ui-table-toolbar-field--action"
                        >
                            <form
                                method="POST"
                                action="{{ route('attendance.kpi.lock') }}"
                            >
                                @csrf
                                <input
                                    type="hidden"
                                    name="month"
                                    value="{{ $month }}"
                                />
                                <input
                                    type="hidden"
                                    name="year"
                                    value="{{ $year }}"
                                />
                                <x-ui.button
                                    type="submit"
                                    variant="btn-outline-dark"
                                    size="sm"
                                    icon="cil-lock-locked"
                                    data-confirm-message="Lock this month KPI scores?"
                                    data-confirm-title="Lock Attendance KPI"
                                    data-confirm-label="Lock"
                                    data-confirm-variant="warning"
                                >
                                    Lock
                                </x-ui.button>
                            </form>
                        </div>
                    @endif
                </x-ui.table-toolbar>
            </x-slot:controls>
            <div class="table-responsive hrms-table">
                <table
                    class="table table-hover mb-0 align-middle hrms-list-table hrms-table"
                >
                    <thead
                        class="bg-light text-uppercase small font-weight-bold"
                    >
                        <tr>
                            <th class="pl-4 py-3">Employee</th>
                            <th class="text-center">Presence</th>
                            <th class="text-center">On-Time</th>
                            <th class="text-center">Overall</th>
                            <th class="text-center">Rating</th>
                            <th class="text-center">Incentive</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($scores as $score)
                            @php
                                $rating = (int) $score->rating;
                                $badgeVariant = $rating >= 5 ? 'success' : ($rating >= 4 ? 'info' : ($rating >= 3 ? 'primary' : ($rating >= 2 ? 'warning' : 'secondary')));
                            @endphp
                            <tr>
                                <td class="pl-4">
                                    <div class="font-weight-bold text-dark">
                                        {{ trim(($score->employee?->first_name ?? '') . ' ' . ($score->employee?->last_name ?? '')) }}
                                    </div>
                                    <small class="text-muted"
                                        >#{{ $score->employee?->employee_id }} &middot; {{ $score->employee?->department?->department ?? 'No Department' }}</small
                                    >
                                </td>
                                <td class="text-center">
                                    {{ number_format((float) $score->attendance_rate, 2) }}
                                </td>
                                <td class="text-center">
                                    {{ number_format((float) $score->punctuality_rate, 2) }}
                                </td>
                                <td class="text-center">
                                    {{ number_format((float) $score->final_score, 2) }}
                                </td>
                                <td class="text-center">
                                    <x-ui.status-badge
                                        class="px-3 py-2"
                                        :status="$rating >= 5 ? 'success' : 'rated'"
                                        :text="$rating"
                                        :variant="$badgeVariant"
                                    />
                                </td>
                                <td class="text-center">
                                    @if ($score->attendance_incentive_eligible)
                                        <x-ui.status-badge
                                            class="px-3 py-2"
                                            status="eligible"
                                            text="Eligible"
                                            variant="success"
                                        />
                                    @else
                                        <x-ui.status-badge
                                            class="px-3 py-2"
                                            status="inactive"
                                            text="Not Eligible"
                                            variant="secondary"
                                        />
                                    @endif
                                </td>
                                <td class="text-center">
                                    <x-ui.status-badge
                                        class="px-3 py-2 text-uppercase"
                                        :status="$score->status"
                                        :text="$score->status"
                                        :variant="$score->status === 'locked' ? 'dark' : 'info'"
                                    />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td
                                    colspan="7"
                                    class="text-center py-4 text-muted"
                                >
                                    No attendance KPI scores found for this
                                    period.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.table-card>
    </div>
    @if ($canManage)
        <x-modal
            id="attendanceKpiConfigModal"
            title="Configure Monthly Attendance KPI"
            subtitle="Set the target percentage for the selected month and year."
        >
            <form
                method="POST"
                action="{{ route('attendance.kpi.store') }}"
            >
                @csrf
                <x-slot:body>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Month</label>
                            <select
                                class="form-control select2bs4"
                                name="month"
                                data-placeholder="Select month"
                                required
                            >
                                @foreach ($monthOptions as $monthValue => $monthLabel)
                                    <option value="{{ $monthValue }}" @selected((int) $month === (int) $monthValue)>
                                        {{ $monthLabel }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Year</label>
                            <input
                                type="number"
                                min="2000"
                                max="2100"
                                class="form-control"
                                name="year"
                                value="{{ $year }}"
                                required
                            />
                        </div>
                        <div class="form-group col-md-4">
                            <label>Target %</label>
                            <input
                                type="number"
                                min="1"
                                max="100"
                                step="0.01"
                                class="form-control"
                                name="target_percentage"
                                value="{{ number_format((float) ($kpi?->target_percentage ?? 100), 2, '.', '') }}"
                                required
                            />
                        </div>
                    </div>
                    <small class="text-muted"
                        >Only one KPI configuration can be active per
                        month.</small
                    >
                </x-slot:body>
                <x-slot:footer>
                    <x-ui.button variant="light" data-coreui-dismiss="modal">
                        Cancel
                    </x-ui.button>
                    <x-ui.button
                        type="submit"
                        variant="primary"
                        icon="cil-save"
                    >
                        Save KPI
                    </x-ui.button>
                </x-slot:footer>
            </form>
        </x-modal>
    @endif
@endsection
