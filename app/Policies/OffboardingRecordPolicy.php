<?php

namespace App\Policies;

use App\Models\ClearanceItem;
use App\Models\Employee;
use App\Models\OffboardingRecord;
use App\Models\User;
use App\Services\AccessControl;

class OffboardingRecordPolicy
{
    private function isDepartmentParticipant(User $user): bool
    {
        return AccessControl::isHeadOrDean($user)
            && (int) ($user->employee?->department_id ?? 0) > 0;
    }

    private function isFinanceParticipant(User $user): bool
    {
        return AccessControl::isFinanceApprover($user);
    }

    public function viewAny(User $user): bool
    {
        return $user->canManageOffboarding()
            || $this->isFinanceParticipant($user)
            || $this->isDepartmentParticipant($user)
            || (bool) $user->employee;
    }

    public function view(User $user, OffboardingRecord $record): bool
    {
        if ($user->canManageOffboarding()) {
            return true;
        }

        if ($this->isDepartmentParticipant($user)) {
            return true;
        }

        if ($this->isFinanceParticipant($user)) {
            $record->loadMissing('clearanceItems');

            return $record->clearanceItems->contains(
                fn (ClearanceItem $item) => strtolower(trim((string) $item->owner_role)) === 'finance'
            );
        }

        if ((int) optional($user->employee)->id === (int) $record->employee_id) {
            return true;
        }

        $record->loadMissing('clearanceItems', 'employee.department');

        return $record->clearanceItems->contains(fn (ClearanceItem $item) => $this->canApproveClearanceItem($user, $record->employee, $item));
    }

    public function create(User $user, Employee $employee): bool
    {
        return $user->canManageOffboarding() && !$employee->user?->isAdmin();
    }

    public function update(User $user, OffboardingRecord $record): bool
    {
        return $user->canManageOffboarding();
    }

    public function finalize(User $user, OffboardingRecord $record): bool
    {
        return $user->canManageOffboarding();
    }

    public function submit(User $user, OffboardingRecord $record): bool
    {
        return $user->canManageOffboarding();
    }

    public function reopen(User $user, OffboardingRecord $record): bool
    {
        return $user->canManageOffboarding();
    }

    public function close(User $user, OffboardingRecord $record): bool
    {
        return $user->canManageOffboarding();
    }

    public function remind(User $user, OffboardingRecord $record): bool
    {
        return $user->canManageOffboarding() && $record->isOpen();
    }

    public function requestCancellation(User $user, OffboardingRecord $record): bool
    {
        return (int) optional($user->employee)->id === (int) $record->employee_id
            && $record->canEmployeeRequestCancellation()
            && !$record->employee?->user?->archived_at;
    }

    public function reviewCancellation(User $user, OffboardingRecord $record): bool
    {
        return $user->canManageOffboarding() && $record->hasPendingCancellationRequest();
    }

    public function approveItem(User $user, ClearanceItem $item): bool
    {
        return $this->canApproveClearanceItem($user, $item->offboardingRecord?->employee, $item);
    }

    private function canApproveClearanceItem(User $user, ?Employee $employee, ?ClearanceItem $item): bool
    {
        if (!$employee || !$item) {
            return false;
        }

        $record = $item->offboardingRecord;
        if (!$record || !$record->isOpen()) {
            return false;
        }

        if (!$this->isActiveForCurrentStage($record, $item)) {
            return false;
        }

        $ownerRole = strtolower(trim((string) $item->owner_role));
        $employeeDepartmentId = $employee->department_id;
        $userDepartmentId = $user->employee?->department_id;

        if (
            $ownerRole === 'hr'
            && in_array((string) $item->module_key, ['hr_final_review', 'hr_exit_interview', 'documentation_release'], true)
            && !$this->hrFinalizationUnlocked($record)
        ) {
            return false;
        }

        return match ($ownerRole) {
            'department_head' => !$user->canManageOffboarding()
                && (
                    AccessControl::isHeadOrDean($user)
                    || (
                        $employeeDepartmentId
                        && $employeeDepartmentId === $userDepartmentId
                        && AccessControl::headApproversForDepartment($employeeDepartmentId)->contains('id', $user->id)
                    )
                ),
            'hr' => $user->canManageOffboarding() || AccessControl::isHrHead($user),
            'finance' => $this->isFinanceParticipant($user),
            default => false,
        };
    }

    private function isActiveForCurrentStage(OffboardingRecord $record, ClearanceItem $item): bool
    {
        $ownerRole = strtolower(trim((string) $item->owner_role));

        return match ($ownerRole) {
            'department_head' => in_array($record->status, [
                OffboardingRecord::STATUS_SUBMITTED,
                OffboardingRecord::STATUS_DEPARTMENT_REVIEW,
            ], true),
            'finance' => $record->status === OffboardingRecord::STATUS_FINANCE_CLEARANCE,
            'hr' => $record->status === OffboardingRecord::STATUS_HR_FINALIZATION
                && !in_array((string) $item->module_key, ['resignation_notice_received'], true),
            default => false,
        };
    }

    private function hrFinalizationUnlocked(OffboardingRecord $record): bool
    {
        $record->loadMissing('clearanceItems');

        return $record->clearanceItems
            ->where('required', true)
            ->where('owner_role', '!=', 'hr')
            ->every(fn (ClearanceItem $item) => $item->status === ClearanceItem::STATUS_CLEARED);
    }
}

