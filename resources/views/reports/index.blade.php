@extends ('layouts.admin')

@section ('content')
    <div class="container-fluid reports-workspace" id="reportsWorkspace">
        <div class="reports-print-heading d-none" id="reportsPrintHeading">
            <h2 class="reports-print-title" id="reportsPrintTitle">Report Summary</h2>
            <p class="reports-print-meta" id="reportsPrintMeta"></p>
        </div>
        <x-page-header
            eyebrow="Reports"
            title="Reports Console"
            subtitle="Export-ready HR reports."
        >
            <x-slot:actions>
                <x-ui.button
                    type="print"
                    variant="outline-light"
                    size="sm"
                    id="printReportHero"
                >
                    Print
                </x-ui.button>
            </x-slot:actions>
        </x-page-header>

        <div class="card reports-filter-card mb-3">
            <div class="card-body py-3">
                <div class="ui-table-card__controls ui-table-card__actions ui-table-card__actions--toolbar reports-filter-dock">
                    @php $showReportAdvancedFilters = false; @endphp
                    <x-ui.table-toolbar
                        as="div"
                        class="reports-global-filters"
                        id="reportsGlobalFilters"
                    >
                        <div class="reports-toolbar-shell">
                            <div class="reports-toolbar-primary">
                                <div
                                    class="ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--search reports-filter-field reports-filter-field--search"
                                    data-toolbar-label="Search"
                                >
                                    <label class="ui-toolbar__label" for="reportSearch"
                                        >Search</label
                                    >
                                    <input
                                        type="text"
                                        class="form-control ui-toolbar__control"
                                        id="reportSearch"
                                        placeholder="Records..."
                                    />
                                </div>

                                <div class="reports-toolbar-toggle-wrap">
                                    <label class="ui-toolbar__label reports-toolbar-toggle-label" for="reportsToolbarFiltersToggle"
                                        >Filters</label
                                    >
                                    <button
                                        type="button"
                                        class="btn btn-outline-secondary ui-toolbar__submit reports-toolbar-toggle"
                                        id="reportsToolbarFiltersToggle"
                                        data-coreui-toggle="collapse"
                                        data-coreui-target="#reportsToolbarFiltersCollapse"
                                        aria-expanded="{{ $showReportAdvancedFilters ? 'true' : 'false' }}"
                                        aria-controls="reportsToolbarFiltersCollapse"
                                    >
                                        <i class="cil-filter"></i>
                                        <span>Filters</span>
                                    </button>
                                </div>
                            </div>

                            <div id="reportsToolbarFiltersCollapse" class="reports-toolbar-panel collapse {{ $showReportAdvancedFilters ? 'show' : '' }}">
                                <div class="reports-toolbar-grid">
                                    <div
                                        class="ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--date reports-filter-field reports-filter-field--date"
                                        data-toolbar-label="Date From"
                                    >
                                        <label class="ui-toolbar__label" for="reportDateFrom"
                                            >Date From</label
                                        >
                                        <input
                                            type="date"
                                            class="form-control ui-toolbar__control"
                                            id="reportDateFrom"
                                        />
                                    </div>

                                    <div
                                        class="ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--date reports-filter-field reports-filter-field--date"
                                        data-toolbar-label="Date To"
                                    >
                                        <label class="ui-toolbar__label" for="reportDateTo"
                                            >Date To</label
                                        >
                                        <input
                                            type="date"
                                            class="form-control ui-toolbar__control"
                                            id="reportDateTo"
                                        />
                                    </div>

                                    <div
                                        class="ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--filter reports-filter-field reports-filter-field--department"
                                        data-toolbar-label="Department"
                                    >
                                        <label class="ui-toolbar__label" for="reportDepartment"
                                            >Department</label
                                        >
                                        <select
                                            class="form-control ui-toolbar__control select2bs4"
                                            id="reportDepartment"
                                            data-toolbar-select2="1"
                                            data-placeholder="All Departments"
                                            data-allow-clear="1"
                                        >
                                            <option value=""></option>
                                            @foreach ($departments as $department)
                                                <option
                                                    value="{{ strtolower(trim($department->department)) }}"
                                                >
                                                    {{ $department->department }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div
                                        class="ui-toolbar__field ui-toolbar__field--action ui-table-toolbar-field ui-table-toolbar-field--action reports-filter-field reports-filter-field--action"
                                        data-toolbar-label=""
                                    >
                                        <button
                                            type="button"
                                            class="btn btn-primary ui-toolbar__submit ui-table-standard-toolbar__submit"
                                            id="applyReportFilters"
                                        >
                                            <span>Apply</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </x-ui.table-toolbar>
                </div>
            </div>
        </div>

        <ul
            class="nav nav-tabs reports-tabs mb-3 flex-nowrap"
            id="reportsModuleTabs"
            role="tablist"
        >
            <li class="nav-item" role="presentation">
                <a
                    class="nav-link active"
                    id="tab-department-tab"
                    data-toggle="tab"
                    href="#tab-department"
                    role="tab"
                    aria-controls="tab-department"
                    aria-selected="true"
                >
                    Department Metrics
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a
                    class="nav-link"
                    id="tab-anomalies-tab"
                    data-toggle="tab"
                    href="#tab-anomalies"
                    role="tab"
                    aria-controls="tab-anomalies"
                    aria-selected="false"
                >
                    Attendance Anomalies
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a
                    class="nav-link"
                    id="tab-leaves-tab"
                    data-toggle="tab"
                    href="#tab-leaves"
                    role="tab"
                    aria-controls="tab-leaves"
                    aria-selected="false"
                >
                    Leave Summary
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a
                    class="nav-link"
                    id="tab-documents-tab"
                    data-toggle="tab"
                    href="#tab-documents"
                    role="tab"
                    aria-controls="tab-documents"
                    aria-selected="false"
                >
                    Document Expiry
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a
                    class="nav-link"
                    id="tab-travel-orders-tab"
                    data-toggle="tab"
                    href="#tab-travel-orders"
                    role="tab"
                    aria-controls="tab-travel-orders"
                    aria-selected="false"
                >
                    Travel Orders
                </a>
            </li>
        </ul>

        <div class="tab-content" id="reportsModuleTabsContent">
            <div
                class="tab-pane fade show active"
                id="tab-department"
                role="tabpanel"
                aria-labelledby="tab-department-tab"
                data-report-title="Department Metrics"
            >
                <x-ui.table-card
                    title="Department Metrics"
                    id="table-department-metrics"
                    class="report-table-card"
                >
                    <table
                        class="table table-hover align-middle mb-0 report-data-table datatable hrms-table"
                        id="departmentMetricsTable"
                        data-dt-paging="0"
                    >
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Department</th>
                                <th class="text-center">Employees</th>
                                <th class="text-center">Presence</th>
                                <th class="text-center">Leave Requests</th>
                                <th class="text-center">Leave Approved</th>
                                <th class="text-center">
                                    Document Compliance %
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($metrics as $metric)
                                @php
                                    $deptName = strtolower(trim((string) ($metric->department?->department ?? '')));
                                @endphp
                                <tr
                                    data-report-date="{{ $metric->metric_date?->toDateString() }}"
                                    data-report-department="{{ $deptName }}"
                                    data-report-status=""
                                >
                                    <td>
                                        {{ $metric->metric_date?->format('M d, Y') }}
                                    </td>
                                    <td>
                                        {{ $metric->department?->department }}
                                    </td>
                                    <td class="text-center">
                                        {{ $metric->total_employees }}
                                    </td>
                                    <td class="text-center">
                                        {{ $metric->attendance_rate ?? '-' }}
                                    </td>
                                    <td class="text-center">
                                        {{ $metric->leave_requests }}
                                    </td>
                                    <td class="text-center">
                                        {{ $metric->leave_approved }}
                                    </td>
                                    <td class="text-center">
                                        {{ $metric->document_compliance_rate }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td
                                        colspan="7"
                                        class="text-center text-muted py-4"
                                    >
                                        No metrics available.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    <x-slot:footer>
                        {{ $metrics->fragment('table-department-metrics')->links() }}
                    </x-slot:footer>
                </x-ui.table-card>
            </div>

            <div
                class="tab-pane fade"
                id="tab-anomalies"
                role="tabpanel"
                aria-labelledby="tab-anomalies-tab"
                data-report-title="Attendance Anomalies"
            >
                <x-ui.table-card
                    title="Attendance Anomalies"
                    id="table-attendance-anomalies"
                    class="report-table-card"
                >
                    <table
                        class="table table-hover align-middle mb-0 report-data-table datatable hrms-table"
                        id="attendanceAnomaliesTable"
                        data-dt-paging="0"
                    >
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Type</th>
                                <th class="text-center">Minutes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($anomalies as $anomaly)
                                @php
                                    $anomalyDepartment = strtolower(
                                        trim((string) ($anomaly->employee?->department?->department ?? ''))
                                    );
                                @endphp
                                <tr
                                    data-report-date="{{ $anomaly->date?->toDateString() }}"
                                    data-report-department="{{ $anomalyDepartment }}"
                                    data-report-status=""
                                >
                                    <td>
                                        {{ $anomaly->date?->format('M d, Y') }}
                                    </td>
                                    <td>
                                        {{ trim(($anomaly->employee->first_name ?? '') . ' ' . ($anomaly->employee->last_name ?? '')) }}
                                    </td>
                                    <td>
                                        {{ $anomaly->employee?->department?->department ?? 'N/A' }}
                                    </td>
                                    <td>
                                        {{ ucwords(str_replace('_', ' ', $anomaly->type)) }}
                                    </td>
                                    <td class="text-center">
                                        {{ $anomaly->minutes }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td
                                        colspan="5"
                                        class="text-center text-muted py-4"
                                    >
                                        No anomalies recorded.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    <x-slot:footer>
                        {{ $anomalies->fragment('table-attendance-anomalies')->links() }}
                    </x-slot:footer>
                </x-ui.table-card>
            </div>

            <div
                class="tab-pane fade"
                id="tab-leaves"
                role="tabpanel"
                aria-labelledby="tab-leaves-tab"
                data-report-title="Leave Summary"
            >
                <x-ui.table-card
                    title="Leave Summary"
                    id="table-leave-summary"
                    class="report-table-card"
                >
                    <table
                        class="table table-hover align-middle mb-0 report-data-table datatable hrms-table"
                        id="leaveSummaryTable"
                        data-dt-paging="0"
                    >
                        <thead>
                            <tr>
                                <th>Request #</th>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Leave Type</th>
                                <th>Start</th>
                                <th>End</th>
                                <th>Status</th>
                                <th class="text-right">Filed At</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($leaves as $leave)
                                @php
                                    $leaveStatus = strtolower(trim((string) $leave->status));
                                    $leaveDepartment = strtolower(
                                        trim((string) ($leave->employee?->department?->department ?? ''))
                                    );
                                @endphp
                                <tr
                                    data-report-date="{{ $leave->created_at?->toDateString() }}"
                                    data-report-department="{{ $leaveDepartment }}"
                                    data-report-status="{{ $leaveStatus }}"
                                >
                                    <td>{{ $leave->id }}</td>
                                    <td>
                                        {{ trim(($leave->employee->first_name ?? '') . ' ' . ($leave->employee->last_name ?? '')) }}
                                    </td>
                                    <td>
                                        {{ $leave->employee?->department?->department ?? 'N/A' }}
                                    </td>
                                    <td>{{ $leave->leaveType?->name }}</td>
                                    <td>
                                        {{ $leave->start_date?->format('M d, Y') }}
                                    </td>
                                    <td>
                                        {{ $leave->end_date?->format('M d, Y') }}
                                    </td>
                                    <td class="text-center">
                                        <x-ui.table-status-badge
                                            :status="$leave->status"
                                        />
                                    </td>
                                    <td class="text-right">
                                        {{ $leave->created_at?->format('M d, Y H:i') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td
                                        colspan="8"
                                        class="text-center text-muted py-4"
                                    >
                                        No leave records found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    <x-slot:footer>
                        {{ $leaves->fragment('table-leave-summary')->links() }}
                    </x-slot:footer>
                </x-ui.table-card>
            </div>

            <div
                class="tab-pane fade"
                id="tab-documents"
                role="tabpanel"
                aria-labelledby="tab-documents-tab"
                data-report-title="Document Expiry"
            >
                <x-ui.table-card
                    title="Document Expiry"
                    id="table-document-expiry"
                    class="report-table-card"
                >
                    <table
                        class="table table-hover align-middle mb-0 report-data-table datatable hrms-table"
                        id="documentExpiryTable"
                        data-dt-paging="0"
                    >
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Document</th>
                                <th class="text-right">Expires</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($documents as $document)
                                @php
                                    $statusLabel = 'Valid';
                                    $statusClass = 'badge-success';

                                    if ($document->expires_at) {
                                        if ($document->expires_at->isPast()) {
                                            $statusLabel = 'Expired';
                                            $statusClass = 'badge-danger';
                                        } elseif ($document->expires_at->lte(now()->addDays(30))) {
                                            $statusLabel = 'Expiring Soon';
                                            $statusClass = 'badge-warning';
                                        }
                                    }

                                    $documentDepartment = strtolower(
                                        trim((string) ($document->employee?->department?->department ?? ''))
                                    );
                                    $documentStatus = strtolower(trim($statusLabel));
                                @endphp
                                <tr
                                    data-report-date="{{ $document->expires_at?->toDateString() }}"
                                    data-report-department="{{ $documentDepartment }}"
                                    data-report-status="{{ $documentStatus }}"
                                >
                                    <td>
                                        {{ trim(($document->employee->first_name ?? '') . ' ' . ($document->employee->last_name ?? '')) }}
                                    </td>
                                    <td>
                                        {{ $document->employee?->department?->department ?? 'N/A' }}
                                    </td>
                                    <td>
                                        {{ $document->documents?->document }}
                                    </td>
                                    <td class="text-right">
                                        {{ $document->expires_at?->format('M d, Y') }}
                                    </td>
                                    <td class="text-center">
                                        <x-ui.status-badge
                                            status="{{ $statusLabel }}"
                                            class="px-3 py-2"
                                        />
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td
                                        colspan="5"
                                        class="text-center text-muted py-4"
                                    >
                                        No expiring documents.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    <x-slot:footer>
                        {{ $documents->fragment('table-document-expiry')->links() }}
                    </x-slot:footer>
                </x-ui.table-card>
            </div>

            <div
                class="tab-pane fade"
                id="tab-travel-orders"
                role="tabpanel"
                aria-labelledby="tab-travel-orders-tab"
                data-report-title="Travel Orders"
            >
                <x-ui.table-card
                    title="Travel Orders"
                    id="table-travel-orders"
                    class="report-table-card"
                >
                    <table
                        class="table table-hover align-middle mb-0 report-data-table datatable hrms-table"
                        id="travelOrdersTable"
                        data-dt-paging="0"
                    >
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Destination</th>
                                <th>Date Range</th>
                                <th>Status</th>
                                <th class="text-right">Submitted</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($travelOrders as $travelOrder)
                                @php
                                    $travelDepartment = strtolower(
                                        trim((string) ($travelOrder->employee?->department?->department ?? ''))
                                    );
                                    $travelStatus = strtolower(trim((string) $travelOrder->status));
                                @endphp
                                <tr
                                    data-report-date="{{ $travelOrder->date_from?->toDateString() }}"
                                    data-report-department="{{ $travelDepartment }}"
                                    data-report-status="{{ $travelStatus }}"
                                >
                                    <td>
                                        {{ trim(($travelOrder->employee->first_name ?? '') . ' ' . ($travelOrder->employee->last_name ?? '')) }}
                                    </td>
                                    <td>
                                        {{ $travelOrder->employee?->department?->department ?? 'N/A' }}
                                    </td>
                                    <td>
                                        <div>
                                            {{ $travelOrder->destination }}
                                        </div>
                                        <div class="text-muted small">
                                            {{ \Illuminate\Support\Str::limit($travelOrder->purpose, 80) }}
                                        </div>
                                    </td>
                                    <td>
                                        {{ $travelOrder->date_from?->format('M d, Y') }} to {{ $travelOrder->date_to?->format('M d, Y') }}
                                    </td>
                                    <td class="text-center">
                                        <x-ui.table-status-badge
                                            status="{{ $travelOrder->statusLabel() }}"
                                        />
                                    </td>
                                    <td class="text-right">
                                        {{ $travelOrder->submitted_at?->format('M d, Y H:i') ?? '-' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td
                                        colspan="6"
                                        class="text-center text-muted py-4"
                                    >
                                        No travel orders found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    <x-slot:footer>
                        {{ $travelOrders->fragment('table-travel-orders')->links() }}
                    </x-slot:footer>
                </x-ui.table-card>
            </div>
        </div>
    </div>
@endsection
