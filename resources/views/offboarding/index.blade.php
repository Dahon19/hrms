@extends ('layouts.admin')
@section ('content')
    @php $statusOptions = [ \App\Models\OffboardingRecord::STATUS_DRAFT => 'Draft', \App\Models\OffboardingRecord::STATUS_SUBMITTED => 'Submitted', \App\Models\OffboardingRecord::STATUS_DEPARTMENT_REVIEW => 'Department Review', \App\Models\OffboardingRecord::STATUS_FINANCE_CLEARANCE => 'Finance Clearance', \App\Models\OffboardingRecord::STATUS_HR_FINALIZATION => 'HR Finalization', \App\Models\OffboardingRecord::STATUS_COMPLETED => 'Completed', \App\Models\OffboardingRecord::STATUS_ARCHIVED => 'Archived', ]; $ownerOptions = [ 'hr' => 'HR', 'department_head' => 'Department Head', 'finance' => 'Finance', ];
    $canCreateDraft = auth()->user()?->canManageOffboarding() ?? false;
@endphp
    <x-ui.hero
        title="Offboarding"
        :subtitle="$isEmployeeMonitor
            ? 'Monitor your current offboarding status and clearance progress.'
            : 'Track resignation workflow, physical clearance routing, and final account deactivation.'"
    >
        <x-slot:actions>
            @if ($canCreateDraft)
                <x-ui.button
                    variant="primary"
                    size="sm"
                    data-coreui-toggle="modal"
                    data-coreui-target="#offboardingCreateModal"
                    icon="cil-user-x"
                >
                    Create Draft
                </x-ui.button>
            @endif
            {{-- <span class="badge bg-light text-dark offboarding-hero-badge"
                >Records: {{ $records->total() }}</span
            > --}}
        </x-slot:actions>
    </x-ui.hero>
    <x-ui.table-card
        :title="$isEmployeeMonitor ? 'My Offboarding Status' : 'Workflow Directory'"
        :subtitle="$isEmployeeMonitor
            ? 'Review your current stage, last working day, and clearance progress.'
            : 'Search employees in the clearance pipeline and review the active stage for each record.'"
        class="offboarding-card"
    >
        @unless ($isEmployeeMonitor)
            <x-slot:controls>
                <x-ui.table-toolbar
                    method="GET"
                    :action="route('offboarding.index')"
                    class="offboarding-toolbar mb-0"
                >
                    <div
                        class="ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--search"
                        data-toolbar-label="Search"
                    >
                        <label class="form-label" for="offboarding_search">Search</label>
                        <input
                            type="text"
                            id="offboarding_search"
                            name="search"
                            value="{{ $search }}"
                            class="form-control form-control-sm"
                            placeholder="Employee ID or name"
                        />
                    </div>
                    <div class="ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--filter" data-toolbar-label="Current Stage">
                        <label class="form-label" for="offboarding_status">Current Stage</label>
                        <select
                            id="offboarding_status"
                            name="status"
                            class="form-control form-control-sm select2bs4"
                            data-toolbar-select2="1"
                            data-placeholder="All stages"
                            data-allow-clear="1"
                        >
                            <option value=""></option>
                            @foreach ($statusOptions as $value => $label)
                                <option
                                    value="{{ $value }}"
                                    @selected ($status === $value)
                                    >{{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--filter" data-toolbar-label="Owner">
                        <label class="form-label" for="offboarding_owner_role">Owner</label>
                        <select
                            id="offboarding_owner_role"
                            name="owner_role"
                            class="form-control form-control-sm select2bs4"
                            data-toolbar-select2="1"
                            data-placeholder="All owners"
                            data-allow-clear="1"
                        >
                            <option value=""></option>
                            @foreach ($ownerOptions as $value => $label)
                                <option
                                    value="{{ $value }}"
                                    @selected ($ownerRole === $value)
                                    >{{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div
                        class="ui-toolbar__field ui-toolbar__field--action ui-table-toolbar-field ui-table-toolbar-field--action"
                    >
                        <x-ui.button type="submit" variant="primary" size="sm">
                            Apply
                        </x-ui.button>
                    </div>
                </x-ui.table-toolbar>
            </x-slot:controls>
        @endunless
        <table
            class="table table-hover align-middle mb-1 hrms-table offboarding-table"
        >
            <thead class="bg-light">
                <tr class="text-uppercase text-muted small">
                    <th class="py-2">Employee</th>
                    <th class="py-2">Last Working Day</th>
                    <th class="py-2">Current Stage</th>
                    <th class="py-2">Clearance Progress</th>
                    <th class="py-2 text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    @php $employee = $record->employee; $employeeName = trim(($employee?->first_name ?? '') . ' ' . ($employee?->last_name ?? '')); $employeeMeta = implode(' / ', array_filter([ $employee?->employee_id ? '#'.$employee->employee_id : null, $employee?->department?->department, ])); $statusVariant = match ($record->status) { \App\Models\OffboardingRecord::STATUS_DRAFT => 'warning', \App\Models\OffboardingRecord::STATUS_SUBMITTED => 'info', \App\Models\OffboardingRecord::STATUS_DEPARTMENT_REVIEW => 'primary', \App\Models\OffboardingRecord::STATUS_FINANCE_CLEARANCE => 'info', \App\Models\OffboardingRecord::STATUS_HR_FINALIZATION => 'dark', \App\Models\OffboardingRecord::STATUS_COMPLETED => 'success', \App\Models\OffboardingRecord::STATUS_ARCHIVED => 'secondary', default => 'secondary', }; $lastWorkingDay = optional($record->display_last_working_day)->format('M d, Y') ?: 'Not set'; $resignationReason = $record->display_reason ?: 'No reason recorded'; @endphp
                    <tr>
                        <td class="ps-2">
                            <div class="offboarding-primary-text">
                                {{ $employeeName ?: 'Employee' }}
                            </div>
                            <div class="offboarding-secondary-text">
                                {{ $employeeMeta ?: 'No employee metadata available' }}
                            </div>
                        </td>
                        <td>
                            <div class="offboarding-primary-text">
                                {{ $lastWorkingDay }}
                            </div>
                            <div class="offboarding-secondary-text">
                                {{ $resignationReason }}
                            </div>
                        </td>
                        <td>
                            <x-ui.status-badge
                                :status="$record->status"
                                :text="$record->stage_label"
                                :variant="$statusVariant"
                            />
                        </td>
                        <td>
                            <div class="offboarding-progress-text">
                                <span class="text-success">
                                    <i
                                        class="cil-check-circle"
                                        aria-hidden="true"
                                    ></i
                                    >{{ $record->cleared_items_count }} Cleared
                                </span>
                                <span class="text-warning">
                                    <i
                                        class="cil-media-pause"
                                        aria-hidden="true"
                                    ></i
                                    >{{ $record->pending_items_count }} Pending
                                </span>
                                <span class="text-danger">
                                    <i
                                        class="cil-ban"
                                        aria-hidden="true"
                                    ></i
                                    >{{ $record->blocked_items_count }} Blocked
                                </span>
                            </div>
                        </td>
                        <td class="text-center">
                            <div class="crud-actions justify-content-center">
                                @if ($isEmployeeMonitor)
                                    <x-ui.button
                                        type="button"
                                        variant="outline-primary"
                                        size="sm"
                                        icon="cil-spreadsheet"
                                        data-coreui-toggle="modal"
                                        data-coreui-target="#offboardingStatusModal"
                                        data-record-employee="{{ $employeeName ?: 'Employee' }}"
                                        data-record-stage="{{ $record->stage_label }}"
                                        data-record-status-variant="{{ $statusVariant }}"
                                        data-record-status="{{ $record->status }}"
                                        data-record-last-working-day="{{ $lastWorkingDay }}"
                                        data-record-reason="{{ $record->display_reason ?: 'No reason recorded' }}"
                                        data-record-cleared="{{ $record->cleared_items_count }}"
                                        data-record-pending="{{ $record->pending_items_count }}"
                                        data-record-blocked="{{ $record->blocked_items_count }}"
                                        aria-label="View My Offboarding Status"
                                        title="View My Offboarding Status"
                                    >
                                        View Status
                                    </x-ui.button>
                                @else
                                    <x-ui.button
                                        type="view"
                                        size="sm"
                                        :href="route('offboarding.show', $record)"
                                        aria-label="View Offboarding Workflow"
                                        title="View Offboarding Workflow"
                                    />
                                    <x-ui.button
                                        type="view"
                                        size="sm"
                                        icon="cil-print"
                                        :href="route('offboarding.export', $record)"
                                        target="_blank"
                                        rel="noopener"
                                        aria-label="Print Clearance"
                                        title="Print Clearance"
                                    />
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-5">
                            <div class="offboarding-empty-state">
                                <div class="offboarding-empty-icon">
                                    <i
                                        class="cil-user-x"
                                        aria-hidden="true"
                                    ></i>
                                </div>
                                <div class="offboarding-empty-title">
                                    No Offboarding Records
                                </div>
                                <div class="offboarding-empty-text">
                                    No employees are currently in the
                                    resignation and clearance workflow.
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <x-slot:footer>
            {{ $records->links() }}
        </x-slot:footer>
    </x-ui.table-card>
    @if ($isEmployeeMonitor)
        <x-ui.modal id="offboardingStatusModal" size="md">
            <x-ui.modal-header
                title="Offboarding Status"
                subtitle="Monitor the current progress of your clearance process."
            />
            <div class="modal-body">
                <div class="offboarding-summary-grid mb-3">
                    <div class="offboarding-stat-card">
                        <div class="offboarding-stat-label">Employee</div>
                        <div class="offboarding-stat-value" id="offboardingStatusEmployee">-</div>
                    </div>
                    <div class="offboarding-stat-card">
                        <div class="offboarding-stat-label">Current Stage</div>
                        <div class="offboarding-stat-value">
                            <x-ui.status-badge
                                id="offboardingStatusBadge"
                                status="secondary"
                                text="Pending"
                                variant="secondary"
                            />
                        </div>
                    </div>
                    <div class="offboarding-stat-card">
                        <div class="offboarding-stat-label">Last Working Day</div>
                        <div class="offboarding-stat-value" id="offboardingStatusLastDay">-</div>
                    </div>
                    <div class="offboarding-stat-card">
                        <div class="offboarding-stat-label">Reason</div>
                        <div class="offboarding-stat-value" id="offboardingStatusReason">-</div>
                    </div>
                </div>
                <div class="offboarding-stat-card offboarding-stat-card--plain">
                    <div class="offboarding-stat-label">Clearance Progress</div>
                    <div class="offboarding-checklist-metrics mt-2">
                        <span class="text-success">
                            <i class="cil-check-circle mr-1" aria-hidden="true"></i>
                            <span id="offboardingStatusCleared">0</span> Cleared
                        </span>
                        <span class="text-warning">
                            <i class="cil-media-pause mr-1" aria-hidden="true"></i>
                            <span id="offboardingStatusPending">0</span> Pending
                        </span>
                        <span class="text-danger">
                            <i class="cil-ban mr-1" aria-hidden="true"></i>
                            <span id="offboardingStatusBlocked">0</span> Blocked
                        </span>
                    </div>
                </div>
            </div>
            <x-ui.modal-footer>
                <x-ui.button type="button" variant="light" data-coreui-dismiss="modal">
                    Close
                </x-ui.button>
            </x-ui.modal-footer>
        </x-ui.modal>
    @endif
    @if ($canCreateDraft)
        <x-modal
            id="offboardingCreateModal"
            title="Start Offboarding"
            subtitle="Issue a clearance workflow for the employee separation process."
            size="lg"
            content-class="employee-modal employee-modal--form offboarding-create-modal"
        >
            <form
                id="offboardingCreateForm"
                method="POST"
                action="{{ route('offboarding.store') }}"
                enctype="multipart/form-data"
                class="hrms-form-layout"
                data-skip-coreui-validation="1"
            >
                @csrf
                <div class="modal-body">
                    @if ($errors->any() || session('error'))
                        <div class="alert alert-danger mb-4">
                            <div class="font-weight-bold mb-2">Create draft could not be completed.</div>
                            @if (session('error'))
                                <div class="small mb-2">{{ session('error') }}</div>
                            @endif
                            @if ($errors->any())
                                <ul class="mb-0 pl-3 small">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endif
                    <div class="offboarding-modal-section">
                        <div class="offboarding-modal-section__header">
                            <div class="offboarding-modal-section__eyebrow">
                                Step 1
                            </div>
                            <div class="offboarding-modal-section__title">
                                Employee Selection
                            </div>
                            <div class="offboarding-modal-section__text">
                                Choose the employee whose separation is
                                being processed by HR.
                            </div>
                        </div>
                        <div class="form-group mb-0">
                            <label for="offboarding_employee_id"
                                >Select employee</label
                            >
                            <select
                                name="employee_id"
                                id="offboarding_employee_id"
                                class="form-control select2bs4 @error('employee_id') is-invalid @enderror"
                                data-placeholder="Select employee"
                                required
                            >
                                <option value="">
                                    -- Select Employee --
                                </option>
                                @foreach ($availableEmployees as $employee)
                                    @php $employeeName = trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')); $positionLabel = $employee->positions ->map(fn ($assignment) => $assignment->position?->position) ->filter() ->unique() ->implode(', '); $employeeMeta = implode(' / ', array_filter([ $employee->employee_id ? '#'.$employee->employee_id : null, $employee->department?->department, $positionLabel ?: null, ])); @endphp
                                    <option
                                        value="{{ $employee->id }}"
                                        @selected ((int) old('employee_id', $selectedEmployeeId) === (int) $employee->id)
                                    >
                                        {{ $employeeName ?: 'Employee' }}{{ $employeeMeta ? ' - '.$employeeMeta : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('employee_id')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="offboarding-modal-section">
                        <div class="offboarding-modal-section__header">
                            <div class="offboarding-modal-section__eyebrow">
                                Step 2
                            </div>
                            <div class="offboarding-modal-section__title">
                                Separation Details
                            </div>
                            <div class="offboarding-modal-section__text">
                                Set the separation date, last working day,
                                reason, and attach any supporting letter if
                                available.
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="offboarding_separation_date"
                                        >Separation Date</label
                                    >
                                    <input
                                        type="date"
                                        name="separation_date"
                                        id="offboarding_separation_date"
                                        class="form-control @error('separation_date') is-invalid @enderror"
                                        value="{{ old('separation_date', now()->toDateString()) }}"
                                        required
                                    />
                                    @error('separation_date')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                                </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="offboarding_last_day"
                                        >Last Working Day</label
                                    >
                                    <input
                                        type="date"
                                        name="last_working_day"
                                        id="offboarding_last_day"
                                        class="form-control @error('last_working_day') is-invalid @enderror"
                                        value="{{ old('last_working_day') }}"
                                        min="{{ old('separation_date', now()->toDateString()) }}"
                                        required
                                    />
                                    @error('last_working_day')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="offboarding_reason"
                                        >Separation Reason</label
                                    >
                                    <select
                                        name="resignation_reason"
                                        id="offboarding_reason"
                                        class="form-control select2bs4 @error('resignation_reason') is-invalid @enderror"
                                        data-placeholder="Select reason"
                                        required
                                    >
                                        <option value="">
                                            Select reason
                                        </option>
                                        @foreach (\App\Http\Requests\Offboarding\StoreOffboardingRequest::SEPARATION_REASONS as $reason)
                                            <option
                                                value="{{ $reason }}"
                                                @selected(old('resignation_reason', 'Resignation') === $reason)
                                            >
                                                {{ $reason }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('resignation_reason')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-group mb-0">
                                    <label
                                        >Supporting Letter
                                        <span class="text-muted"
                                            >(optional)</span
                                        ></label
                                    >
                                    <input
                                        type="file"
                                        name="resignation_letter_attachment"
                                        id="offboarding_letter"
                                        class="filepond @error('resignation_letter_attachment') is-invalid @enderror"
                                        accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                                        data-accepted-file-types=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                                        data-max-file-size="10MB"
                                        data-filepond-label-idle='Drop supporting letter here or <span class="filepond--label-action">Browse</span>'
                                    />
                                    @error('resignation_letter_attachment')
                                        <div class="invalid-feedback d-block">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <x-ui.modal-footer>
                    <x-ui.button
                        variant="light"
                        data-coreui-dismiss="modal"
                    >
                        Cancel
                    </x-ui.button>
                    <x-ui.button type="submit" variant="danger">
                        Create Draft
                    </x-ui.button>
                </x-ui.modal-footer>
            </form>
        </x-modal>
        @push ('scripts')
            <script>
                document.addEventListener("DOMContentLoaded", function () {
                    const modalEl = document.getElementById("offboardingCreateModal");
                    const form = document.getElementById("offboardingCreateForm");
                    const separationDate = document.getElementById("offboarding_separation_date");
                    const lastWorkingDay = document.getElementById("offboarding_last_day");

                    if (!modalEl || !form) return;

                    if (separationDate && lastWorkingDay) {
                        const syncLastWorkingDayMin = () => {
                            const value = separationDate.value || "";
                            lastWorkingDay.min = value;
                            if (lastWorkingDay.value && value && lastWorkingDay.value < value) {
                                lastWorkingDay.value = value;
                            }
                        };

                        separationDate.addEventListener("change", syncLastWorkingDayMin);
                        syncLastWorkingDayMin();
                    }

                    form.addEventListener("submit", function (event) {
                        if (!form.reportValidity()) {
                            event.preventDefault();
                            event.stopPropagation();
                            if (typeof window.showToast === "function") {
                                window.showToast(
                                    "warning",
                                    "Complete the required offboarding details before creating a draft."
                                );
                            }
                            return;
                        }

                        const submitButton = form.querySelector('button[type="submit"]');
                        if (submitButton) {
                            submitButton.disabled = true;
                        }
                    });

                    @if ($shouldOpenCreateModal || ($errors->any() && old('employee_id')) || session('error'))
                        if (window.coreui && window.coreui.Modal) {
                            window.coreui.Modal.getOrCreateInstance(modalEl).show();
                        } else if (window.jQuery) {
                            window.jQuery(modalEl).modal("show");
                        }
                    @endif
                });
            </script>
        @endpush
    @endif
    @if ($isEmployeeMonitor)
        @push ('scripts')
            <script>
                document.addEventListener("DOMContentLoaded", function () {
                    const statusModal = document.getElementById("offboardingStatusModal");
                    if (!statusModal) return;

                    const badge = statusModal.querySelector("#offboardingStatusBadge");
                    const employeeEl = statusModal.querySelector("#offboardingStatusEmployee");
                    const lastDayEl = statusModal.querySelector("#offboardingStatusLastDay");
                    const reasonEl = statusModal.querySelector("#offboardingStatusReason");
                    const clearedEl = statusModal.querySelector("#offboardingStatusCleared");
                    const pendingEl = statusModal.querySelector("#offboardingStatusPending");
                    const blockedEl = statusModal.querySelector("#offboardingStatusBlocked");

                    const populateStatusModal = function (event) {
                        const trigger = event.relatedTarget;
                        if (!trigger) return;

                        employeeEl.textContent = trigger.getAttribute("data-record-employee") || "-";
                        lastDayEl.textContent = trigger.getAttribute("data-record-last-working-day") || "-";
                        reasonEl.textContent = trigger.getAttribute("data-record-reason") || "-";
                        clearedEl.textContent = trigger.getAttribute("data-record-cleared") || "0";
                        pendingEl.textContent = trigger.getAttribute("data-record-pending") || "0";
                        blockedEl.textContent = trigger.getAttribute("data-record-blocked") || "0";

                        const stage = trigger.getAttribute("data-record-stage") || "Pending";
                        const variant = trigger.getAttribute("data-record-status-variant") || "secondary";
                        const status = trigger.getAttribute("data-record-status") || "secondary";

                        if (badge) {
                            badge.textContent = stage;
                            badge.className = "badge badge-" + variant + " px-3 py-2";
                            badge.setAttribute("data-status", status);
                        }
                    };

                    statusModal.addEventListener("show.coreui.modal", populateStatusModal);
                    statusModal.addEventListener("show.bs.modal", populateStatusModal);
                });
            </script>
        @endpush
    @endif
@endsection
