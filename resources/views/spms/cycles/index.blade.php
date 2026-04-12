@extends ('layouts.admin')

@section ('content')
    <div class="container-fluid spms-page" id="spmsCyclesPage">
        <x-page-header
            eyebrow="Performance"
            title="SPMS Cycles"
            subtitle="Start, evaluate, finalize scorecards, and close each cycle."
        >
            <x-slot:actions>
                @if ($canManage)
                    <x-ui.button
                        variant="outline-light"
                        size="sm"
                        class="px-3"
                        data-toggle="modal"
                        data-target="#spmsCycleCreateModal"
                        icon="cil-plus"
                    >
                        New Cycle
                    </x-ui.button>
                @endif
            </x-slot:actions>
        </x-page-header>

        <x-ui.table-card
            title="Cycle Directory"
            subtitle="Active employees: {{ $employeeCount }}"
            class="border-0 hrms-list-card spms-cycle-card"
        >
            <x-slot:controls>
                <x-ui.table-toolbar
                    as="div"
                    class="spms-filter-bar"
                >
                    <div class="spms-filter-shell">
                        <div class="spms-filter-primary">
                            <div
                                class="ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--search spms-filter-search"
                            >
                                <label
                                    class="small text-muted mb-1 d-block"
                                    for="spmsCycleSearchInput"
                                    >Search</label
                                >
                                <input
                                    type="text"
                                    class="form-control form-control-sm"
                                    id="spmsCycleSearchInput"
                                    placeholder="Search"
                                />
                            </div>
                            <div class="spms-filter-toggle-wrap">
                                <label
                                    class="small text-muted mb-1 d-block spms-filter-toggle-label"
                                    for="spmsCyclesFiltersToggle"
                                    >Filters</label
                                >
                                <x-ui.button
                                    type="button"
                                    variant="outline-secondary"
                                    size="sm"
                                    icon="cil-filter"
                                    id="spmsCyclesFiltersToggle"
                                    class="spms-filter-toggle"
                                    data-coreui-toggle="collapse"
                                    data-coreui-target="#spmsCyclesFiltersCollapse"
                                    aria-expanded="false"
                                    aria-controls="spmsCyclesFiltersCollapse"
                                >
                                    Filters
                                </x-ui.button>
                            </div>
                        </div>
                        <div id="spmsCyclesFiltersCollapse" class="spms-filter-panel collapse">
                            <div class="offcanvas-body spms-filter-offcanvas-body">
                                <div class="spms-filter-grid">
                                    <div class="ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--filter">
                                        <label
                                            class="small text-muted mb-1 d-block"
                                            for="spmsCycleStatusFilter"
                                            >Status</label
                                        >
                                        <select
                                            class="form-control form-control-sm spms-filter-status select2bs4"
                                            id="spmsCycleStatusFilter"
                                            data-toolbar-select2="1"
                                            data-placeholder="All statuses"
                                            data-allow-clear="1"
                                        >
                                            <option value=""></option>
                                            <option value="setup">Setup</option>
                                            <option value="evaluation">Evaluation</option>
                                            <option value="closed">Closed</option>
                                        </select>
                                    </div>
                                    <div class="ui-toolbar__field ui-table-toolbar-field">
                                        <label
                                            class="small text-muted mb-1 d-block"
                                            for="spmsCyclePeriodFilter"
                                            >Year</label
                                        >
                                        <input
                                            type="text"
                                            class="form-control form-control-sm spms-filter-cycle"
                                            id="spmsCyclePeriodFilter"
                                            placeholder="Year"
                                        />
                                    </div>
                                    <div class="ui-toolbar__field ui-toolbar__field--action ui-table-toolbar-field ui-table-toolbar-field--action spms-filter-apply">
                                        <x-ui.button
                                            type="button"
                                            variant="primary"
                                            size="sm"
                                            id="spmsCyclesApply"
                                        >
                                            Apply
                                        </x-ui.button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </x-ui.table-toolbar>
            </x-slot:controls>
            <div class="table-responsive hrms-table">
                <table
                    class="table hrms-table"
                    id="spmsCyclesTable"
                    data-no-datatable="1"
                >
                    <thead
                        class="bg-light text-uppercase small font-weight-bold"
                    >
                        <tr>
                            <th class="pl-4 py-3" data-sort-key="title">
                                Cycle
                            </th>
                            <th class="text-center" data-sort-key="status">
                                Status
                            </th>
                            <th class="text-center" data-sort-key="evaluations">
                                Evaluations
                            </th>
                            <th class="text-center" data-sort-key="completion">
                                Completion
                            </th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="spmsCyclesTableBody">
                        @forelse ($cycles as $row)
                            @php
                                $cycle = $row['cycle'];
                                $completion = (float) ($row['completion_rate'] ?? 0);
                                $statusVariant = match ($cycle->status) {
                                    'setup' => 'secondary',
                                    'evaluation' => $cycle->isReadyForClosure() ? 'warning' : 'info',
                                    'closed' => 'success',
                                    default => 'secondary',
                                };
                                $statusText = match (true) {
                                    $cycle->status === 'setup' => 'start',
                                    $cycle->status === 'evaluation' && $cycle->isReadyForClosure() => 'finish',
                                    $cycle->status === 'evaluation' => 'active',
                                    $cycle->status === 'closed' => 'closed',
                                    default => $cycle->status,
                                };
                            @endphp
                            <tr
                                data-title="{{ strtolower($cycle->title) }}"
                                data-status="{{ strtolower($cycle->status) }}"
                                data-period="{{ strtolower(optional($cycle->period_start)->format('M d, Y') . ' - ' . optional($cycle->period_end)->format('M d, Y')) }}"
                                data-evaluations="{{ (int) $cycle->evaluations_count }}"
                                data-completion="{{ $completion }}"
                            >
                                <td class="pl-4">
                                    <div class="spms-cycle-title">
                                        {{ $cycle->title }}
                                    </div>
                                    <small class="spms-cycle-period"
                                        >{{ optional($cycle->period_start)->format('M d, Y') }} - {{ optional($cycle->period_end)->format('M d, Y') }}</small
                                    >
                                </td>
                                <td class="text-center">
                                    <x-ui.status-badge
                                        class="px-3 py-2 text-uppercase spms-cycle-status"
                                        :status="$cycle->status"
                                        :text="$statusText"
                                        :variant="$statusVariant"
                                    />
                                </td>
                                <td class="text-center">
                                    <div class="spms-cycle-stat">
                                        <strong
                                            >{{ (int) $cycle->evaluations_count }}</strong
                                        >
                                        <small>
                                            {{ (int) ($row['pending_count'] ?? 0) }} in progress /
                                            {{ (int) ($row['submitted_count'] ?? 0) }} ready to finalize /
                                            {{ (int) ($row['final_count'] ?? 0) }} completed
                                        </small>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="spms-progress-wrap mx-auto">
                                        <div class="progress progress-xs">
                                            <div
                                                class="progress-bar bg-primary"
                                                role="progressbar"
                                                style="width: {{ $completion }}%"
                                            ></div>
                                        </div>
                                        <small
                                            class="text-muted spms-progress-label"
                                            >{{ number_format($completion, 1) }}%
                                            complete</small
                                        >
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div
                                        class="crud-actions justify-content-center"
                                    >
                                        <x-ui.button
                                            type="view"
                                            size="sm"
                                            :href="route('spms.cycle.show', $cycle->id)"
                                            aria-label="View SPMS Cycle"
                                            title="View SPMS Cycle"
                                        />
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <div class="spms-empty-state">
                                        <i class="cil-chart-line"></i>
                                        <h6>No SPMS cycles found</h6>
                                        <p class="mb-0">Create an SPMS cycle to start performance evaluations.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($cycles->hasPages())
                <x-slot:footer>
                    <div class="hrms-list-footer d-flex justify-content-end">
                        {{ $cycles->links() }}
                    </div>
                </x-slot:footer>
            @endif
        </x-ui.table-card>
    </div>
    @if ($canManage)
        <x-modal
            id="spmsCycleCreateModal"
            title="Create SPMS Cycle"
            subtitle="Set the title and evaluation period for the new cycle."
            size="lg"
        >
            <form
                method="POST"
                action="{{ route('spms.cycles.store') }}"
            >
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label
                            >Title
                            <span class="text-danger">*</span></label
                        >
                        <input
                            type="text"
                            class="form-control"
                            name="title"
                            value="{{ old('title') }}"
                            required
                        />
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label
                                >Period Start
                                <span class="text-danger"
                                    >*</span
                                ></label
                            >
                            <input
                                type="date"
                                class="form-control"
                                name="period_start"
                                value="{{ old('period_start', now()->startOfYear()->toDateString()) }}"
                                required
                            />
                        </div>
                        <div class="form-group col-md-6">
                            <label
                                >Period End
                                <span class="text-danger"
                                    >*</span
                                ></label
                            >
                            <input
                                type="date"
                                class="form-control"
                                name="period_end"
                                value="{{ old('period_end', now()->endOfYear()->toDateString()) }}"
                                required
                            />
                        </div>
                    </div>
                </div>
                <x-ui.modal-footer>
                    <x-ui.button variant="light" data-coreui-dismiss="modal">
                        Cancel
                    </x-ui.button>
                    <x-ui.button
                        type="submit"
                        variant="primary"
                        icon="cil-save"
                    >
                        Save Cycle
                    </x-ui.button>
                </x-ui.modal-footer>
            </form>
        </x-modal>
    @endif
@endsection
