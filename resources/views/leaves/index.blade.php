@extends ('layouts.admin')
@section ('content')
    <div
        class="container-fluid"
        id="leavesIndexPage"
        data-has-errors="{{ $errors->any() ? '1' : '0' }}"
        data-request-error="{{ $errors->any() && (old('leave_type_id') || old('start_date') || old('end_date') || old('reason')) ? '1' : '0' }}"
        data-edit-error="{{ $errors->any() && old('form_context') === 'leave_edit' ? '1' : '0' }}"
    >
        @php $privateUrl = function (?string $path) { if (!$path) { return null; } $parts = explode('/', $path); if (count($parts) < 3) { return null; } return route('storage.file', [ 'folder' => $parts[0], 'subfolder' => $parts[1], 'filename' => $parts[2], ]); }; @endphp
        <x-page-header
            eyebrow="Operations"
            title="Leave"
            subtitle="File new requests, monitor approvals, and track leave balances."
        >
            <x-slot:actions>
                @if ($canFileLeave ?? true)
                    <x-ui.button
                        variant="outline-primary"
                        size="sm"
                        data-toggle="modal"
                        data-target="#leaveRequestModal"
                        icon="cil-plus"
                    >
                        New Leave Request
                    </x-ui.button>
                @endif
                <x-ui.button
                    variant="outline-light"
                    size="sm"
                    class="px-3"
                    data-toggle="modal"
                    data-target="#leavePolicyModal"
                    icon="cil-info"
                >
                    Policy
                </x-ui.button>
            </x-slot:actions>
        </x-page-header>
        @if ($canFileLeave ?? true)
            {{-- Leave Balances Card --}}
            <div class="card leave-balance-card shadow-sm mb-4">
                <div class="card-header border-0 bg-transparent">
                    <h3 class="card-title font-weight-bold text-dark">
                        <i class="cil-balance-scale mr-2 text-info"></i> Leave
                        Balances
                        <span class="text-muted font-weight-normal"
                            >({{ $currentYear }})</span
                        >
                    </h3>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-0 small"><i class="cil-info mr-1"></i> Balances increase by 1 each month from a base of 5 (max 17) and reset to 5 each year.</p>
                    @php $consumed = $totalConsumed ?? 0; $earned = \App\Models\LeaveBalance::calculateEarnedForEmployeeYear($employee, $currentYear); $remaining = \App\Models\LeaveBalance::computedRemainingForEmployee($employee, $currentYear, $consumed); @endphp
                    <div class="row mt-3">
                        <div class="col-md-4">
                            <div class="stat-card stat-card-primary">
                                <span class="stat-label">Earned Credits</span>
                                <span
                                    class="stat-value text-primary"
                                    >{{ $earned }}</span
                                >
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card stat-card-danger">
                                <span class="stat-label">Consumed</span>
                                <span
                                    class="stat-value text-danger"
                                    >{{ $consumed }}</span
                                >
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card stat-card-success">
                                <span class="stat-label"
                                    >Balance Remaining</span
                                >
                                <span class="stat-value">{{ $remaining }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="alert alert-info shadow-sm mb-4">
                <i class="cil-info mr-1"></i> {{ $leaveLockReason ?? 'Leave requests are unavailable.' }}
            </div>
        @endif
        @if ($canFileLeave ?? true)
            {{-- Leave Requests Card --}}
            <x-ui.table-card
                title="Leave Requests"
                subtitle="Track submissions and review history."
                class="leave-requests-card hrms-list-card"
            >
                <table class="table hrms-table" id="leavesRequestsTable">
                    <thead class="bg-light">
                        <tr class="text-muted small text-uppercase">
                            <th class="py-3 pl-4">Type</th>
                            <th class="py-3">Date Range</th>
                            <th class="py-3 text-center">Stage</th>
                            <th class="py-3 text-center">Status</th>
                            <th class="py-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($requests as $request)
                            {{-- Same PHP Match Logic --}}
                            @php $isAcademicDept = ($request->employee?->department?->department_type ?? '') === 'Academic'; $headLabel = $isAcademicDept ? 'Dean Review' : 'Head Review'; $stage = match ($request->status) { 'Pending' => $headLabel, 'Approved' => 'HR Review', 'HR Approved' => 'Completed', 'Needs Revision' => 'Employee Revision', default => $request->status, }; $attachmentUrl = $privateUrl($request->attachment_path); $canResubmit = in_array($request->status, ['Declined', 'HR Declined'], true); $leaveEditPayload = ['update_url' => route('leaves.update', $request), 'leave_id' => $request->id, 'leave_type_id' => $request->leave_type_id, 'start_date' => $request->start_date?->format('Y-m-d'), 'end_date' => $request->end_date?->format('Y-m-d'), 'reason' => $request->reason, 'notes' => $request->notes,]; @endphp
                            <tr
                                class="leave-row"
                                data-employee="{{ $request->employee->first_name }} {{ $request->employee->last_name }}"
                                data-department="{{ $request->employee->department->department ?? '-' }}"
                                data-type="{{ $request->leaveType->name ?? '-' }}"
                                data-dates="{{ $request->start_date->format('M d, Y') }} - {{ $request->end_date->format('M d, Y') }}"
                                data-status="{{ $request->status }}"
                                data-reason="{{ $request->reason ?? '-' }}"
                                data-notes="{{ $request->notes ?? '' }}"
                                data-stage="{{ $stage }}"
                                data-attachment="{{ $request->leaveType?->requires_attachment && $attachmentUrl ? $attachmentUrl : '' }}"
                            >
                                <td
                                    class="align-middle pl-4 font-weight-bold text-dark"
                                >
                                    {{ $request->leaveType->name ?? '-' }}
                                </td>
                                <td class="align-middle text-muted">
                                    {{ $request->start_date->format('M d, Y') }} - {{ $request->end_date->format('M d, Y') }}
                                </td>
                                <td class="align-middle text-center">
                                    <x-ui.status-badge
                                        status="secondary"
                                        text="{{ $stage }}"
                                        class="badge-pill px-3 py-2"
                                    />
                                </td>
                                <td class="align-middle text-center">
                                    @php $statusLabel = match ($request->status) { 'Approved' => 'On Process', 'HR Approved' => 'Approved', 'Declined', 'HR Declined' => 'Needs Revision', default => $request->status, }; @endphp
                                    <x-ui.status-badge
                                        status="{{ $statusLabel }}"
                                        class="badge-pill px-3 py-2"
                                    />
                                </td>
                                <td class="align-middle text-center">
                                    <div
                                        class="crud-actions justify-content-center"
                                    >
                                        <x-ui.button
                                            type="view"
                                            size="sm"
                                            class="view-leave"
                                            aria-label="View Leave Request"
                                            title="View Leave Request"
                                        />
                                        @if ($canResubmit)
                                            <x-ui.button
                                                type="edit"
                                                size="sm"
                                                class="edit-leave"
                                                data-edit='@json($leaveEditPayload)'
                                                aria-label="Resubmit Leave Request"
                                                title="Resubmit Leave Request"
                                            />
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center leave-requests-empty-cell">
                                    <x-ui.empty-state
                                        icon="cil-beach-access"
                                        title="No leave requests found"
                                        message="You have no pending or historical leave requests."
                                    />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </x-ui.table-card>
        @endif
    </div>
    @if ($canFileLeave ?? true)
        {{-- MODAL: NEW LEAVE REQUEST --}}
        <x-ui.modal id="leaveRequestModal" size="lg">
                    <x-ui.modal-header
                        title="New Leave Request"
                    />
                    <form
                        action="{{ route('leaves.store') }}"
                        method="POST"
                        enctype="multipart/form-data"
                    >
                        @csrf
                        <div class="modal-body">
                            <div class="form-group">
                                <label for="modal_leave_type_id"
                                    >Leave Type
                                    <span class="text-danger">*</span></label
                                >
                                <select
                                    id="modal_leave_type_id"
                                    name="leave_type_id"
                                    class="form-control select2bs4 @error('leave_type_id') is-invalid @enderror"
                                    required
                                >
                                    <option value="">Select type</option>
                                    @foreach ($types as $type)
                                        <option
                                            value="{{ $type->id }}"
                                            {{ (string) old('leave_type_id') === (string) $type->id ? 'selected' : '' }}
                                        >
                                            {{ $type->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error ('leave_type_id')
                                    <span
                                        class="invalid-feedback d-block"
                                        >{{ $message }}</span
                                    >
                                @enderror
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="modal_start_date"
                                            >Start Date
                                            <span class="text-danger"
                                                >*</span
                                            ></label
                                        >
                                        <input
                                            type="date"
                                            id="modal_start_date"
                                            name="start_date"
                                            class="form-control @error('start_date') is-invalid @enderror"
                                            value="{{ old('start_date') }}"
                                            required
                                        />
                                        @error ('start_date')
                                            <span
                                                class="invalid-feedback d-block"
                                                >{{ $message }}</span
                                            >
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="modal_end_date"
                                            >End Date
                                            <span class="text-danger"
                                                >*</span
                                            ></label
                                        >
                                        <input
                                            type="date"
                                            id="modal_end_date"
                                            name="end_date"
                                            class="form-control @error('end_date') is-invalid @enderror"
                                            value="{{ old('end_date') }}"
                                            required
                                        />
                                        @error ('end_date')
                                            <span
                                                class="invalid-feedback d-block"
                                                >{{ $message }}</span
                                            >
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            {{-- Team conflict warning --}}
                            <div
                                id="leaveConflictBanner"
                                class="alert alert-warning d-none mb-0 mt-2 py-2 px-3 small"
                                role="alert"
                            >
                                <i class="cil-warning mr-1"></i>
                                <strong>Heads-up:</strong>
                                <span id="leaveConflictText"></span>
                                <br><small class="text-muted">You may still submit — this is for planning awareness only.</small>
                            </div>
                            <div class="form-group">
                                <label for="modal_reason">Reason</label>
                                <textarea
                                    id="modal_reason"
                                    name="reason"
                                    rows="3"
                                    class="form-control @error('reason') is-invalid @enderror"
                                    >{{ old('reason') }}</textarea
                                >
                                @error ('reason')
                                    <span
                                        class="invalid-feedback d-block"
                                        >{{ $message }}</span
                                    >
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="modal_attachment">Attachment</label>
                                <input
                                    type="file"
                                    id="modal_attachment"
                                    name="attachment"
                                    class="filepond @error('attachment') is-invalid @enderror"
                                    data-accepted-file-types=".pdf,.jpg,.jpeg,.png"
                                    data-max-file-size="5MB"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                />
                                @error ('attachment')
                                    <span
                                        class="invalid-feedback d-block"
                                        >{{ $message }}</span
                                    >
                                @enderror
                                <small class="text-muted d-block"
                                    >Upload supporting documents if
                                    required.</small
                                >
                            </div>
                        </div>
                        <x-ui.modal-footer>
                            <x-ui.button
                                type="submit"
                                variant="primary"
                                icon="cil-paper-plane"
                            >
                                Submit Request
                            </x-ui.button>
                        </x-ui.modal-footer>
                    </form>
        </x-ui.modal>
        {{-- MODAL: EDIT LEAVE REQUEST --}}
        <x-ui.modal id="leaveEditModal" size="lg">
                    <x-ui.modal-header
                        title="Resubmit Leave Request"
                    />
                    <form
                        id="leaveEditForm"
                        action="#"
                        method="POST"
                        enctype="multipart/form-data"
                        data-update-template="{{ route('leaves.update', ['leave' => 0]) }}"
                    >
                        @csrf
                        @method ('PUT')
                        <input
                            type="hidden"
                            name="form_context"
                            value="leave_edit"
                        />
                        <input
                            type="hidden"
                            name="leave_id"
                            id="edit_leave_id"
                            value="{{ old('leave_id') }}"
                        />
                        <div class="modal-body">
                            <div class="form-group">
                                <label for="edit_leave_type_id"
                                    >Leave Type
                                    <span class="text-danger">*</span></label
                                >
                                <select
                                    id="edit_leave_type_id"
                                    name="leave_type_id"
                                    class="form-control select2bs4 @error('leave_type_id') is-invalid @enderror"
                                    required
                                >
                                    @foreach ($types as $type)
                                        <option
                                            value="{{ $type->id }}"
                                            {{ (string) old('leave_type_id') === (string) $type->id ? 'selected' : '' }}
                                            >{{ $type->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error ('leave_type_id')
                                    <span
                                        class="invalid-feedback d-block"
                                        >{{ $message }}</span
                                    >
                                @enderror
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="edit_start_date"
                                            >Start Date
                                            <span class="text-danger"
                                                >*</span
                                            ></label
                                        >
                                        <input
                                            type="date"
                                            id="edit_start_date"
                                            name="start_date"
                                            class="form-control @error('start_date') is-invalid @enderror"
                                            value="{{ old('start_date') }}"
                                            required
                                        />
                                        @error ('start_date')
                                            <span
                                                class="invalid-feedback d-block"
                                                >{{ $message }}</span
                                            >
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="edit_end_date"
                                            >End Date
                                            <span class="text-danger"
                                                >*</span
                                            ></label
                                        >
                                        <input
                                            type="date"
                                            id="edit_end_date"
                                            name="end_date"
                                            class="form-control @error('end_date') is-invalid @enderror"
                                            value="{{ old('end_date') }}"
                                            required
                                        />
                                        @error ('end_date')
                                            <span
                                                class="invalid-feedback d-block"
                                                >{{ $message }}</span
                                            >
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            <div
                                class="small text-muted mb-3"
                                id="edit-leave-hints"
                                data-remaining='@json($remainingByTypeYear)'
                            >
                                <span id="edit-leave-days"
                                    >Days requested: -</span
                                >
                                <span class="mx-1">|</span>
                                <span id="edit-leave-remaining"
                                    >Remaining balance: -</span
                                >
                            </div>
                            <div class="form-group">
                                <label for="edit_reason">Reason</label>
                                <textarea
                                    id="edit_reason"
                                    name="reason"
                                    rows="3"
                                    class="form-control @error('reason') is-invalid @enderror"
                                    >{{ old('reason') }}</textarea
                                >
                                @error ('reason')
                                    <span
                                        class="invalid-feedback d-block"
                                        >{{ $message }}</span
                                    >
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="edit_attachment">Attachment</label>
                                <input
                                    type="file"
                                    id="edit_attachment"
                                    name="attachment"
                                    class="filepond @error('attachment') is-invalid @enderror"
                                    data-accepted-file-types=".pdf,.jpg,.jpeg,.png"
                                    data-max-file-size="5MB"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                />
                                @error ('attachment')
                                    <span
                                        class="invalid-feedback d-block"
                                        >{{ $message }}</span
                                    >
                                @enderror
                                <small class="text-muted d-block"
                                    >Upload supporting documents if
                                    required.</small
                                >
                            </div>
                            <div
                                class="alert alert-info d-none"
                                id="edit-leave-notes"
                            ></div>
                        </div>
                        <x-ui.modal-footer>
                            <x-ui.button
                                type="submit"
                                variant="primary"
                                icon="cil-check"
                            >
                                Resubmit
                            </x-ui.button>
                        </x-ui.modal-footer>
                    </form>
        </x-ui.modal>
    @endif
    {{-- MODAL: LEAVE DETAILS --}}
    <x-ui.modal id="leaveDetailsModal">
                <x-ui.modal-header
                    title="Leave Application Details"
                />
                <div class="modal-body px-4">
                    <div class="text-center mb-4">
                        <p id="leave-stage-text" class="text-muted small mt-2 mb-0"></p>
                    </div>
                    <div class="detail-row d-flex justify-content-between">
                        <span class="detail-label">Employee</span>
                        <span class="detail-value" id="leave-employee"></span>
                    </div>
                    <div class="detail-row d-flex justify-content-between">
                        <span class="detail-label">Type</span>
                        <span
                            class="detail-value font-weight-bold text-dark"
                            id="leave-type"
                        ></span>
                    </div>
                    <div class="detail-row d-flex justify-content-between">
                        <span class="detail-label">Period</span>
                        <span
                            class="detail-value text-primary"
                            id="leave-dates"
                        ></span>
                    </div>
                    <div class="mt-4">
                        <label class="detail-label">Reason for Leave</label>
                        <div
                            class="p-3 bg-light rounded small text-muted"
                            id="leave-reason"
                        ></div>
                    </div>
                    <div class="mt-4 d-none" id="leave-notes-section">
                        <label class="detail-label">Feedback</label>
                        <div
                            class="p-3 bg-light rounded small text-muted"
                            id="leave-notes"
                        ></div>
                    </div>
                    <div
                        class="mt-4 text-center"
                        id="leave-attachment-container"
                    >
                        <span id="leave-attachment"></span>
                    </div>
                </div>
    </x-ui.modal>
    {{-- MODAL: LEAVE POLICY --}}
    <x-ui.modal id="leavePolicyModal" size="lg">
                <x-ui.modal-header title="Policy Breakdown" />
                <div class="modal-body p-4 bg-light">
                    <div class="card border-0 shadow-none">
                        <div class="card-body p-3 bg-white rounded border">
                            <h6
                                class="font-weight-bold small text-muted text-uppercase mb-3"
                            >
                                Policy Breakdown
                            </h6>
                            <table
                                class="table hrms-table"
                                data-no-datatable="1"
                            >
                                <tr class="small text-muted">
                                    <td>Leave Policy</td>
                                    <td class="text-end">
                                        General Leave Policy
                                    </td>
                                </tr>
                                <tr class="small text-muted">
                                    <td>Monthly Accrual</td>
                                    <td class="text-end">+1.00 Day/Month</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
    </x-ui.modal>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    const conflictUrl = @json(route('leaves.team-conflicts'));
    const banner      = document.getElementById('leaveConflictBanner');
    const bannerText  = document.getElementById('leaveConflictText');
    const startInput  = document.getElementById('modal_start_date');
    const endInput    = document.getElementById('modal_end_date');

    if (!banner || !startInput || !endInput) return;

    let debounce = null;

    function checkConflicts() {
        const start = startInput.value;
        const end   = endInput.value || start;
        if (!start) {
            banner.classList.add('d-none');
            return;
        }
        clearTimeout(debounce);
        debounce = setTimeout(function () {
            fetch(conflictUrl + '?start=' + encodeURIComponent(start) + '&end=' + encodeURIComponent(end), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                const list = (data.conflicts || []);
                if (list.length === 0) {
                    banner.classList.add('d-none');
                    return;
                }
                const names = list.map(function (c) {
                    return '<strong>' + c.name + '</strong> (' + c.start + ' – ' + c.end + ')';
                }).join(', ');
                bannerText.innerHTML = ' ' + list.length + ' teammate(s) already on leave: ' + names + '.';
                banner.classList.remove('d-none');
            })
            .catch(function () { banner.classList.add('d-none'); });
        }, 400);
    }

    startInput.addEventListener('change', checkConflicts);
    endInput.addEventListener('change', checkConflicts);
}());
</script>
@endpush
