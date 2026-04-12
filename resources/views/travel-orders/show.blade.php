@extends('layouts.admin')

@section('content')
    @php
        $employee = $travelOrder->employee;
        $isOwnRequest = (int) (auth()->user()->employee?->id ?? 0) === (int) $travelOrder->employee_id;
        $canUpdate = auth()->user()->can('update', $travelOrder);
        $canSubmit = auth()->user()->can('submit', $travelOrder);
        $canCancel = auth()->user()->can('cancel', $travelOrder);
        $canComplete = auth()->user()->can('complete', $travelOrder)
            && $travelOrder->status === \App\Models\TravelOrder::STATUS_APPROVED;
        $canDepartmentApprove = auth()->user()->can('approveDepartment', $travelOrder)
            && $travelOrder->status === \App\Models\TravelOrder::STATUS_SUBMITTED;
        $canHrApprove = auth()->user()->can('approveHr', $travelOrder)
            && $travelOrder->status === \App\Models\TravelOrder::STATUS_DEPARTMENT_APPROVED;
        $canFinalApprove = auth()->user()->can('finalApprove', $travelOrder)
            && $travelOrder->status === \App\Models\TravelOrder::STATUS_HR_REVIEW;
        $canPrint = in_array($travelOrder->status, [\App\Models\TravelOrder::STATUS_APPROVED, \App\Models\TravelOrder::STATUS_COMPLETED], true);
        $currentStepTitle = 'Current status';
        $currentStepText = 'This travel order moves through one approval step at a time.';
        $requestSummary = 'This travel order is still being prepared.';
        $currentStatusLabel = $travelOrder->statusLabel();
        $transportOptions = collect($transportOptions ?? [])->values();
        $currentTransportMode = trim((string) $travelOrder->transport_mode);
        if ($currentTransportMode !== '' && !$transportOptions->contains($currentTransportMode)) {
            $transportOptions = $transportOptions->prepend($currentTransportMode);
        }
        $attachmentUrl = function (?string $path) {
            if (!$path) {
                return null;
            }

            $parts = explode('/', $path, 3);
            if (count($parts) < 3) {
                return null;
            }

            return route('storage.file', [
                'folder' => $parts[0],
                'subfolder' => $parts[1],
                'filename' => $parts[2],
            ]);
        };
        $submittedByName = $travelOrder->submittedBy?->name
            ?? $employee?->user?->name
            ?? 'System';
        $departmentTrailValue = $travelOrder->departmentApprovedBy?->name ?? 'Pending';
        $departmentTrailMeta = $travelOrder->department_approved_at?->format('M d, Y h:i A') ?? 'Not yet acted on';
        $hrTrailValue = $travelOrder->hrReviewedBy?->name ?? 'Pending';
        $hrTrailMeta = $travelOrder->hr_reviewed_at?->format('M d, Y h:i A') ?? 'Not yet acted on';
        $finalTrailValue = $travelOrder->finalApprovedBy?->name ?? 'Pending / Not required yet';
        $finalTrailMeta = $travelOrder->final_approved_at?->format('M d, Y h:i A') ?? 'No final signature yet';

        if ($travelOrder->status === \App\Models\TravelOrder::STATUS_REJECTED) {
            if (!$travelOrder->department_approved_at) {
                $departmentTrailValue = ($travelOrder->updatedBy?->name ?? 'Department reviewer') . ' rejected this request';
                $departmentTrailMeta = $travelOrder->rejected_at?->format('M d, Y h:i A') ?? 'Rejected';
                $hrTrailValue = 'Not reached';
                $hrTrailMeta = 'Stopped at department review';
                $finalTrailValue = 'Not reached';
                $finalTrailMeta = 'Stopped at department review';
            } elseif (!$travelOrder->hr_reviewed_at) {
                $hrTrailValue = ($travelOrder->updatedBy?->name ?? 'HR reviewer') . ' rejected this request';
                $hrTrailMeta = $travelOrder->rejected_at?->format('M d, Y h:i A') ?? 'Rejected';
                $finalTrailValue = 'Not reached';
                $finalTrailMeta = 'Stopped at HR review';
            } else {
                $finalTrailValue = ($travelOrder->updatedBy?->name ?? 'Final approver') . ' rejected this request';
                $finalTrailMeta = $travelOrder->rejected_at?->format('M d, Y h:i A') ?? 'Rejected';
            }
        } elseif ($travelOrder->status === \App\Models\TravelOrder::STATUS_CANCELLED) {
            if (!$travelOrder->department_approved_at) {
                $departmentTrailValue = 'Cancelled before department review';
                $departmentTrailMeta = $travelOrder->cancelled_at?->format('M d, Y h:i A') ?? 'Cancelled';
                $hrTrailValue = 'Not reached';
                $hrTrailMeta = 'Cancelled before HR review';
                $finalTrailValue = 'Not reached';
                $finalTrailMeta = 'Cancelled before final approval';
            } elseif (!$travelOrder->hr_reviewed_at) {
                $hrTrailValue = 'Cancelled after department review';
                $hrTrailMeta = $travelOrder->cancelled_at?->format('M d, Y h:i A') ?? 'Cancelled';
                $finalTrailValue = 'Not reached';
                $finalTrailMeta = 'Cancelled before final approval';
            } elseif (!$travelOrder->final_approved_at) {
                $finalTrailValue = 'Cancelled after HR review';
                $finalTrailMeta = $travelOrder->cancelled_at?->format('M d, Y h:i A') ?? 'Cancelled';
            }
        }

        if ($canDepartmentApprove) {
            $currentStepTitle = 'Current status';
            $currentStepText = 'Waiting for the department head to act on this travel order.';
            $requestSummary = 'Waiting for department approval.';
        } elseif ($canHrApprove) {
            $currentStepTitle = 'Current status';
            $currentStepText = 'The department has already approved this travel order. HR is next.';
            $requestSummary = 'Waiting for HR approval.';
        } elseif ($canFinalApprove) {
            $currentStepTitle = 'Current status';
            $currentStepText = 'Department and HR review are complete. This travel order is waiting for final approval.';
            $requestSummary = 'Waiting for final approval.';
        } elseif ($canSubmit) {
            $currentStepTitle = 'Current status';
            $currentStepText = 'Submit this draft to start the approval process.';
            $requestSummary = 'Draft not yet submitted.';
        } elseif ($travelOrder->status === \App\Models\TravelOrder::STATUS_SUBMITTED) {
            $currentStepTitle = 'Current status';
            $currentStepText = 'Waiting for the department head to approve or decline this travel order.';
            $requestSummary = 'Submitted and waiting for department approval.';
        } elseif ($travelOrder->status === \App\Models\TravelOrder::STATUS_DEPARTMENT_APPROVED) {
            $currentStepTitle = 'Current status';
            $currentStepText = $isOwnRequest
                ? 'Waiting for another HR approver. Self-approval is not allowed.'
                : 'Waiting for HR to approve or decline this travel order.';
            $requestSummary = 'Department approval is complete.';
        } elseif ($travelOrder->status === \App\Models\TravelOrder::STATUS_HR_REVIEW) {
            $currentStepTitle = 'Current status';
            $currentStepText = 'Waiting for President final approval or decline.';
            $requestSummary = 'HR approval is complete.';
        } elseif ($canComplete) {
            $currentStepTitle = 'Current status';
            $currentStepText = 'This travel order is fully approved and can be marked complete after the trip.';
            $requestSummary = 'Approved and waiting for completion.';
        } elseif ($travelOrder->status === \App\Models\TravelOrder::STATUS_REJECTED) {
            $currentStepTitle = 'Outcome';
            $currentStepText = 'This travel order was rejected and will not move forward.';
            $requestSummary = 'Rejected.';
        } elseif ($travelOrder->status === \App\Models\TravelOrder::STATUS_CANCELLED) {
            $currentStepTitle = 'Outcome';
            $currentStepText = 'This travel order was cancelled and is now read-only.';
            $requestSummary = 'Cancelled.';
        } elseif ($travelOrder->status === \App\Models\TravelOrder::STATUS_COMPLETED) {
            $currentStepTitle = 'Outcome';
            $currentStepText = 'All approvals and completion steps are done.';
            $requestSummary = 'Completed.';
        } elseif ($travelOrder->status === \App\Models\TravelOrder::STATUS_APPROVED) {
            $currentStepTitle = 'Current status';
            $currentStepText = 'All approvals are complete. The only remaining step is completion.';
            $requestSummary = 'All approvals are complete.';
        }

        $summaryItems = [
            ['label' => 'Requested by', 'value' => trim(($employee?->first_name ?? '') . ' ' . ($employee?->last_name ?? '')) ?: 'Employee'],
            ['label' => 'Office', 'value' => $employee?->department?->department ?? 'No department'],
            ['label' => 'Travel dates', 'value' => ($travelOrder->date_from?->format('M d, Y') ?? 'Not set') . ' to ' . ($travelOrder->date_to?->format('M d, Y') ?? 'Not set')],
            ['label' => 'Submitted on', 'value' => $travelOrder->submitted_at?->format('M d, Y h:i A') ?? 'Not submitted'],
        ];
        if ($travelOrder->transport_mode) {
            $summaryItems[] = ['label' => 'Transport', 'value' => $travelOrder->transport_mode];
        }
        if ($travelOrder->budget_proposal !== null) {
            $summaryItems[] = ['label' => 'Budget', 'value' => 'PHP ' . number_format((float) $travelOrder->budget_proposal, 2)];
        }

        $reviewSteps = [
            [
                'title' => 'Submitted',
                'actor' => $submittedByName,
                'meta' => $travelOrder->submitted_at?->format('M d, Y h:i A') ?? 'Draft not yet submitted',
            ],
            [
                'title' => 'Department review',
                'actor' => $departmentTrailValue,
                'meta' => $departmentTrailMeta,
            ],
            [
                'title' => 'HR review',
                'actor' => $hrTrailValue,
                'meta' => $hrTrailMeta,
            ],
            [
                'title' => 'Final approval',
                'actor' => $finalTrailValue,
                'meta' => $finalTrailMeta,
            ],
        ];
        $hasRejectErrors = old('reject_action') && $errors->has('decision_reason');
    @endphp

    <div class="container-fluid pt-4" data-page="travel-orders.show">
    <x-page-header
        eyebrow="Travel Order"
        title="{{ $travelOrder->destination }}"
        subtitle="{{ $requestSummary }}"
    >
        <x-slot:actions>
            <div class="travel-order-action-cluster d-flex gap-2">
                <x-ui.button
                    variant="outline-light"
                    size="sm"
                    :href="route('travel-orders.index')"
                    icon="cil-arrow-left"
                >
                    Back
                </x-ui.button>
            </div>
        </x-slot:actions>
    </x-page-header>

    <div class="travel-order-show-shell travel-order-show-shell--compact">
        <div class="card shadow-sm travel-order-show-card">
            <div class="card-body">
                <div class="travel-order-compact-status">
                    <div>
                        <div class="travel-order-overview-item__label">{{ $currentStepTitle }}</div>
                        <p class="text-muted mb-0">{{ $currentStepText }}</p>
                    </div>
                    <div class="travel-order-action-panel">
                        @if ($canSubmit)
                            <form method="POST" action="{{ route('travel-orders.submit', $travelOrder) }}">
                                @csrf
                                <x-ui.button type="submit" variant="success" icon="cil-check" title="Submit for approval" aria-label="Submit for approval">
                                    Submit
                                </x-ui.button>
                            </form>
                            <form method="POST" action="{{ route('travel-orders.cancel', $travelOrder) }}">
                                @csrf
                                <x-ui.button type="submit" variant="outline-danger" icon="cil-x" title="Cancel draft" aria-label="Cancel draft">
                                    Cancel
                                </x-ui.button>
                            </form>
                        @elseif ($canDepartmentApprove)
                            <form method="POST" action="{{ route('travel-orders.department-approve', $travelOrder) }}">
                                @csrf
                                <x-ui.button type="submit" variant="success" icon="cil-check" title="Approve request" aria-label="Approve request">
                                    Approve
                                </x-ui.button>
                            </form>
                            <x-ui.button
                                type="button"
                                variant="outline-danger"
                                icon="cil-x"
                                title="Decline request"
                                aria-label="Decline request"
                                data-coreui-toggle="modal"
                                data-coreui-target="#travelOrderRejectModal"
                                data-reject-action="{{ route('travel-orders.department-reject', $travelOrder) }}"
                                data-reject-label="Department Decline"
                            >
                                Decline
                            </x-ui.button>
                        @elseif ($canHrApprove)
                            <form method="POST" action="{{ route('travel-orders.hr-approve', $travelOrder) }}">
                                @csrf
                                <x-ui.button type="submit" variant="success" icon="cil-check" title="Approve request" aria-label="Approve request">
                                    Approve
                                </x-ui.button>
                            </form>
                            <x-ui.button
                                type="button"
                                variant="outline-danger"
                                icon="cil-x"
                                title="Decline request"
                                aria-label="Decline request"
                                data-coreui-toggle="modal"
                                data-coreui-target="#travelOrderRejectModal"
                                data-reject-action="{{ route('travel-orders.hr-reject', $travelOrder) }}"
                                data-reject-label="HR Decline"
                            >
                                Decline
                            </x-ui.button>
                        @elseif ($canFinalApprove)
                            <form method="POST" action="{{ route('travel-orders.final-approve', $travelOrder) }}">
                                @csrf
                                <x-ui.button type="submit" variant="success" icon="cil-check-circle" title="Approve request" aria-label="Approve request">
                                    Approve
                                </x-ui.button>
                            </form>
                            <x-ui.button
                                type="button"
                                variant="outline-danger"
                                icon="cil-x"
                                title="Decline request"
                                aria-label="Decline request"
                                data-coreui-toggle="modal"
                                data-coreui-target="#travelOrderRejectModal"
                                data-reject-action="{{ route('travel-orders.final-reject', $travelOrder) }}"
                                data-reject-label="Final Decline"
                            >
                                Decline
                            </x-ui.button>
                        @elseif ($canComplete)
                            <form method="POST" action="{{ route('travel-orders.complete', $travelOrder) }}">
                                @csrf
                                <x-ui.button type="submit" variant="success" icon="cil-check-circle" title="Approve and complete request" aria-label="Approve and complete request">
                                    Approve
                                </x-ui.button>
                            </form>
                            <div class="text-muted small">Mark complete after the trip is finished.</div>
                        @elseif ($canCancel)
                            <form method="POST" action="{{ route('travel-orders.cancel', $travelOrder) }}">
                                @csrf
                                <x-ui.button type="submit" variant="outline-danger" icon="cil-x" title="Cancel request" aria-label="Cancel request">
                                    Cancel Request
                                </x-ui.button>
                            </form>
                        @else
                            <div class="text-muted small">No action is available for your role at this stage.</div>
                        @endif
                    </div>
                </div>

                @if ($canPrint)
                    <div class="travel-order-secondary-actions">
                        @if ($canPrint)
                            <x-ui.button
                                type="print"
                                variant="outline-secondary"
                                size="sm"
                                class="travel-order-secondary-action-link"
                                :href="route('travel-orders.print', $travelOrder)"
                            >
                                Print travel order
                            </x-ui.button>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        <div class="travel-order-compact-grid">
            <div class="card shadow-sm travel-order-show-card">
                <div class="card-header travel-order-show-card__header">
                    <x-ui.detail-head title="Summary" />
                </div>
                <div class="card-body">
                    <div class="travel-order-overview-grid">
                        @foreach ($summaryItems as $item)
                            <div class="travel-order-overview-item">
                                <div class="travel-order-overview-item__label">{{ $item['label'] }}</div>
                                <div class="travel-order-overview-item__value">{{ $item['value'] }}</div>
                            </div>
                        @endforeach
                    </div>

                    <div class="travel-order-copy-block mt-3">
                        <div class="travel-order-copy-block__label">Purpose</div>
                        <div class="travel-order-purpose">{{ $travelOrder->purpose }}</div>
                    </div>

                    @if ($travelOrder->remarks)
                        <div class="travel-order-copy-block">
                            <div class="travel-order-copy-block__label">Notes</div>
                            <div class="travel-order-purpose">{{ $travelOrder->remarks }}</div>
                        </div>
                    @endif

                    <div class="travel-order-copy-block">
                        <div class="travel-order-copy-block__label">Files</div>
                        @forelse ($travelOrder->attachments as $attachment)
                            <a
                                href="{{ $attachmentUrl($attachment->path) }}"
                                target="_blank"
                                rel="noopener"
                                class="travel-order-attachment"
                            >
                                <i class="cil-paperclip mr-2"></i>{{ $attachment->label ?: 'Attachment' }}
                            </a>
                        @empty
                            <div class="text-muted small">No attachments uploaded.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="card shadow-sm travel-order-show-card">
                <div class="card-header travel-order-show-card__header">
                    <x-ui.detail-head title="Approval Trail" />
                </div>
                <div class="card-body">
                    <div class="travel-order-review-list">
                        @foreach ($reviewSteps as $step)
                            <div class="travel-order-review-item">
                                <div class="travel-order-review-item__title">{{ $step['title'] }}</div>
                                <div class="travel-order-review-item__actor">{{ $step['actor'] }}</div>
                                <div class="travel-order-review-item__meta">{{ $step['meta'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        @if ($canUpdate)
            <div class="card shadow-sm travel-order-show-card">
                <div class="card-header travel-order-show-card__header">
                    <x-ui.detail-head title="Edit Draft" />
                </div>
                <div class="card-body">
                    <form
                        method="POST"
                        action="{{ route('travel-orders.update', $travelOrder) }}"
                        enctype="multipart/form-data"
                        class="row g-3 hrms-form-layout"
                    >
                        @csrf
                        @method('PATCH')

                        <div class="col-md-6">
                            <label class="form-label">Destination</label>
                            <input
                                type="text"
                                name="destination"
                                value="{{ old('destination', $travelOrder->destination) }}"
                                class="form-control"
                                required
                            />
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Transport Mode</label>
                            @if ($transportOptions->isNotEmpty())
                                <select
                                    name="transport_mode"
                                    class="form-control"
                                    required
                                >
                                    <option value="" disabled @selected(!old('transport_mode', $travelOrder->transport_mode))>
                                        Select transportation
                                    </option>
                                    @foreach ($transportOptions as $transportOption)
                                        <option value="{{ $transportOption }}" @selected(old('transport_mode', $travelOrder->transport_mode) === $transportOption)>
                                            {{ $transportOption }}
                                        </option>
                                    @endforeach
                                </select>
                            @else
                                <input
                                    type="text"
                                    name="transport_mode"
                                    value="{{ old('transport_mode', $travelOrder->transport_mode) }}"
                                    class="form-control"
                                    placeholder="Enter transportation"
                                    required
                                />
                            @endif
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Budget Proposal</label>
                            <input
                                type="number"
                                name="budget_proposal"
                                value="{{ old('budget_proposal', $travelOrder->budget_proposal) }}"
                                class="form-control"
                                min="0"
                                step="0.01"
                                placeholder="0.00"
                            />
                        </div>

                        <div class="col-12">
                            <label class="form-label">Purpose</label>
                            <textarea
                                name="purpose"
                                rows="4"
                                class="form-control"
                                required
                            >{{ old('purpose', $travelOrder->purpose) }}</textarea>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Date From</label>
                            <input
                                type="date"
                                name="date_from"
                                value="{{ old('date_from', optional($travelOrder->date_from)->toDateString()) }}"
                                class="form-control"
                                required
                            />
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Date To</label>
                            <input
                                type="date"
                                name="date_to"
                                value="{{ old('date_to', optional($travelOrder->date_to)->toDateString()) }}"
                                class="form-control"
                                required
                            />
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Departure Time</label>
                            <input
                                type="time"
                                name="departure_time"
                                value="{{ old('departure_time', $travelOrder->departure_time) }}"
                                class="form-control"
                            />
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Return Time</label>
                            <input
                                type="time"
                                name="return_time"
                                value="{{ old('return_time', $travelOrder->return_time) }}"
                                class="form-control"
                            />
                        </div>

                        <div class="col-12">
                            <label class="form-label">Remarks</label>
                            <textarea
                                name="remarks"
                                rows="3"
                                class="form-control"
                            >{{ old('remarks', $travelOrder->remarks) }}</textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Add Attachments</label>
                            <input
                                type="file"
                                name="attachments[]"
                                class="form-control"
                                multiple
                                accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                            />
                        </div>

                        <div class="col-12 d-flex justify-content-end">
                            <x-ui.button type="submit" variant="primary">
                                Update Draft
                            </x-ui.button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <x-ui.modal id="travelOrderRejectModal" title="Decline Travel Order">
            <form
                method="POST"
                id="travelOrderRejectForm"
                action="{{ old('reject_action', '#') }}"
            >
                @csrf
                <input type="hidden" name="reject_action" id="travel_order_reject_action" value="{{ old('reject_action', '') }}" />
                <input type="hidden" name="reject_label" id="travel_order_reject_label" value="{{ old('reject_label', 'Decline') }}" />
                <div class="modal-body">
                    @if ($hasRejectErrors)
                        <div class="alert alert-danger py-2 px-3 mb-3 small" role="alert">
                            Please provide the decline reason.
                        </div>
                    @endif
                    <div class="form-group mb-0">
                        <label for="travel_order_decision_reason">Reason for decline</label>
                        <textarea
                            name="decision_reason"
                            id="travel_order_decision_reason"
                            rows="4"
                            class="form-control @error('decision_reason') is-invalid @enderror"
                            required
                            placeholder="Explain why this request is declined."
                        >{{ old('decision_reason') }}</textarea>
                        @error ('decision_reason')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <x-ui.modal-footer>
                    <x-ui.button type="button" variant="light" data-coreui-dismiss="modal">
                        Cancel
                    </x-ui.button>
                    <x-ui.button type="submit" variant="danger" icon="cil-x">
                        Submit Decline
                    </x-ui.button>
                </x-ui.modal-footer>
            </form>
        </x-ui.modal>
    </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            if (typeof window.jQuery === "undefined") return;

            const $ = window.jQuery;
            const $modal = $("#travelOrderRejectModal");
            const $form = $("#travelOrderRejectForm");
            if (!$modal.length || !$form.length) return;

            const setRejectState = ($trigger) => {
                const action = ($trigger && $trigger.data("reject-action")) || $("#travel_order_reject_action").val() || "#";
                const label = ($trigger && $trigger.data("reject-label")) || $("#travel_order_reject_label").val() || "Decline";

                $form.attr("action", action);
                $("#travel_order_reject_action").val(action);
                $("#travel_order_reject_label").val(label);
            };

            $(document).on("click", "[data-reject-action]", function () {
                setRejectState($(this));
            });

            $modal.on("show.bs.modal show.coreui.modal", function (event) {
                setRejectState($(event.relatedTarget));
            });

            @if ($hasRejectErrors)
                setRejectState(null);
                $modal.modal("show");
            @endif
        })();
    </script>
@endpush
