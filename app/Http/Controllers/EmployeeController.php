<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeePosition;
use App\Models\User;
use App\Models\EmployeeNfc;
use App\Models\Position;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Arr;
use App\Services\AccessControl;
use Illuminate\Support\Str;

class EmployeeController extends Controller
{
    private const EMPLOYEE_NAME_REGEX = "/^(?=.{1,255}$)[A-Za-z]+(?:[ .'-][A-Za-z]+)*$/";

    private function scopedDepartmentPositionsQuery(int $departmentId)
    {
        $query = Position::query()
            ->whereRaw('LOWER(position) != ?', ['admin'])
            ->orderBy('position');

        if (Schema::hasColumn('positions', 'department_id')) {
            return $query->where('department_id', $departmentId);
        }

        $positionIds = EmployeePosition::query()
            ->whereHas('employee', function ($employeeQuery) use ($departmentId) {
                $employeeQuery->where('department_id', $departmentId);
            })
            ->pluck('position_id');

        if (Schema::hasTable('job_postings') && Schema::hasColumn('job_postings', 'department_id')) {
            $jobPostingPositionIds = DB::table('job_postings')
                ->where('department_id', $departmentId)
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

    private function departmentScopedPositions(?int $departmentId)
    {
        if (!$departmentId) {
            return Position::query()
                ->whereRaw('LOWER(position) != ?', ['admin'])
                ->orderBy('position')
                ->paginate(10);
        }

        return $this->scopedDepartmentPositionsQuery($departmentId)->paginate(10);
    }

    public function index()
    {
        $user = Auth::user();
        if ($user->can('view-employees')) {
            $search = trim((string) request()->query('search', ''));
            $departmentFilter = strtolower(trim((string) request()->query('department', '')));
            $positionFilter = strtolower(trim((string) request()->query('position', '')));
            $sortFilter = trim((string) request()->query('sort', 'name_asc'));
            $accountScope = trim((string) request()->query('account_scope', 'active'));
            if (!in_array($accountScope, ['active', 'archived'], true)) {
                $accountScope = 'active';
            }

            $query = Employee::with(['user', 'department', 'positions.position'])
                ->nonAdmin();

            if (Employee::offboardingTablesAvailable()) {
                $query->with('activeOffboardingRecord');
            }
            $departmentId = $user->employee?->department_id;
            $normalizedDept = AccessControl::normalizeDepartmentName($user->employee?->department?->department ?? '');
            $excludedHeadDept = in_array($normalizedDept, ['hr department', 'presidents office'], true);
            $isDepartmentSupport = AccessControl::isDepartmentSupport($user);

            if (
                $departmentId
                && (
                    (AccessControl::isDepartmentLeader($user) && !$excludedHeadDept)
                    || $isDepartmentSupport
                )
            ) {
                $query->where('department_id', $departmentId);
            }

            if (($user->isReadOnlyStaff() || $isDepartmentSupport) && !$user->isAdmin()) {
                $query->whereHas('user', function ($query) {
                    $query->where('role', '!=', 'admin');
                });
            }

            $query->whereHas('user', function ($userQuery) use ($accountScope) {
                if ($accountScope === 'archived') {
                    $userQuery->whereNotNull('archived_at');
                    return;
                }

                $userQuery->whereNull('archived_at');
            });

            if ($search !== '') {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('employee_id', 'like', '%' . $search . '%')
                        ->orWhere('first_name', 'like', '%' . $search . '%')
                        ->orWhere('last_name', 'like', '%' . $search . '%')
                        ->orWhereHas('department', function ($departmentQuery) use ($search) {
                            $departmentQuery->where('department', 'like', '%' . $search . '%');
                        })
                        ->orWhereHas('positions.position', function ($positionQuery) use ($search) {
                            $positionQuery->where('position', 'like', '%' . $search . '%');
                        });
                });
            }

            if ($departmentFilter !== '') {
                $query->whereHas('department', function ($departmentQuery) use ($departmentFilter) {
                    $departmentQuery->whereRaw('LOWER(TRIM(department)) = ?', [$departmentFilter]);
                });
            }

            if ($positionFilter !== '') {
                $query->where(function ($positionQuery) use ($positionFilter) {
                    if ($positionFilter === 'dean') {
                        $positionQuery->whereHas('positions.position', function ($rowPositionQuery) {
                            $rowPositionQuery->whereRaw('LOWER(TRIM(position)) = ?', ['dean']);
                        })->orWhere(function ($headQuery) {
                            $headQuery->whereHas('positions.position', function ($rowPositionQuery) {
                                $rowPositionQuery->whereRaw('LOWER(TRIM(position)) = ?', ['head']);
                            })->whereHas('department', function ($departmentQuery) {
                                $departmentQuery->whereRaw('LOWER(TRIM(department_type)) = ?', ['academic']);
                            });
                        });

                        return;
                    }

                    $positionQuery->whereHas('positions.position', function ($rowPositionQuery) use ($positionFilter) {
                        $rowPositionQuery->whereRaw('LOWER(TRIM(position)) = ?', [$positionFilter]);
                    });
                });
            }

            switch ($sortFilter) {
                case 'name_desc':
                    $query->orderByDesc('last_name')->orderByDesc('first_name');
                    break;
                case 'department_asc':
                    $query->orderBy(
                        Department::select('department')
                            ->whereColumn('departments.id', 'employees.department_id')
                            ->limit(1)
                    )->orderBy('last_name')->orderBy('first_name');
                    break;
                case 'position_asc':
                    $query->orderByRaw("(
                        SELECT COALESCE(MIN(positions.position), '')
                        FROM employee_positions
                        INNER JOIN positions ON positions.id = employee_positions.position_id
                        WHERE employee_positions.employee_id = employees.id
                    ) ASC")->orderBy('last_name')->orderBy('first_name');
                    break;
                case 'name_asc':
                default:
                    $query->orderBy('last_name')->orderBy('first_name');
                    break;
            }

            $employees = $query
                ->paginate(10)
                ->withQueryString();
            $departments = Department::orderBy('department')->get();
            $toolbarDepartments = $departments;
            $toolbarPositions = Position::query()
                ->whereRaw('LOWER(position) != ?', ['admin'])
                ->orderBy('position')
                ->get()
                ->map(function (Position $position) {
                    $label = ucfirst(trim((string) $position->position));
                    return strtolower($label) === 'head' ? 'Head' : $label;
                })
                ->push('Dean')
                ->filter()
                ->unique()
                ->sort()
                ->values();
            $positions = collect();
            $nextEmployeeId = null;
            if ($user->isAdmin()) {
                $positions = $this->departmentScopedPositions(
                    (int) (old('department_id') ?: old('department_id_value'))
                );
                $nextEmployeeId = Employee::nextEmployeeId();
            }
            return view('employees.index', compact(
                'employees',
                'departments',
                'toolbarDepartments',
                'toolbarPositions',
                'positions',
                'nextEmployeeId',
                'search',
                'departmentFilter',
                'positionFilter',
                'sortFilter',
                'accountScope'
            ));
        }

        abort(403, 'Unauthorized access.');
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        Gate::authorize('manage-employees');

        $request->merge([
            'first_name' => $this->normalizeWhitespace($request->input('first_name')),
            'last_name' => $this->normalizeWhitespace($request->input('last_name')),
            'address' => $this->normalizeOptionalText($request->input('address')),
        ]);

        if (!$request->filled('employee_id')) {
            $request->merge(['employee_id' => Employee::nextEmployeeId()]);
        }

        $validator = Validator::make($request->all(), [
            'email'       => 'required|email|unique:users,email',
            'employee_id' => 'required|unique:employees',
            'gender'     => 'required|in:male,female',
            'first_name'  => ['required', 'string', 'max:255', 'regex:' . self::EMPLOYEE_NAME_REGEX],
            'last_name'   => ['required', 'string', 'max:255', 'regex:' . self::EMPLOYEE_NAME_REGEX],
            'address'     => 'nullable|string|max:1000',
            'department_id'  => 'required|exists:departments,id',
            'position_ids'   => 'required|array|min:1',
            'position_ids.*' => 'exists:positions,id',
            'hire_date'   => 'nullable|date',
            'nfc_uid'     => 'nullable|string|unique:employee_nfcs,nfc_uid',
        ], $this->employeeValidationMessages());

        $validator->after(function ($validator) use ($request) {
            $name = trim($request->first_name . ' ' . $request->last_name);
            if (User::where('name', $name)->exists()) {
                $validator->errors()->add('first_name', 'Username already exists.');
            }
        });

        $validator->after(function ($validator) use ($request) {
            $this->validatePositionAvailability($validator, (int) $request->department_id, $request->input('position_ids', []), null);
        });

        $validator->validate();

        try {
            DB::transaction(function () use ($request) {
                // 1. Automatically create the User account
                $newUser = User::create([
                    'name'     => trim($request->first_name . ' ' . $request->last_name),
                    'email'    => $request->email,
                    'gender'   => $request->gender,
                    'password' => Hash::make('password'), // Default password as requested
                    'role'     => 'employee', 
                ]);

                // 2. Create the Employee record linked to that User
                $employee = Employee::create([
                    'user_id'       => $newUser->id,
                    'employee_id'   => $request->employee_id,
                    'first_name'    => $request->first_name,
                    'last_name'     => $request->last_name,
                    'address'       => $request->address,
                    'department_id' => $request->department_id,
                    'hire_date'     => $request->hire_date,
                    'status'        => 'active',
                ]);

                EmployeePosition::query()->where('employee_id', $employee->id)->delete();
                collect($request->input('position_ids', []))
                    ->filter()
                    ->map(fn ($positionId) => (int) $positionId)
                    ->unique()
                    ->each(function (int $positionId) use ($employee) {
                        EmployeePosition::create([
                            'employee_id' => $employee->id,
                            'position_id' => $positionId,
                        ]);
                    });

                if ($request->filled('nfc_uid')) {
                    EmployeeNfc::updateOrCreate(
                        ['employee_id' => $employee->id],
                        ['nfc_uid' => $request->nfc_uid]
                    );
                }
            });

            Cache::forget('latest_nfc_uid');

            return redirect()->route('employees.index')->with('success', 'Employee and User Account created successfully!');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Failed to create records: ' . $e->getMessage());
        }
    }

