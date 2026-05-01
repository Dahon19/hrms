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
                @can('manage-attendance')
                    <x-ui.button
                        variant="primary"
                        size="sm"
                        class="px-3"
                        data-coreui-toggle="modal"
                        data-coreui-target="#attendancePolicySettingsModalV2"
                    >
                        <i class="cil-settings mr-1"></i> Settings
                    </x-ui.button>
                @endcan
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
                                        @if ($attendanceSetting->require_four_taps)
                                            <th>Morning In</th>
                                            <th>Morning Out</th>
                                            <th>Afternoon In</th>
                                            <th>Afternoon Out</th>
                                        @else
                                            <th>Time In</th>
                                            <th>Time Out</th>
                                        @endif
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
                                            @if ($attendanceSetting->require_four_taps)
                                                <td class="align-middle font-weight-bold text-success">{{ $morningIn }}</td>
                                                <td class="align-middle font-weight-bold text-danger">{{ $morningOut }}</td>
                                                <td class="align-middle font-weight-bold text-success">{{ $afternoonIn }}</td>
                                                <td class="align-middle font-weight-bold text-danger">{{ $afternoonOut }}</td>
                                            @else
                                                <td class="align-middle font-weight-bold text-success">{{ $morningIn }}</td>
                                                <td class="align-middle font-weight-bold text-danger">{{ $afternoonOut }}</td>
                                            @endif
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
    @can('manage-attendance')
        <x-ui.modal
            id="attendancePolicySettingsModalV2"
            size="md"
        >
            <x-ui.modal-header
                title="Attendance Policy Settings"
                subtitle="Configure the shift times, breaks, and grace periods."
            />
            <form method="POST" action="{{ route('attendance.settings.update') }}">
                @csrf
                @method('PUT')
                <div class="modal-body p-4">
                    <h6 class="text-uppercase text-muted font-weight-bold mb-3 small">
                        <i class="cil-clock mr-1"></i> Shift Schedule
                    </h6>
                    <div class="row g-3 bg-light rounded p-3 mb-4 mx-0 border">
                        <div class="col-md-6 mb-3">
                            <label class="form-label font-weight-bold" for="setting_shift_start">Shift Start</label>
                            <input
                                id="setting_shift_start"
                                type="time"
                                name="shift_start"
                                value="{{ old('shift_start', \Carbon\Carbon::parse($attendanceSetting->shift_start)->format('H:i')) }}"
                                class="form-control @error('shift_start') is-invalid @enderror"
                                required
                            />
                            @error ('shift_start')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label font-weight-bold" for="setting_shift_end">Shift End</label>
                            <input
                                id="setting_shift_end"
                                type="time"
                                name="shift_end"
                                value="{{ old('shift_end', \Carbon\Carbon::parse($attendanceSetting->shift_end)->format('H:i')) }}"
                                class="form-control @error('shift_end') is-invalid @enderror"
                                required
                            />
                            @error ('shift_end')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label class="form-label font-weight-bold" for="setting_break_start">Break Start</label>
                            <input
                                id="setting_break_start"
                                type="time"
                                name="break_start"
                                value="{{ old('break_start', \Carbon\Carbon::parse($attendanceSetting->break_start)->format('H:i')) }}"
                                class="form-control @error('break_start') is-invalid @enderror"
                                required
                            />
                            @error ('break_start')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-0">
                            <label class="form-label font-weight-bold" for="setting_break_end">Break End</label>
                            <input
                                id="setting_break_end"
                                type="time"
                                name="break_end"
                                value="{{ old('break_end', \Carbon\Carbon::parse($attendanceSetting->break_end)->format('H:i')) }}"
                                class="form-control @error('break_end') is-invalid @enderror"
                                required
                            />
                            @error ('break_end')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <h6 class="text-uppercase text-muted font-weight-bold mb-3 small mt-2">
                        <i class="cil-shield-alt mr-1"></i> Policy Rules
                    </h6>
                    <div class="row g-3 bg-light rounded p-3 mb-4 mx-0 border">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label class="form-label font-weight-bold" for="setting_grace_minutes">Grace Period</label>
                            <div class="input-group">
                                <input
                                    id="setting_grace_minutes"
                                    type="number"
                                    name="grace_minutes"
                                    value="{{ old('grace_minutes', $attendanceSetting->grace_minutes) }}"
                                    class="form-control @error('grace_minutes') is-invalid @enderror"
                                    min="0"
                                    required
                                />
                                <div class="input-group-append">
                                    <span class="input-group-text bg-white">mins</span>
                                </div>
                            </div>
                            <div class="form-text small text-muted">Minutes allowed before late.</div>
                            @error ('grace_minutes')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-0">
                            <label class="form-label font-weight-bold" for="setting_overtime_threshold_minutes">Overtime Threshold</label>
                            <div class="input-group">
                                <input
                                    id="setting_overtime_threshold_minutes"
                                    type="number"
                                    name="overtime_threshold_minutes"
                                    value="{{ old('overtime_threshold_minutes', $attendanceSetting->overtime_threshold_minutes) }}"
                                    class="form-control @error('overtime_threshold_minutes') is-invalid @enderror"
                                    min="0"
                                    required
                                />
                                <div class="input-group-append">
                                    <span class="input-group-text bg-white">mins</span>
                                </div>
                            </div>
                            <div class="form-text small text-muted">Minimum excess time for OT.</div>
                            @error ('overtime_threshold_minutes')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <h6 class="text-uppercase text-muted font-weight-bold mb-3 small mt-2">
                        <i class="cil-options mr-1"></i> Mode & Features
                    </h6>
                    <div class="row g-3 mx-0">
                        <div class="col-12 mb-3">
                            <div class="form-check custom-control custom-switch custom-control-lg">
                                <input
                                    class="custom-control-input"
                                    type="checkbox"
                                    value="1"
                                    id="setting_require_four_taps"
                                    name="require_four_taps"
                                    @checked(old('require_four_taps', $attendanceSetting->require_four_taps))
                                />
                                <label class="custom-control-label font-weight-bold" style="padding-top:2px;" for="setting_require_four_taps">
                                    Require 4-Tap System
                                </label>
                                <div class="text-muted small mt-1">If enabled, employees must tap in and out for morning AND afternoon. If disabled, employees only tap once for Time In and once for Time Out (2-tap mode).</div>
                            </div>
                        </div>
                        <div class="col-12 mb-2">
                            <div class="form-check custom-control custom-switch custom-control-lg">
                                <input
                                    class="custom-control-input"
                                    type="checkbox"
                                    value="1"
                                    id="setting_weekend_overtime"
                                    name="weekend_overtime"
                                    @checked(old('weekend_overtime', $attendanceSetting->weekend_overtime))
                                />
                                <label class="custom-control-label font-weight-bold" style="padding-top:2px;" for="setting_weekend_overtime">
                                    Track Weekend Overtime
                                </label>
                                <div class="text-muted small mt-1">Automatically compute weekend records as overtime hours.</div>
                            </div>
                        </div>
                    </div>
                </div>
                <x-ui.modal-footer>
                    <x-ui.button type="button" variant="light" data-coreui-dismiss="modal">
                        Cancel
                    </x-ui.button>
                    <x-ui.button type="submit" variant="primary" icon="cil-save">
                        Save Settings
                    </x-ui.button>
                </x-ui.modal-footer>
            </form>
        </x-ui.modal>
    @endcan
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
    {{-- Removed conditional modal show to diagnose persistence issue --}}
    @if (session('success'))
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                if (typeof window.showToast === 'function') {
                    window.showToast('success', '{{ session('success') }}');
                }
            });
        </script>
    @endif
@endpush
