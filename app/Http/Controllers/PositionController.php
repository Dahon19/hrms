<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\EmployeePosition;
use App\Models\Position;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class PositionController extends Controller
{
    private function hasDepartmentScope(): bool
    {
        static $hasColumn;

        if ($hasColumn === null) {
            $hasColumn = Schema::hasColumn('positions', 'department_id');
        }

        return $hasColumn;
    }

    private function hasEmployeeLimitScope(): bool
    {
        static $hasColumn;

        if ($hasColumn === null) {
            $hasColumn = Schema::hasColumn('positions', 'employee_limit');
        }

        return $hasColumn;
    }

    private function resolveEmployeeLimit(string $positionName, mixed $limitInput): ?int
    {
        if (!$this->hasEmployeeLimitScope()) {
            return null;
        }

        if ($limitInput !== null && $limitInput !== '') {
            return max((int) $limitInput, 1);
        }

        return Position::defaultCapacityFor($positionName);
    }

    /**
     * Display a listing of the position catalog.
     */
    public function index(Request $request)
    {
        Gate::authorize('view-positions');

        $search = trim((string) $request->query('search', ''));
        $hasDepartmentScope = $this->hasDepartmentScope();

        $positions = Position::query()
            ->select([
                DB::raw('MIN(id) as id'),
                'position',
                DB::raw('COUNT(*) as scope_count'),
            ])
            ->when($this->hasEmployeeLimitScope(), fn ($query) => $query->addSelect(DB::raw('MIN(employee_limit) as employee_limit')))
            ->whereRaw('LOWER(position) != ?', ['admin'])
            ->when($hasDepartmentScope, fn ($query) => $query->whereNotNull('department_id'))
            ->when($search, function ($query, $search) {
                return $query->where('position', 'like', "%{$search}%");
            })
            ->groupBy('position')
            ->orderBy('position')
            ->paginate(10)
            ->withQueryString();

        $departments = Department::orderBy('department')->get();

        $positionDepartmentIds = collect();
        if ($hasDepartmentScope) {
            $groupedNames = $positions->getCollection()->pluck('position')->filter()->values();
            $positionDepartmentIds = Position::query()
                ->whereIn('position', $groupedNames)
                ->whereNotNull('department_id')
                ->get(['position', 'department_id'])
                ->groupBy('position')
                ->map(fn ($items) => $items->pluck('department_id')->map(fn ($id) => (int) $id)->unique()->values());
        }

        $hasEmployeeLimitScope = $this->hasEmployeeLimitScope();

        return view('positions.index', compact('positions', 'departments', 'search', 'hasDepartmentScope', 'positionDepartmentIds', 'hasEmployeeLimitScope'));
    }

    /**
     * Return employees assigned to a position for modal rendering.
     */
    public function members(Position $position)
    {
        Gate::authorize('view-positions');

        $positionIds = Position::query()
            ->whereRaw('LOWER(position) = ?', [strtolower((string) $position->position)])
            ->when($this->hasDepartmentScope(), fn ($query) => $query->whereNotNull('department_id'))
            ->pluck('id');

        $members = EmployeePosition::query()
            ->whereIn('position_id', $positionIds)
            ->with(['employee.department', 'employee.user'])
            ->get()
            ->filter(fn ($employeePosition) => $employeePosition->employee)
            ->map(function ($employeePosition) {
                $employee = $employeePosition->employee;
                $user = $employee->user;

                $name = trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? ''));
                $department = $employee->department->department ?? 'No department';
                $initial = strtoupper(substr($employee->first_name ?? $user->name ?? 'U', 0, 1));

                $avatarUrl = null;
                if ($user && !empty($user->avatar)) {
                    $parts = explode('/', $user->avatar);
                    if (count($parts) >= 3) {
                        $avatarUrl = route('storage.file', [
                            'folder' => $parts[0],
                            'subfolder' => $parts[1],
                            'filename' => $parts[2],
                        ]);
                    }
                }

                return [
                    'name' => $name ?: 'Unnamed Employee',
                    'department' => $department,
                    'avatar_url' => $avatarUrl,
                    'initial' => $initial ?: 'U',
                ];
            })
            ->values();

        return response()->json([
            'position' => ucfirst($position->position),
            'members' => $members,
        ]);
    }

    /**
     * Store a newly created position in the catalog.
     */
    public function store(Request $request)
    {
        Gate::authorize('manage-positions');

        $validated = $request->validate([
            'position' => [
                'required',
                'string',
                'max:255',
            ],
            'employee_limit' => $this->hasEmployeeLimitScope() ? 'nullable|integer|min:1|max:500' : 'nullable',
        ] + ($this->hasDepartmentScope() ? [
            'department_ids' => 'required|array|min:1',
            'department_ids.*' => 'required|integer|exists:departments,id',
        ] : []));

        if (strtolower(trim((string) $request->input('position'))) === 'admin') {
            return back()
                ->withErrors(['position' => 'Admin cannot be managed as a department position.'])
                ->withInput();
        }

        if (!$this->hasDepartmentScope()) {
            Position::create([
                'position' => $validated['position'],
                'employee_limit' => $this->resolveEmployeeLimit($validated['position'], $validated['employee_limit'] ?? null),
            ]);

            return redirect()->route('positions.index')
                ->with('success', 'Position added to catalog.');
        }

        $departmentIds = collect($validated['department_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $duplicateDepartmentIds = Position::query()
            ->where('position', $validated['position'])
            ->whereIn('department_id', $departmentIds)
            ->pluck('department_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (!empty($duplicateDepartmentIds)) {
            $departmentNames = Department::query()
                ->whereIn('id', $duplicateDepartmentIds)
                ->orderBy('department')
                ->pluck('department')
                ->all();

            return back()
                ->withErrors([
                    'department_ids' => 'Position already exists for: ' . implode(', ', $departmentNames) . '.',
                ])
                ->withInput();
        }

        foreach ($departmentIds as $departmentId) {
            Position::create([
                'department_id' => $departmentId,
                'position' => $validated['position'],
                'employee_limit' => $this->resolveEmployeeLimit($validated['position'], $validated['employee_limit'] ?? null),
            ]);
        }

        return redirect()->route('positions.index')
            ->with('success', 'Position added to catalog.');
    }

    /**
     * Update the position name in the catalog.
     */
    public function update(Request $request, string $id)
    {
        Gate::authorize('manage-positions');

        $position = Position::findOrFail($id);

        $validated = $request->validate([
            'position' => [
                'required',
                'string',
                'max:255',
            ],
            'employee_limit' => $this->hasEmployeeLimitScope() ? 'nullable|integer|min:1|max:500' : 'nullable',
        ] + ($this->hasDepartmentScope() ? [
            'department_ids' => 'required|array|min:1',
            'department_ids.*' => 'required|integer|exists:departments,id',
        ] : []));

        if (strtolower(trim((string) $request->input('position'))) === 'admin') {
            return back()
                ->withErrors(['position' => 'Admin cannot be managed as a department position.'])
                ->withInput();
        }

        if (!$this->hasDepartmentScope()) {
            $position->update([
                'position' => $validated['position'],
                'employee_limit' => $this->resolveEmployeeLimit($validated['position'], $validated['employee_limit'] ?? null),
            ]);

            return redirect()->route('positions.index')
                ->with('success', 'Position updated successfully.');
        }

        $originalName = (string) $position->position;
        $groupPositions = Position::query()
            ->whereRaw('LOWER(position) = ?', [strtolower($originalName)])
            ->whereNotNull('department_id')
            ->get();

        $groupPositionIds = $groupPositions->pluck('id');
        $existingDepartmentIds = $groupPositions->pluck('department_id')->map(fn ($deptId) => (int) $deptId)->unique()->values();
        $selectedDepartmentIds = collect($validated['department_ids'] ?? [])
            ->map(fn ($deptId) => (int) $deptId)
            ->unique()
            ->values();

        $conflictDepartmentIds = Position::query()
            ->where('position', $validated['position'])
            ->whereIn('department_id', $selectedDepartmentIds)
            ->whereNotIn('id', $groupPositionIds)
            ->pluck('department_id')
            ->map(fn ($deptId) => (int) $deptId)
            ->unique()
            ->all();

        if (!empty($conflictDepartmentIds)) {
            $departmentNames = Department::query()
                ->whereIn('id', $conflictDepartmentIds)
                ->orderBy('department')
                ->pluck('department')
                ->all();

            return back()
                ->withErrors([
                    'department_ids' => 'Position already exists for: ' . implode(', ', $departmentNames) . '.',
                ])
                ->withInput();
        }

        $departmentIdsToRemove = $existingDepartmentIds->diff($selectedDepartmentIds)->values();

        if ($departmentIdsToRemove->isNotEmpty()) {
            $blockedDepartmentNames = $groupPositions
                ->filter(function ($groupPosition) use ($departmentIdsToRemove) {
                    if (!$departmentIdsToRemove->contains((int) $groupPosition->department_id)) {
                        return false;
                    }

                    return $groupPosition->employeePositions()->exists() || $groupPosition->jobPostings()->exists();
                })
                ->pluck('department.department')
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (!empty($blockedDepartmentNames)) {
                return back()
                    ->withErrors([
                        'department_ids' => 'Cannot remove departments with active position records: ' . implode(', ', $blockedDepartmentNames) . '.',
                    ])
                    ->withInput();
            }
        }

        foreach ($groupPositions as $groupPosition) {
            if ($selectedDepartmentIds->contains((int) $groupPosition->department_id)) {
                $groupPosition->update([
                    'position' => $validated['position'],
                    'employee_limit' => $this->resolveEmployeeLimit($validated['position'], $validated['employee_limit'] ?? null),
                ]);
            }
        }

        Position::query()
            ->whereIn('id', $groupPositions
                ->filter(fn ($groupPosition) => $departmentIdsToRemove->contains((int) $groupPosition->department_id))
                ->pluck('id'))
            ->delete();

        $departmentIdsToCreate = $selectedDepartmentIds->diff($existingDepartmentIds)->values();
        foreach ($departmentIdsToCreate as $departmentId) {
            Position::create([
                'department_id' => $departmentId,
                'position' => $validated['position'],
                'employee_limit' => $this->resolveEmployeeLimit($validated['position'], $validated['employee_limit'] ?? null),
            ]);
        }

        return redirect()->route('positions.index')
            ->with('success', 'Position updated successfully.');
    }

    /**
     * Remove the position from the catalog.
     */
    public function destroy(string $id)
    {
        Gate::authorize('manage-positions');

        $position = Position::findOrFail($id);

        if ($position->employeePositions()->exists()) {
            return redirect()->route('positions.index')
                ->with('error', 'Position cannot be deleted while employees are assigned to it.');
        }

        $position->delete();

        return redirect()->route('positions.index')
            ->with('success', 'Position removed from catalog.');
    }
}
