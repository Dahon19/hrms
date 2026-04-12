<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AccessControl
{
    private const HR_DEPARTMENT_NAMES = [
        'hr department',
        'human resource department',
        'human resources department',
        'human resource office',
        'human resources office',
        'human resource management office',
        'human resources management office',
    ];

    private const FINANCE_DEPARTMENT_NAMES = [
        'finance bursars office',
        'finance & bursars office',
        'accounting office',
    ];

    public static function normalizeDepartmentName(?string $departmentName): string
    {
        $normalized = strtolower(trim($departmentName ?? ''));
        $normalized = preg_replace('/[^a-z0-9 ]/i', '', $normalized);
        return trim(preg_replace('/\s+/', ' ', $normalized));
    }

    public static function isHrDepartmentName(?string $departmentName): bool
    {
        $normalized = self::normalizeDepartmentName($departmentName);
        if ($normalized === '') {
            return false;
        }

        if (in_array($normalized, self::HR_DEPARTMENT_NAMES, true)) {
            return true;
        }

        return str_contains($normalized, 'human resource')
            || str_contains($normalized, 'human resources')
            || preg_match('/(^| )hr($| )/', $normalized) === 1;
    }

    public static function isHead(User $user): bool
    {
        return $user->positionName() === 'head';
    }

    public static function isDepartmentLeader(User $user): bool
    {
        return $user->positionName() === 'head';
    }

    public static function isSecretary(User $user): bool
    {
        return $user->positionName() === 'secretary';
    }

    public static function isCoordinator(User $user): bool
    {
        return $user->positionName() === 'coordinator';
    }

    public static function isDepartmentSupport(User $user): bool
    {
        return self::isSecretary($user) || self::isCoordinator($user);
    }

    public static function isHeadOrDean(User $user): bool
    {
        $positions = $user->employee?->positions?->pluck('position.position')->filter() ?? collect();
        if ($positions->isNotEmpty()) {
            $normalized = $positions->map(fn ($pos) => strtolower(trim($pos)))->values();
            return $normalized->contains(function ($pos) {
                return in_array($pos === 'dean' ? 'head' : $pos, ['head'], true);
            });
        }

        return $user->positionName() === 'head';
    }

    public static function isOrgChartViewer(User $user): bool
    {
        $positions = $user->employee?->positions?->pluck('position.position')->filter() ?? collect();
        $normalized = $positions->map(fn ($pos) => strtolower(trim($pos)))->values();
        return $normalized->contains(function ($pos) {
            $pos = $pos === 'dean' ? 'head' : $pos;
            return in_array($pos, ['head', 'coordinator', 'secretary'], true);
        });
    }

    public static function isHrHead(User $user): bool
    {
        if (!self::isHead($user)) {
            return false;
        }
        return self::isHrDepartmentName($user->employee?->department?->department ?? '');
    }

    public static function isHrStaff(User $user): bool
    {
        return self::isHrDepartmentName($user->employee?->department?->department ?? '');
    }

    public static function isPresidentHead(User $user): bool
    {
        if (!self::isHead($user)) {
            return false;
        }
        $normalizedDept = self::normalizeDepartmentName($user->employee?->department?->department ?? '');
        return $normalizedDept === 'presidents office';
    }

    public static function isPresidentOffice(User $user): bool
    {
        $normalizedDept = self::normalizeDepartmentName($user->employee?->department?->department ?? '');

        return $normalizedDept === 'presidents office';
    }

    public static function canAccessDashboard(User $user): bool
    {
        return $user->isAdmin()
            || self::isHeadOrDean($user)
            || self::isHrStaff($user)
            || self::isPresidentOffice($user);
    }

    public static function isHrDepartmentLeader(User $user): bool
    {
        if (!self::isDepartmentLeader($user)) {
            return false;
        }
        return self::isHrDepartmentName($user->employee?->department?->department ?? '');
    }

    public static function isPresidentDepartmentLeader(User $user): bool
    {
        if (!self::isDepartmentLeader($user)) {
            return false;
        }
        $normalizedDept = self::normalizeDepartmentName($user->employee?->department?->department ?? '');
        return $normalizedDept === 'presidents office';
    }

    public static function canViewEmployeeDocuments(User $user, ?Employee $employee): bool
    {
        if (!$employee) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if (self::isHrStaff($user)) {
            return true;
        }

        if (self::isDepartmentSupport($user)) {
            $userDeptId = $user->employee?->department_id;
            return $userDeptId && (int) $employee->department_id === (int) $userDeptId;
        }

        return (int) optional($user->employee)->id === (int) $employee->id;
    }

    public static function headApproversForDepartment(?int $departmentId): Collection
    {
        if (!$departmentId) {
            return collect();
        }

        return User::whereHas('employee', function ($query) use ($departmentId) {
            $query->where('department_id', $departmentId)
                ->whereHas('positions.position', function ($positionQuery) {
                    $positionQuery->whereIn('position', ['head', 'dean']);
                });
        })->get();
    }

    public static function hrHeadUsers(): Collection
    {
        return User::query()
            ->get()
            ->filter(fn (User $user) => self::isHrHead($user))
            ->values();
    }

    public static function hrUsers(): Collection
    {
        return User::query()
            ->get()
            ->filter(fn (User $user) => self::isHrStaff($user))
            ->values();
    }

    public static function presidentHeadUsers(): Collection
    {
        return User::whereHas('employee.department', function ($query) {
            $query->whereRaw('LOWER(department) = ?', ['presidents office']);
        })->whereHas('employee.positions.position', function ($positionQuery) {
            $positionQuery->whereIn('position', ['head']);
        })->get();
    }

    public static function adminUsers(): Collection
    {
        return User::where('role', 'admin')->get();
    }

    public static function isFinanceDepartmentName(?string $departmentName): bool
    {
        return in_array(self::normalizeDepartmentName($departmentName), self::FINANCE_DEPARTMENT_NAMES, true);
    }

    public static function isFinanceApprover(User $user): bool
    {
        return self::isHeadOrDean($user)
            && self::isFinanceDepartmentName($user->employee?->department?->department ?? '');
    }

    public static function financeApprovers(): Collection
    {
        return User::query()
            ->get()
            ->filter(fn (User $user) => self::isFinanceApprover($user))
            ->values();
    }

    public static function financeUsers(): Collection
    {
        return User::whereHas('employee.department', function ($query) {
            $query->where(function ($departmentQuery) {
                $departmentQuery
                    ->whereRaw('LOWER(TRIM(department)) = ?', ['finance bursars office'])
                    ->orWhereRaw('LOWER(TRIM(department)) = ?', ['finance & bursars office'])
                    ->orWhereRaw('LOWER(TRIM(department)) = ?', ['accounting office']);
            });
        })->get();
    }
}
