<?php

namespace App\Policies;

use App\Models\TravelOrder;
use App\Models\User;
use App\Services\AccessControl;

class TravelOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->employee || $user->isAdmin() || AccessControl::isHeadOrDean($user) || AccessControl::isHrHead($user);
    }

    public function view(User $user, TravelOrder $travelOrder): bool
    {
        if ($user->isAdmin() || AccessControl::isHrHead($user) || AccessControl::isPresidentHead($user)) {
            return true;
        }

        if ((int) ($user->employee?->id ?? 0) === (int) $travelOrder->employee_id) {
            return true;
        }

        return (int) ($user->employee?->department_id ?? 0) === (int) $travelOrder->department_id
            && AccessControl::isHeadOrDean($user);
    }

    public function create(User $user): bool
    {
        return (bool) $user->employee;
    }

    public function update(User $user, TravelOrder $travelOrder): bool
    {
        return (int) ($user->employee?->id ?? 0) === (int) $travelOrder->employee_id
            && $travelOrder->status === TravelOrder::STATUS_DRAFT;
    }

    public function submit(User $user, TravelOrder $travelOrder): bool
    {
        return $this->update($user, $travelOrder);
    }

    public function cancel(User $user, TravelOrder $travelOrder): bool
    {
        $isCancelableStatus = !in_array($travelOrder->status, [TravelOrder::STATUS_CANCELLED, TravelOrder::STATUS_COMPLETED], true);

        if ($user->isAdmin() || AccessControl::isHrHead($user)) {
            return $isCancelableStatus;
        }

        return (int) ($user->employee?->id ?? 0) === (int) $travelOrder->employee_id
            && $isCancelableStatus;
    }

    public function complete(User $user, TravelOrder $travelOrder): bool
    {
        return $user->isAdmin() || AccessControl::isHrHead($user);
    }

    public function remind(User $user, TravelOrder $travelOrder): bool
    {
        if (in_array($travelOrder->status, [
            TravelOrder::STATUS_DRAFT,
            TravelOrder::STATUS_CANCELLED,
            TravelOrder::STATUS_COMPLETED,
            TravelOrder::STATUS_REJECTED,
        ], true)) {
            return false;
        }

        if ($user->isAdmin() || AccessControl::isHrHead($user)) {
            return true;
        }

        if ((int) ($user->employee?->id ?? 0) === (int) $travelOrder->employee_id) {
            return true;
        }

        return AccessControl::isHeadOrDean($user)
            && (int) ($user->employee?->department_id ?? 0) === (int) $travelOrder->department_id;
    }

    public function approveDepartment(User $user, TravelOrder $travelOrder): bool
    {
        return AccessControl::isHeadOrDean($user)
            && (int) ($user->employee?->department_id ?? 0) === (int) $travelOrder->department_id
            && $travelOrder->status === TravelOrder::STATUS_SUBMITTED;
    }

    public function approveHr(User $user, TravelOrder $travelOrder): bool
    {
        $isOwnRequest = (int) ($user->employee?->id ?? 0) !== 0
            && (int) ($user->employee?->id ?? 0) === (int) $travelOrder->employee_id;

        if ($isOwnRequest) {
            return false;
        }

        return ($user->isAdmin() || AccessControl::isHrHead($user))
            && $travelOrder->status === TravelOrder::STATUS_DEPARTMENT_APPROVED;
    }

    public function finalApprove(User $user, TravelOrder $travelOrder): bool
    {
        return AccessControl::isPresidentHead($user)
            && $travelOrder->status === TravelOrder::STATUS_HR_REVIEW;
    }
}
