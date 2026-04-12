<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\AccessControl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $q = trim((string) $request->query('q', ''));
        $limit = (int) $request->query('limit', 20);
        $limit = max(1, min($limit, 50));

        $canViewAll = $user->isAdmin()
            || AccessControl::isHrDepartmentLeader($user)
            || AccessControl::isPresidentDepartmentLeader($user);
        $canViewDepartment = AccessControl::isDepartmentLeader($user);

        $includeArchivedRequested = $request->boolean('include_archived', false);
        $includeArchived = $includeArchivedRequested && $canViewAll;

        $query = Employee::query()
            ->select([
                'id',
                'employee_id',
                'first_name',
                'middle_name',
                'last_name',
                'suffix',
                'department_id',
                'user_id',
            ])
            ->with([
                'department:id,department',
                'user:id,role,archived_at',
            ])
            ->whereHas('user', function ($userQuery) use ($includeArchived) {
                $userQuery->where('role', '!=', 'admin');
                if (!$includeArchived) {
                    $userQuery->whereNull('archived_at');
                }
            });

        if ($canViewAll) {
            // Full list already allowed.
        } elseif ($canViewDepartment) {
            $query->where('department_id', (int) ($user->employee?->department_id ?? 0));
        } else {
            $query->where('id', (int) ($user->employee?->id ?? 0));
        }

        if ($q !== '') {
            $query->where(function ($searchQuery) use ($q) {
                $searchQuery->where('employee_id', 'like', '%' . $q . '%')
                    ->orWhere('first_name', 'like', '%' . $q . '%')
                    ->orWhere('middle_name', 'like', '%' . $q . '%')
                    ->orWhere('last_name', 'like', '%' . $q . '%');
            });
        }

        $employees = $query
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit($limit)
            ->get();

        $results = $employees->map(function (Employee $employee) {
            $middleInitial = $employee->middle_name
                ? ' ' . strtoupper(substr($employee->middle_name, 0, 1)) . '.'
                : '';
            $suffix = $employee->suffix ? ' ' . $employee->suffix : '';
            $displayName = trim($employee->first_name . $middleInitial . ' ' . $employee->last_name . $suffix);

            return [
                'id' => $employee->id,
                'text' => $displayName,
                'employee_id' => $employee->employee_id,
                'name' => $displayName,
                'archived' => (bool) optional($employee->user)->archived_at,
            ];
        })->values();

        return response()->json([
            'results' => $results,
            'pagination' => ['more' => false],
        ]);
    }
}
