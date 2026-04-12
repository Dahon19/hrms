@extends ('layouts.admin')

@section ('content')
    <div id="auditLogsPage">
        <x-ui.hero
            title="Audit Logs"
            subtitle="Track user activity, changes, and downloads across the system."
        >
            <x-slot:actions>
                <div class="dropdown d-inline-block">
                    <button
                        type="button"
                        class="btn btn-outline-light btn-sm px-3 dropdown-toggle"
                        data-coreui-toggle="dropdown"
                        aria-expanded="false"
                    >
                        <i class="cil-cloud-download mr-1"></i> Reports
                    </button>
                    <div class="dropdown-menu dropdown-menu-end shadow-sm">
                        <button
                            type="button"
                            class="dropdown-item js-audit-print"
                            data-print-url="{{ route('audit-logs.print') }}"
                        >
                            <i class="cil-print mr-2"></i> Print
                        </button>
                        <button
                            type="button"
                            class="dropdown-item js-audit-export"
                            data-export-url="{{ route('audit-logs.export') }}"
                        >
                            <i class="cil-description mr-2"></i> Export
                        </button>
                    </div>
                </div>
                <span class="badge audit-badge">Latest 10 per page</span>
            </x-slot:actions>
        </x-ui.hero>

        @php
            $actionOptions = $logs->pluck('action')->filter()->unique()->values();
            $userOptions = $logs->pluck('user.name')->filter()->unique()->values();
        @endphp

        <div class="row g-4">
            <div class="col-12">
                <x-ui.table-card title="Activity Log" class="audit-card">
                    <x-slot:controls>
                        <x-ui.table-toolbar as="div" class="audit-filters">
                            <div class="audit-filter ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--filter">
                                <label for="auditFilterAction">Action</label>
                                <select
                                    id="auditFilterAction"
                                    class="form-control form-control-sm select2bs4"
                                    data-toolbar-select2="1"
                                    data-placeholder="All actions"
                                    data-allow-clear="1"
                                >
                                    <option value=""></option>
                                    @foreach ($actionOptions as $action)
                                        <option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>
                                            {{ \Illuminate\Support\Str::headline($action) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="audit-filter ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--filter">
                                <label for="auditFilterUser">User</label>
                                <select
                                    id="auditFilterUser"
                                    class="form-control form-control-sm select2bs4"
                                    data-toolbar-select2="1"
                                    data-placeholder="All users"
                                    data-allow-clear="1"
                                >
                                    <option value=""></option>
                                    @foreach ($userOptions as $name)
                                        <option value="{{ $name }}" @selected(($filters['user'] ?? '') === $name)>
                                            {{ $name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="audit-filter ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--date">
                                <label for="auditFilterStart">From</label>
                                <input
                                    type="date"
                                    id="auditFilterStart"
                                    class="form-control form-control-sm"
                                    value="{{ $filters['start'] ?? '' }}"
                                />
                            </div>
                            <div class="audit-filter ui-toolbar__field ui-table-toolbar-field ui-table-toolbar-field--date">
                                <label for="auditFilterEnd">To</label>
                                <input
                                    type="date"
                                    id="auditFilterEnd"
                                    class="form-control form-control-sm"
                                    value="{{ $filters['end'] ?? '' }}"
                                />
                            </div>
                        </x-ui.table-toolbar>
                    </x-slot:controls>

                    <div class="table-responsive">
                    <table
                        class="table hrms-table audit-table"
                        id="auditLogsTable"
                        data-dt-search="0"
                        data-dt-paging="0"
                    >
                        <thead class="bg-light">
                            <tr>
                                <th>Time</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Model</th>
                                <th>Record</th>
                                <th>Summary</th>
                                <th class="text-center">Actions</th>
                                <th class="text-end">IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($logs as $log)
                                <tr
                                    data-time="{{ $log->created_at?->format('M d, Y H:i') }}"
                                    data-user="{{ $log->user?->name ?? 'System' }}"
                                    data-action="{{ \Illuminate\Support\Str::headline($log->action) }}"
                                    data-model="{{ class_basename($log->auditable_type) }}"
                                    data-record="{{ $log->auditable_id }}"
                                    data-summary="{{ $log->summary_text ?: '-' }}"
                                    data-ip="{{ $log->ip_address ?? '-' }}"
                                    data-metadata='@json($log->metadata ?? [])'
                                >
                                    <td>{{ $log->created_at?->format('M d, Y H:i') }}</td>
                                    <td>{{ $log->user?->name ?? 'System' }}</td>
                                    <td>
                                        <span class="badge badge-{{ $log->action === 'created' ? 'success' : ($log->action === 'deleted' ? 'danger' : 'info') }}">
                                            {{ \Illuminate\Support\Str::headline($log->action) }}
                                        </span>
                                    </td>
                                    <td>{{ class_basename($log->auditable_type) }}</td>
                                    <td>{{ $log->auditable_id }}</td>
                                    <td>
                                        <div class="audit-summary-text">{{ $log->summary_text ?: '-' }}</div>
                                    </td>
                                    <td class="text-center align-middle">
                                        <div class="crud-actions justify-content-center">
                                            <x-ui.button
                                                type="view"
                                                size="sm"
                                                class="view-audit-log"
                                                aria-label="View Audit Log Details"
                                                title="View Audit Log Details"
                                            />
                                        </div>
                                    </td>
                                    <td class="text-end text-muted">{{ $log->ip_address ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        No audit logs yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    </div>
                    <x-slot:footer>
                        {{ $logs->links() }}
                    </x-slot:footer>
                </x-ui.table-card>
            </div>
        </div>
    </div>
@endsection

<x-ui.modal id="auditLogDetailsModal" size="lg">
    <x-ui.modal-header title="Audit Log Details" />
    <div class="modal-body">
        <div class="audit-details-layout">
            <div class="audit-details-overview">
                <div class="audit-details-chip">
                    <span class="audit-details-label">Time</span>
                    <strong id="audit-log-details-time">-</strong>
                </div>
                <div class="audit-details-chip">
                    <span class="audit-details-label">User</span>
                    <strong id="audit-log-details-user">-</strong>
                </div>
                <div class="audit-details-chip">
                    <span class="audit-details-label">Action</span>
                    <strong id="audit-log-details-action">-</strong>
                </div>
                <div class="audit-details-chip">
                    <span class="audit-details-label">Model</span>
                    <strong id="audit-log-details-model">-</strong>
                </div>
                <div class="audit-details-chip">
                    <span class="audit-details-label">Record</span>
                    <strong id="audit-log-details-record">-</strong>
                </div>
                <div class="audit-details-chip">
                    <span class="audit-details-label">IP Address</span>
                    <strong id="audit-log-details-ip">-</strong>
                </div>
            </div>
            <div class="audit-details-panel">
                <div class="audit-details-label">Summary</div>
                <div class="audit-details-summary" id="audit-log-details-summary">-</div>
            </div>
            <div class="audit-details-panel">
                <div class="audit-details-label">Metadata</div>
                <pre class="audit-details-metadata mb-0" id="audit-log-details-metadata">-</pre>
            </div>
        </div>
    </div>
    <x-ui.modal-footer>
        <x-ui.button type="button" variant="cancel" icon="cil-x" data-coreui-dismiss="modal">Close</x-ui.button>
    </x-ui.modal-footer>
</x-ui.modal>
