@extends ('layouts.admin')

@section ('content')
    <div
        class="container-fluid"
        id="eligibilityIndexPage"
        data-reward-title-options-by-type='@json($rewardTitleOptionsByType ?? [])'
        data-assignable-reward-types-by-employee='@json($assignableRewardTypesByEmployee ?? [])'
    >
        <x-page-header
            eyebrow="Recognition & Rewards"
            title="Eligibility Dashboard"
            subtitle="Recognition eligibility based on years of service, attendance rating, and finalized SPMS result."
        >
            <x-slot:actions>
                @can ('manage-rewards')
                    <x-ui.button
                        variant="outline-secondary"
                        icon="cil-settings"
                        size="sm"
                        data-toggle="modal"
                        data-target="#awardTitleSetupModal"
                    >
                        Award Titles
                    </x-ui.button>
                    <x-ui.button
                        variant="primary"
                        icon="cil-star"
                        size="sm"
                        data-toggle="modal"
                        data-target="#assignRewardModal"
                    >
                        Assign Recognition
                    </x-ui.button>
                @endcan
                <x-ui.button
                    type="print"
                    variant="outline-light"
                    size="sm"
                    class="px-3 js-eligibility-export report-print-btn"
                    :href="route('eligibility.print', request()->query())"
                    target="_blank"
                    rel="noopener"
                >
                    Print Report
                </x-ui.button>
            </x-slot:actions>
        </x-page-header>

        <x-ui.table-card
            title="Eligibility List"
            subtitle="System-generated recognition eligibility across the workforce."
            :responsive="false"
            class="hrms-list-card"
            id="eligibilityListContainer"
        >
            <x-slot:controls>
                @php
                    $showEligibilityAdvancedFilters = filled($filters['department_id'] ?? null)
                        || filled($filters['milestone'] ?? null)
                        || filled($filters['attendance_category'] ?? null)
                        || filled($filters['spms_rating'] ?? null);
                @endphp
                <x-ui.table-toolbar
                    method="GET"
                    :action="route('eligibility.index')"
                    class="eligibility-index-toolbar mb-0"
                    id="eligibilityFilterForm"
                >
                    <div class="ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--filter eligibility-toolbar-field" data-toolbar-label="Department">
                        <label class="form-label" for="eligibilityDepartment"
                            >Department</label
                        >
                        <select
                            id="eligibilityDepartment"
                            name="department_id"
                            class="form-control form-control-sm select2bs4"
                            data-toolbar-select2="1"
                            data-placeholder="Dept"
                            data-allow-clear="1"
                        >
                            <option value=""></option>
                            @foreach ($departments as $department)
                                <option
                                    value="{{ $department->id }}"
                                    {{ (int) $filters['department_id'] === (int) $department->id ? 'selected' : '' }}
                                >
                                    {{ $department->department }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--filter eligibility-toolbar-field" data-toolbar-label="Milestone">
                        <label class="form-label" for="eligibilityMilestone"
                            >Milestone</label
                        >
                        <select
                            id="eligibilityMilestone"
                            name="milestone"
                            class="form-control form-control-sm select2bs4"
                            data-toolbar-select2="1"
                            data-placeholder="Years"
                            data-allow-clear="1"
                        >
                            <option value=""></option>
                            @foreach ([5,10,15,20] as $milestone)
                                <option
                                    value="{{ $milestone }}"
                                    {{ (int) $filters['milestone'] === $milestone ? 'selected' : '' }}
                                    >{{ $milestone }} years
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--filter eligibility-toolbar-field" data-toolbar-label="Attendance">
                        <label class="form-label" for="eligibilityAttendance"
                            >Attendance</label
                        >
                        <select
                            id="eligibilityAttendance"
                            name="attendance_category"
                            class="form-control form-control-sm select2bs4"
                            data-toolbar-select2="1"
                            data-placeholder="Status"
                            data-allow-clear="1"
                        >
                            <option value=""></option>
                            <option
                                value="perfect"
                                {{ $filters['attendance_category'] === 'perfect' ? 'selected' : '' }}
                                >Qualified
                            </option>
                            <option
                                value="not_qualified"
                                {{ $filters['attendance_category'] === 'not_qualified' ? 'selected' : '' }}
                                >Not Qualified
                            </option>
                        </select>
                    </div>
                    <div class="ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--filter eligibility-toolbar-field" data-toolbar-label="SPMS">
                        <label class="form-label" for="eligibilitySpms"
                            >SPMS</label
                        >
                        <select
                            id="eligibilitySpms"
                            name="spms_rating"
                            class="form-control form-control-sm select2bs4"
                            data-toolbar-select2="1"
                            data-placeholder="Rating"
                            data-allow-clear="1"
                        >
                            <option value=""></option>
                            @foreach (['outstanding', 'very_satisfactory', 'satisfactory', 'unsatisfactory', 'poor'] as $rating)
                                <option
                                    value="{{ $rating }}"
                                    {{ $filters['spms_rating'] === $rating ? 'selected' : '' }}
                                >
                                    {{ ucwords(str_replace('_', ' ', $rating)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div
                        class="ui-toolbar__field ui-toolbar__field--action ui-table-toolbar-field ui-table-toolbar-field--action eligibility-toolbar-field eligibility-toolbar-field--action"
                    >
                        <x-ui.button
                            type="submit"
                            variant="primary"
                            size="sm"
                            id="eligibilityToolbarApply"
                            class="eligibility-toolbar-apply-button"
                        >
                            Apply
                        </x-ui.button>
                    </div>
                </x-ui.table-toolbar>
            </x-slot:controls>

            <div class="position-relative">
                <div
                    class="eligibility-loading-overlay d-none"
                    id="eligibilityLoadingOverlay"
                >
                    <x-ui.spinner color="primary" label="Loading eligibility records..." />
                </div>
                <div class="table-responsive hrms-table">
                    <table
                        class="table hrms-table"
                        id="eligibilityTable"
                        data-dt-search="0"
                    >
                        <thead
                            class="bg-light text-uppercase small font-weight-bold"
                        >
                            <tr>
                                <th class="ps-4 py-3">Employee</th>
                                <th class="text-center">Service</th>
                                <th class="text-center">
                                    Attendance Basis
                                </th>
                                <th class="text-center">Performance</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="eligibilityTableBody">
                            @forelse ($records as $row)
                                @php
                                $employee = $row['employee'];
                                $eligibility = $row['eligibility'];
                            @endphp
                                <tr class="eligibility-row-main">
                                    <td class="ps-4 align-middle">
                                        <div class="eligibility-employee-cell">
                                            <div
                                                class="eligibility-employee-name"
                                            >
                                                {{ trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')) }}
                                            </div>
                                            <div
                                                class="eligibility-employee-meta"
                                            >
                                                <span
                                                    >#{{ $employee->employee_id }}</span
                                                >
                                                <span
                                                    >{{ $employee->department?->department ?? 'No Department' }}</span
                                                >
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center align-middle">
                                        @if ($eligibility['tenure']['eligible'])
                                            <span
                                                class="badge badge-primary px-3 py-2"
                                                >{{ $eligibility['tenure']['milestone'] }} years</span
                                            >
                                        @else
                                            <span
                                                class="badge badge-secondary px-3 py-2"
                                                >Not Eligible</span
                                            >
                                        @endif
                                    </td>
                                    <td class="text-center align-middle">
                                        @if ($eligibility['attendance']['eligible'])
                                            <span
                                                class="badge badge-success px-3 py-2"
                                                >Qualified</span
                                            >
                                        @else
                                            <span
                                                class="badge badge-warning px-3 py-2"
                                                >Not Qualified</span
                                            >
                                        @endif
                                    </td>
                                    <td class="text-center align-middle">
                                        @if ($eligibility['performance']['eligible'])
                                            <span
                                                class="badge badge-info px-3 py-2"
                                            >
                                                {{ strtoupper((string) ($eligibility['performance']['rating'] ?? 'N/A')) }}
                                            </span>
                                        @else
                                            <span
                                                class="badge badge-secondary px-3 py-2"
                                                >Not Eligible</span
                                            >
                                        @endif
                                    </td>
                                    <td class="text-end pe-4 align-middle">
                                        <div
                                            class="crud-actions eligibility-actions justify-content-end"
                                        >
                                            <x-ui.button
                                                type="details"
                                                size="sm"
                                                icon="cil-chevron-bottom"
                                                class="js-eligibility-toggle-detail"
                                                aria-expanded="false"
                                                aria-label="View basis summary"
                                                title="View basis summary"
                                            >
                                            </x-ui.button>
                                            <x-ui.button
                                                type="view"
                                                size="sm"
                                                :href="route('eligibility.show', $employee)"
                                                aria-label="View eligibility details"
                                                title="View eligibility details"
                                            >
                                            </x-ui.button>
                                        </div>
                                    </td>
                                </tr>
                                <tr class="eligibility-row-detail d-none">
                                    <td colspan="5" class="bg-light">
                                        <div class="eligibility-detail-grid small">
                                            <div class="eligibility-detail-item">
                                                <span class="eligibility-detail-label">Service basis</span>
                                                <div class="eligibility-detail-value">
                                                    {{ (int) ($eligibility['tenure']['years'] ?? 0) }} years served
                                                </div>
                                                <div class="eligibility-detail-note">
                                                    @if ($eligibility['tenure']['eligible'])
                                                        Counts toward the {{ (int) ($eligibility['tenure']['milestone'] ?? 0) }}-year milestone.
                                                    @else
                                                        {{ $eligibility['tenure']['reason'] ?? 'No milestone reached yet.' }}
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="eligibility-detail-item">
                                                <span class="eligibility-detail-label">Attendance basis</span>
                                                <div class="eligibility-detail-value">
                                                    Overall {{ number_format((float) ($eligibility['attendance']['final_score'] ?? 0), 2) }}%
                                                    · Rating {{ (int) ($eligibility['attendance']['rating'] ?? 0) }}
                                                </div>
                                                <div class="eligibility-detail-note">
                                                    Presence {{ number_format((float) ($eligibility['attendance']['attendance_rate'] ?? 0), 2) }}%
                                                    · On-Time {{ number_format((float) ($eligibility['attendance']['punctuality_rate'] ?? 0), 2) }}%
                                                </div>
                                            </div>
                                            <div class="eligibility-detail-item">
                                                <span class="eligibility-detail-label">Performance basis</span>
                                                <div class="eligibility-detail-value">
                                                    {{ strtoupper((string) ($eligibility['performance']['rating'] ?? 'N/A')) }}
                                                    · {{ number_format((float) ($eligibility['performance']['score'] ?? 0), 2) }}
                                                </div>
                                                <div class="eligibility-detail-note">
                                                    {{ $eligibility['performance']['reason'] ?? 'No finalized SPMS basis found.' }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-5">
                                        <div class="eligibility-empty-state">
                                            <i class="cil-star"></i>
                                            <h6>No eligible employees found</h6>
                                            <p class="mb-0">Adjust the filters or wait for updated attendance/SPMS records.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </x-ui.table-card>

        @can ('manage-rewards')
            <x-ui.modal id="awardTitleSetupModal" size="lg">
                <x-ui.modal-header title="Award Title Setup" icon="cil-settings" />
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        Manage the recognition titles available for assignment from the eligibility dashboard.
                    </p>
                            <form
                                method="POST"
                                action="{{ route('rewards.titles.store') }}"
                                class="reward-title-setup-form"
                            >
                                @csrf
                                <div class="reward-title-setup-form__field reward-title-setup-form__field--type">
                                    <label class="form-label">
                                        Award Type
                                        <span class="text-danger">*</span>
                                    </label>
                                    <select
                                        name="award_type"
                                        class="form-control select2bs4"
                                        data-placeholder="Select type"
                                        required
                                    >
                                        <option value=""></option>
                                        @foreach (['tenure' => 'Tenure', 'attendance' => 'Attendance', 'performance' => 'Performance', 'special' => 'Special Recognition'] as $value => $label)
                                            <option
                                                value="{{ $value }}"
                                                @selected(old('award_type') === $value)
                                            >
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="reward-title-setup-form__field reward-title-setup-form__field--title">
                                    <label class="form-label">
                                        Award Title
                                        <span class="text-danger">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        name="title"
                                        class="form-control"
                                        value="{{ old('title') }}"
                                        placeholder="Enter award title"
                                        required
                                    />
                                </div>
                                <div class="reward-title-setup-form__field reward-title-setup-form__field--action">
                                    <x-ui.button
                                        type="submit"
                                        variant="primary"
                                        size="sm"
                                        icon="cil-plus"
                                        class="reward-title-setup-form__submit"
                                    >
                                        Add Title
                                    </x-ui.button>
                                </div>
                            </form>

                            <div class="table-responsive hrms-table mt-4">
                                <table class="table hrms-table mb-0">
                                    <thead class="bg-light text-uppercase small font-weight-bold">
                                        <tr>
                                            <th class="ps-4 py-3">Award Type</th>
                                            <th>Award Title</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($rewardTitles as $rewardTitle)
                                            <tr>
                                                <td class="ps-4 align-middle">
                                                    <span class="text-capitalize">
                                                        {{ str_replace('_', ' ', $rewardTitle->award_type) }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <form
                                                        method="POST"
                                                        action="{{ route('rewards.titles.update', $rewardTitle) }}"
                                                        class="reward-title-inline-form"
                                                    >
                                                        @csrf
                                                        @method('PATCH')
                                                        <div class="reward-title-inline-form__field reward-title-inline-form__field--type">
                                                            <select
                                                                name="award_type"
                                                                class="form-control form-control-sm"
                                                                required
                                                            >
                                                                @foreach (['tenure' => 'Tenure', 'attendance' => 'Attendance', 'performance' => 'Performance', 'special' => 'Special Recognition'] as $value => $label)
                                                                    <option
                                                                        value="{{ $value }}"
                                                                        @selected($rewardTitle->award_type === $value)
                                                                    >
                                                                        {{ $label }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <div class="reward-title-inline-form__field reward-title-inline-form__field--title">
                                                            <input
                                                                type="text"
                                                                name="title"
                                                                class="form-control form-control-sm"
                                                                value="{{ $rewardTitle->title }}"
                                                                required
                                                            />
                                                        </div>
                                                        <div class="reward-title-inline-form__field reward-title-inline-form__field--action">
                                                            <div class="crud-actions justify-content-center">
                                                                <x-ui.button
                                                                    type="submit"
                                                                    variant="primary"
                                                                    size="sm"
                                                                    icon="cil-save"
                                                                    title="Update title"
                                                                >
                                                                    Save
                                                                </x-ui.button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                </td>
                                                <td class="text-center align-middle">
                                                    <form
                                                        method="POST"
                                                        action="{{ route('rewards.titles.destroy', $rewardTitle) }}"
                                                        onsubmit="return confirm('Delete this award title?');"
                                                    >
                                                        @csrf
                                                        @method('DELETE')
                                                        <x-ui.button
                                                            type="submit"
                                                            variant="danger"
                                                            size="sm"
                                                            icon="cil-trash"
                                                            title="Delete title"
                                                        >
                                                            Delete
                                                        </x-ui.button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="3" class="text-center py-4">
                                                    <span class="text-muted">
                                                        No award titles configured yet.
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                </div>
                <x-ui.modal-footer>
                    <x-ui.button
                        variant="light"
                        size="sm"
                        data-coreui-dismiss="modal"
                    >
                        Close
                    </x-ui.button>
                </x-ui.modal-footer>
            </x-ui.modal>

            <x-ui.modal id="assignRewardModal" size="md">
                <x-ui.modal-header title="Assign Recognition" icon="cil-star" />
                <form
                    method="POST"
                    action="{{ route('rewards.store') }}"
                    id="assignRewardForm"
                >
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label>
                                Employee
                                <span class="text-danger">*</span>
                            </label>
                            <select
                                name="employee_id"
                                class="form-control select2bs4"
                                data-placeholder="Search employee"
                                required
                            >
                                <option value=""></option>
                                @foreach ($employeesForManual as $employee)
                                    <option
                                        value="{{ $employee->id }}"
                                        @selected((int) old('employee_id') === (int) $employee->id)
                                    >
                                        {{ trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')) }}
                                        @if ($employee->employee_id)
                                            ({{ $employee->employee_id }})
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted d-block mt-2">
                                The system offers only titles that match the selected employee's current eligibility. Special Recognition stays available for any active employee.
                            </small>
                        </div>
                        <div class="form-group">
                            <label>
                                Recognition Title
                                <span class="text-danger">*</span>
                            </label>
                            <select
                                class="form-control"
                                id="rewardAwardTitle"
                                name="reward_title_id"
                                required
                                data-selected-title-id="{{ old('reward_title_id') }}"
                                disabled
                            >
                                <option value="">Select employee first</option>
                            </select>
                            <small class="text-muted d-block mt-2">
                                Category is determined automatically from the selected title.
                            </small>
                        </div>
                        <div class="form-group mb-0">
                            <label>
                                Award Date
                                <span class="text-danger">*</span>
                            </label>
                            <input
                                type="date"
                                class="form-control"
                                name="award_date"
                                value="{{ old('award_date', now()->toDateString()) }}"
                                required
                            />
                        </div>
                    </div>
                    <x-ui.modal-footer>
                        <x-ui.button
                            variant="light"
                            size="sm"
                            data-coreui-dismiss="modal"
                        >
                            Cancel
                        </x-ui.button>
                        <x-ui.button
                            type="submit"
                            variant="primary"
                            size="sm"
                            icon="cil-save"
                        >
                            Save Recognition
                        </x-ui.button>
                    </x-ui.modal-footer>
                </form>
            </x-ui.modal>
        @endcan
    </div>
@endsection
