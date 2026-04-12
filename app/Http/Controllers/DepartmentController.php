<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\DepartmentType;
use App\Models\Position;
use App\Models\EmployeePosition;
use App\Models\Employee;
use App\Services\AccessControl;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    private function scopedDepartmentPositionsQuery(Department $department)
    {
        $query = Position::query()
            ->whereRaw('LOWER(position) != ?', ['admin'])
            ->orderBy('position');

        if (Schema::hasColumn('positions', 'department_id')) {
            return $query->where('department_id', $department->id);
        }

        $positionIds = EmployeePosition::query()
            ->whereHas('employee', function ($employeeQuery) use ($department) {
                $employeeQuery->where('department_id', $department->id);
            })
            ->pluck('position_id');

        if (Schema::hasTable('job_postings') && Schema::hasColumn('job_postings', 'department_id')) {
            $jobPostingPositionIds = \DB::table('job_postings')
                ->where('department_id', $department->id)
                ->pluck('position_id');

            $positionIds = $positionIds->merge($jobPostingPositionIds);
        }

        $positionIds = $positionIds
            ->filter()
            ->map(fn ($positionId) => (int) $positionId)
            ->unique()
            ->values();

        if ($positionIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('id', $positionIds->all());
    }

    public function index(Request $request)
    {
        Gate::authorize('view-departments');

        $search = trim((string) $request->query('search', ''));
        $typeFilter = trim((string) $request->query('type', ''));
        $user = Auth::user();
        $departmentId = $user->employee?->department_id;
        $normalizedDept = AccessControl::normalizeDepartmentName($user->employee?->department?->department ?? '');
        $excludedHeadDept = in_array($normalizedDept, ['hr department', 'presidents office'], true);
        $canOrgChart = AccessControl::isOrgChartViewer($user) && $departmentId;
        $isHead = $canOrgChart;
        $isSpecialHead = $isHead && $excludedHeadDept;
        $showOrgChart = $request->get('view') === 'org';

        $departmentsQuery = Department::query()
            ->when($search, function ($query) use ($search) {
                return $query->where('department', 'like', "%{$search}%");
            })
            ->when($typeFilter !== '', function ($query) use ($typeFilter) {
                return $query->where('department_type', $typeFilter);
            })
            ->orderBy('department', 'asc');

        if ($isHead && !$isSpecialHead) {
            $departmentsQuery->where('id', $departmentId);
        }

        $departments = $departmentsQuery->paginate(10)->withQueryString();
        $departmentTypes = $this->departmentTypes();

        $orgChart = collect();
        $orgChartDepartment = null;
        $department = null;
        if ($isHead) {
            $department = $isSpecialHead ? Department::find($departmentId) : $departments->first();
        }

        if ($isHead && $department) {
            $orgChartDepartment = $department;
            $positionGroups = $this->scopedDepartmentPositionsQuery($department)
                ->get()
                ->groupBy(function (Position $position) {
                    $normalized = strtolower(trim($position->position));
                    return $normalized === 'dean' ? 'head' : $normalized;
                })
                ->sortKeys();

            $employees = Employee::with(['positions.position'])
                ->where('department_id', $department->id)
                ->whereHas('user', function ($query) {
                    $query->whereNull('archived_at');
                })
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get();

            $orgChart = $positionGroups->map(function ($group, $normalizedPosition) use ($employees, $department) {
                $positionIds = $group->pluck('id')->all();
                $members = $employees->filter(function ($employee) use ($positionIds) {
                    $primaryPositionId = $employee->positions->sortBy('id')->first()?->position_id;
                    return $primaryPositionId && in_array($primaryPositionId, $positionIds, true);
                })->values();

                $positionName = $group->first()->position;
                if (
                    $department->department_type === 'Academic'
                    && in_array(strtolower(trim($positionName)), ['head', 'dean'], true)
                ) {
                    $positionName = 'Dean';
                } elseif ($normalizedPosition === 'head') {
                    $positionName = 'Head';
                }

                return (object) [
                    'position' => $positionName,
                    'members' => $members,
                ];
            })->values();
        }

        return view('departments.index', compact('departments', 'departmentTypes', 'orgChart', 'orgChartDepartment', 'isHead', 'isSpecialHead', 'showOrgChart', 'search', 'typeFilter'));
    }

    public function store(Request $request)
    {
        Gate::authorize('manage-departments');

        $validated = $request->validate([
            'department' => 'required|string|max:255|unique:departments,department',
            'department_type' => ['required', $this->departmentTypeRule()],
        ]);

        $validated['department_type'] = $this->normalizeDepartmentType($validated['department_type']);
        $department = Department::create($validated);

        return redirect()->route('departments.index')->with('success', 'Department created successfully!');
    }

    public function update(Request $request, Department $department)
    {
        Gate::authorize('manage-departments');

        $validated = $request->validate([
            'department' => 'required|string|max:255|unique:departments,department,' . $department->id,
            'department_type' => ['required', $this->departmentTypeRule()],
        ]);

        $validated['department_type'] = $this->normalizeDepartmentType($validated['department_type']);
        $department->update($validated);

        return redirect()->route('departments.index')->with('success', 'Department updated successfully!');
    }

    public function destroy(Department $department)
    {
        Gate::authorize('manage-departments');

        $department->delete();

        return redirect()->route('departments.index')->with('success', 'Department deleted successfully!');
    }

    public function updateLogo(Request $request, Department $department)
    {
        $user = Auth::user();
        $isOwnDepartment = (int) ($user?->employee?->department_id ?? 0) === (int) $department->id;
        $canUpdateOwnDepartmentLogo = in_array($user?->positionName() ?? '', ['head', 'secretary'], true) && $isOwnDepartment;

        if (!$canUpdateOwnDepartmentLogo && !$user?->can('manage-departments')) {
            abort(403);
        }

        $request->validate([
            'logo' => 'required|image|mimes:png,jpg,jpeg,webp|max:10240',
        ]);

        $disk = Storage::disk('local');
        $folder = 'department_logos/' . $department->id;
        $filename = $request->file('logo')->hashName();
        $path = $request->file('logo')->storeAs($folder, $filename, 'local');

        if (!empty($department->logo_path) && $department->logo_path !== $path && $disk->exists($department->logo_path)) {
            $disk->delete($department->logo_path);
        }

        $department->update(['logo_path' => $path]);

        $files = collect($disk->files($folder))->sortByDesc(function ($filePath) use ($disk) {
            return $disk->lastModified($filePath);
        })->values();
        $files->slice(2)->each(function ($filePath) use ($disk) {
            $disk->delete($filePath);
        });

        return redirect()->route('departments.index')->with('success', 'Department logo updated successfully!');
    }

    private function normalizeDepartmentType(string $type): string
    {
        $normalized = trim($type);
        if ($normalized === 'Aministrative') {
            return 'Administrative';
        }
        if ($normalized === 'SupportOperations') {
            return 'Support/Operations';
        }
        return $normalized;
    }

    private function departmentTypes()
    {
        if (Schema::hasTable('department_types')) {
            $defaults = collect([
                'Academic',
                'Administrative',
                'Support/Operations',
            ]);

            $existing = Department::query()
                ->whereNotNull('department_type')
                ->pluck('department_type')
                ->map(fn ($type) => $this->normalizeDepartmentType((string) $type))
                ->filter()
                ->unique()
                ->values();

            $namesToSync = $defaults
                ->merge($existing)
                ->unique()
                ->values();

            $currentMaxSortOrder = (int) DepartmentType::query()->max('sort_order');

            $namesToSync->each(function (string $name) use (&$currentMaxSortOrder) {
                $record = DepartmentType::query()->firstOrNew(['name' => $name]);
                if (!$record->exists) {
                    $currentMaxSortOrder++;
                    $record->sort_order = $currentMaxSortOrder;
                    $record->save();
                }
            });

            return DepartmentType::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(10);
        }

        $defaults = collect([
            'Academic',
            'Administrative',
            'Support/Operations',
        ]);

        $existing = Department::query()
            ->whereNotNull('department_type')
            ->pluck('department_type')
            ->map(fn ($type) => $this->normalizeDepartmentType((string) $type))
            ->filter()
            ->unique()
            ->values();

        return $defaults
            ->merge($existing)
            ->unique()
            ->sort()
            ->values()
            ->map(fn ($name, $index) => (object) [
                'id' => null,
                'name' => $name,
                'sort_order' => $index + 1,
            ]);
    }

    private function departmentTypeRule()
    {
        if (Schema::hasTable('department_types')) {
            return Rule::exists('department_types', 'name');
        }

        return Rule::in($this->departmentTypes()->pluck('name')->all());
    }

    public function positions(Request $request, Department $department): JsonResponse
    {
        Gate::authorize('view-departments');

        $employeeId = $request->query('employee_id');
        $employeePositionIds = collect();
        if ($employeeId) {
            $employeePositionIds = EmployeePosition::query()
                ->where('employee_id', $employeeId)
                ->pluck('position_id')
                ->map(fn ($positionId) => (int) $positionId)
                ->unique()
                ->values();
        }
        $isAcademic = $department->department_type === 'Academic';
        $totalLimit = $isAcademic ? 20 : null;
        $totalUsed = Employee::where('department_id', $department->id)
            ->whereHas('user', function ($query) {
                $query->whereNull('archived_at');
            })
            ->when($employeeId, function ($query) use ($employeeId) {
                $query->where('id', '!=', $employeeId);
            })
            ->count();
        $totalAvailable = $totalLimit !== null ? max($totalLimit - $totalUsed, 0) : null;

        if ($totalLimit !== null && $totalAvailable === 0 && !$employeeId) {
            return response()->json([
                'positions' => [],
                'meta' => [
                    'total_limit' => $totalLimit,
                    'total_used' => $totalUsed,
                    'total_available' => $totalAvailable,
                ],
            ]);
        }

        $positions = $this->scopedDepartmentPositionsQuery($department)
            ->get()
            ->map(function (Position $position) use ($department, $employeeId) {
                $normalizedName = strtolower(trim($position->position));
                $displayName = ucfirst($position->position);

                if ($department->department_type === 'Academic' && in_array($normalizedName, ['head', 'dean'], true)) {
                    $displayName = 'Dean';
                } elseif ($normalizedName === 'head') {
                    $displayName = 'Head';
                }

                $limit = $position->capacityLimit();
                $count = EmployeePosition::where('position_id', $position->id)
                    ->whereHas('employee', function ($query) use ($department, $employeeId) {
                        $query->where('department_id', $department->id)
                            ->whereHas('user', function ($userQuery) {
                                $userQuery->whereNull('archived_at');
                            });
                        if ($employeeId) {
                            $query->where('id', '!=', $employeeId);
                        }
                    })
                    ->distinct('employee_id')
                    ->count('employee_id');

                return [
                    'id' => $position->id,
                    'name' => $displayName,
                    'limit' => $limit,
                    'count' => $count,
                    'is_occupied' => $limit !== null && $count >= $limit,
                ];
            })
            ->filter(function (array $position) use ($employeePositionIds) {
                if (!$position['is_occupied']) {
                    return true;
                }

                return $employeePositionIds->contains((int) $position['id']);
            })
            ->values();

        return response()->json([
            'positions' => $positions,
            'meta' => [
                'total_limit' => $totalLimit,
                'total_used' => $totalUsed,
                'total_available' => $totalAvailable,
                'department_type' => $department->department_type,
            ],
        ]);
    }

    public function positionEmployees(Request $request, Department $department, Position $position): JsonResponse
    {
        Gate::authorize('view-departments');

        abort_if((int) $position->department_id !== (int) $department->id, 404);

        $employees = Employee::where('department_id', $department->id)
            ->whereHas('positions', function ($query) use ($position) {
                $query->where('position_id', $position->id);
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(function (Employee $employee) {
                return [
                    'id' => $employee->id,
                    'employee_id' => $employee->employee_id,
                    'first_name' => $employee->first_name,
                    'last_name' => $employee->last_name,
                ];
            });

        return response()->json([
            'department' => $department->department,
            'position' => $position->position,
            'employees' => $employees,
        ]);
    }
}
