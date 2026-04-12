@extends ('layouts.admin')
@section ('content')
    @php
        $hasRevisionErrors = old('revision_action') && (
            $errors->has('suggested_start_date')
            || $errors->has('suggested_end_date')
            || $errors->has('notes')
            || $errors->has('status')
        );
        $hasPresidentDecisionErrors = old('president_action') && (
            $errors->has('status')
            || $errors->has('notes')
        );
    @endphp
    <div
        class="container-fluid pt-3"
        id="leaveApprovalsPage"
        data-has-revision-errors="{{ $hasRevisionErrors ? '1' : '0' }}"
        data-revision-action="{{ old('revision_action', '') }}"
        data-revision-start="{{ old('suggested_start_date', '') }}"
        data-revision-end="{{ old('suggested_end_date', '') }}"
        data-revision-notes="{{ old('notes', '') }}"
        data-has-president-errors="{{ $hasPresidentDecisionErrors ? '1' : '0' }}"
        data-president-action="{{ old('president_action', '') }}"
        data-president-status="{{ old('status', 'Declined') }}"
        data-president-notes="{{ old('notes', '') }}"
    >
        @php $privateUrl = function (?string $path) { if (!$path) { return null; } $parts = explode('/', $path); if (count($parts) < 3) { return null; } return route('storage.file', [ 'folder' => $parts[0], 'subfolder' => $parts[1], 'filename' => $parts[2], ]); }; @endphp
        <x-page-header
            eyebrow="Operations"
            :title="($isPresidentApprover ?? false) ? 'Leave Monitoring' : 'Approval Queue'"
            :subtitle="($isPresidentApprover ?? false) ? 'View employees currently on leave and leave records history.' : 'Review pending requests, inspect details, and keep decisions consistent.'"
        />
        <x-ui.table-card
            title="Leave Approvals"
            :subtitle="($isPresidentApprover ?? false) ? 'Employees currently on leave and leave records history.' : 'Pending requests and leave decision history.'"
            :responsive="false"
            class="hrms-list-card"
        >
            <x-slot:controls>
                <x-ui.table-toolbar
                    method="GET"
                    :action="route('leaves.approvals')"
                    class="leave-approvals-toolbar"
                >
                    <div class="">
                        <ul
                            class="nav nav-pills small align-items-center leave-pill-tabs"
                            id="leaveTabs"
                            role="tablist"
                        >
                            <li class="nav-item">
                                <a
                                    class="nav-link active"
                                    data-toggle="tab"
                                    href="#head-pending"
                                    role="tab"
                                >
                                    {{ ($isPresidentApprover ?? false) ? 'On Leave' : 'Pending' }}
                                    <span
                                        class="badge badge-primary ml-1"
                                        >{{ $pendingRequests->count() }}</span
                                    >
                                </a>
                            </li>
                            <li class="nav-item">
                                <a
                                    class="nav-link"
                                    data-toggle="tab"
                                    href="#head-history"
                                    role="tab"
                                    >History</a
                                >
                            </li>
                        </ul>
                    </div>
                </x-ui.table-toolbar>
            </x-slot:controls>
            <div class="tab-content">
                <div
                    class="tab-pane fade show active"
                    id="head-pending"
                    role="tabpanel"
                >
                    <table
                        class="table table-hover mb-0 align-middle datatable hrms-list-table hrms-table"
                    >
                        <thead
                            class="bg-light text-uppercase small font-weight-bold"
                        >
                            <tr>
                                <th class="pl-4 py-3">Employee</th>
                                @if ($isPresidentApprover ?? false || $isHrHead ?? false)
                                    <th>Department</th>
                                @endif
                                <th>Leave Type</th>
                                <th>Schedule</th>
                                <th>Reason</th>
                                <th>Attachment</th>
                                <th class="text-center">
                                    {{ ($isPresidentApprover ?? false) ? 'Status' : 'Quick Actions' }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($pendingRequests as $request)
                                @php $attachmentUrl = $privateUrl($request->attachment_path); @endphp
                                @php $statusDisplay = $request->status === 'Approved' ? 'Head Approved' : (in_array($request->status, ['Declined', 'HR Declined'], true) ? 'Needs Revision' : $request->status); @endphp
                                <tr
                                    class="leave-row approval-row clickable-row"
                                    style="cursor: pointer"
                                    data-employee="{{ $request->employee->first_name }} {{ $request->employee->last_name }}"
                                    data-department="{{ $request->employee->department->department ?? '-' }}"
                                    data-type="{{ $request->leaveType->name ?? '-' }}"
                                    data-dates="{{ $request->start_date->format('M d, Y') }} - {{ $request->end_date->format('M d, Y') }}"
                                    data-status="{{ $statusDisplay }}"
                                    data-reason="{{ $request->reason ?? '-' }}"
                                    data-attachment="{{ $request->leaveType?->requires_attachment && $attachmentUrl ? $attachmentUrl : '' }}"
                                >
                                    <td class="pl-4">
                                        <div class="font-weight-bold text-dark">
                                            {{ $request->employee->first_name }} {{ $request->employee->last_name }}
                                        </div>
                                        <small class="text-muted"
                                            >ID: {{ $request->employee->employee_id }}</small
                                        >
                                    </td>
                                    @if ($isPresidentApprover ?? false || $isHrHead ?? false)
                                        <td class="align-middle text-muted">
                                            {{ $request->employee->department->department ?? '-' }}
                                        </td>
                                    @endif
                                    <td class="align-middle">
                                        <span
                                            class="badge badge-light border"
                                            >{{ $request->leaveType->name ?? '-' }}</span
                                        >
                                    </td>
                                    <td class="align-middle">
                                        <div
                                            class="small font-weight-bold text-dark"
                                        >
                                            {{ $request->start_date->format('M d, Y') }}
                                        </div>
                                        <div class="text-xs text-muted">
                                            to {{ $request->end_date->format('M d, Y') }}
                                        </div>
                                    </td>
                                    <td class="align-middle text-muted small">
                                        {{ \Illuminate\Support\Str::limit($request->reason ?: '-', 90) }}
                                    </td>
                                    <td class="align-middle">
                                        @if ($attachmentUrl)
                                            <a
                                                href="{{ $attachmentUrl }}"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="btn btn-sm btn-outline-primary"
                                            >
                                                View File
                                            </a>
                                        @else
                                            <span class="text-muted small">-</span>
                                        @endif
                                    </td>
                                    <td class="text-center align-middle">
                                        @if ($isPresidentApprover ?? false)
                                            @if ($request->status === 'HR Approved')
                                                <div class="crud-actions justify-content-center">
                                                    <form
                                                        action="{{ route('leaves.president.approve', $request) }}"
                                                        method="POST"
                                                        class="d-inline"
                                                        data-confirm-message="Approve this leave request as President?"
                                                        data-confirm-title="Approve Leave Request"
                                                        data-confirm-label="Approve"
                                                        data-confirm-variant="primary"
                                                    >
                                                        @csrf
                                                        <x-ui.button
                                                            type="submit"
                                                            variant="approve"
                                                            size="sm"
                                                            aria-label="Approve Leave Request"
                                                            title="Approve Leave Request"
                                                        />
                                                    </form>
                                                    <x-ui.button
                                                        type="decline"
                                                        size="sm"
                                                        icon="cil-x-circle"
                                                        aria-label="Decline Request"
                                                        title="Decline Request"
                                                        data-toggle="modal"
                                                        data-target="#presidentDecisionModal"
                                                        data-coreui-toggle="modal"
                                                        data-coreui-target="#presidentDecisionModal"
                                                        data-action="{{ route('leaves.president.decline', $request) }}"
                                                    />
                                                </div>
                                            @else
                                                <span class="text-muted small">-</span>
                                            @endif
                                        @else
                                            <div class="crud-actions justify-content-center">
                                                @if ($request->status === 'Pending')
                                                    <form
                                                        action="{{ route('leaves.head.approve', $request) }}"
                                                        method="POST"
                                                        class="d-inline"
                                                        data-confirm-message="Approve this leave request?"
                                                        data-confirm-title="Approve Leave Request"
                                                        data-confirm-label="Approve"
                                                        data-confirm-variant="primary"
                                                    >
                                                        @csrf
                                                        <x-ui.button
                                                            type="submit"
                                                            variant="approve"
                                                            size="sm"
                                                            aria-label="Approve Leave Request"
                                                            title="Approve Leave Request"
                                                        />
                                                    </form>
                                                    <x-ui.button
                                                        type="decline"
                                                        size="sm"
                                                        icon="cil-reload"
                                                        aria-label="Return for Revision"
                                                        title="Return for Revision"
                                                        data-toggle="modal"
                                                        data-target="#headRevisionModal"
                                                        data-coreui-toggle="modal"
                                                        data-coreui-target="#headRevisionModal"
                                                        data-action="{{ route('leaves.head.decline', $request) }}"
                                                        data-start="{{ $request->start_date->toDateString() }}"
                                                        data-end="{{ $request->end_date->toDateString() }}"
                                                    />
                                                @elseif ($request->status === 'Approved')
                                                    <form
                                                        action="{{ route('leaves.hr.approve', $request) }}"
                                                        method="POST"
                                                        class="d-inline"
                                                        data-confirm-message="Approve this leave request?"
                                                        data-confirm-title="Approve Leave Request"
                                                        data-confirm-label="Approve"
                                                        data-confirm-variant="primary"
                                                    >
                                                        @csrf
                                                        <x-ui.button
                                                            type="submit"
                                                            variant="approve"
                                                            size="sm"
                                                            aria-label="Approve Leave Request"
                                                            title="Approve Leave Request"
                                                        />
                                                    </form>
                                                    <x-ui.button
                                                        type="decline"
                                                        size="sm"
                                                        icon="cil-reload"
                                                        aria-label="Return for Revision"
                                                        title="Return for Revision"
                                                        data-toggle="modal"
                                                        data-target="#headRevisionModal"
                                                        data-coreui-toggle="modal"
                                                        data-coreui-target="#headRevisionModal"
                                                        data-action="{{ route('leaves.hr.decline', $request) }}"
                                                        data-start="{{ $request->start_date->toDateString() }}"
                                                        data-end="{{ $request->end_date->toDateString() }}"
                                                    />
                                                @else
                                                    <span class="text-muted small">-</span>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ ($isPresidentApprover ?? false || $isHrHead ?? false) ? 7 : 6 }}" class="text-center py-5">
                                        <i
                                            class="cil-check-circle fs-3 text-muted mb-2"
                                        ></i>
                                        <p class="text-muted mb-0">No pending requests to review.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="tab-pane fade" id="head-history" role="tabpanel">
                    <table
                        class="table table-hover mb-0 align-middle datatable hrms-list-table hrms-table"
                    >
                        <thead
                            class="bg-light text-uppercase small font-weight-bold"
                        >
                            <tr>
                                <th class="pl-4 py-3">Employee</th>
                                <th>Department</th>
                                <th>Type</th>
                                <th>Date Range</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($historyRequests as $request)
                                @php $attachmentUrl = $privateUrl($request->attachment_path); @endphp
                                @php $statusDisplay = $request->status === 'Approved' ? 'Head Approved' : (in_array($request->status, ['Declined', 'HR Declined'], true) ? 'Needs Revision' : $request->status); @endphp
                                <tr
                                    class="leave-row approval-row"
                                    data-employee="{{ $request->employee->first_name }} {{ $request->employee->last_name }}"
                                    data-department="{{ $request->employee->department->department ?? '-' }}"
                                    data-type="{{ $request->leaveType->name ?? '-' }}"
                                    data-dates="{{ $request->start_date->format('M d, Y') }} - {{ $request->end_date->format('M d, Y') }}"
                                    data-status="{{ $statusDisplay }}"
                                    data-reason="{{ $request->reason ?? '-' }}"
                                    data-attachment="{{ $request->leaveType?->requires_attachment && $attachmentUrl ? $attachmentUrl : '' }}"
                                >
                                    <td class="pl-4 font-weight-bold">
                                        {{ $request->employee->first_name }} {{ $request->employee->last_name }}
                                    </td>
                                    <td class="text-muted">
                                        {{ $request->employee->department->department ?? '-' }}
                                    </td>
                                    <td>
                                        <span
                                            class="badge badge-light border"
                                            >{{ $request->leaveType->name ?? '-' }}</span
                                        >
                                    </td>
                                    <td class="small">
                                        {{ $request->start_date->format('M d, Y') }} - {{ $request->end_date->format('M d, Y') }}
                                    </td>
                                    <td class="text-center">
                                        <span
                                            class="badge badge-{{ $request->status === 'HR Approved' ? 'success' : (in_array($request->status, ['Declined', 'HR Declined'], true) ? 'warning' : 'info') }} px-3 py-2"
                                        >
                                            {{ $statusDisplay }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td
                                        colspan="5"
                                        class="text-center py-5 text-muted"
                                    >
                                        No history found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </x-ui.table-card>
    </div>
    <x-modal
        id="leaveApprovalModal"
        title="Review Leave Details"
        subtitle="Review the employee request before taking approval action."
    >
                <div class="modal-body p-4">
                    <div class="row mb-3 pb-3 border-bottom">
                        <div class="col-12">
                            <label
                                class="text-xs text-uppercase text-muted font-weight-bold"
                                >Employee Information</label
                            >
                            <h5
                                class="text-primary font-weight-bold mb-0"
                                id="leave-employee-approval"
                            ></h5>
                            <p class="text-muted small mb-0" id="leave-department-approval"></p>
                        </div>
                    </div>
                    <div class="row mb-4">
                        <div class="col-6 border-right">
                            <label
                                class="text-xs text-uppercase text-muted font-weight-bold d-block"
                                >Leave Type</label
                            >
                            <span
                                class="font-weight-bold text-dark"
                                id="leave-type-approval"
                            ></span>
                        </div>
                        <div class="col-6 pl-3">
                            <label
                                class="text-xs text-uppercase text-muted font-weight-bold d-block"
                                >Schedule</label
                            >
                            <span
                                class="font-weight-bold text-dark"
                                id="leave-dates-approval"
                            ></span>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label
                            class="text-xs text-uppercase text-muted font-weight-bold"
                            >Reason for Leave</label
                        >
                        <div
                            class="bg-light p-3 rounded text-dark italic"
                            id="leave-reason-approval"
                            style="font-style: italic"
                        ></div>
                    </div>
                    <div class="row align-items-center">
                        <div class="col-6">
                            <label
                                class="text-xs text-uppercase text-muted font-weight-bold d-block"
                                >Attachment</label
                            >
                            <div id="leave-attachment-approval"></div>
                        </div>
                        <div class="col-6 text-right">
                            <span
                                class="badge px-3 py-2"
                                id="leave-status-badge-approval"
                            ></span>
                        </div>
                    </div>
                </div>
    </x-modal>
    @if (!($isPresidentApprover ?? false))
        <x-modal
            id="headRevisionModal"
            title="Suggest Revision"
            subtitle="Propose schedule changes and provide revision notes."
        >
                    <form id="headRevisionForm" method="POST" action="#">
                        @csrf
                        <input
                            type="hidden"
                            id="revision_action"
                            name="revision_action"
                            value="{{ old('revision_action', '') }}"
                        />
                        <div class="modal-body">
                            @if ($hasRevisionErrors)
                                <div class="alert alert-danger py-2 px-3 mb-3 small" role="alert">
                                    Please review and correct the highlighted fields.
                                </div>
                            @endif
                            <div class="form-group">
                                <label for="suggest_start_date"
                                    >Suggested Start Date</label
                                >
                                <input
                                    type="date"
                                    id="suggest_start_date"
                                    name="suggested_start_date"
                                    value="{{ old('suggested_start_date', '') }}"
                                    class="form-control @error('suggested_start_date') is-invalid @enderror"
                                />
                                @error ('suggested_start_date')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="suggest_end_date"
                                    >Suggested End Date</label
                                >
                                <input
                                    type="date"
                                    id="suggest_end_date"
                                    name="suggested_end_date"
                                    value="{{ old('suggested_end_date', '') }}"
                                    class="form-control @error('suggested_end_date') is-invalid @enderror"
                                />
                                @error ('suggested_end_date')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group mb-0">
                                <label for="suggest_notes"
                                    >Notes</label
                                >
                                <textarea
                                    id="suggest_notes"
                                    name="notes"
                                    rows="3"
                                    required
                                    class="form-control @error('notes') is-invalid @enderror"
                                    placeholder="Reason for revision"
                                >{{ old('notes', '') }}</textarea>
                                @error ('notes')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <x-ui.modal-footer>
                            <x-ui.button
                                variant="light"
                                class="btn btn-light btn-sm"
                                data-dismiss="modal"
                            >
                                Cancel
                            </x-ui.button>
                            <x-ui.button
                                type="submit"
                                variant="primary"
                                icon="cil-paper-plane"
                            >
                                Send Revision
                            </x-ui.button>
                        </x-ui.modal-footer>
                    </form>
        </x-modal>
    @endif
    @if ($isPresidentApprover ?? false)
        <x-modal
            id="presidentDecisionModal"
            title="President Decision"
            subtitle="Set the request outcome and provide final notes."
        >
                    <form id="presidentDecisionForm" method="POST" action="#">
                        @csrf
                        <input
                            type="hidden"
                            id="president_action"
                            name="president_action"
                            value="{{ old('president_action', '') }}"
                        />
                        <div class="modal-body">
                            @if ($hasPresidentDecisionErrors)
                                <div class="alert alert-danger py-2 px-3 mb-3 small" role="alert">
                                    Please review and correct the highlighted fields.
                                </div>
                            @endif
                            <div class="form-group">
                                <label for="president_status">Decision</label>
                                <select
                                    id="president_status"
                                    name="status"
                                    class="form-control @error('status') is-invalid @enderror"
                                >
                                    <option value="Declined" {{ old('status', 'Declined') === 'Declined' ? 'selected' : '' }}>Declined</option>
                                    <option value="Needs Revision" {{ old('status') === 'Needs Revision' ? 'selected' : '' }}>Needs Revision</option>
                                </select>
                                @error ('status')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group mb-0">
                                <label for="president_notes">Notes</label>
                                <textarea
                                    id="president_notes"
                                    name="notes"
                                    rows="3"
                                    required
                                    class="form-control @error('notes') is-invalid @enderror"
                                    placeholder="Add final notes or guidance"
                                >{{ old('notes', '') }}</textarea>
                                @error ('notes')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <x-ui.modal-footer>
                            <x-ui.button
                                variant="light"
                                class="btn btn-light btn-sm"
                                data-dismiss="modal"
                            >
                                Cancel
                            </x-ui.button>
                            <x-ui.button
                                type="submit"
                                variant="danger"
                                icon="cil-x-circle"
                            >
                                Submit Decision
                            </x-ui.button>
                        </x-ui.modal-footer>
                    </form>
        </x-modal>
    @endif
    <x-modal
        id="approveConfirmModal"
        title="Confirm Approval"
        subtitle="Confirm approval for this leave request."
        size="md"
    >
                <div class="modal-body">
                    <p class="mb-0 text-muted">Confirm approval for this leave request?</p>
                </div>
                <x-ui.modal-footer>
                    <x-ui.button
                        variant="light"
                        class="btn btn-light btn-sm"
                        data-dismiss="modal"
                    >
                        Cancel
                    </x-ui.button>
                    <x-ui.button
                        variant="primary"
                        id="approveConfirmBtn"
                        icon="cil-check"
                    >
                        Approve
                    </x-ui.button>
                </x-ui.modal-footer>
    </x-modal>
@endsection
