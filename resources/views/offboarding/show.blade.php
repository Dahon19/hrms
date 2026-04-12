@extends ('layouts.admin')

@section ('content')
    @php
    use App\Models\ClearanceItem;
    use App\Models\OffboardingRecord;

    $employee = $offboarding->employee;
    $authUser = auth()->user();
    $fullName = trim(($employee?->first_name ?? '') . ' ' . ($employee?->last_name ?? ''));
    $statusLabel = $offboarding->stage_label;
    $statusVariant = match ($offboarding->status) {
        OffboardingRecord::STATUS_DRAFT => 'warning',
        OffboardingRecord::STATUS_SUBMITTED => 'info',
        OffboardingRecord::STATUS_DEPARTMENT_REVIEW => 'primary',
        OffboardingRecord::STATUS_FINANCE_CLEARANCE => 'info',
        OffboardingRecord::STATUS_HR_FINALIZATION => 'dark',
        OffboardingRecord::STATUS_COMPLETED => 'success',
        OffboardingRecord::STATUS_CANCELLED => 'secondary',
        OffboardingRecord::STATUS_ARCHIVED => 'secondary',
        default => 'secondary',
    };
    $canSubmit = $authUser?->can('submit', $offboarding)
        && $offboarding->status === OffboardingRecord::STATUS_DRAFT;
    $canFinalize = $authUser?->can('finalize', $offboarding)
        && $offboarding->status === OffboardingRecord::STATUS_HR_FINALIZATION
        && $offboarding->clearanceItems->where('required', true)->every(
            fn ($item) => $item->status === ClearanceItem::STATUS_CLEARED
        );
    $canReopen = $authUser?->can('reopen', $offboarding) && $offboarding->isFinalized();
    $canClose = $authUser?->can('close', $offboarding)
        && $offboarding->status === OffboardingRecord::STATUS_COMPLETED;
    $canRequestCancellation = $authUser?->can('requestCancellation', $offboarding);
    $canReviewCancellation = $authUser?->can('reviewCancellation', $offboarding);
    $ownerOptions = [
        'hr' => 'HR',
        'department_head' => 'Department Head',
        'finance' => 'Finance',
    ];
    $statusOptions = [
        ClearanceItem::STATUS_PENDING => 'Pending',
        ClearanceItem::STATUS_CLEARED => 'Cleared',
        ClearanceItem::STATUS_BLOCKED => 'Blocked',
    ];
    $hasCancellationRejectErrors = old('cancellation_review_action') && $errors->has('cancellation_review_notes');
    $pdsStatus = is_string($overview['pds_status'])
        ? str_replace('_', ' ', ucfirst($overview['pds_status']))
        : ($overview['pds_status'] ?? 'No PDS');
