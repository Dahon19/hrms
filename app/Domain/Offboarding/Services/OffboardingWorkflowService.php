<?php

namespace App\Domain\Offboarding\Services;

use App\Models\ClearanceItem;
use App\Models\Employee;
use App\Models\OffboardingRecord;
use App\Models\User;
use App\Services\AccessControl;
use App\Services\HrmsNotificationService;
use App\Support\Workflow\InteractsWithWorkflow;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use LogicException;

class OffboardingWorkflowService
{
    use InteractsWithWorkflow;

    public function __construct(
        private readonly HrmsNotificationService $notificationService,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function checklistTemplate(): array
    {
        return [
            [
                'unit_name' => 'Human Resources',
                'owner_role' => 'hr',
                'module_key' => 'resignation_notice_received',
                'item_name' => 'Resignation notice received',
                'display_order' => 10,
            ],
            [
                'unit_name' => 'Department Head',
                'owner_role' => 'department_head',
                'module_key' => 'department_interview_handover',
                'item_name' => 'Interview, handover, and asset clearance',
                'display_order' => 20,
            ],
            [
                'unit_name' => 'Finance Office',
                'owner_role' => 'finance',
                'module_key' => 'finance_clearance',
                'item_name' => 'Financial clearance',
                'display_order' => 30,
            ],
            [
                'unit_name' => 'Human Resources',
                'owner_role' => 'hr',
                'module_key' => 'hr_final_review',
                'item_name' => 'Final HR review and document release',
                'display_order' => 40,
            ],
        ];
    }

    /**
     * @return array<string, array{label: string, description: string, order: int}>
     */
    public static function stageDefinitions(): array
    {
        return [
            OffboardingRecord::STATUS_DRAFT => [
                'label' => 'Draft',
                'description' => 'HR has prepared the resignation record and can now send it to the clearance reviewers.',
                'order' => 10,
            ],
            OffboardingRecord::STATUS_SUBMITTED => [
                'label' => 'Sent for Review',
                'description' => 'HR has sent the offboarding record for review and notified the employee, department head, and finance head.',
                'order' => 20,
            ],
            OffboardingRecord::STATUS_DEPARTMENT_REVIEW => [
                'label' => 'Department Interview',
                'description' => 'Department head conducts the interview, handover review, and signs the printed clearance.',
                'order' => 30,
            ],
            OffboardingRecord::STATUS_FINANCE_CLEARANCE => [
                'label' => 'Finance Clearance',
                'description' => 'Finance verifies loans, cash advances, and outstanding obligations before release.',
                'order' => 40,
            ],
            OffboardingRecord::STATUS_HR_FINALIZATION => [
                'label' => 'HR Finalization',
                'description' => 'HR performs the exit interview and verifies the signed physical clearance form.',
                'order' => 50,
            ],
            OffboardingRecord::STATUS_COMPLETED => [
                'label' => 'Completed',
                'description' => 'The employee has been cleared. Account access is deactivated on or after the recorded last working day.',
                'order' => 60,
            ],
            OffboardingRecord::STATUS_CANCELLED => [
                'label' => 'Cancelled',
                'description' => 'The employee withdrew the resignation and HR closed the offboarding workflow.',
                'order' => 65,
            ],
            OffboardingRecord::STATUS_ARCHIVED => [
                'label' => 'Archived',
                'description' => 'Historical offboarding record only.',
                'order' => 70,
            ],
        ];
    }

    /**
     * @return array{key: string, label: string, description: string, order: int}
     */
    public static function stageForModuleKey(string $moduleKey): array
    {
        $stageKey = match ($moduleKey) {
            'resignation_notice_received' => OffboardingRecord::STATUS_SUBMITTED,
            'department_interview_handover' => OffboardingRecord::STATUS_DEPARTMENT_REVIEW,
            'finance_clearance' => OffboardingRecord::STATUS_FINANCE_CLEARANCE,
            'hr_final_review', 'hr_exit_interview', 'documentation_release' => OffboardingRecord::STATUS_HR_FINALIZATION,
            default => OffboardingRecord::STATUS_HR_FINALIZATION,
        };

        $definition = self::stageDefinitions()[$stageKey];

        return [
            'key' => $stageKey,
            'label' => $definition['label'],
            'description' => $definition['description'],
            'order' => $definition['order'],
        ];
    }

    public function initiate(Employee $employee, User $actor, array $data): OffboardingRecord
    {
        return DB::transaction(function () use ($employee, $actor, $data) {
            $employee->loadMissing(['user', 'department']);

            $existing = $employee->offboardingRecords()->active()->lockForUpdate()->latest()->first();
            if ($existing) {
                throw new LogicException('This employee already has an active offboarding record.');
            }

            $record = OffboardingRecord::create([
                'employee_id' => $employee->id,
                'initiated_by' => $actor->id,
                'initiated_by_hr_id' => $actor->id,
                'separation_date' => $data['separation_date'] ?? now()->toDateString(),
                'effective_last_working_day' => $data['last_working_day'],
                'last_working_day' => $data['last_working_day'],
                'reason' => $data['resignation_reason'],
                'resignation_reason' => $data['resignation_reason'],
                'remarks' => $data['remarks'] ?? null,
                'status' => OffboardingRecord::STATUS_DRAFT,
                'resignation_letter_attachment' => $this->storeResignationLetter($data['resignation_letter_attachment'] ?? null),
            ]);

            foreach (self::checklistTemplate() as $item) {
                $attributes = $item + [
                    'status' => ClearanceItem::STATUS_PENDING,
                    'required' => true,
                    'notes' => null,
                ];

                if ($item['module_key'] === 'resignation_notice_received') {
                    $attributes['status'] = ClearanceItem::STATUS_CLEARED;
                    $attributes['approved_by_user_id'] = $actor->id;
                    $attributes['approved_at'] = now();
                    $attributes['notes'] = 'Physical resignation letter received by HR and logged in the system.';
                    $attributes['remarks'] = $attributes['notes'];
                    $attributes['cleared_by'] = $actor->id;
                    $attributes['cleared_at'] = $attributes['approved_at'];
                }

                $record->clearanceItems()->create($attributes);
            }

            $this->logWorkflowAction('offboarding_created', OffboardingRecord::class, $record->id, [
                'employee_id' => $employee->id,
                'offboarding_record_id' => $record->id,
                'status' => $record->status,
                'resignation_reason' => $record->display_reason,
            ], $actor->id);

            return $record->load(['employee.user', 'employee.department', 'clearanceItems']);
        });
    }

    public function submit(OffboardingRecord $record, User $actor): OffboardingRecord
    {
        return DB::transaction(function () use ($record, $actor) {
            $record->loadMissing(['employee.user', 'employee.department', 'clearanceItems']);

            if ($record->status !== OffboardingRecord::STATUS_DRAFT) {
                throw new LogicException('Only draft offboarding records can be submitted.');
            }

            $record->forceFill([
                'status' => OffboardingRecord::STATUS_SUBMITTED,
                'submitted_at' => now(),
            ])->save();

            $this->logWorkflowAction(
                'offboarding_submitted',
                OffboardingRecord::class,
                $record->id,
                $this->transitionMetadata(OffboardingRecord::STATUS_DRAFT, $record->status, [
                    'employee_id' => $record->employee_id,
                    'offboarding_record_id' => $record->id,
                ]),
                $actor->id,
            );

            $this->notifySubmitted($record->fresh(['employee.user', 'employee.department']), $actor);

            return $record->fresh(['employee.user', 'employee.department', 'clearanceItems']);
        });
    }

    public function updateClearanceItem(ClearanceItem $item, User $actor, array $data): ClearanceItem
    {
        return DB::transaction(function () use ($item, $actor, $data) {
            $item->loadMissing('offboardingRecord.employee.department');
            $record = $item->offboardingRecord;

            if (!$record || !$record->isOpen()) {
                throw new LogicException('Only active offboarding records can be updated.');
            }

            if ($record->status === OffboardingRecord::STATUS_DRAFT && $item->module_key !== 'resignation_notice_received') {
                throw new LogicException('Submit the workflow before updating clearance items.');
            }

            $status = $data['status'];
            $notes = $data['notes'] ?? null;

            if ($item->owner_role === 'finance' && !$this->departmentItemsCleared($record) && $status !== ClearanceItem::STATUS_PENDING) {
                throw new LogicException('Finance clearance cannot be completed before department review is finished.');
            }

            if (
                $item->owner_role === 'hr'
                && in_array($item->module_key, ['hr_final_review', 'hr_exit_interview', 'documentation_release'], true)
                && !$this->hrFinalizationUnlocked($record)
            ) {
                throw new LogicException('HR cannot update stage 4 until department and finance clearance are completed.');
            }

            $approvedAt = $status !== ClearanceItem::STATUS_PENDING ? now() : null;
            $approvedBy = $status !== ClearanceItem::STATUS_PENDING ? $actor->id : null;

            $item->forceFill([
                'status' => $status,
                'notes' => $notes,
                'remarks' => $notes,
                'approved_by_user_id' => $approvedBy,
                'approved_at' => $approvedAt,
                'cleared_by' => $status === ClearanceItem::STATUS_CLEARED ? $actor->id : null,
                'cleared_at' => $status === ClearanceItem::STATUS_CLEARED ? $approvedAt : null,
            ])->save();

            $record = $record->fresh(['employee.user', 'employee.department', 'clearanceItems']);
            $previousStatus = $record->status;
            $nextStatus = $this->deriveWorkflowStatus($record);
            if ($previousStatus !== $nextStatus) {
                $record->forceFill(['status' => $nextStatus])->save();
            }

            $this->logWorkflowAction('clearance_item_updated', ClearanceItem::class, $item->id, [
                'offboarding_record_id' => $record->id,
                'clearance_item_id' => $item->id,
                'item_name' => $item->item_name,
                'status' => $item->status,
                'owner_role' => $item->owner_role,
            ], $actor->id);

            if ($previousStatus !== $record->fresh()->status) {
                $this->handleStageTransitionNotifications($record->fresh(['employee.user', 'employee.department', 'clearanceItems']), $previousStatus, $actor);
            }

            return $item->fresh(['approvedBy', 'offboardingRecord']);
        });
    }

    public function finalize(OffboardingRecord $record, User $actor): OffboardingRecord
    {
        return DB::transaction(function () use ($record, $actor) {
            $record->loadMissing(['employee.user', 'employee.department', 'clearanceItems']);

            if ($record->isFinalized()) {
                return $record;
            }

            if ($record->status !== OffboardingRecord::STATUS_HR_FINALIZATION) {
                throw new LogicException('Offboarding must reach HR finalization before completion.');
            }

            if ($record->clearanceItems()
                ->where('required', true)
                ->where('owner_role', '!=', 'hr')
                ->where('status', '!=', ClearanceItem::STATUS_CLEARED)
                ->exists()) {
                throw new LogicException('Department and finance clearances must be completed before HR finalization.');
            }

            $hrFinalItems = $record->clearanceItems
                ->where('owner_role', 'hr')
                ->whereIn('module_key', ['hr_final_review', 'hr_exit_interview', 'documentation_release']);

            foreach ($hrFinalItems as $item) {
                $item->forceFill([
                    'status' => ClearanceItem::STATUS_CLEARED,
                    'approved_by_user_id' => $actor->id,
                    'approved_at' => now(),
                    'cleared_by' => $actor->id,
                    'cleared_at' => now(),
                    'notes' => $item->notes ?: 'Final HR review completed during workflow finalization.',
                    'remarks' => $item->remarks ?: 'Final HR review completed during workflow finalization.',
                ])->save();
            }

            $record->forceFill([
                'status' => OffboardingRecord::STATUS_COMPLETED,
                'finalized_by' => $actor->id,
                'finalized_at' => now(),
                'completed_at' => now(),
            ])->save();

            if ($this->shouldDeactivateNow($record)) {
                $this->deactivateEmployeeAccess($record, $actor);
            }

            $this->logWorkflowAction('hr_finalized_offboarding', OffboardingRecord::class, $record->id, [
                'employee_id' => $record->employee_id,
                'offboarding_record_id' => $record->id,
                'status' => $record->status,
            ], $actor->id);

            $this->notifyCompleted($record->fresh(['employee.user', 'employee.department']), $actor);

            return $record->fresh(['employee.user', 'employee.department', 'clearanceItems']);
        });
    }

    public function requestCancellation(OffboardingRecord $record, User $actor, ?string $reason = null): OffboardingRecord
    {
        return DB::transaction(function () use ($record, $actor, $reason) {
            $record->loadMissing(['employee.user', 'employee.department']);

            if (!$record->canEmployeeRequestCancellation()) {
                throw new LogicException('This offboarding record can no longer accept a resignation cancellation request.');
            }

            if ($record->employee?->user?->archived_at) {
                throw new LogicException('Resignation cancellation can only be requested before the account is deactivated.');
            }

            $record->forceFill([
                'cancellation_requested_by' => $actor->id,
                'cancellation_requested_at' => now(),
                'cancellation_reason' => $reason,
                'cancellation_request_status' => OffboardingRecord::CANCELLATION_STATUS_PENDING,
                'cancellation_reviewed_by' => null,
                'cancellation_reviewed_at' => null,
                'cancellation_review_notes' => null,
            ])->save();

            $this->logWorkflowAction('offboarding_cancellation_requested', OffboardingRecord::class, $record->id, [
                'employee_id' => $record->employee_id,
                'offboarding_record_id' => $record->id,
                'status' => $record->status,
            ], $actor->id);

            $this->notifyCancellationRequested($record->fresh(['employee.user', 'employee.department']), $actor);

            return $record->fresh(['employee.user', 'employee.department', 'clearanceItems']);
        });
    }

    public function approveCancellation(OffboardingRecord $record, User $actor, ?string $notes = null): OffboardingRecord
    {
        return DB::transaction(function () use ($record, $actor, $notes) {
            $record->loadMissing(['employee.user', 'employee.department']);

            if (!$record->hasPendingCancellationRequest()) {
                throw new LogicException('No pending resignation cancellation request was found.');
            }

            $employee = $record->employee;
            if ($employee?->user?->archived_at) {
                throw new LogicException('Resignation cancellation can no longer be approved after the account is deactivated.');
            }

            if ($employee && strtolower((string) $employee->status) !== 'active') {
                $employee->forceFill(['status' => 'active'])->save();
            }

            if ($employee?->user && $employee->user->archived_at !== null) {
                $employee->user->forceFill(['archived_at' => null])->save();
            }

            $record->forceFill([
                'status' => OffboardingRecord::STATUS_CANCELLED,
                'completed_at' => null,
                'finalized_at' => null,
                'finalized_by' => null,
                'closed_at' => null,
                'cancellation_request_status' => OffboardingRecord::CANCELLATION_STATUS_APPROVED,
                'cancellation_reviewed_by' => $actor->id,
                'cancellation_reviewed_at' => now(),
                'cancellation_review_notes' => $notes,
            ])->save();

            $this->logWorkflowAction('offboarding_cancellation_approved', OffboardingRecord::class, $record->id, [
                'employee_id' => $record->employee_id,
                'offboarding_record_id' => $record->id,
                'status' => $record->status,
            ], $actor->id);

            $this->notifyCancellationReviewed($record->fresh(['employee.user', 'employee.department']), $actor, true);

            return $record->fresh(['employee.user', 'employee.department', 'clearanceItems']);
        });
    }

    public function rejectCancellation(OffboardingRecord $record, User $actor, ?string $notes = null): OffboardingRecord
    {
        return DB::transaction(function () use ($record, $actor, $notes) {
            $record->loadMissing(['employee.user', 'employee.department']);

            if (!$record->hasPendingCancellationRequest()) {
                throw new LogicException('No pending resignation cancellation request was found.');
            }

            $record->forceFill([
                'cancellation_request_status' => OffboardingRecord::CANCELLATION_STATUS_REJECTED,
                'cancellation_reviewed_by' => $actor->id,
                'cancellation_reviewed_at' => now(),
                'cancellation_review_notes' => $notes,
            ])->save();

            if ($this->shouldDeactivateNow($record)) {
                $this->deactivateEmployeeAccess($record, $actor);
            }

            $this->logWorkflowAction('offboarding_cancellation_rejected', OffboardingRecord::class, $record->id, [
                'employee_id' => $record->employee_id,
                'offboarding_record_id' => $record->id,
                'status' => $record->status,
            ], $actor->id);

            $this->notifyCancellationReviewed($record->fresh(['employee.user', 'employee.department']), $actor, false);

            return $record->fresh(['employee.user', 'employee.department', 'clearanceItems']);
        });
    }

    public function reopen(OffboardingRecord $record, User $actor): OffboardingRecord
    {
        return DB::transaction(function () use ($record, $actor) {
            $record->loadMissing(['employee.user', 'employee.department', 'clearanceItems']);

            if ($record->status === OffboardingRecord::STATUS_ARCHIVED) {
                throw new LogicException('Archived offboarding records are historical and cannot be reopened.');
            }

            $reopenedStatus = $this->deriveWorkflowStatus($record, reopenMode: true);

            $record->forceFill([
                'status' => $reopenedStatus,
                'reopened_by' => $actor->id,
                'reopened_at' => now(),
                'finalized_by' => null,
                'finalized_at' => null,
                'completed_at' => null,
            ])->save();

            $this->logWorkflowAction('offboarding_reopened', OffboardingRecord::class, $record->id, [
                'employee_id' => $record->employee_id,
                'offboarding_record_id' => $record->id,
                'status' => $record->status,
            ], $actor->id);

            $this->notifyOwnersForCurrentStage($record->fresh(['employee.user', 'employee.department', 'clearanceItems']), $actor);

            return $record->fresh(['employee.user', 'employee.department', 'clearanceItems']);
        });
    }

    public function close(OffboardingRecord $record, User $actor): OffboardingRecord
    {
        return DB::transaction(function () use ($record, $actor) {
            $record->loadMissing('employee.user');

            if ($record->status !== OffboardingRecord::STATUS_COMPLETED) {
                throw new LogicException('Only completed offboarding records can be archived.');
            }

            $record->forceFill([
                'status' => OffboardingRecord::STATUS_ARCHIVED,
                'closed_at' => now(),
            ])->save();

            $this->logWorkflowAction('offboarding_archived', OffboardingRecord::class, $record->id, [
                'employee_id' => $record->employee_id,
                'offboarding_record_id' => $record->id,
                'status' => $record->status,
            ], $actor->id);

            return $record->fresh(['employee.user', 'employee.department', 'clearanceItems']);
        });
    }

    public function remindCurrentStage(OffboardingRecord $record, User $actor): int
    {
        $record->loadMissing(['employee.user', 'employee.department', 'clearanceItems']);

        if (!$record->isOpen()) {
            throw new LogicException('Only active offboarding records can send reminders.');
        }

        $currentOwnerRole = match ($record->status) {
            OffboardingRecord::STATUS_SUBMITTED,
            OffboardingRecord::STATUS_DEPARTMENT_REVIEW => 'department_head',
            OffboardingRecord::STATUS_FINANCE_CLEARANCE => 'finance',
            OffboardingRecord::STATUS_HR_FINALIZATION => 'hr',
            default => null,
        };

        if (!$currentOwnerRole) {
            throw new LogicException('This offboarding record has no reminder-enabled stage.');
        }

        $pendingItems = $record->clearanceItems
            ->where('owner_role', $currentOwnerRole)
            ->filter(fn (ClearanceItem $item) => $item->status === ClearanceItem::STATUS_PENDING)
            ->values();

        if ($pendingItems->isEmpty()) {
            throw new LogicException('There are no pending clearance items for the current stage.');
        }

        $recipients = $this->resolveRecipientsForRole($record->employee, $currentOwnerRole)
            ->unique('id')
            ->values();

        if ($recipients->isEmpty()) {
            throw new LogicException('No recipients are configured for the current offboarding stage.');
        }

        $itemPreview = $pendingItems
            ->pluck('item_name')
            ->filter()
            ->take(2)
            ->implode(', ');

        $remainingCount = $pendingItems->count();
        $message = trim(($record->employee?->first_name . ' ' . $record->employee?->last_name) . ' has ' . $remainingCount . ' pending clearance item' . ($remainingCount === 1 ? '' : 's') . ' for ' . str_replace('_', ' ', $currentOwnerRole) . '.');

        if ($itemPreview !== '') {
            $message .= ' Pending: ' . $itemPreview . ($remainingCount > 2 ? ', ...' : '') . '.';
        }

        $this->notifyWorkflowUsers($this->notificationService, $recipients, [
            'title' => 'Offboarding action reminder',
            'message' => $message,
            'module' => 'offboarding',
            'record_id' => $record->id,
            'route_name' => 'offboarding.show',
            'route_params' => ['offboarding' => $record->id],
            'event_key' => 'offboarding.stage.reminder',
            'priority' => 'high',
            'type' => 'warning',
        ], $actor);

        $this->logWorkflowAction('offboarding_stage_reminder_sent', OffboardingRecord::class, $record->id, [
            'employee_id' => $record->employee_id,
            'offboarding_record_id' => $record->id,
            'status' => $record->status,
            'owner_role' => $currentOwnerRole,
            'pending_items_count' => $remainingCount,
        ], $actor->id);

        return $recipients->count();
    }

    public function shouldDeactivateNow(OffboardingRecord $record): bool
    {
        if ($record->status === OffboardingRecord::STATUS_CANCELLED || $record->hasPendingCancellationRequest()) {
            return false;
        }

        $lastWorkingDay = $record->display_last_working_day;

        if (!$lastWorkingDay) {
            return true;
        }

        return $lastWorkingDay->copy()->startOfDay()->lte(now()->startOfDay());
    }

    public function processPendingDeactivations(?User $actor = null): int
    {
        $processed = 0;

        OffboardingRecord::query()
            ->with(['employee.user'])
            ->whereIn('status', [OffboardingRecord::STATUS_COMPLETED, OffboardingRecord::STATUS_ARCHIVED])
            ->orderBy('id')
            ->chunkById(100, function ($records) use (&$processed, $actor) {
                foreach ($records as $record) {
                    if (!$this->shouldDeactivateNow($record)) {
                        continue;
                    }

                    if ($this->deactivateEmployeeAccess($record, $actor)) {
                        $processed++;
                    }
                }
            });

        return $processed;
    }

    private function storeResignationLetter(mixed $file): ?string
    {
        if (!$file instanceof UploadedFile) {
            return null;
        }

        return $file->store('offboarding_letters');
    }

    private function departmentItemsCleared(OffboardingRecord $record): bool
    {
        return $record->clearanceItems
            ->where('owner_role', 'department_head')
            ->where('required', true)
            ->every(fn (ClearanceItem $item) => $item->status === ClearanceItem::STATUS_CLEARED);
    }

    private function financeItemsCleared(OffboardingRecord $record): bool
    {
        return $record->clearanceItems
            ->where('owner_role', 'finance')
            ->where('required', true)
            ->every(fn (ClearanceItem $item) => $item->status === ClearanceItem::STATUS_CLEARED);
    }

    private function hrFinalizationUnlocked(OffboardingRecord $record): bool
    {
        return $record->clearanceItems
            ->where('required', true)
            ->where('owner_role', '!=', 'hr')
            ->every(fn (ClearanceItem $item) => $item->status === ClearanceItem::STATUS_CLEARED);
    }

    private function deriveWorkflowStatus(OffboardingRecord $record, bool $reopenMode = false): string
    {
        if (!$reopenMode && in_array($record->status, [OffboardingRecord::STATUS_COMPLETED, OffboardingRecord::STATUS_ARCHIVED], true)) {
            return $record->status;
        }

        if (!$record->submitted_at) {
            return OffboardingRecord::STATUS_DRAFT;
        }

        if ($this->financeItemsCleared($record)) {
            return OffboardingRecord::STATUS_HR_FINALIZATION;
        }

        if ($this->departmentItemsCleared($record)) {
            return OffboardingRecord::STATUS_FINANCE_CLEARANCE;
        }

        $departmentItems = $record->clearanceItems->where('owner_role', 'department_head');
        $hasDepartmentReviewActivity = $departmentItems->contains(function (ClearanceItem $item) {
            return $item->status !== ClearanceItem::STATUS_PENDING || filled($item->notes) || filled($item->remarks);
        });

        return $hasDepartmentReviewActivity
            ? OffboardingRecord::STATUS_DEPARTMENT_REVIEW
            : OffboardingRecord::STATUS_SUBMITTED;
    }

    private function handleStageTransitionNotifications(OffboardingRecord $record, string $previousStatus, User $actor): void
    {
        $currentStatus = $record->status;

        if ($previousStatus !== OffboardingRecord::STATUS_FINANCE_CLEARANCE && $currentStatus === OffboardingRecord::STATUS_FINANCE_CLEARANCE) {
            $this->logWorkflowAction('department_clearance_completed', OffboardingRecord::class, $record->id, [
                'employee_id' => $record->employee_id,
                'offboarding_record_id' => $record->id,
            ], $actor->id);

            $this->notifyHrStageCompleted(
                $record,
                'Department clearance completed',
                'department_review'
            );

            return;
        }

        if ($previousStatus !== OffboardingRecord::STATUS_HR_FINALIZATION && $currentStatus === OffboardingRecord::STATUS_HR_FINALIZATION) {
            $this->logWorkflowAction('finance_clearance_completed', OffboardingRecord::class, $record->id, [
                'employee_id' => $record->employee_id,
                'offboarding_record_id' => $record->id,
            ], $actor->id);

            $this->notifyHrStageCompleted(
                $record,
                'Finance clearance completed',
                'finance_clearance'
            );
        }
    }

    private function notifySubmitted(OffboardingRecord $record, User $actor): void
    {
        $employeeUser = $record->employee?->user;
        $recipients = collect();

        if ($employeeUser) {
            $recipients->push($employeeUser);
        }

        $recipients = $recipients
            ->merge($this->resolveRecipientsForRole($record->employee, 'department_head'))
            ->merge($this->resolveRecipientsForRole($record->employee, 'finance'))
            ->unique('id')
            ->values();

        $this->notifyWorkflowUsers($this->notificationService, $recipients, [
            'title' => 'Offboarding sent for review',
            'message' => trim(($record->employee?->first_name . ' ' . $record->employee?->last_name) . ' has entered offboarding review. Department head and finance head have been notified to check their clearance steps.'),
            'module' => 'offboarding',
            'record_id' => $record->id,
            'route_name' => 'offboarding.show',
            'route_params' => ['offboarding' => $record->id],
            'event_key' => 'offboarding.submitted',
            'priority' => 'high',
            'type' => 'warning',
        ], $actor);
    }

    private function notifyCancellationRequested(OffboardingRecord $record, User $actor): void
    {
        $recipients = AccessControl::adminUsers()
            ->merge(AccessControl::hrHeadUsers())
            ->unique('id')
            ->values();

        $this->notifyWorkflowUsers($this->notificationService, $recipients, [
            'title' => 'Resignation cancellation requested',
            'message' => trim(($record->employee?->first_name . ' ' . $record->employee?->last_name) . ' requested to cancel the resignation/offboarding process.'),
            'module' => 'offboarding',
            'record_id' => $record->id,
            'route_name' => 'offboarding.show',
            'route_params' => ['offboarding' => $record->id],
            'event_key' => 'offboarding.cancellation.requested',
            'priority' => 'high',
            'type' => 'warning',
        ], $actor);
    }

    private function notifyCancellationReviewed(OffboardingRecord $record, User $actor, bool $approved): void
    {
        $employeeUser = $record->employee?->user;
        if (!$employeeUser) {
            return;
        }

        $this->notifyWorkflowUsers($this->notificationService, collect([$employeeUser]), [
            'title' => $approved ? 'Resignation cancellation approved' : 'Resignation cancellation declined',
            'message' => $approved
                ? 'HR approved your resignation cancellation request. Offboarding has been closed.'
                : 'HR declined your resignation cancellation request. Offboarding remains active.',
            'module' => 'offboarding',
            'record_id' => $record->id,
            'route_name' => 'offboarding.show',
            'route_params' => ['offboarding' => $record->id],
            'event_key' => $approved ? 'offboarding.cancellation.approved' : 'offboarding.cancellation.rejected',
            'priority' => 'high',
            'type' => $approved ? 'success' : 'warning',
        ], $actor);
    }

    private function notifyHrStageCompleted(OffboardingRecord $record, string $title, string $stageKey): void
    {
        $recipients = AccessControl::adminUsers()
            ->merge(AccessControl::hrHeadUsers())
            ->unique('id')
            ->values();

        $this->notifyWorkflowUsers($this->notificationService, $recipients, [
            'title' => $title,
            'message' => trim(($record->employee?->first_name . ' ' . $record->employee?->last_name) . ' advanced to ' . str_replace('_', ' ', $stageKey) . '.'),
            'module' => 'offboarding',
            'record_id' => $record->id,
            'route_name' => 'offboarding.show',
            'route_params' => ['offboarding' => $record->id],
            'event_key' => 'offboarding.' . $stageKey . '.completed',
            'priority' => 'high',
            'type' => 'info',
        ]);
    }

    private function notifyCompleted(OffboardingRecord $record, User $actor): void
    {
        $recipients = AccessControl::adminUsers()
            ->merge(AccessControl::hrHeadUsers())
            ->merge($record->employee?->user ? collect([$record->employee->user]) : collect())
            ->unique('id')
            ->values();

        $this->notifyWorkflowUsers($this->notificationService, $recipients, [
            'title' => 'Offboarding completed',
            'message' => trim(($record->employee?->first_name . ' ' . $record->employee?->last_name) . ' has completed the offboarding workflow.'),
            'module' => 'offboarding',
            'record_id' => $record->id,
            'route_name' => 'offboarding.show',
            'route_params' => ['offboarding' => $record->id],
            'event_key' => 'offboarding.completed',
            'priority' => 'high',
            'type' => 'success',
        ], $actor);
    }

    private function notifyOwnersForCurrentStage(OffboardingRecord $record, User $actor): void
    {
        $recipients = match ($record->status) {
            OffboardingRecord::STATUS_SUBMITTED, OffboardingRecord::STATUS_DEPARTMENT_REVIEW => $this->resolveRecipientsForRole($record->employee, 'department_head')
                ->merge($record->employee?->user ? collect([$record->employee->user]) : collect()),
            OffboardingRecord::STATUS_FINANCE_CLEARANCE => $this->resolveRecipientsForRole($record->employee, 'finance')
                ->merge($record->employee?->user ? collect([$record->employee->user]) : collect()),
            OffboardingRecord::STATUS_HR_FINALIZATION => AccessControl::adminUsers()
                ->merge(AccessControl::hrHeadUsers())
                ->merge($record->employee?->user ? collect([$record->employee->user]) : collect()),
            default => collect(),
        };

        $this->notifyWorkflowUsers($this->notificationService, $recipients->unique('id')->values(), [
            'title' => 'Offboarding reopened',
            'message' => trim(($record->employee?->first_name . ' ' . $record->employee?->last_name) . ' was reopened for ' . str_replace('_', ' ', $record->status) . '.'),
            'module' => 'offboarding',
            'record_id' => $record->id,
            'route_name' => 'offboarding.show',
            'route_params' => ['offboarding' => $record->id],
            'event_key' => 'offboarding.reopened',
            'priority' => 'high',
            'type' => 'warning',
        ], $actor);
    }

    private function deactivateEmployeeAccess(OffboardingRecord $record, ?User $actor = null): bool
    {
        $record->loadMissing(['employee.user']);

        $employee = $record->employee;
        $user = $employee?->user;
        $changed = false;
        $timestamp = now();

        if ($user && $user->archived_at === null) {
            $user->forceFill(['archived_at' => $timestamp])->save();
            $changed = true;
        }

        if ($employee && strtolower((string) $employee->status) !== 'inactive') {
            $employee->forceFill(['status' => 'inactive'])->save();
            $changed = true;
        }

        if ($changed) {
            $this->logWorkflowAction('employee_account_deactivated', OffboardingRecord::class, $record->id, [
                'employee_id' => $record->employee_id,
                'offboarding_record_id' => $record->id,
            ], $actor?->id);
        }

        return $changed;
    }

    private function resolveRecipientsForRole(?Employee $employee, string $ownerRole): Collection
    {
        $employeeDepartmentId = $employee?->department_id;
        $ownerRole = strtolower(trim($ownerRole));

        return match ($ownerRole) {
            'department_head' => $employeeDepartmentId
                ? AccessControl::headApproversForDepartment($employeeDepartmentId)
                : collect(),
            'finance' => AccessControl::financeApprovers(),
            'hr' => AccessControl::adminUsers()->merge(AccessControl::hrHeadUsers())->unique('id')->values(),
            default => collect(),
        };
    }
}

