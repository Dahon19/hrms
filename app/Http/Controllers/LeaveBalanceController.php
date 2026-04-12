<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Department;
use App\Models\LeaveBalance;
use App\Models\LeaveBalanceYearSetting;
use App\Models\LeaveType;
use App\Services\AccessControl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class LeaveBalanceController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('view-leave-balances');
        $currentYear = (int) now()->year;
        $year = (int) $request->query('year', $currentYear);
        if ($year < 2000 || $year > 2100) {
            $year = $currentYear;
        }

        $user = $request->user();
        $isHeadViewer = $user && (AccessControl::isHrHead($user) || AccessControl::isPresidentHead($user));
        $search = trim((string) $request->query('search', ''));
        $selectedDepartmentId = (int) $request->query('department_id', 0);
        $selectedEmployeeId = (int) $request->query('employee_id', 0);
        $employeesQuery = Employee::query()
            ->with(['department', 'user'])
            ->nonAdmin()
            ->orderBy('last_name')
            ->orderBy('first_name');

        $employeesQuery->where(function ($query) {
            $query->whereDoesntHave('department', function ($deptQuery) {
                $deptQuery->whereRaw('LOWER(department) = ?', ['presidents office']);
            })->orWhereDoesntHave('positions.position', function ($posQuery) {
                $posQuery->where('position', 'head');
            });
        });
        if ($isHeadViewer) {
            $employeesQuery->whereHas('user', function ($query) {
                $query->where('role', '!=', 'admin');
            });
        }

        $departmentOptions = match (true) {
            $user->canViewData(), $isHeadViewer => Department::query()
                ->whereHas('employees.user', fn ($query) => $query->where('role', '!=', 'admin'))
                ->orderBy('department')
                ->get(),
            default => Department::query()
                ->whereKey($user?->employee?->department_id)
                ->orderBy('department')
                ->get(),
        };

        $allowedDepartmentIds = $departmentOptions
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($selectedDepartmentId > 0 && !in_array($selectedDepartmentId, $allowedDepartmentIds, true)) {
            $selectedDepartmentId = $user->canViewData() || $isHeadViewer
                ? 0
                : (int) ($user?->employee?->department_id ?? 0);
        }

        if ($selectedDepartmentId > 0) {
            $employeesQuery->where('department_id', $selectedDepartmentId);
        }

        if ($search !== '') {
            $employeesQuery->where(function ($query) use ($search) {
                $query->where('first_name', 'like', '%' . $search . '%')
                    ->orWhere('last_name', 'like', '%' . $search . '%')
                    ->orWhere('employee_id', 'like', '%' . $search . '%');
            });
        }

        if ($selectedEmployeeId > 0) {
            if (!$user->canViewData() && (int) ($user?->employee?->id ?? 0) !== $selectedEmployeeId) {
                abort(403);
            }

            $employeesQuery->where('id', $selectedEmployeeId);
        } elseif (!$user->canViewData()) {
            $employeesQuery->where('id', (int) ($user?->employee?->id ?? 0));
        }

        $rows = $employeesQuery
            ->paginate(10)
            ->withQueryString();

        $employeeIds = $rows->getCollection()->pluck('id');
        $totals = LeaveBalance::select('employee_id', 'year', DB::raw('SUM(consumed) as total_consumed'))
            ->where('year', $year)
            ->when($employeeIds->isNotEmpty(), fn ($query) => $query->whereIn('employee_id', $employeeIds))
            ->groupBy('employee_id', 'year')
            ->get()
            ->keyBy(function ($row) {
                return $row->employee_id . '-' . $row->year;
            });

        $configuredStartingBalance = LeaveBalance::configuredStartingBalanceForYear($year);
        $configuredEligibilityMonths = LeaveBalance::configuredEligibilityMonthsForYear($year);
        $rows->setCollection(
            $rows->getCollection()->map(function ($employee) use ($totals, $year) {
                $totalKey = $employee->id . '-' . $year;
                $totalConsumed = $totals[$totalKey]->total_consumed ?? 0;
                $earned = LeaveBalance::calculateEarnedForEmployeeYear($employee, $year);

                return [
                'id' => $employee->id,
                'employee' => $employee,
                'year' => $year,
                'earned' => $earned,
                'consumed' => $totalConsumed,
                'remaining' => LeaveBalance::computedRemainingForEmployee($employee, $year, $totalConsumed),
                ];
            })
        );

        return view('leaves.balances', compact(
            'rows',
            'year',
            'search',
            'selectedDepartmentId',
            'departmentOptions',
            'configuredStartingBalance',
            'configuredEligibilityMonths'
        ));
    }

    public function storeYearSetting(Request $request)
    {
        Gate::authorize('manage-leave-balances');

        $validated = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'starting_balance' => 'required|numeric|min:0|max:999.99',
            'eligibility_months' => 'required|integer|min:0|max:24',
        ]);

        LeaveBalanceYearSetting::updateOrCreate(
            ['year' => (int) $validated['year']],
            [
                'starting_balance' => (float) $validated['starting_balance'],
                'eligibility_months' => (int) $validated['eligibility_months'],
            ]
        );

        return redirect()
            ->route('leave-balances.index', ['year' => (int) $validated['year']])
            ->with('success', 'Leave balance configuration updated.');
    }

    public function create()
    {
        Gate::authorize('manage-leave-balances');
        $employees = Employee::nonAdmin()->orderBy('last_name')->orderBy('first_name')
            ->where(function ($query) {
                $query->whereDoesntHave('department', function ($deptQuery) {
                    $deptQuery->whereRaw('LOWER(department) = ?', ['presidents office']);
                })->orWhereDoesntHave('positions.position', function ($posQuery) {
                    $posQuery->where('position', 'head');
                });
            })
            ->get();
        $types = LeaveType::orderBy('name')->get();
        return view('leaves.balances', compact('employees', 'types'));
    }

    public function store(Request $request)
    {
        Gate::authorize('manage-leave-balances');
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'year' => 'required|integer|min:2000|max:2100',
            'consumed' => 'required|numeric|min:0',
        ]);

        $employee = Employee::with('user', 'department', 'positions.position')->find($validated['employee_id']);
        if ($employee?->user && AccessControl::isPresidentHead($employee->user)) {
            return back()->with('error', "President's Office head does not have leave balances.");
        }

        $validated['earned'] = $employee
            ? LeaveBalance::calculateEarnedForEmployeeYear($employee, (int) $validated['year'])
            : LeaveBalance::calculateEarnedForYear((int) $validated['year']);
        LeaveBalance::create($validated);

        return redirect()->route('leave-balances.index')->with('success', 'Leave balance created.');
    }

    public function edit(LeaveBalance $leave_balance)
    {
        Gate::authorize('manage-leave-balances');
        $employees = Employee::nonAdmin()->orderBy('last_name')->orderBy('first_name')
            ->where(function ($query) {
                $query->whereDoesntHave('department', function ($deptQuery) {
                    $deptQuery->whereRaw('LOWER(department) = ?', ['presidents office']);
                })->orWhereDoesntHave('positions.position', function ($posQuery) {
                    $posQuery->where('position', 'head');
                });
            })
            ->get();
        $types = LeaveType::orderBy('name')->get();
        return view('leaves.balances', [
            'balance' => $leave_balance,
            'employees' => $employees,
            'types' => $types,
        ]);
    }

    public function update(Request $request, LeaveBalance $leave_balance)
    {
        Gate::authorize('manage-leave-balances');
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'year' => 'required|integer|min:2000|max:2100',
            'consumed' => 'required|numeric|min:0',
        ]);

        $employee = Employee::with('user', 'department', 'positions.position')->find($validated['employee_id']);
        if ($employee?->user && AccessControl::isPresidentHead($employee->user)) {
            return back()->with('error', "President's Office head does not have leave balances.");
        }

        $validated['earned'] = $employee
            ? LeaveBalance::calculateEarnedForEmployeeYear($employee, (int) $validated['year'])
            : LeaveBalance::calculateEarnedForYear((int) $validated['year']);
        $leave_balance->update($validated);

        return redirect()->route('leave-balances.index')->with('success', 'Leave balance updated.');
    }

    public function destroy(LeaveBalance $leave_balance)
    {
        Gate::authorize('manage-leave-balances');
        $leave_balance->delete();
        return redirect()->route('leave-balances.index')->with('success', 'Leave balance deleted.');
    }
}