@endphp
    <div
        class="container-fluid pt-4"
        id="offboardingShowPage"
        data-page="offboarding.show"
    >
        <x-page-header
            eyebrow="Offboarding"
            :title="$fullName ?: 'Offboarding Record'"
            :subtitle="'Last working day: ' . (optional($offboarding->display_last_working_day)->format('F d, Y') ?: 'Not set')"
        >
            <x-slot:actions>
                <x-ui.button
                    variant="outline-light"
                    size="sm"
                    :href="route('offboarding.index')"
                    icon="cil-arrow-left"
                >
                    Back
                </x-ui.button>

                @if ($canSubmit)
                    <form
                        method="POST"
                        action="{{ route('offboarding.submit', $offboarding) }}"
                        class="d-inline-flex"
                    >
                        @csrf
                        <x-ui.button
                            type="submit"
                            variant="primary"
                            size="sm"
                            icon="cil-paper-plane"
                        >
                            Send for Review
                        </x-ui.button>
                    </form>
                @endif

                @if ($canFinalize)
                    <form
                        method="POST"
                        action="{{ route('offboarding.finalize', $offboarding) }}"
                        class="d-inline-flex"
                    >
                        @csrf
                        <x-ui.button
                            type="submit"
                            variant="primary"
                            size="sm"
                            icon="cil-check-circle"
                        >
                            Mark Completed
                        </x-ui.button>
                    </form>
                @endif

                @if ($canRequestCancellation)
                    <x-ui.button
                        variant="outline-warning"
                        size="sm"
                        icon="cil-action-undo"
                        data-coreui-toggle="modal"
                        data-coreui-target="#offboardingCancellationRequestModal"
                    >
                        Request Cancellation
                    </x-ui.button>
                @endif

                @if ($canReopen)
                    <form
                        method="POST"
                        action="{{ route('offboarding.reopen', $offboarding) }}"
                        class="d-inline-flex"
                    >
                        @csrf
                        <x-ui.button
                            type="submit"
                            variant="outline-warning"
                            size="sm"
                            icon="cil-action-undo"
                        >
                            Reopen
                        </x-ui.button>
                    </form>
                @endif

                @if ($canClose)
                    <form
                        method="POST"
                        action="{{ route('offboarding.close', $offboarding) }}"
                        class="d-inline-flex"
                    >
                        @csrf
                        <x-ui.button
                            type="submit"
                            variant="outline-secondary"
                            size="sm"
                            icon="cil-archive"
                        >
                            Archive
                        </x-ui.button>
                    </form>
                @endif
            </x-slot:actions>
        </x-page-header>

        <div class="offboarding-detail-stack">
            @if ($offboarding->hasPendingCancellationRequest())
                <div class="alert alert-warning mb-4">
                    <div class="font-weight-bold">Resignation cancellation requested</div>
                    <div class="small mb-1">
                        Requested by {{ $offboarding->cancellationRequestedBy?->name ?? ($fullName ?: 'Employee') }}
                        on {{ $offboarding->cancellation_requested_at?->format('M d, Y h:i A') ?? 'N/A' }}.
                    </div>
                    <div class="small">
                        {{ $offboarding->cancellation_reason ?: 'No cancellation reason provided.' }}
                    </div>
                    @if ($canReviewCancellation)
                        <div class="d-flex gap-2 mt-3">
                            <form method="POST" action="{{ route('offboarding.approve-cancellation', $offboarding) }}">
                                @csrf
                                <x-ui.button type="submit" variant="primary" size="sm" icon="cil-check-circle">
                                    Approve Cancellation
                                </x-ui.button>
                            </form>
                            <x-ui.button
                                type="button"
                                variant="outline-secondary"
                                size="sm"
                                icon="cil-ban"
                                data-coreui-toggle="modal"
                                data-coreui-target="#offboardingCancellationRejectModal"
                            >
                                Decline Cancellation
                            </x-ui.button>
                        </div>
                    @endif
                </div>
            @elseif ($offboarding->cancellation_request_status === OffboardingRecord::CANCELLATION_STATUS_REJECTED)
                <div class="alert alert-info mb-4">
                    <div class="font-weight-bold">Resignation cancellation was declined</div>
                    <div class="small">
                        {{ $offboarding->cancellation_review_notes ?: 'No review notes were recorded.' }}
                    </div>
                </div>
            @elseif ($offboarding->cancellation_request_status === OffboardingRecord::CANCELLATION_STATUS_APPROVED)
                <div class="alert alert-success mb-4">
                    <div class="font-weight-bold">Resignation cancellation was approved</div>
                    <div class="small">
                        {{ $offboarding->cancellation_review_notes ?: 'Offboarding was closed after HR approved the resignation cancellation request.' }}
                    </div>
                </div>
            @endif

            <div class="offboarding-summary-grid mb-4">
                <div class="offboarding-stat-card">
                    <div class="offboarding-stat-label">Status</div>
                    <div class="offboarding-stat-value">
                        <x-ui.status-badge
                            class="text-uppercase"
                            :status="$offboarding->status"
                            :text="$statusLabel"
                            :variant="$statusVariant"
                        />
                    </div>
                </div>

                <div class="offboarding-stat-card">
                    <div class="offboarding-stat-label">Reason</div>
                    <div class="offboarding-stat-value">
                        {{ $offboarding->display_reason ?: 'Not specified' }}
                    </div>
                </div>

                <div class="offboarding-stat-card">
                    <div class="offboarding-stat-label">Initiated By</div>
                    <div class="offboarding-stat-value">
                        {{ $offboarding->initiatedBy?->name ?? 'System' }}
                    </div>
                </div>

                <div class="offboarding-stat-card">
                    <div class="offboarding-stat-label">PDS Status</div>
                    <div class="offboarding-stat-value">{{ $pdsStatus }}</div>
                </div>

                <div class="offboarding-stat-card">
                    <div class="offboarding-stat-label">Reference Snapshot</div>
                    <div class="offboarding-checklist-metrics">
                        <span class="text-success">
                            <i
                                class="cil-folder-open mr-1"
                                aria-hidden="true"
                            ></i
                            >{{ $overview['documents_count'] }} Documents
                        </span>
                        <span class="text-info">
                            <i
                                class="cil-balance-scale mr-1"
                                aria-hidden="true"
                            ></i
                            >{{ $overview['leave_balances_count'] }} Leave Rows
                        </span>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-12">
                    <div class="card shadow-sm offboarding-card h-100">
                        <div
                            class="ui-table-card__toolbar offboarding-toolbar-wrap"
                        >
                            <div>
                                <div class="offboarding-detail-card-title">
                                    Record Summary
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-3">
                            <div
                                class="offboarding-notes-grid offboarding-notes-grid--single"
                            >
                                <div
                                    class="offboarding-stat-card offboarding-stat-card--plain"
                                >
                                    <div class="offboarding-stat-label">
                                        Remarks
                                    </div>
                                    <div class="offboarding-stat-note">
                                        {{ $offboarding->remarks ?: 'No remarks provided.' }}
                                    </div>
                                </div>

                                <div
                                    class="offboarding-stat-card offboarding-stat-card--plain"
                                >
                                    <div class="offboarding-stat-label">
                                        Resignation Letter
                                    </div>
                                    @if ($offboarding->resignation_letter_attachment)
                                        <div class="offboarding-stat-note">
                                            Scanned copy attached at
                                            <code
                                                >{{ $offboarding->resignation_letter_attachment }}</code
                                            >
                                        </div>
                                    @else
                                        <div class="offboarding-stat-note">
                                            No scanned resignation letter
                                            uploaded.
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <x-ui.table-card
                title="Clearance Checklist"
                :responsive="false"
                class="offboarding-card"
            >
                @if ($groupedItems->isEmpty())
                    <div class="text-center py-5">
                        <div class="offboarding-empty-state">
                            <div class="offboarding-empty-icon">
                                <i class="cil-user-x" aria-hidden="true"></i>
                            </div>
                            <div class="offboarding-empty-title">
                                No Checklist Items
                            </div>
                            <div class="offboarding-empty-text">
                                No clearance steps match the current filters.
                            </div>
                        </div>
                    </div>
                @else
                    @foreach ($groupedItems as $stage)
                        <div class="offboarding-stage-section">
                            <div class="offboarding-stage-section-head">
                                <div class="offboarding-stage-head-grid">
                                    <div>
                                        <div class="offboarding-stage-eyebrow">
                                            Stage {{ $loop->iteration }}
                                        </div>
                                        <div class="offboarding-stage-title">
                                            {{ $stage['label'] }}
                                        </div>
                                        <div
                                            class="offboarding-stage-description"
                                        >
                                            {{ $stage['description'] }}
                                        </div>
                                    </div>
                                    <div class="offboarding-stage-meta">
                                        <span class="offboarding-stage-count">
                                            <i
                                                class="cil-list-rich mr-1"
                                                aria-hidden="true"
                                            ></i>
                                            {{ $stage['items']->count() }} item{{ $stage['items']->count() === 1 ? '' : 's' }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table
                                    class="table hrms-table mb-0 offboarding-table offboarding-detail-table"
                                >
                                    <thead>
                                        <tr>
                                            <th>Owner</th>
                                            <th>Checklist Item</th>
                                            <th>Status</th>
                                            <th>Approved</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($stage['items'] as $item)
                                            @php
                                            $badgeVariant = match ($item->status) {
                                                ClearanceItem::STATUS_CLEARED => 'success',
                                                ClearanceItem::STATUS_BLOCKED => 'warning',
                                                default => 'info',
                                            };
                                            $ownerLabel = str_replace('_', ' ', ucfirst((string) $item->owner_role));
                                            $unitLabel = trim((string) $item->unit_name);
                                            $itemNotes = $item->notes ?: $item->remarks;
                                            $approvedAt = $item->approved_at?->format('M d, Y h:i A')
                                                ?? $item->cleared_at?->format('M d, Y h:i A')
                                                ?? '-';
                                            $approvedBy = $item->approvedBy?->name
                                                ?? $item->clearedBy?->name
                                                ?? 'Awaiting action';
                                        @endphp
                                            <tr>
                                                <td>
                                                    <div
                                                        class="offboarding-primary-text"
                                                    >
                                                        {{ $unitLabel ?: $ownerLabel }}
                                                    </div>
                                                    @if ($unitLabel !== '' && strcasecmp($unitLabel, $ownerLabel) !== 0)
                                                        <div
                                                            class="offboarding-secondary-text"
                                                        >
                                                            {{ $ownerLabel }}
                                                        </div>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div
                                                        class="offboarding-primary-text"
                                                    >
                                                        {{ $item->item_name }}
                                                    </div>
                                                    @if ($itemNotes)
                                                        <div
                                                            class="offboarding-secondary-text"
                                                        >
                                                            {{ $itemNotes }}
                                                        </div>
                                                    @endif
                                                </td>
                                                <td>
                                                    <x-ui.status-badge
                                                        :status="$item->status"
                                                        :text="ucfirst($item->status)"
                                                        :variant="$badgeVariant"
                                                    />
                                                </td>
                                                <td>
                                                    <div
                                                        class="offboarding-primary-text"
                                                    >
                                                        {{ $approvedAt }}
                                                    </div>
                                                    <div
                                                        class="offboarding-secondary-text"
                                                    >
                                                        {{ $approvedBy }}
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    @can ('approveItem', $item)
                                                        <x-ui.button
                                                            type="edit"
                                                            size="sm"
                                                            data-toggle="modal"
                                                            data-target="#clearanceItemModal"
                                                            data-item-action="{{ route('offboarding.items.update', [$offboarding, $item]) }}"
                                                            data-item-name="{{ $item->item_name }}"
                                                            data-item-status="{{ $item->status }}"
                                                            data-item-remarks="{{ $itemNotes }}"
                                                        >
                                                            Update
                                                        </x-ui.button>
                                                    @else
                                                        <span
                                                            class="offboarding-secondary-text"
                                                            >Read only</span
                                                        >
                                                    @endcan
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                @endif
            </x-ui.table-card>
        </div>
    </div>
    <x-ui.modal id="clearanceItemModal" class="offboarding-action-modal">
                <form method="POST" id="clearanceItemForm">
                    @csrf
                    @method ('PATCH')

                    <x-ui.modal-header
                        title="Update Clearance Item"
                        subtitle="Record the current outcome of this checklist step and attach any operational notes."
                    />

                    <div class="modal-body">
                        <div class="offboarding-form-surface">
                            <div class="offboarding-form-grid">
                                <div class="offboarding-form-field offboarding-form-field--full">
                                    <label for="clearance_item_name">Checklist Item</label>
                                    <div class="offboarding-form-static">
                                        <input
                                            type="text"
                                            id="clearance_item_name"
                                            class="form-control"
                                            readonly
                                        />
                                    </div>
                                </div>

                                <div class="offboarding-form-field offboarding-form-field--compact">
                                    <label for="clearance_item_status">Status</label>
                                    <select
                                        name="status"
                                        id="clearance_item_status"
                                        class="form-control"
                                        required
                                    >
                                        @foreach ($statusOptions as $value => $label)
                                            <option value="{{ $value }}">
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="offboarding-form-field offboarding-form-field--full">
                                    <label for="clearance_item_remarks">Notes</label>
                                    <textarea
                                        name="notes"
                                        id="clearance_item_remarks"
                                        rows="4"
                                        class="form-control"
                                        placeholder="Record signature confirmation, interview remarks, blockers, or any related clearance notes."
                                    ></textarea>
                                    <small class="offboarding-form-help">
                                        Notes become required when the item is marked as Blocked.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <x-ui.modal-footer>
                        <button
                            type="button"
                            class="btn btn-light btn-sm"
                            data-coreui-dismiss="modal"
                        >
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary btn-sm">
                            Save
                        </button>
                    </x-ui.modal-footer>
                </form>
    </x-ui.modal>

    @if ($canRequestCancellation)
        <x-ui.modal id="offboardingCancellationRequestModal" class="offboarding-action-modal">
            <form method="POST" action="{{ route('offboarding.request-cancellation', $offboarding) }}">
                @csrf
                <x-ui.modal-header
                    title="Request Resignation Cancellation"
                    subtitle="Explain the request clearly so HR can review the change in offboarding status."
                />
                <div class="modal-body">
                    <div class="offboarding-form-surface">
                        <div class="offboarding-form-grid">
                            <div class="offboarding-form-field offboarding-form-field--full">
                                <label for="offboarding_cancellation_reason">Reason</label>
                                <textarea
                                    name="cancellation_reason"
                                    id="offboarding_cancellation_reason"
                                    rows="4"
                                    class="form-control"
                                    placeholder="Explain why the resignation or offboarding process should be cancelled."
                                >{{ old('cancellation_reason') }}</textarea>
                                <small class="offboarding-form-help">
                                    HR will review this request before the offboarding workflow is closed.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                <x-ui.modal-footer>
                    <x-ui.button type="button" variant="light" data-coreui-dismiss="modal">
                        Back
                    </x-ui.button>
                    <x-ui.button type="submit" variant="warning" icon="cil-action-undo">
                        Send Request
                    </x-ui.button>
                </x-ui.modal-footer>
            </form>
        </x-ui.modal>
    @endif

    @if ($canReviewCancellation)
        <x-ui.modal id="offboardingCancellationRejectModal" class="offboarding-action-modal">
            <form method="POST" action="{{ route('offboarding.reject-cancellation', $offboarding) }}">
                @csrf
                <input type="hidden" name="cancellation_review_action" value="reject" />
                <x-ui.modal-header
                    title="Decline Resignation Cancellation"
                    subtitle="Provide a concise decision note before the cancellation request is rejected."
                />
                <div class="modal-body">
                    @if ($hasCancellationRejectErrors)
                        <div class="alert alert-danger py-2 px-3 mb-3 small" role="alert">
                            Please provide review notes before declining.
                        </div>
                    @endif
                    <div class="offboarding-form-surface">
                        <div class="offboarding-form-grid">
                            <div class="offboarding-form-field offboarding-form-field--full">
                                <label for="offboarding_cancellation_review_notes">Review Notes</label>
                                <textarea
                                    name="cancellation_review_notes"
                                    id="offboarding_cancellation_review_notes"
                                    rows="4"
                                    class="form-control @error('cancellation_review_notes') is-invalid @enderror"
                                    required
                                    placeholder="State the reason for declining this cancellation request."
                                >{{ old('cancellation_review_notes') }}</textarea>
                                @error ('cancellation_review_notes')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
                <x-ui.modal-footer>
                    <x-ui.button type="button" variant="light" data-coreui-dismiss="modal">
                        Back
                    </x-ui.button>
                    <x-ui.button type="submit" variant="outline-secondary" icon="cil-ban">
                        Confirm Decline
                    </x-ui.button>
                </x-ui.modal-footer>
            </form>
        </x-ui.modal>
    @endif
@endsection

@push ('scripts')
    <script>
        (function () {
            if (typeof window.jQuery === "undefined") return;

            const $ = window.jQuery;
            const $modal = $("#clearanceItemModal");
            const $form = $("#clearanceItemForm");
            const $status = $("#clearance_item_status");
            const $notes = $("#clearance_item_remarks");

            if (!$modal.length || !$form.length) return;

            const syncNotesRequirement = () => {
                if (!$status.length || !$notes.length) return;
                const requiresNotes = $status.val() === "blocked";
                $notes.prop("required", requiresNotes);
            };

            const applyItemData = ($button) => {
                if (!$button || !$button.length) return;

                $form.attr("action", $button.data("item-action") || "#");
                $("#clearance_item_name").val($button.data("item-name") || "");
                $("#clearance_item_status").val($button.data("item-status") || "pending");
                $("#clearance_item_remarks").val($button.data("item-remarks") || "");
                syncNotesRequirement();
            };

            $(document).on("click", "[data-item-action]", function () {
                applyItemData($(this));
            });

            $modal.on("show.bs.modal show.coreui.modal", function (event) {
                applyItemData($(event.relatedTarget));
            });

            $status.on("change", syncNotesRequirement);
            syncNotesRequirement();

            @if ($hasCancellationRejectErrors)
                $("#offboardingCancellationRejectModal").modal("show");
            @endif
        })();
    </script>
@endpush
