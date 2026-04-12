@extends ('layouts.admin')

@section ('content')
    <div class="container-fluid pt-4 spms-page" id="spmsEvaluationsIndexPage">
        <x-page-header
            eyebrow="Performance"
            title="Evaluations"
            subtitle="Track evaluation progress, final scores, and completion across SPMS cycles."
        />

        <div class="row spms-overview-row">
            <div class="col-md-4">
                <div class="spms-overview-card">
                    <div class="spms-overview-label">Evaluations</div>
                    <div class="spms-overview-value">
                        {{ $evaluations->total() }}
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="spms-overview-card">
                    <div class="spms-overview-label">Submitted</div>
                    <div class="spms-overview-value">
                        {{ $evaluations->getCollection()->where('status', 'submitted')->count() }}
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="spms-overview-card">
                    <div class="spms-overview-label">Finalized</div>
                    <div class="spms-overview-value">
                        {{ $evaluations->getCollection()->where('status', 'final')->count() }}
                    </div>
                </div>
            </div>
        </div>

        <x-ui.table-card
            title="Evaluation List"
            subtitle="Track evaluation progress, final scores, and completion status."
            class="hrms-list-card spms-table-card"
        >
            <x-slot:controls>
                <x-ui.table-toolbar as="div" class="spms-evaluations-toolbar">
                    <div
                        class="ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--search"
                    >
                        <label
                            class="form-label"
                            for="spmsEvaluationsSearchInput"
                            >Search</label
                        >
                        <input
                            id="spmsEvaluationsSearchInput"
                            type="search"
                            class="form-control form-control-sm"
                            placeholder="Search"
                        />
                    </div>
                    <div class="ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--filter">
                        <label
                            class="form-label"
                            for="spmsEvaluationsStatusFilter"
                            >Status</label
                        >
                        <select
                            id="spmsEvaluationsStatusFilter"
                            class="form-control form-control-sm select2bs4"
                            data-placeholder="All statuses"
                        >
                            <option value="">All</option>
                            <option value="pending">Pending</option>
                            <option value="submitted">Submitted</option>
                            <option value="final">Finalized</option>
                        </select>
                    </div>
                    <div class="ui-toolbar__field ui-table-toolbar-field">
                        <label
                            class="form-label"
                            for="spmsEvaluationsCycleFilter"
                            >Cycle</label
                        >
                        <input
                            id="spmsEvaluationsCycleFilter"
                            type="text"
                            class="form-control form-control-sm"
                            placeholder="Cycle"
                        />
                    </div>
                    <div
                        class="ui-toolbar__field ui-toolbar__field--action ui-table-toolbar-field ui-table-toolbar-field--action"
                    >
                        <x-ui.button
                            type="button"
                            variant="primary"
                            size="sm"
                            id="spmsEvaluationsApply"
                        >
                            Apply</x-ui.button
                        >
                    </div>
                </x-ui.table-toolbar>
            </x-slot:controls>

            <table
                class="table table-hover align-middle mb-0 hrms-list-table hrms-table"
            >
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Cycle</th>
                        <th>Status</th>
                        <th>Score</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody id="spmsEvaluationsTableBody">
                    @forelse ($evaluations as $evaluation)
                        @php
                        $status = (string) $evaluation->status;
                        $employeeName = trim(($evaluation->employee?->first_name ?? '') . ' ' . ($evaluation->employee?->last_name ?? ''));
                        $cycleTitle = $evaluation->cycle?->title ?? 'SPMS Cycle';
                    @endphp
                        <tr
                            data-search="{{ strtolower(trim($employeeName . ' ' . ($evaluation->employee?->employee_id ?? '') . ' ' . $cycleTitle)) }}"
                            data-status="{{ strtolower($status) }}"
                            data-cycle="{{ strtolower($cycleTitle) }}"
                        >
                            <td>
                                <div class="font-weight-bold">
                                    {{ $employeeName }}
                                </div>
                                <small
                                    class="text-muted"
                                    >{{ $evaluation->employee?->employee_id }}</small
                                >
                            </td>
                            <td>{{ $cycleTitle }}</td>
                            <td class="text-center">
                                <x-ui.table-status-badge
                                    status="{{ $status === 'final' ? 'finalized' : $status }}"
                                />
                            </td>
                            <td class="text-right">
                                {{ number_format((float) $evaluation->total_score, 2) }}
                            </td>
                            <td class="text-center">
                                <div
                                    class="crud-actions justify-content-center"
                                >
                                    <x-ui.button
                                        type="view"
                                        size="sm"
                                        :href="route('spms.evaluations.show', $evaluation->id)"
                                        aria-label="View Evaluation"
                                        title="View Evaluation"
                                    />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-4">
                                <div class="hrms-empty-state">
                                    <div class="hrms-empty-state__icon">
                                        <i class="cil-chart-line"></i>
                                    </div>
                                    <div class="hrms-empty-state__title">No SPMS Evaluations Found</div>
                                    <div class="hrms-empty-state__text">No SPMS evaluations match the current scope.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <x-slot:footer>
                {{ $evaluations->links() }}
            </x-slot:footer>
        </x-ui.table-card>
    </div>
@endsection
