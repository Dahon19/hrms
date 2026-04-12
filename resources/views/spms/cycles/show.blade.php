@extends ('layouts.admin')

@section ('content')
    @php
        $canManageSpms = Gate::allows('manage-spms');
        $pendingCount = (int) ($cycleStatusCounts['pending'] ?? 0);
        $submittedCount = (int) ($cycleStatusCounts['submitted'] ?? 0);
        $finalCount = (int) ($cycleStatusCounts['final'] ?? 0);
        $statusText = match (true) {
            $cycle->status === 'setup' => 'start',
            $cycle->status === 'evaluation' && $cycle->isReadyForClosure() => 'finish',
            $cycle->status === 'evaluation' => 'active',
            $cycle->status === 'closed' => 'closed',
            default => $cycle->status,
        };
        $nextStepTitle = match (true) {
            $cycle->status === 'setup' => 'Step 1: Start',
            $cycle->status === 'closed' => 'Cycle complete',
            $pendingCount > 0 => 'Step 2: Evaluate',
            $submittedCount > 0 => 'Step 3: Finalize Scorecards',
            default => 'Step 4: Finish',
        };
        $nextStepMessage = match (true) {
            $cycle->status === 'setup' => 'Open the cycle so evaluators can start scoring.',
            $cycle->status === 'closed' => 'All scorecards are already locked for this cycle.',
            $pendingCount > 0 => 'Complete the remaining scorecards.',
            $submittedCount > 0 => 'Lock the submitted scorecards before closing the cycle.',
            default => 'Close the cycle to generate IDP drafts from finalized SPMS results.',
        };
        $primaryAction = match (true) {
            $cycle->status === 'setup' && $canManageSpms => 'start',
            $cycle->status === 'evaluation' && $submittedCount > 0 && $canManageSpms => 'finalize',
            $cycle->status === 'evaluation' && $pendingCount === 0 && $submittedCount === 0 && $canManageSpms => 'finish',
            default => null,
        };
    @endphp
    <div class="container-fluid pt-4" id="spmsCycleShowPage">
        <x-page-header
            eyebrow="SPMS"
            title="{{ $cycle->title }}"
            subtitle="{{ optional($cycle->period_start)->format('M d, Y') }} - {{ optional($cycle->period_end)->format('M d, Y') }} | {{ ucfirst($statusText) }}"
        >
            <x-slot:actions>
                <x-ui.button
                    variant="outline-light"
                    size="sm"
                    :href="route('spms.cycles.index')"
                    icon="cil-arrow-left"
                >
                    Back
                </x-ui.button>
                @if ($primaryAction === 'start')
                    <form
                        method="POST"
                        action="{{ route('spms.cycles.transition', $cycle->id) }}"
                        class="d-inline spms-confirm-form"
                        data-spms-confirm="Start this SPMS cycle and generate the employee scorecards?"
                    >
                        @csrf
                        <input type="hidden" name="status" value="evaluation" />
                        <button
                            type="submit"
                            class="btn btn-outline-light btn-sm px-3"
                        >
                            <i class="cil-media-play mr-1"></i> Start
                        </button>
                    </form>
                @elseif ($primaryAction === 'finalize')
                    <form
                        method="POST"
                        action="{{ route('spms.cycles.finalize-submitted', $cycle->id) }}"
                        class="d-inline spms-confirm-form"
                        data-spms-confirm="Finalize all submitted scorecards in this cycle?"
                    >
                        @csrf
                        <button
                            type="submit"
                            class="btn btn-outline-light btn-sm px-3"
                        >
                            <i class="cil-check-alt mr-1"></i> Finalize Scorecards
                        </button>
                    </form>
                @elseif ($primaryAction === 'finish')
                    <form
                        method="POST"
                        action="{{ route('spms.cycles.close', $cycle->id) }}"
                        class="d-inline spms-confirm-form"
                        data-spms-confirm="Close this SPMS cycle? Submitted scorecards will be finalized and IDP drafts will be generated."
                    >
                        @csrf
                        <button
                            type="submit"
                            class="btn btn-outline-light btn-sm px-3"
                        >
                            <i class="cil-check-circle mr-1"></i> Finish
                        </button>
                    </form>
                @endif
                @if ($canManageSpms)
                    <form
                        method="POST"
                        action="{{ route('spms.cycles.sync-evaluators', $cycle->id) }}"
                        class="d-inline spms-confirm-form"
                        data-spms-confirm="Sync evaluator assignments in this cycle to the current department heads?"
                    >
                        @csrf
                        <button
                            type="submit"
                            class="btn btn-outline-light btn-sm px-3"
                        >
                            <i class="cil-user-follow mr-1"></i> Refresh Assignments
                        </button>
                    </form>
                    @if ($pendingCount > 0 && $cycle->status === 'evaluation')
                        <form
                            method="POST"
                            action="{{ route('spms.cycles.remind-pending', $cycle->id) }}"
                            class="d-inline spms-confirm-form"
                            data-spms-confirm="Send reminders to evaluators with pending SPMS scorecards in this cycle?"
                        >
                            @csrf
                            <button
                                type="submit"
                                class="btn btn-outline-light btn-sm px-3"
                            >
                                <i class="cil-bell mr-1"></i> Send Reminders
                            </button>
                        </form>
                    @endif
                    <div class="dropdown d-inline-block spms-hero-export">
                        <button
                            type="button"
                            class="btn btn-outline-light btn-sm px-3 dropdown-toggle"
                            data-coreui-toggle="dropdown"
                            aria-expanded="false"
                        >
                            <i class="cil-cloud-download mr-1"></i> Reports
                        </button>
                        <div class="dropdown-menu dropdown-menu-end shadow-sm">
                            <a
                                href="{{ route('spms.report', $cycle->id) }}"
                                class="dropdown-item"
                                target="_blank"
                                rel="noopener"
                            >
                                <i class="cil-print mr-2"></i> Print
                            </a>
                            <a
                                href="{{ route('spms.report.excel', $cycle->id) }}"
                                class="dropdown-item"
                            >
                                <i class="cil-description mr-2"></i> Export
                            </a>
                        </div>
                    </div>
                @endif
            </x-slot:actions>
        </x-page-header>

        <div class="alert alert-light border shadow-sm mb-3">
            <div class="font-weight-bold">{{ $nextStepTitle }}</div>
            <div class="text-muted mb-0">{{ $nextStepMessage }}</div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="spms-overview-card spms-overview-card--pending">
                    <div class="spms-overview-head">
                        <span class="spms-overview-icon">
                            <i class="cil-clock"></i>
                        </span>
                        <div>
                            <div class="spms-overview-label">In Progress</div>
                            <div class="spms-overview-meta spms-overview-meta--top">Still being scored</div>
                        </div>
                    </div>
                    <div class="spms-overview-value">{{ $pendingCount }}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="spms-overview-card spms-overview-card--submitted">
                    <div class="spms-overview-head">
                        <span class="spms-overview-icon">
                            <i class="cil-description"></i>
                        </span>
                        <div>
                            <div class="spms-overview-label">For Final Check</div>
                            <div class="spms-overview-meta spms-overview-meta--top">Submitted and ready to finalize</div>
                        </div>
                    </div>
                    <div class="spms-overview-value">{{ $submittedCount }}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="spms-overview-card spms-overview-card--final">
                    <div class="spms-overview-head">
                        <span class="spms-overview-icon">
                            <i class="cil-check-circle"></i>
                        </span>
                        <div>
                            <div class="spms-overview-label">Completed</div>
                            <div class="spms-overview-meta spms-overview-meta--top">Fully finalized scorecards</div>
                        </div>
                    </div>
                    <div class="spms-overview-value">{{ $finalCount }}</div>
                </div>
            </div>
        </div>

        <x-ui.table-card
            title="Scorecards"
            subtitle="Employee scorecards and current step."
            class="hrms-list-card"
        >
            <x-slot:controls>
                <x-ui.table-toolbar
                    method="GET"
                    :action="route('spms.cycle.show', $cycle->id)"
                    id="spmsCycleEmployeesFilterForm"
                    class="spms-cycle-employees-toolbar"
                    searchName="search"
                    searchLabel="Employee"
                    :searchValue="$filters['search']"
                    searchPlaceholder="Search employee"
                    submitLabel="Apply"
                >
                    <div class="ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--filter">
                        <label class="ui-toolbar__label" for="spmsDepartmentFilter">Department</label>
                        <select
                            id="spmsDepartmentFilter"
                            name="department_id"
                            class="form-control select2bs4"
                            data-toolbar-select2="1"
                            data-placeholder="All departments"
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
                    <div class="ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--filter spms-cycle-employees-toolbar__status">
                        <label class="ui-toolbar__label" for="spmsEmployeeStatusFilter">Step</label>
                        <select
                            class="form-control select2bs4"
                            id="spmsEmployeeStatusFilter"
                            data-toolbar-select2="1"
                            data-placeholder="All steps"
                            data-allow-clear="1"
                        >
                            <option value=""></option>
                            <option value="pending">In Progress</option>
                            <option value="submitted">For Final Check</option>
                            <option value="final">Completed</option>
                        </select>
                    </div>
                </x-ui.table-toolbar>
            </x-slot:controls>

            <table
                class="table table-hover align-middle mb-0 hrms-table hrms-list-table"
                id="spmsEmployeesTable"
            >
                <thead class="bg-light text-uppercase small font-weight-bold">
                    <tr>
                        <th class="pl-4 py-3">Employee</th>
                        <th class="text-center">Step</th>
                        <th class="text-center">Total Score</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody id="spmsEmployeesTableBody">
                    @forelse ($employees as $employee)
                        @php
                        $evaluation = $evaluationMap->get($employee->id);
                        $status = $evaluation?->status ?? 'pending';
                        $badgeVariant = match ($status) {
                            'pending' => 'secondary',
                            'submitted' => 'info',
                            'final' => 'success',
                            default => 'secondary',
                        };
                        $statusText = match ($status) {
                            'pending' => 'in progress',
                            'submitted' => 'for final check',
                            'final' => 'completed',
                            default => $status,
                        };
                    @endphp
                        <tr data-status="{{ strtolower($status) }}">
                            <td class="pl-4">
                                <div class="font-weight-bold text-dark">
                                    {{ trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')) }}
                                </div>
                                <small class="text-muted"
                                    >#{{ $employee->employee_id }} &middot; {{ $employee->department?->department ?? 'No Department' }}</small
                                >
                            </td>
                            <td class="text-center">
                                <x-ui.status-badge
                                    class="px-3 py-2 text-uppercase"
                                    :status="$status"
                                    :text="$statusText"
                                    :variant="$badgeVariant"
                                />
                            </td>
                            <td class="text-center">
                                {{ $evaluation ? number_format((float) $evaluation->total_score, 2) : '-' }}
                            </td>
                            <td class="text-center">
                                <div
                                    class="crud-actions justify-content-center"
                                >
                                    <x-ui.button
                                        type="view"
                                        size="sm"
                                        :href="route('spms.evaluation.show', [$employee->id, $cycle->id])"
                                        aria-label="View Evaluation"
                                        title="View Evaluation"
                                    />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center py-5">
                                <div class="spms-empty-state">
                                    <i class="cil-people"></i>
                                    <h6>No employees found</h6>
                                    <p class="mb-0">Adjust filters to load employee records for this cycle.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if ($employees->hasPages())
                <x-slot:footer>
                    <div class="d-flex justify-content-end">
                        {{ $employees->links() }}
                    </div>
                </x-slot:footer>
            @endif
        </x-ui.table-card>
    </div>
@endsection
