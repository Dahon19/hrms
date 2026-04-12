@extends ('layouts.admin')

@section ('content')
    @php
        $historyUser = auth()->user();
        $showHistoryEmployeeFilters = $historyUser && \App\Services\AccessControl::canAccessDashboard($historyUser);
        $showHistoryEmployeeIdentity = $showHistoryEmployeeFilters;
    @endphp
    <div class="container-fluid pt-3">
        <x-attendance-hero eyebrow="Attendance Records">
            <x-slot:actions>
                <x-ui.button
                    type="print"
                    variant="outline-light"
                    size="sm"
                    class="px-3 report-print-btn"
                    :href="route('attendance.history.print', request()->query())"
                    target="_blank"
                    rel="noopener"
                >
                    Print
                </x-ui.button>
            </x-slot:actions>
        </x-attendance-hero>
        <div class="col-12 px-0">
            <x-ui.table-card
                title="Attendance Records"
                :responsive="false"
                class="hrms-list-card"
            >
                <x-slot:controls>
                    <x-ui.table-toolbar
                        method="GET"
                        :action="route('attendance.history')"
                        class="attendance-filter-form attendance-filter-form--history"
                    >
                            <div
                                class="attendance-filter-field ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--filter"
                            >
                                <label class="attendance-filter-label"
                                    >Period</label
                                >
                                <select
                                    name="period"
                                    id="historyPeriod"
                                    class="form-control form-control-sm"
                                >
                                    <option
                                        value="weekly"
                                        {{ ($filters['period'] ?? 'weekly') === 'weekly' ? 'selected' : '' }}
                                        >Weekly
                                    </option>
                                    <option
                                        value="monthly"
                                        {{ ($filters['period'] ?? '') === 'monthly' ? 'selected' : '' }}
                                        >Monthly
                                    </option>
                                </select>
                            </div>
                            <div
                                class="attendance-filter-field period-field period-date ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--date"
                            >
                                <label class="attendance-filter-label"
                                    >Week Of</label
                                >
                                <input
                                    type="date"
                                    name="date"
                                    class="form-control form-control-sm"
                                    value="{{ $filters['date'] ?? now()->toDateString() }}"
                                />
                            </div>
                            <div
                                class="attendance-filter-field period-field period-month d-none ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--date"
                            >
                                <label class="attendance-filter-label"
                                    >Month</label
                                >
                                <input
                                    type="month"
                                    name="month"
                                    class="form-control form-control-sm"
                                    value="{{ $filters['month'] ?? now()->format('Y-m') }}"
                                />
                            </div>
                            @if ($showHistoryEmployeeFilters)
                                <div
                                    class="attendance-filter-field attendance-filter-field--search ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--search"
                                >
                                    <label class="attendance-filter-label"
                                        >Employee</label
                                    >
                                    <input
                                        type="search"
                                        name="search"
                                        class="form-control form-control-sm"
                                        value="{{ $search ?? '' }}"
                                        placeholder="ID or name"
                                    />
                                </div>
                                <div
                                    class="attendance-filter-field ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--filter"
                                >
                                    <label class="attendance-filter-label"
                                        >Department</label
                                    >
                                    <select
                                        name="department_id"
                                        class="form-control form-control-sm select2bs4"
                                        data-placeholder="All departments"
                                        data-allow-clear="1"
                                    >
                                        <option value="">All departments</option>
                                        @foreach (($departmentOptions ?? collect()) as $departmentOption)
                                            <option
                                                value="{{ $departmentOption->id }}"
                                                {{ (int) ($selectedDepartmentId ?? 0) === (int) $departmentOption->id ? 'selected' : '' }}
                                            >
                                                {{ $departmentOption->department }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            <div
                                class="attendance-filter-field attendance-filter-field--action d-grid ui-toolbar__field ui-toolbar__field--action ui-table-toolbar-field ui-table-toolbar-field--action"
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
                    </x-ui.table-toolbar>
                </x-slot:controls>

                <div class="attendance-report-body">
                    <div class="small text-muted mb-2">
                        Showing records for:
                        <strong
                            >{{ $filters['label'] ?? now()->format('F j, Y') }}</strong
                        >
                    </div>

                    <div class="attendance-summary-grid mb-3">
                        @if ($showHistoryEmployeeIdentity)
                            <div class="attendance-summary-card">
                                <div class="border rounded bg-white p-2 h-100">
                                    <div class="small text-muted text-uppercase">
                                        Employees
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold">
                                        {{ $totals['employees'] ?? 0 }}
                                    </div>
                                </div>
                            </div>
                        @endif
                        <div class="attendance-summary-card">
                            <div class="border rounded bg-white p-2 h-100">
                                <div class="small text-muted text-uppercase">
                                    Present Days
                                </div>
                                <div
                                    class="h5 mb-0 font-weight-bold text-success"
                                >
                                    {{ $totals['present_days'] ?? 0 }}
                                </div>
                            </div>
                        </div>
                        <div class="attendance-summary-card">
                            <div class="border rounded bg-white p-2 h-100">
                                <div class="small text-muted text-uppercase">
                                    Absent Days
                                </div>
                                <div
                                    class="h5 mb-0 font-weight-bold text-danger"
                                >
                                    {{ $totals['absent_days'] ?? 0 }}
                                </div>
                            </div>
                        </div>
                        <div class="attendance-summary-card">
                            <div class="border rounded bg-white p-2 h-100">
                                <div class="small text-muted text-uppercase">
                                    Late Days
                                </div>
                                <div
                                    class="h5 mb-0 font-weight-bold text-warning"
                                >
                                    {{ $totals['late_days'] ?? 0 }}
                                </div>
                            </div>
                        </div>
                        <div class="attendance-summary-card">
                            <div class="border rounded bg-white p-2 h-100">
                                <div class="small text-muted text-uppercase">
                                    Official Business
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-info">
                                    {{ $totals['official_business_days'] ?? 0 }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="attendance-glass-stage">
                        <div class="table-wrapper glass-table-wrap">
                            <table
                                class="table table-hover align-middle mb-0 hrms-list-table w-100 hrms-table"
                                data-no-datatable="1"
                            >
                                <thead>
                                    <tr>
                                        @if ($showHistoryEmployeeIdentity)
                                            <th class="text-left">Employee</th>
                                        @endif
                                        <th>Morning</th>
                                        <th>Afternoon</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($attendance as $att)
                                        @php
                                $eml = $att->employee;
                                $morningIn = $att->morning_time_in ? \Carbon\Carbon::parse($att->morning_time_in)->format('g:i A') : '--';
                                $morningOut = $att->morning_time_out ? \Carbon\Carbon::parse($att->morning_time_out)->format('g:i A') : '--';
                                $afternoonIn = $att->afternoon_time_in ? \Carbon\Carbon::parse($att->afternoon_time_in)->format('g:i A') : '--';
                                $afternoonOut = $att->afternoon_time_out ? \Carbon\Carbon::parse($att->afternoon_time_out)->format('g:i A') : '--';
                                $statusVariant = match ($att->status) {
                                    'present' => 'success',
                                    'late' => 'warning',
                                    'official_business' => 'info',
                                    'excused' => 'primary',
                                    'holiday' => 'danger',
                                    default => 'secondary',
                                };
                                $statusLabel = match ($att->status) {
                                    'official_business' => 'Official Business',
                                    'excused' => 'Excused',
                                    'holiday' => 'Holiday',
                                    default => ucfirst($att->status),
                                };
                            @endphp
                                        @php
                                            $employeeName = trim(($eml->first_name ?? 'Unknown') . ' ' . ($eml->last_name ?? ''));
                                            $dateLabel = \Carbon\Carbon::parse($att->date)->format('M d, Y');
                                        @endphp
                                        <tr
                                            class="text-center"
                                        >
                                            @if ($showHistoryEmployeeIdentity)
                                                <td class="text-left align-middle">
                                                    <strong>{{ $employeeName }}</strong>
                                                    <div class="text-muted small">
                                                        #{{ $eml->employee_id ?? 'N/A' }}
                                                    </div>
                                                </td>
                                            @endif
                                            <td
                                                class="align-middle font-weight-bold"
                                            >
                                                {{ $morningIn }} - {{ $morningOut }}
                                            </td>
                                            <td
                                                class="align-middle font-weight-bold"
                                            >
                                                {{ $afternoonIn }} - {{ $afternoonOut }}
                                            </td>
                                            <td class="align-middle">
                                                <x-ui.status-badge
                                                    class="px-3 py-2"
                                                    :status="$att->status"
                                                    :text="$statusLabel"
                                                    :variant="$statusVariant"
                                                />
                                            </td>
                                            <td class="align-middle">
                                                {{ $dateLabel }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr class="text-center text-muted">
                                            <td colspan="{{ $showHistoryEmployeeIdentity ? 5 : 4 }}" class="py-4">
                                                No attendance records found for
                                                the selected filter.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <x-slot:footer>
                    {{ $attendance->links() }}
                </x-slot:footer>
            </x-ui.table-card>
        </div>
    </div>
@endsection

@push ('scripts')
    <script>
        (function () {
            const periodSelect = document.getElementById("historyPeriod");
            const filterForm = document.querySelector(".attendance-filter-form--history");
            if (periodSelect) {
                const dateInputs = Array.from(
                    document.querySelectorAll('input[name="date"]')
                );
                const monthInputs = Array.from(
                    document.querySelectorAll('input[name="month"]')
                );
                const setDisabled = (inputs, disabled) => {
                    inputs.forEach((input) => {
                        input.disabled = disabled;
                        input.toggleAttribute("aria-disabled", disabled);
                    });
                };

                const toggleFields = () => {
                    const period = periodSelect.value;
                    const show = (selector, visible) => {
                        document.querySelectorAll(selector).forEach((el) => {
                            el.classList.toggle("d-none", !visible);
                        });
                    };

                    show(".period-date", period === "weekly");
                    show(".period-month", period === "monthly");
                    setDisabled(dateInputs, period !== "weekly");
                    setDisabled(monthInputs, period !== "monthly");
                };

                periodSelect.addEventListener("change", toggleFields);
                if (filterForm) {
                    filterForm.addEventListener("submit", toggleFields);
                }
                toggleFields();
            }
        })();
    </script>
@endpush
