<?php

namespace App\Policies;

use App\Models\PdsProfile;
use App\Models\User;
use App\Services\AccessControl;

class PdsProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || AccessControl::isHrHead($user) || (bool) $user->employee;
    }

    public function view(User $user, PdsProfile $profile): bool
    {
        if ($user->isAdmin() || AccessControl::isHrHead($user)) {
            return true;
        }

        return (int) ($user->employee?->id ?? 0) === (int) $profile->employee_id;
    }

    public function update(User $user, PdsProfile $profile): bool
    {
        if ($profile->status === 'verified' || $profile->employee?->hasActiveOffboardingRecord()) {
            return false;
        }

        return (int) ($user->employee?->id ?? 0) === (int) $profile->employee_id
            && !$user->isAdmin()
            && in_array($profile->status, ['draft', 'needs_correction'], true);
    }

    public function verify(User $user, PdsProfile $profile): bool
    {
        return ($user->isAdmin() || AccessControl::isHrHead($user))
            && (int) ($user->employee?->id ?? 0) !== (int) $profile->employee_id;
    }

    public function overrideLock(User $user, PdsProfile $profile): bool
    {
        return $user->isAdmin();
    }
}

