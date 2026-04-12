@extends ('layouts.admin')

@section ('content')
    @php
        $isEmployeeSelfService = !$canManage;
    @endphp
    <div
        class="container-fluid"
        id="idpIndexPage"
        data-page="idp.index"
        data-open-plan-id="{{ $openPlanId ?: '' }}"
    >
        <x-page-header
            eyebrow="Performance"
            title="Individual Development Plans"
            subtitle="{{ $isEmployeeSelfService
                ? 'Review your development plan, competency gaps, and recorded action items from finalized SPMS cycles.'
                : 'Development plans generated after finalized SPMS scorecards are closed into a cycle.' }}"
        >
            <x-slot:actions>
                <div
                    class="d-flex align-items-center flex-wrap justify-content-end gap-2"
                >
                    <span class="badge badge-light text-dark border px-3 py-2">
                        {{ $plans->total() }} plans
                    </span>
                </div>
            </x-slot:actions>
        </x-page-header>

        <x-ui.table-card
            title="{{ $isEmployeeSelfService ? 'My Development Plans' : 'IDP Directory' }}"
            subtitle="{{ $isEmployeeSelfService
                ? 'Your finalized SPMS results and development actions.'
                : 'Search, filter, and update employee development plans.' }}"
            class="hrms-list-card idp-index-card"
        >
            <x-slot:controls>
                <x-ui.table-toolbar
                    method="GET"
                    :action="route('idp.index')"
                    class="idp-toolbar"
                    id="idpIndexToolbar"
                >
                    @if ($canManage)
                        <div
                            class="idp-toolbar__field idp-toolbar__field--search ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--search"
                        >
                            <label
                                class="small text-muted mb-1 d-block"
                                for="idpSearchInput"
                                >Search</label
                            >
                            <input
                                type="search"
                                name="search"
                                id="idpSearchInput"
                                value="{{ $filters['search'] ?? '' }}"
                                class="form-control form-control-sm"
                                placeholder="Search"
                            />
                        </div>

                        <div
                            class="idp-toolbar__field ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--filter"
                        >
                            <label
                                class="small text-muted mb-1 d-block"
                                for="idpDepartmentFilter"
                                >Department</label
                            >
                            <select
                                name="department"
                                id="idpDepartmentFilter"
                                class="form-control form-control-sm"
                            >
                                <option value="">All</option>
                                @foreach ($departments as $department)
                                    <option
                                        value="{{ $department['value'] }}"
                                        @selected ((string) ($filters['department'] ?? '') === (string) $department['value'])
                                    >
                                        {{ $department['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div
                            class="idp-toolbar__field ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--filter"
                        >
                            <label
                                class="small text-muted mb-1 d-block"
                                for="idpPositionFilter"
                                >Position</label
                            >
                            <select
                                name="position"
                                id="idpPositionFilter"
                                class="form-control form-control-sm"
                            >
                                <option value="">All</option>
                                @foreach ($positions as $position)
                                    <option
                                        value="{{ $position['value'] }}"
                                        @selected ((string) ($filters['position'] ?? '') === (string) $position['value'])
                                    >
                                        {{ $position['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div
                        class="idp-toolbar__field ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--filter"
                    >
                        <label
                            class="small text-muted mb-1 d-block"
                            for="idpStatusFilter"
                            >Status</label
                        >
                        <select
                            name="status"
                            id="idpStatusFilter"
                            class="form-control form-control-sm"
                        >
                            <option value="">All</option>
                            @foreach ($statusOptions as $option)
                                <option
                                    value="{{ $option['value'] }}"
                                    @selected ((string) ($filters['status'] ?? '') === (string) $option['value'])
                                >
                                    {{ $option['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @if ($canManage)
                        <div
                            class="idp-toolbar__actions ui-toolbar__field ui-toolbar__field--action ui-table-toolbar-field ui-table-toolbar-field--action"
                        >
                            <x-ui.button type="submit" variant="primary" size="sm">
                                Apply
                            </x-ui.button>
                        </div>
                    @endif
                </x-ui.table-toolbar>
            </x-slot:controls>

            <div class="table-responsive">
            <table
                class="table table-hover align-middle mb-0 hrms-table idp-table"
            >
                <thead>
                    <tr>
                        @if ($canManage)
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Position</th>
                        @endif
                        <th>Cycle</th>
                        <th>Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($plans as $plan)
                        @php
                        $employee = $plan->employee;
                        $employeeName = trim(($employee?->first_name ?? '') . ' ' . ($employee?->last_name ?? ''));
                        $departmentName = $employee?->department?->department ?? 'No Department';
                        $positionName = $employee?->positions?->first()?->position?->position ?? 'Employee';
                        $cycleName = $plan->cycle?->title ?? 'SPMS Cycle';
                        $status = strtolower(trim((string) $plan->status));
                        $statusLabel = match ($status) {
                            'submitted' => 'Pending',
                            'reviewed', 'active' => 'Active',
                            'locked', 'archived' => 'Archived',
                            default => 'Draft',
                        };
                        $statusVariant = match ($status) {
                            'submitted' => 'warning',
                            'reviewed', 'active' => 'success',
                            'locked', 'archived' => 'secondary',
                            default => 'info',
                        };
                        $canEditPlan = $canManage && !in_array($status, ['locked', 'archived'], true);
                        $isOpen = (string) $openPlanId === (string) $plan->id;
                        $planGoals = $isOpen ? old('development_goals', $plan->development_goals) : $plan->development_goals;
                        $planNotes = $isOpen ? old('employee_notes', $plan->employee_notes) : $plan->employee_notes;
                        $gaps = is_array($plan->competency_gaps ?? null) ? $plan->competency_gaps : [];
                    @endphp
                        <tr class="idp-row-main" data-plan-id="{{ $plan->id }}">
                            @if ($canManage)
                                <td>
                                    <div class="font-weight-bold text-dark">
                                        {{ $employeeName ?: 'Employee' }}
                                    </div>
                                    <div class="text-muted small">
                                        #{{ $employee?->employee_id ?? 'N/A' }}
                                    </div>
                                </td>
                                <td>{{ $departmentName }}</td>
                                <td>{{ $positionName }}</td>
                            @endif
                            <td>{{ $cycleName }}</td>
                            <td>
                                <span
                                    class="badge badge-{{ $statusVariant }} idp-status-badge"
                                    >{{ $statusLabel }}</span
                                >
                            </td>
                            <td class="text-center">
                                <div
                                    class="crud-actions justify-content-center"
                                >
                                    <x-ui.button
                                        type="view"
                                        size="sm"
                                        class="idp-row-toggle"
                                        data-idp-toggle="row"
                                        data-idp-mode="view"
                                        data-idp-target="idpDetailRow-{{ $plan->id }}"
                                        aria-label="View IDP Details"
                                        title="View IDP Details"
                                    />
                                    @if ($canEditPlan)
                                        <x-ui.button
                                            type="edit"
                                            size="sm"
                                            class="idp-row-toggle"
                                            data-idp-toggle="row"
                                            data-idp-mode="edit"
                                            data-idp-target="idpDetailRow-{{ $plan->id }}"
                                            data-idp-focus="1"
                                            aria-label="Edit IDP"
                                            title="Edit IDP"
                                        />
                                    @endif
                                </div>
                            </td>
                        </tr>
                        <tr
                            id="idpDetailRow-{{ $plan->id }}"
                            class="idp-detail-row {{ $isOpen ? '' : 'd-none' }}"
                            data-plan-id="{{ $plan->id }}"
                            data-idp-mode="{{ old('plan_id') === (string) $plan->id ? 'edit' : 'view' }}"
                        >
                            <td colspan="{{ $canManage ? 6 : 3 }}" class="idp-detail-cell">
                                <div class="idp-detail-panel">
                                    <div
                                        class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3"
                                    >
                                        <div>
                                            <div
                                                class="text-muted small text-uppercase letter-spacing-sm"
                                            >
                                                Plan Details
                                            </div>
                                            <h5
                                                class="mb-1 font-weight-bold text-dark"
                                            >
                                                {{ $employeeName ?: 'Employee' }}
                                            </h5>
                                            <div class="text-muted small">
                                                @if ($canManage)
                                                    {{ $departmentName }} | {{ $positionName }} | {{ $cycleName }}
                                                @else
                                                    {{ $cycleName }}
                                                @endif
                                            </div>
                                        </div>

                                        <div
                                            class="d-flex align-items-center gap-2 flex-wrap"
                                        >
                                            <span
                                                class="badge badge-{{ $statusVariant }} idp-status-badge"
                                                >{{ $statusLabel }}</span
                                            >
                                            <span
                                                class="badge badge-light border text-dark"
                                            >
                                                {{ $canEditPlan ? 'Editable' : 'Read only' }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="row g-3">
                                        <div class="col-lg-4">
                                            <div class="idp-detail-summary">
                                                <div class="idp-summary-card">
                                                    <div
                                                        class="idp-summary-label"
                                                    >
                                                        Final Rating
                                                    </div>
                                                    <div
                                                        class="idp-summary-value"
                                                    >
                                                        {{ strtoupper((string) ($plan->final_spms_rating ?? 'N/A')) }}
                                                    </div>
                                                </div>

                                                <div class="idp-summary-card">
                                                    <div
                                                        class="idp-summary-label"
                                                    >
                                                        Final Score
                                                    </div>
                                                    <div
                                                        class="idp-summary-value"
                                                    >
                                                        {{ number_format((float) ($plan->final_spms_score ?? 0), 2) }}
                                                    </div>
                                                </div>

                                                <div class="idp-summary-card">
                                                    <div
                                                        class="idp-summary-label"
                                                    >
                                                        Competency Gaps
                                                    </div>
                                                    @if ($gaps)
                                                        <ul
                                                            class="idp-gap-list"
                                                        >
                                                            @foreach ($gaps as $gap)
                                                                <li
                                                                    class="idp-gap-item"
                                                                >
                                                                    <span
                                                                        class="idp-gap-name"
                                                                        >{{ $gap['name'] ?? 'Criterion' }}</span
                                                                    >
                                                                    <span
                                                                        class="idp-gap-score"
                                                                    >
                                                                        {{ number_format((float) ($gap['score'] ?? 0), 2) }}/5
                                                                    </span>
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    @else
                                                        <div
                                                            class="text-muted small"
                                                        >
                                                            No major competency
                                                            gaps were flagged in
                                                            the finalized
                                                            evaluation.
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-lg-8">
                                            @if ($canEditPlan)
                                                <div
                                                    class="idp-readonly-panel"
                                                    data-idp-readonly="1"
                                                >
                                                    <div class="form-group">
                                                        <label
                                                            class="small text-muted text-uppercase mb-1"
                                                            >Development
                                                            Goals</label
                                                        >
                                                        <div
                                                            class="idp-readonly-value"
                                                        >
                                                            {{ filled($planGoals) ? $planGoals : 'No development goals recorded.' }}
                                                        </div>
                                                    </div>

                                                    <div
                                                        class="form-group mb-0"
                                                    >
                                                        <label
                                                            class="small text-muted text-uppercase mb-1"
                                                            >Employee
                                                            Notes</label
                                                        >
                                                        <div
                                                            class="idp-readonly-value"
                                                        >
                                                            {{ filled($planNotes) ? $planNotes : 'No employee notes recorded.' }}
                                                        </div>
                                                    </div>
                                                </div>
                                                <form
                                                    method="POST"
                                                    action="{{ route('idp.update', $plan) }}"
                                                    class="idp-edit-form d-none"
                                                    data-idp-editor="1"
                                                    data-plan-id="{{ $plan->id }}"
                                                >
                                                    @csrf
                                                    @method ('PATCH')
                                                    <input
                                                        type="hidden"
                                                        name="plan_id"
                                                        value="{{ $plan->id }}"
                                                    />

                                                    <div
                                                        class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3"
                                                    >
                                                        <div>
                                                            <div
                                                                class="text-muted small text-uppercase letter-spacing-sm"
                                                            >
                                                                Editable Fields
                                                            </div>
                                                            <h6
                                                                class="mb-0 font-weight-bold text-dark"
                                                            >
                                                                Development
                                                                actions and
                                                                notes
                                                            </h6>
                                                        </div>

                                                        <div
                                                            class="d-flex gap-2 flex-wrap ml-auto"
                                                        >
                                                            <x-ui.button
                                                                type="reset"
                                                                variant="outline-secondary"
                                                                size="sm"
                                                                data-idp-cancel="1"
                                                            >
                                                                Cancel
                                                            </x-ui.button>
                                                            <x-ui.button
                                                                type="submit"
                                                                variant="primary"
                                                                size="sm"
                                                                data-idp-save="1"
                                                                disabled
                                                                icon="cil-save"
                                                            >
                                                                Save Changes
                                                            </x-ui.button>
                                                        </div>
                                                    </div>

                                                    <div
                                                        class="form-group mb-3"
                                                    >
                                                        <label
                                                            class="form-label"
                                                            for="development_goals_{{ $plan->id }}"
                                                        >
                                                            Development Goals
                                                        </label>
                                                        <textarea
                                                            id="development_goals_{{ $plan->id }}"
                                                            name="development_goals"
                                                            rows="5"
                                                            class="form-control form-control-sm idp-edit-control"
                                                            data-idp-field="development_goals"
                                                            data-initial-value="{{ old('plan_id') === (string) $plan->id ? old('development_goals', $plan->development_goals) : $planGoals }}"
                                                            placeholder="Describe coaching, training, mentoring, and focused improvement actions."
                                                            >{{ old('plan_id') === (string) $plan->id ? old('development_goals', $plan->development_goals) : $planGoals }}</textarea
                                                        >
                                                        @error ('development_goals')
                                                            <div
                                                                class="invalid-feedback d-block"
                                                            >
                                                                {{ $message }}
                                                            </div>
                                                        @enderror
                                                    </div>

                                                    <div
                                                        class="form-group mb-0"
                                                    >
                                                        <label
                                                            class="form-label"
                                                            for="employee_notes_{{ $plan->id }}"
                                                        >
                                                            Employee Notes
                                                        </label>
                                                        <textarea
                                                            id="employee_notes_{{ $plan->id }}"
                                                            name="employee_notes"
                                                            rows="5"
                                                            class="form-control form-control-sm idp-edit-control"
                                                            data-idp-field="employee_notes"
                                                            data-initial-value="{{ old('plan_id') === (string) $plan->id ? old('employee_notes', $plan->employee_notes) : $planNotes }}"
                                                            placeholder="Add implementation notes or support requirements."
                                                            >{{ old('plan_id') === (string) $plan->id ? old('employee_notes', $plan->employee_notes) : $planNotes }}</textarea
                                                        >
                                                        @error ('employee_notes')
                                                            <div
                                                                class="invalid-feedback d-block"
                                                            >
                                                                {{ $message }}
                                                            </div>
                                                        @enderror
                                                    </div>
                                                </form>
                                            @else
                                                <div class="idp-readonly-panel">
                                                    <div class="form-group">
                                                        <label
                                                            class="small text-muted text-uppercase mb-1"
                                                            >Development
                                                            Goals</label
                                                        >
                                                        <div
                                                            class="idp-readonly-value"
                                                        >
                                                            {{ filled($plan->development_goals) ? $plan->development_goals : 'No development goals recorded.' }}
                                                        </div>
                                                    </div>

                                                    <div
                                                        class="form-group mb-0"
                                                    >
                                                        <label
                                                            class="small text-muted text-uppercase mb-1"
                                                            >Employee
                                                            Notes</label
                                                        >
                                                        <div
                                                            class="idp-readonly-value"
                                                        >
                                                            {{ filled($plan->employee_notes) ? $plan->employee_notes : 'No employee notes recorded.' }}
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty-state
                            colspan="{{ $canManage ? 6 : 3 }}"
                            icon="cil-layers"
                            title="No IDP plans found"
                            message="{{ $hasFilters ? 'No records match the current filters.' : ($isEmployeeSelfService ? 'Your development plan will appear here after a finalized SPMS cycle generates one.' : 'Employees appear here after finalized SPMS scorecards are closed into an IDP draft.') }}"
                        />
                    @endforelse
                </tbody>
            </table>
            </div>

            <x-slot:footer>
                <div class="d-flex justify-content-end">
                    {{ $plans->links() }}
                </div>
            </x-slot:footer>
        </x-ui.table-card>
    </div>
@endsection
