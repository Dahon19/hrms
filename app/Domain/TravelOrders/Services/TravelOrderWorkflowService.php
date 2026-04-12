<?php

namespace App\Domain\TravelOrders\Services;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\TravelOrder;
use App\Models\TravelOrderAttachment;
use App\Models\User;
use App\Services\AccessControl;
use App\Services\HrmsNotificationService;
use App\Support\Workflow\InteractsWithWorkflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

class TravelOrderWorkflowService
{
    use InteractsWithWorkflow;

    public function __construct(
        private readonly HrmsNotificationService $notificationService,
    ) {
    }

    public function createDraft(Employee $employee, User $actor, array $data): TravelOrder
    {
        $this->guardEmployeeEligibility($employee, $data);

        return DB::transaction(function () use ($employee, $actor, $data) {
            $travelOrder = TravelOrder::create($this->travelOrderAttributes([
                'employee_id' => $employee->id,
                'department_id' => $employee->department_id,
                'position_id' => $employee->positions()->value('position_id'),
                'destination' => $data['destination'],
                'purpose' => $data['purpose'],
                'date_from' => $data['date_from'],
                'date_to' => $data['date_to'],
                'departure_time' => $data['departure_time'] ?? null,
                'return_time' => $data['return_time'] ?? null,
                'transport_mode' => $data['transport_mode'] ?? null,
                'budget_proposal' => $data['budget_proposal'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'status' => TravelOrder::STATUS_DRAFT,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
                'submitted_by' => $actor->id,
            ]));

            $this->storeAttachments($travelOrder, $employee, $actor, $data['attachments'] ?? []);

            $this->logWorkflowAction(
                'travel_order_created',
                TravelOrder::class,
                $travelOrder->id,
                $this->transitionMetadata(null, $travelOrder->status, [
                    'travel_order_id' => $travelOrder->id,
                    'employee_id' => $employee->id,
                ]),
                $actor->id,
            );

            return $travelOrder->fresh(['employee.department', 'position', 'attachments']);
        });
    }

    public function updateDraft(TravelOrder $travelOrder, User $actor, array $data): TravelOrder
    {
        if ($travelOrder->status !== TravelOrder::STATUS_DRAFT) {
            throw new LogicException('Only draft travel orders can be updated.');
        }

        $employee = $travelOrder->employee()->with(['user', 'department'])->firstOrFail();
        $this->guardEmployeeEligibility($employee, $data, $travelOrder->id);

        return DB::transaction(function () use ($travelOrder, $employee, $actor, $data) {
            $travelOrder->fill($this->travelOrderAttributes([
                'destination' => $data['destination'],
                'purpose' => $data['purpose'],
                'date_from' => $data['date_from'],
                'date_to' => $data['date_to'],
                'departure_time' => $data['departure_time'] ?? null,
                'return_time' => $data['return_time'] ?? null,
                'transport_mode' => $data['transport_mode'] ?? null,
                'budget_proposal' => $data['budget_proposal'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'position_id' => $employee->positions()->value('position_id'),
                'updated_by' => $actor->id,
            ]))->save();

            $this->storeAttachments($travelOrder, $employee, $actor, $data['attachments'] ?? []);

            $this->logWorkflowAction(
                'travel_order_updated',
                TravelOrder::class,
                $travelOrder->id,
                $this->transitionMetadata($travelOrder->status, $travelOrder->status, [
                    'travel_order_id' => $travelOrder->id,
                    'employee_id' => $employee->id,
                ]),
                $actor->id,
            );

            return $travelOrder->fresh(['employee.department', 'position', 'attachments']);
        });
    }

    public function submitTravelOrder(TravelOrder $travelOrder, User $actor): TravelOrder
    {
        return $this->submit($travelOrder, $actor);
    }

    public function submit(TravelOrder $travelOrder, User $actor): TravelOrder
    {
        if ($travelOrder->status !== TravelOrder::STATUS_DRAFT) {
            throw new LogicException('Only draft travel orders can be submitted.');
        }

        return DB::transaction(function () use ($travelOrder, $actor) {
            $isOwnRequest = (int) ($actor->employee?->id ?? 0) === (int) $travelOrder->employee_id;
            $isSameDepartment = (int) ($actor->employee?->department_id ?? 0) === (int) ($travelOrder->department_id ?? 0);
            $autoSkipDepartmentApproval = $isOwnRequest
                && $isSameDepartment
                && AccessControl::isHeadOrDean($actor);
            $autoSkipHrApproval = $autoSkipDepartmentApproval && AccessControl::isHrHead($actor);

            $previousStatus = $travelOrder->status;
            if ($autoSkipHrApproval) {
                if ($this->finalApprovers()->isEmpty()) {
                    throw new LogicException('President approval is required. Assign a President Head before HR approval.');
                }

                $travelOrder->forceFill($this->travelOrderAttributes([
                    'status' => TravelOrder::STATUS_HR_REVIEW,
                    'submitted_at' => now(),
                    'department_approved_by' => $actor->id,
                    'department_approved_at' => now(),
                    'hr_reviewed_by' => $actor->id,
                    'hr_reviewed_at' => now(),
                    'approved_at' => null,
                    'final_approved_by' => null,
                    'final_approved_at' => null,
                    'updated_by' => $actor->id,
                ]))->save();

                $this->logTransition('travel_order_submitted', $travelOrder, $actor, $previousStatus, TravelOrder::STATUS_SUBMITTED);
                $this->logTransition('travel_order_department_approved', $travelOrder, $actor, TravelOrder::STATUS_SUBMITTED, TravelOrder::STATUS_DEPARTMENT_APPROVED);
                $this->logTransition('travel_order_hr_approved', $travelOrder, $actor, TravelOrder::STATUS_DEPARTMENT_APPROVED, $travelOrder->status);

                $this->notifyFinalApprover($travelOrder->fresh(['employee.user', 'employee.department']), $actor);
            } elseif ($autoSkipDepartmentApproval) {
                $travelOrder->forceFill($this->travelOrderAttributes([
                    'status' => TravelOrder::STATUS_DEPARTMENT_APPROVED,
                    'submitted_at' => now(),
                    'department_approved_by' => $actor->id,
                    'department_approved_at' => now(),
                    'updated_by' => $actor->id,
                ]))->save();

                $this->logTransition('travel_order_submitted', $travelOrder, $actor, $previousStatus, TravelOrder::STATUS_SUBMITTED);
                $this->logTransition('travel_order_department_approved', $travelOrder, $actor, TravelOrder::STATUS_SUBMITTED, $travelOrder->status);

                $this->notifyHr($travelOrder->fresh(['employee.user', 'employee.department']), $actor);
            } else {
                $travelOrder->forceFill($this->travelOrderAttributes([
                    'status' => TravelOrder::STATUS_SUBMITTED,
                    'submitted_at' => now(),
                    'updated_by' => $actor->id,
                ]))->save();

                $this->logTransition('travel_order_submitted', $travelOrder, $actor, $previousStatus, $travelOrder->status);

                $this->notifyDepartmentHead($travelOrder->fresh(['employee.user', 'employee.department']), $actor);
            }

            return $travelOrder->fresh(['employee.department', 'position', 'attachments']);
        });
    }

    public function cancelTravelOrder(TravelOrder $travelOrder, User $actor): TravelOrder
    {
        return $this->cancel($travelOrder, $actor);
    }

    public function cancel(TravelOrder $travelOrder, User $actor): TravelOrder
    {
        if (in_array($travelOrder->status, [TravelOrder::STATUS_CANCELLED, TravelOrder::STATUS_COMPLETED], true)) {
            throw new LogicException('This travel order can no longer be cancelled.');
        }

        return DB::transaction(function () use ($travelOrder, $actor) {
            $previousStatus = $travelOrder->status;
            $travelOrder->forceFill($this->travelOrderAttributes([
                'status' => TravelOrder::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'updated_by' => $actor->id,
            ]))->save();

            $this->logTransition('travel_order_cancelled', $travelOrder, $actor, $previousStatus, $travelOrder->status);

            $this->notifyWorkflowUsers($this->notificationService, $this->stakeholders($travelOrder), [
                'title' => 'Travel order cancelled',
                'message' => $this->travelLabel($travelOrder) . ' was cancelled.',
                'module' => 'travel_order',
                'record_id' => $travelOrder->id,
                'route_name' => 'travel-orders.show',
                'route_params' => ['travel_order' => $travelOrder->id],
                'event_key' => 'travel_order.cancelled',
                'priority' => 'high',
                'type' => 'warning',
            ], $actor);

            return $travelOrder->fresh(['employee.department', 'position', 'attachments']);
        });
    }

    public function approveByDepartmentHead(TravelOrder $travelOrder, User $actor): TravelOrder
    {
        return $this->departmentApprove($travelOrder, $actor);
    }

    public function departmentApprove(TravelOrder $travelOrder, User $actor): TravelOrder
    {
        if ($travelOrder->status !== TravelOrder::STATUS_SUBMITTED) {
            throw new LogicException('Only submitted travel orders can be department-approved.');
        }

        return DB::transaction(function () use ($travelOrder, $actor) {
            $previousStatus = $travelOrder->status;
            $travelOrder->forceFill($this->travelOrderAttributes([
                'status' => TravelOrder::STATUS_DEPARTMENT_APPROVED,
                'department_approved_by' => $actor->id,
                'department_approved_at' => now(),
                'updated_by' => $actor->id,
            ]))->save();

            $this->logTransition('travel_order_department_approved', $travelOrder, $actor, $previousStatus, $travelOrder->status);

            $this->notifyHr($travelOrder->fresh(['employee.user', 'employee.department']), $actor);

            return $travelOrder->fresh(['employee.department', 'position', 'attachments']);
        });
    }

    public function rejectTravelOrder(TravelOrder $travelOrder, User $actor, ?string $decisionReason = null): TravelOrder
    {
        return match ($travelOrder->status) {
            TravelOrder::STATUS_SUBMITTED => $this->departmentReject($travelOrder, $actor, $decisionReason),
            TravelOrder::STATUS_DEPARTMENT_APPROVED => $this->hrReject($travelOrder, $actor, $decisionReason),
            TravelOrder::STATUS_HR_REVIEW => $this->finalReject($travelOrder, $actor, $decisionReason),
            default => throw new LogicException('This travel order is not in the correct status for rejection.'),
        };
    }

    public function departmentReject(TravelOrder $travelOrder, User $actor, ?string $decisionReason = null): TravelOrder
    {
        return $this->reject($travelOrder, $actor, TravelOrder::STATUS_SUBMITTED, 'travel_order_department_rejected', 'travel_order.department_rejected', $decisionReason);
    }

    public function approveByHR(TravelOrder $travelOrder, User $actor): TravelOrder
    {
        return $this->hrApprove($travelOrder, $actor);
    }

    public function hrApprove(TravelOrder $travelOrder, User $actor): TravelOrder
    {
        if ($travelOrder->status !== TravelOrder::STATUS_DEPARTMENT_APPROVED) {
            throw new LogicException('HR can only process department-approved travel orders.');
        }

        return DB::transaction(function () use ($travelOrder, $actor) {
            if ($this->finalApprovers()->isEmpty()) {
                throw new LogicException('President approval is required. Assign a President Head before HR approval.');
            }

            $previousStatus = $travelOrder->status;
            $travelOrder->forceFill($this->travelOrderAttributes([
                'status' => TravelOrder::STATUS_HR_REVIEW,
                'hr_reviewed_by' => $actor->id,
                'hr_reviewed_at' => now(),
                'approved_at' => null,
                'final_approved_by' => null,
                'final_approved_at' => null,
                'updated_by' => $actor->id,
            ]))->save();

            $this->logTransition('travel_order_hr_approved', $travelOrder, $actor, $previousStatus, $travelOrder->status);

            $this->notifyFinalApprover($travelOrder->fresh(['employee.user', 'employee.department']), $actor);

            return $travelOrder->fresh(['employee.department', 'position', 'attachments']);
        });
    }

    public function hrReject(TravelOrder $travelOrder, User $actor, ?string $decisionReason = null): TravelOrder
    {
        return $this->reject($travelOrder, $actor, TravelOrder::STATUS_DEPARTMENT_APPROVED, 'travel_order_hr_rejected', 'travel_order.hr_rejected', $decisionReason);
    }

    public function approveFinal(TravelOrder $travelOrder, User $actor): TravelOrder
    {
        return $this->finalApprove($travelOrder, $actor);
    }

    public function finalApprove(TravelOrder $travelOrder, User $actor): TravelOrder
    {
        if ($travelOrder->status !== TravelOrder::STATUS_HR_REVIEW) {
            throw new LogicException('Final approval is only available after HR review.');
        }

        return DB::transaction(function () use ($travelOrder, $actor) {
            $previousStatus = $travelOrder->status;
            $travelOrder->forceFill($this->travelOrderAttributes([
                'status' => TravelOrder::STATUS_APPROVED,
                'approved_at' => now(),
                'final_approved_by' => $actor->id,
                'final_approved_at' => now(),
                'updated_by' => $actor->id,
            ]))->save();

            $this->logTransition('travel_order_final_approved', $travelOrder, $actor, $previousStatus, $travelOrder->status);

            $this->notifyApproved($travelOrder->fresh(['employee.user', 'employee.department']), $actor, 'travel_order.final_approved');

            return $travelOrder->fresh(['employee.department', 'position', 'attachments']);
        });
    }

    public function finalReject(TravelOrder $travelOrder, User $actor, ?string $decisionReason = null): TravelOrder
    {
        return $this->reject($travelOrder, $actor, TravelOrder::STATUS_HR_REVIEW, 'travel_order_final_rejected', 'travel_order.final_rejected', $decisionReason);
    }

    public function markCompleted(TravelOrder $travelOrder, User $actor): TravelOrder
    {
        return $this->complete($travelOrder, $actor);
    }

    public function remindPendingApprovers(TravelOrder $travelOrder, User $actor): int
    {
        $travelOrder->loadMissing(['employee.user', 'employee.department']);

        $recipients = (match ($travelOrder->status) {
            TravelOrder::STATUS_SUBMITTED => AccessControl::headApproversForDepartment($travelOrder->department_id),
            TravelOrder::STATUS_DEPARTMENT_APPROVED => AccessControl::adminUsers()->merge(AccessControl::hrHeadUsers()),
            TravelOrder::STATUS_HR_REVIEW => $this->finalApprovers(),
            default => collect(),
        })->filter()
            ->unique('id')
            ->reject(fn ($user) => (int) $user->id === (int) $actor->id)
            ->values();

        if ($recipients->isEmpty()) {
            return 0;
        }

        $this->notifyWorkflowUsers($this->notificationService, $recipients, [
            'title' => 'Travel order reminder',
            'message' => $this->travelLabel($travelOrder) . ' is still waiting for your action.',
            'module' => 'travel_order',
            'record_id' => $travelOrder->id,
            'route_name' => 'travel-orders.approvals',
            'route_params' => [],
            'event_key' => 'travel_order.reminder_sent',
            'priority' => 'high',
            'type' => 'warning',
        ], $actor);

        $this->logWorkflowAction(
            'travel_order_pending_reminder_sent',
            TravelOrder::class,
            $travelOrder->id,
            $this->transitionMetadata($travelOrder->status, $travelOrder->status, [
                'travel_order_id' => $travelOrder->id,
                'employee_id' => $travelOrder->employee_id,
                'recipient_count' => $recipients->count(),
            ]),
            $actor->id,
        );

        return $recipients->count();
    }

    public function complete(TravelOrder $travelOrder, User $actor): TravelOrder
    {
        if ($travelOrder->status !== TravelOrder::STATUS_APPROVED) {
            throw new LogicException('Only approved travel orders can be marked completed.');
        }

        return DB::transaction(function () use ($travelOrder, $actor) {
            $previousStatus = $travelOrder->status;
            $travelOrder->forceFill($this->travelOrderAttributes([
                'status' => TravelOrder::STATUS_COMPLETED,
                'completed_at' => now(),
                'updated_by' => $actor->id,
            ]))->save();

            $this->logTransition('travel_order_completed', $travelOrder, $actor, $previousStatus, $travelOrder->status);

            $this->notifyWorkflowUsers($this->notificationService, $this->stakeholders($travelOrder), [
                'title' => 'Travel order completed',
                'message' => $this->travelLabel($travelOrder) . ' was marked completed.',
                'module' => 'travel_order',
                'record_id' => $travelOrder->id,
                'route_name' => 'travel-orders.show',
                'route_params' => ['travel_order' => $travelOrder->id],
                'event_key' => 'travel_order.completed',
                'priority' => 'normal',
                'type' => 'success',
            ], $actor);

            return $travelOrder->fresh(['employee.department', 'position', 'attachments']);
        });
    }

    private function reject(TravelOrder $travelOrder, User $actor, string $expectedStatus, string $auditAction, string $eventKey, ?string $decisionReason = null): TravelOrder
    {
        if ($travelOrder->status !== $expectedStatus) {
            throw new LogicException('This travel order is not in the correct status for rejection.');
        }

        return DB::transaction(function () use ($travelOrder, $actor, $auditAction, $eventKey, $decisionReason) {
            $previousStatus = $travelOrder->status;
            $decisionRemark = $this->formatDecisionRemark($travelOrder, $actor, $decisionReason);
            $travelOrder->forceFill($this->travelOrderAttributes([
                'status' => TravelOrder::STATUS_REJECTED,
                'rejected_at' => now(),
                'updated_by' => $actor->id,
                'remarks' => $decisionRemark,
            ]))->save();

            $this->logTransition($auditAction, $travelOrder, $actor, $previousStatus, $travelOrder->status);

            $this->notifyWorkflowUsers($this->notificationService, $this->stakeholders($travelOrder), [
                'title' => 'Travel order rejected',
                'message' => $this->travelLabel($travelOrder) . ' was rejected.',
                'module' => 'travel_order',
                'record_id' => $travelOrder->id,
                'route_name' => 'travel-orders.show',
                'route_params' => ['travel_order' => $travelOrder->id],
                'event_key' => $eventKey,
                'priority' => 'high',
                'type' => 'warning',
            ], $actor);

            return $travelOrder->fresh(['employee.department', 'position', 'attachments']);
        });
    }

    private function formatDecisionRemark(TravelOrder $travelOrder, User $actor, ?string $decisionReason): string
    {
        $base = trim((string) $travelOrder->remarks);
        $reason = trim((string) ($decisionReason ?? 'No reason provided.'));
        $actorName = trim((string) ($actor->name ?? 'Approver'));
        $stamp = now()->format('Y-m-d H:i');
        $entry = 'Decision by ' . $actorName . ' on ' . $stamp . ': ' . $reason;

        return trim(implode("\n", array_filter([$base, $entry])));
    }

    private function storeAttachments(TravelOrder $travelOrder, Employee $employee, User $actor, array $files): void
    {
        foreach ($files as $index => $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $path = $file->store('travel_order_attachments/' . $employee->id, 'local');
            TravelOrderAttachment::create([
                'travel_order_id' => $travelOrder->id,
                'path' => $path,
                'file_path' => $path,
                'label' => $file->getClientOriginalName() ?: ('Attachment ' . ($index + 1)),
                'uploaded_by' => $actor->id,
            ]);
        }
    }

    private function logTransition(string $action, TravelOrder $travelOrder, User $actor, ?string $previousStatus, string $newStatus): void
    {
        $this->logWorkflowAction(
            $action,
            TravelOrder::class,
            $travelOrder->id,
            $this->transitionMetadata($previousStatus, $newStatus, [
                'travel_order_id' => $travelOrder->id,
                'employee_id' => $travelOrder->employee_id,
            ]),
            $actor->id,
        );
    }

    private function guardEmployeeEligibility(Employee $employee, array $data, ?int $ignoreTravelOrderId = null): void
    {
        $employee->loadMissing(['user', 'department']);

        if (strtolower((string) $employee->status) !== 'active') {
            throw new LogicException('Only active employees can file travel orders.');
        }

        if ($employee->hasActiveOffboardingRecord()) {
            throw new LogicException('Travel orders cannot be filed while the employee is in offboarding.');
        }

        $hasOverlap = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', ['Approved', 'HR Approved'])
            ->whereDate('start_date', '<=', $data['date_to'])
            ->whereDate('end_date', '>=', $data['date_from'])
            ->exists();

        if ($hasOverlap) {
            throw new LogicException('Travel dates overlap an approved leave request.');
        }

        $travelOverlap = TravelOrder::query()
            ->where('employee_id', $employee->id)
            ->when($ignoreTravelOrderId, fn ($query) => $query->where('id', '!=', $ignoreTravelOrderId))
            ->whereNotIn('status', [TravelOrder::STATUS_CANCELLED, TravelOrder::STATUS_REJECTED])
            ->whereDate('date_from', '<=', $data['date_to'])
            ->whereDate('date_to', '>=', $data['date_from'])
            ->exists();

        if ($travelOverlap) {
            throw new LogicException('Travel dates overlap an existing travel order.');
        }
    }

    private function notifyDepartmentHead(TravelOrder $travelOrder, User $actor): void
    {
        $recipients = AccessControl::headApproversForDepartment($travelOrder->department_id);

        $this->notifyWorkflowUsers($this->notificationService, $recipients, [
            'title' => 'Travel order submitted',
            'message' => $this->travelLabel($travelOrder) . ' is awaiting department approval.',
            'module' => 'travel_order',
            'record_id' => $travelOrder->id,
            'route_name' => 'travel-orders.approvals',
            'route_params' => [],
            'event_key' => 'travel_order.submitted',
            'priority' => 'high',
            'type' => 'info',
        ], $actor);
    }

    private function notifyHr(TravelOrder $travelOrder, User $actor): void
    {
        $recipients = AccessControl::adminUsers()->merge(AccessControl::hrHeadUsers())->unique('id')->values();

        $this->notifyWorkflowUsers($this->notificationService, $recipients, [
            'title' => 'Department approved travel order',
            'message' => $this->travelLabel($travelOrder) . ' is awaiting HR validation.',
            'module' => 'travel_order',
            'record_id' => $travelOrder->id,
            'route_name' => 'travel-orders.approvals',
            'route_params' => [],
            'event_key' => 'travel_order.department_approved',
            'priority' => 'high',
            'type' => 'info',
        ], $actor);
    }

    private function notifyFinalApprover(TravelOrder $travelOrder, User $actor): void
    {
        $this->notifyWorkflowUsers($this->notificationService, $this->finalApprovers(), [
            'title' => 'Travel order ready for final approval',
            'message' => $this->travelLabel($travelOrder) . ' is ready for final signatory approval.',
            'module' => 'travel_order',
            'record_id' => $travelOrder->id,
            'route_name' => 'travel-orders.approvals',
            'route_params' => [],
            'event_key' => 'travel_order.hr_completed',
            'priority' => 'high',
            'type' => 'info',
        ], $actor);
    }

    private function notifyApproved(TravelOrder $travelOrder, User $actor, string $eventKey): void
    {
        $this->notifyWorkflowUsers($this->notificationService, $this->stakeholders($travelOrder), [
            'title' => 'Travel order approved',
            'message' => $this->travelLabel($travelOrder) . ' is now approved and printable.',
            'module' => 'travel_order',
            'record_id' => $travelOrder->id,
            'route_name' => 'travel-orders.show',
            'route_params' => ['travel_order' => $travelOrder->id],
            'event_key' => $eventKey,
            'priority' => 'high',
            'type' => 'success',
        ], $actor);
    }

    private function stakeholders(TravelOrder $travelOrder): Collection
    {
        $travelOrder->loadMissing('employee.user');

        return collect([$travelOrder->employee?->user])
            ->merge(AccessControl::headApproversForDepartment($travelOrder->department_id))
            ->merge(AccessControl::adminUsers())
            ->merge(AccessControl::hrHeadUsers())
            ->merge($this->finalApprovers())
            ->filter()
            ->unique('id')
            ->values();
    }

    private function finalApprovers(): Collection
    {
        return AccessControl::presidentHeadUsers()->unique('id')->values();
    }

    private function travelLabel(TravelOrder $travelOrder): string
    {
        $travelOrder->loadMissing('employee');
        $name = trim((string) (($travelOrder->employee?->first_name ?? '') . ' ' . ($travelOrder->employee?->last_name ?? '')));

        return trim($name . ' travel order for ' . $travelOrder->destination);
    }

    private function travelOrderAttributes(array $attributes): array
    {
        return array_filter(
            $attributes,
            static fn ($value, $key) => TravelOrder::hasColumn($key),
            ARRAY_FILTER_USE_BOTH
        );
    }
}
