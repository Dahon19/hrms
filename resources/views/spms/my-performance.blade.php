@extends ('layouts.admin')

@section ('content')
    <div class="container-fluid pt-4 spms-page" id="myPerformancePage">
        <x-page-header
            eyebrow="Performance"
            title="Performance"
            subtitle="SPMS history, finalized ratings, and current evaluation status."
        />

        <div class="row spms-overview-row">
            <div class="col-md-4">
                <div class="spms-overview-card">
                    <div class="spms-overview-label">Performance Cycles</div>
                    <div class="spms-overview-value">
                        {{ $evaluations->total() }}
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="spms-overview-card">
                    <div class="spms-overview-label">Average Score</div>
                    <div class="spms-overview-value">
                        {{ number_format((float) $evaluations->getCollection()->avg('total_score'), 2) }}
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="spms-overview-card">
                    <div class="spms-overview-label">Finalized Records</div>
                    <div class="spms-overview-value">
                        {{ $evaluations->getCollection()->where('status', 'final')->count() }}
                    </div>
                </div>
            </div>
        </div>

        <x-ui.table-card
            title="Performance History"
            subtitle="Review finalized ratings and current evaluation status."
            class="hrms-list-card spms-table-card"
        >
            <x-slot:controls>
                <x-ui.table-toolbar as="div" class="my-performance-toolbar">
                    <div
                        class="ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--search"
                    >
                        <label class="form-label" for="myPerformanceSearchInput"
                            >Search</label
                        >
                        <input
                            id="myPerformanceSearchInput"
                            type="search"
                            class="form-control form-control-sm"
                            placeholder="Search"
                        />
                    </div>
                    <div class="ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--filter">
                        <label
                            class="form-label"
                            for="myPerformanceStatusFilter"
                            >Status</label
                        >
                        <select
                            id="myPerformanceStatusFilter"
                            class="form-control form-control-sm"
                        >
                            <option value="">All</option>
                            <option value="pending">Pending</option>
                            <option value="submitted">Submitted</option>
                            <option value="final">Finalized</option>
                        </select>
                    </div>
                    <div
                        class="ui-toolbar__field ui-toolbar__field--action ui-table-toolbar-field ui-table-toolbar-field--action"
                    >
                        <x-ui.button
                            type="button"
                            variant="primary"
                            size="sm"
                            id="myPerformanceApply"
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
                        <th>Cycle</th>
                        <th>Status</th>
                        <th>Score</th>
                        <th>Rating</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody id="myPerformanceTableBody">
                    @forelse ($evaluations as $evaluation)
                        @php
                        $status = (string) $evaluation->status;
                        $cycleTitle = $evaluation->cycle?->title ?? 'SPMS Cycle';
                        $rating = strtoupper((string) ($evaluation->rating_label ?: $scoringService->scoreLabel((float) $evaluation->total_score)));
                    @endphp
                        <tr
                            data-search="{{ strtolower(trim($cycleTitle . ' ' . $rating)) }}"
                            data-status="{{ strtolower($status) }}"
                        >
                            <td>{{ $cycleTitle }}</td>
                            <td>
                                <x-ui.status-badge
                                    class="px-3 py-2"
                                    :status="$status"
                                    :text="strtoupper((string) ($status === 'final' ? 'finalized' : $status))"
                                    :variant="$status === 'final' ? 'success' : 'info'"
                                />
                            </td>
                            <td>
                                {{ number_format((float) $evaluation->total_score, 2) }}
                            </td>
                            <td>{{ $rating }}</td>
                            <td class="text-center">
                                <div
                                    class="crud-actions justify-content-center"
                                >
                                    <x-ui.button
                                        type="view"
                                        size="sm"
                                        :href="route('spms.evaluations.show', $evaluation->id)"
                                        aria-label="View Performance Record"
                                        title="View Performance Record"
                                    />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                No SPMS history is available yet.
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