    public function update(Request $request, Employee $employee)
    {
        $user = Auth::user();
        Gate::authorize('manage-employees');

        if ($employee->hasActiveOffboardingRecord()) {
            return back()->with('error', 'Employee profile is read-only while offboarding is in progress.');
        }

        $request->merge([
            'first_name' => $this->normalizeWhitespace($request->input('first_name')),
            'last_name' => $this->normalizeWhitespace($request->input('last_name')),
            'address' => $this->normalizeOptionalText($request->input('address')),
        ]);

        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|unique:employees,employee_id,' . $employee->id,
            'gender'     => 'required|in:male,female',
            'first_name'  => ['required', 'string', 'max:255', 'regex:' . self::EMPLOYEE_NAME_REGEX],
            'last_name'   => ['required', 'string', 'max:255', 'regex:' . self::EMPLOYEE_NAME_REGEX],
            'address'     => 'nullable|string|max:1000',
            'department_id'  => 'required|exists:departments,id',
            'position_ids'   => 'required|array|min:1',
            'position_ids.*' => 'exists:positions,id',
            'hire_date'   => 'nullable|date',
            'status'      => 'required',
            'nfc_uid'     => 'nullable|string|unique:employee_nfcs,nfc_uid,' . ($employee->nfc->id ?? 'NULL'),
        ], $this->employeeValidationMessages());

        $validator->after(function ($validator) use ($request, $employee) {
            $name = trim($request->first_name . ' ' . $request->last_name);
            if (User::where('name', $name)->where('id', '!=', $employee->user_id)->exists()) {
                $validator->errors()->add('first_name', 'Username already exists.');
            }
        });

        $validator->after(function ($validator) use ($request, $employee) {
            $this->validatePositionAvailability($validator, (int) $request->department_id, $request->input('position_ids', []), $employee->id);
        });

        $validator->validate();

        $employeePayload = [
            'employee_id'   => $request->employee_id,
            'first_name'    => $request->first_name,
            'last_name'     => $request->last_name,
            'address'       => $request->address,
            'department_id' => $request->department_id,
            'status'        => $request->status,
        ];
        if ($request->filled('hire_date')) {
            $employeePayload['hire_date'] = $request->hire_date;
        }
        $employee->update($employeePayload);

        if ($employee->user) {
            $employee->user->update([
                'name' => trim($request->first_name . ' ' . $request->last_name),
                'gender' => $request->gender,
            ]);
        }

        $employee->positions()->delete();
        collect($request->input('position_ids', []))
            ->filter()
            ->map(fn ($positionId) => (int) $positionId)
            ->unique()
            ->each(function (int $positionId) use ($employee) {
                EmployeePosition::create([
                    'employee_id' => $employee->id,
                    'position_id' => $positionId,
                ]);
            });

        if ($request->filled('nfc_uid')) {
            EmployeeNfc::updateOrCreate(
                ['employee_id' => $employee->id],
                ['nfc_uid' => $request->nfc_uid]
            );
        } else {
            EmployeeNfc::where('employee_id', $employee->id)->delete();
        }

        Cache::forget('latest_nfc_uid');

        return redirect()->route('employees.index')->with('success', 'Employee updated.');
    }

    private function normalizeWhitespace(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }

    private function normalizeOptionalText(?string $value): ?string
    {
        $normalized = $this->normalizeWhitespace($value);

        return $normalized !== '' ? $normalized : null;
    }

    private function employeeValidationMessages(): array
    {
        return [
            'first_name.regex' => 'First name can contain letters, spaces, apostrophes, periods, and hyphens only.',
            'last_name.regex' => 'Last name can contain letters, spaces, apostrophes, periods, and hyphens only.',
            'position_ids.required' => 'Select at least one position.',
            'position_ids.array' => 'Select at least one position.',
            'position_ids.min' => 'Select at least one position.',
        ];
    }

    private function validatePositionAvailability($validator, int $departmentId, $positionIdsInput, ?int $employeeId): void
    {
        $department = Department::find($departmentId);
        if (!$department) {
            return;
        }

        $positionIds = collect(Arr::wrap($positionIdsInput))
            ->filter()
            ->map(fn ($positionId) => (int) $positionId)
            ->unique()
            ->values()
            ->all();

        if ($department->department_type === 'Academic') {
            $totalUsed = Employee::where('department_id', $departmentId)
                ->when($employeeId, function ($query) use ($employeeId) {
                    $query->where('id', '!=', $employeeId);
                })
                ->count();
            if ($totalUsed >= 20) {
                $validator->errors()->add('department_id', 'Department has reached the maximum number of employees.');
                return;
            }
        }

        $positions = $this->scopedDepartmentPositionsQuery($departmentId)
            ->whereIn('id', $positionIds)
            ->get()
            ->keyBy('id');

        if (count($positionIds) !== $positions->count()) {
            $validator->errors()->add('position_ids', 'One or more selected positions are invalid.');
            return;
        }

        foreach ($positionIds as $positionId) {
            $position = $positions->get($positionId);
            if (!$position) {
                continue;
            }

            $positionName = strtolower(trim($position->position));

            if ($positionName === 'admin') {
                $validator->errors()->add('position_ids', 'Admin position cannot be assigned.');
                continue;
            }

            $limit = $position->capacityLimit();
            if ($limit === null) {
                continue;
            }

            $count = EmployeePosition::where('position_id', $positionId)
                ->whereHas('employee', function ($query) use ($departmentId, $employeeId) {
                    $query->where('department_id', $departmentId);
                    if ($employeeId) {
                        $query->where('id', '!=', $employeeId);
                    }
                })
                ->count();

            if ($count >= $limit) {
                $validator->errors()->add('position_ids', $position->position . ' is occupied.');
            }
        }
    }

    public function destroy(Employee $employee)
    {
        Gate::authorize('manage-employees');

        DB::transaction(function () use ($employee) {
            if ($employee->user && $employee->user->archived_at === null) {
                $employee->user->forceFill(['archived_at' => now()])->save();
            }

            if (strtolower((string) $employee->status) !== 'inactive') {
                $employee->forceFill(['status' => 'inactive'])->save();
            }
        });

        return redirect()->route('employees.index')->with('success', 'Employee archived.');
    }

    public function resetPassword(Employee $employee)
    {
        Gate::authorize('manage-employees');

        $user = Auth::user();
        if (!$user->isAdmin()) {
            abort(403, 'Unauthorized access.');
        }

        if (!$employee->user) {
            return redirect()->route('employees.index')->with('error', 'No linked user account was found for this employee.');
        }

        $employee->user->forceFill([
            'password' => Hash::make('password'),
            'remember_token' => Str::random(60),
        ]);

        if (Schema::hasColumn('users', 'password_notice_seen_at')) {
            $employee->user->password_notice_seen_at = null;
        }

        $employee->user->save();

        $fullName = trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')) ?: 'Employee';

        return redirect()
            ->route('employees.index', request()->query())
            ->with('success', $fullName . ' password was reset to the default password: password');
    }
}
