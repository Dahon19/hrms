<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\IndividualDevelopmentPlan;
use App\Models\Position;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class IdpController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('view-idp');

        $canManage = Gate::allows('manage-idp');
        $employeeId = (int) ($request->user()?->employee?->id ?? 0);

        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'department' => (string) $request->query('department', ''),
            'position' => (string) $request->query('position', ''),
            'status' => strtolower(trim((string) $request->query('status', ''))),
        ];

        $query = IndividualDevelopmentPlan::query()
            ->with(['employee.department', 'employee.positions.position', 'cycle', 'evaluation']);

        if (!$canManage) {
            $query->where('employee_id', $employeeId);
        }

        if ($filters['search'] !== '') {
            $search = $filters['search'];

            $query->where(function ($planQuery) use ($search) {
                $planQuery->whereHas('employee', function ($employeeQuery) use ($search) {
                    $employeeQuery->where(function ($nameQuery) use ($search) {
                        $nameQuery->where('first_name', 'like', '%' . $search . '%')
                            ->orWhere('last_name', 'like', '%' . $search . '%')
                            ->orWhere('employee_id', 'like', '%' . $search . '%');
                    })->orWhereHas('department', function ($departmentQuery) use ($search) {
                        $departmentQuery->where('department', 'like', '%' . $search . '%');
                    })->orWhereHas('positions.position', function ($positionQuery) use ($search) {
                        $positionQuery->where('position', 'like', '%' . $search . '%');
                    });
                })->orWhereHas('cycle', function ($cycleQuery) use ($search) {
                    $cycleQuery->where('title', 'like', '%' . $search . '%');
                });
            });
        }

        if ($filters['department'] !== '') {
            $departmentId = (int) $filters['department'];
            $query->whereHas('employee', function ($employeeQuery) use ($departmentId) {
                $employeeQuery->where('department_id', $departmentId);
            });
        }

        if ($filters['position'] !== '') {
            $positionId = (int) $filters['position'];
            $query->whereHas('employee.positions', function ($positionQuery) use ($positionId) {
                $positionQuery->where('position_id', $positionId);
            });
        }

        if ($filters['status'] !== '') {
            $statusMap = [
                'draft' => ['draft'],
                'pending' => ['submitted'],
                'active' => ['reviewed', 'active'],
                'archived' => ['locked', 'archived'],
            ];

            $statuses = $statusMap[$filters['status']] ?? [];
            if ($statuses !== []) {
                $query->whereIn('status', $statuses);
            }
        }

        $plans = $query
            ->latest('created_at')
            ->paginate(10)
            ->withQueryString();

        $departments = Department::query()
            ->select('departments.id', 'departments.department')
            ->join('employees', 'employees.department_id', '=', 'departments.id')
            ->join('individual_development_plans', 'individual_development_plans.employee_id', '=', 'employees.id')
            ->when(!$canManage, function ($departmentQuery) use ($employeeId) {
                $departmentQuery->where('employees.id', $employeeId);
            })
            ->distinct()
            ->orderBy('departments.department')
            ->get()
            ->map(function (Department $department) {
                return [
                    'value' => (string) $department->id,
                    'label' => (string) $department->department,
                ];
            })
            ->values();

        $positions = Position::query()
            ->select('positions.id', 'positions.position')
            ->join('employee_positions', 'employee_positions.position_id', '=', 'positions.id')
            ->join('employees', 'employees.id', '=', 'employee_positions.employee_id')
            ->join('individual_development_plans', 'individual_development_plans.employee_id', '=', 'employees.id')
            ->when(!$canManage, function ($positionQuery) use ($employeeId) {
                $positionQuery->where('employees.id', $employeeId);
            })
            ->distinct()
            ->orderBy('positions.position')
            ->get()
            ->map(function (Position $position) {
                return [
                    'value' => (string) $position->id,
                    'label' => (string) $position->position,
                ];
            })
            ->values();

        return view('idp.index', [
            'plans' => $plans,
            'canManage' => $canManage,
            'filters' => $filters,
            'departments' => $departments,
            'positions' => $positions,
            'statusOptions' => [
                ['value' => 'draft', 'label' => 'Draft'],
                ['value' => 'pending', 'label' => 'Pending'],
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'archived', 'label' => 'Archived'],
            ],
            'hasFilters' => collect($filters)->contains(fn ($value) => filled($value)),
            'openPlanId' => old('plan_id'),
        ]);
    }

    public function update(Request $request, IndividualDevelopmentPlan $idp)
    {
        Gate::authorize('view-idp');
        Gate::authorize('manage-idp');

        if (in_array(strtolower((string) $idp->status), ['locked', 'archived'], true)) {
            abort(403);
        }

        $validated = $request->validate([
            'plan_id' => ['required', 'integer'],
            'development_goals' => ['nullable', 'string', 'max:5000'],
            'employee_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $idp->update([
            'development_goals' => $validated['development_goals'] ?? null,
            'employee_notes' => $validated['employee_notes'] ?? null,
        ]);

        AuditLogger::logSystem('idp_updated', [
            'idp_id' => $idp->id,
            'employee_id' => $idp->employee_id,
            'spms_cycle_id' => $idp->spms_cycle_id,
        ], $request->user()?->id, IndividualDevelopmentPlan::class, $idp->id);

        return back()->with('success', 'Individual Development Plan updated.');
    }
}
