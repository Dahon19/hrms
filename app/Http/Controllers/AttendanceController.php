<?php

namespace App\Http\Controllers;

use App\Events\AttendanceRecorded;
use App\Domain\TravelOrders\Services\TravelOrderAttendanceService;
use App\Models\Attendance;
use App\Models\AttendanceAnomaly;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeNfc;
use App\Services\AccessControl;
use App\Services\AttendanceCalendarService;
use App\Services\AttendancePolicyService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly TravelOrderAttendanceService $travelOrderAttendanceService,
        private readonly AttendanceCalendarService $attendanceCalendarService,
    ) {
    }

    private function canViewAllAttendance($user): bool
    {
        if (!$user) {
            return false;
        }

        return $user->isAdmin() || AccessControl::isHrStaff($user);
    }

    private function canViewDepartmentAttendance($user): bool
    {
        if (!$user) {
            return false;
        }

        return AccessControl::isDepartmentLeader($user);
    }

    private function resolveDateRange(Request $request): array
    {
        $period = strtolower((string) $request->query('period', 'daily'));
        if (!in_array($period, ['daily', 'weekly', 'monthly', 'custom'], true)) {
            $period = 'daily';
        }

        $date = $request->query('date');
        $month = $request->query('month');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $today = now();
        $rangeStart = $today->copy()->startOfDay();
        $rangeEnd = $today->copy()->endOfDay();

        if ($period === 'weekly') {
            $ref = $date ? Carbon::parse($date) : $today->copy();
            $rangeStart = $ref->copy()->startOfWeek();
            $rangeEnd = $ref->copy()->endOfWeek();
        } elseif ($period === 'monthly') {
            if ($month && preg_match('/^\d{4}-\d{2}$/', (string) $month)) {
                $ref = Carbon::createFromFormat('Y-m', (string) $month)->startOfMonth();
            } else {
                $ref = $date ? Carbon::parse($date)->startOfMonth() : $today->copy()->startOfMonth();
            }
            $rangeStart = $ref->copy()->startOfMonth();
            $rangeEnd = $ref->copy()->endOfMonth();
            $month = $rangeStart->format('Y-m');
        } elseif ($period === 'custom') {
            $validated = $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
            ]);
            $rangeStart = Carbon::parse($validated['start_date'])->startOfDay();
            $rangeEnd = Carbon::parse($validated['end_date'])->endOfDay();
            $startDate = $rangeStart->toDateString();
            $endDate = $rangeEnd->toDateString();
        } else {
            $ref = $date ? Carbon::parse($date) : $today->copy();
            $rangeStart = $ref->copy()->startOfDay();
            $rangeEnd = $ref->copy()->endOfDay();
            $date = $rangeStart->toDateString();
        }

        $label = match ($period) {
            'daily' => $rangeStart->format('F j, Y'),
            'weekly' => $rangeStart->format('M j, Y') . ' - ' . $rangeEnd->format('M j, Y'),
            'monthly' => $rangeStart->format('F Y'),
            'custom' => $rangeStart->format('M j, Y') . ' - ' . $rangeEnd->format('M j, Y'),
            default => $rangeStart->format('F j, Y'),
        };

        return [
            'period' => $period,
            'date' => $date ?: $today->toDateString(),
            'month' => $month ?: $today->format('Y-m'),
            'start_date' => $startDate ?: $today->toDateString(),
            'end_date' => $endDate ?: $today->toDateString(),
            'range_start' => $rangeStart->toDateString(),
            'range_end' => $rangeEnd->toDateString(),
            'label' => $label,
        ];
    }

    private function syncHireDateFromAttendance(int $employeeId): void
    {
        $employee = Employee::with('user')->find($employeeId);
        if (!$this->employeeCanRecordAttendance($employee)) {
            return;
        }

        $firstDate = Attendance::where('employee_id', $employeeId)->min('date');
        if (!$firstDate) {
            return;
        }

        if (!$employee->hire_date || $employee->hire_date !== $firstDate) {
            $employee->hire_date = $firstDate;
            $employee->save();
        }
    }

    private function employeeCanRecordAttendance(?Employee $employee): bool
    {
        if (!$employee) {
            return false;
        }

        if (strtolower((string) $employee->status) !== 'active') {
            return false;
        }

        if ($employee->user?->archived_at) {
            return false;
        }

        return true;
    }
    private function excludeAdminFromAttendance($query)
    {
        return $query->whereHas('employee.user', function ($q) {
            $q->where('role', '!=', 'admin');
        });
    }

    private function scopeDepartmentAttendance($query, ?int $departmentId)
    {
        if (!$departmentId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('employee', function ($q) use ($departmentId) {
            $q->where('department_id', $departmentId);
        });
    }

    /**
     * Get Attendance Logs via JSON for integration/DataTable
     */
    public function getAttendance(): JsonResponse
    {
        $user = Auth::user();
        $employee = $user->employee;
        $isAdmin = $this->canViewAllAttendance($user);
        $isDeptLeader = $this->canViewDepartmentAttendance($user);
        $today = now()->toDateString();

        if ($isAdmin) {
            $attendance = Attendance::with('employee.department')
                ->whereDate('date', $today)
                ->orderBy('date', 'desc');
            $employeeScope = Employee::query()
                ->with(['department', 'user'])
                ->whereHas('user', fn ($query) => $query->where('role', '!=', 'admin'));
            $attendance = $attendance->get();
        } elseif ($isDeptLeader) {
            $attendance = Attendance::with('employee.department')->orderBy('date', 'desc');
            $attendance = $this->excludeAdminFromAttendance($attendance);
            $attendance = $this->scopeDepartmentAttendance($attendance, $employee?->department_id);
            $employeeScope = Employee::query()
                ->with(['department', 'user'])
                ->where('department_id', $employee?->department_id)
                ->whereHas('user', fn ($query) => $query->where('role', '!=', 'admin'));
            $attendance = $attendance->get();
        } else {
            $today = now()->toDateString();
            $attendance = $employee
                ? Attendance::with('employee.department')->where('employee_id', $employee->id)->orderBy('date', 'desc')->get()
                : collect();
            $employeeScope = Employee::query()
                ->with(['department', 'user'])
                ->where('id', $employee?->id ?? 0);
        }

        $attendance = $this->travelOrderAttendanceService->mergeTravelRows(
            collect($attendance),
            $employeeScope->get(),
            $today,
            $today
        );
        $attendance = $this->attendanceCalendarService->applyLeaveRows(
            $attendance,
            $employeeScope->get(),
            $today,
            $today
        );
        $attendance = $this->attendanceCalendarService->applyHolidayRows(
            $attendance,
            $employeeScope->get(),
            $today,
            $today
        );

        return response()->json($attendance);
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $employee = $user->employee;
        $positionName = $user->positionName();
        $isAdmin = $this->canViewAllAttendance($user);
        $isDeptLeader = $this->canViewDepartmentAttendance($user);
        $isHrHead = AccessControl::isHrHead($user);

        if (!$isAdmin && !$isDeptLeader && !$isHrHead) {
            return redirect()->route('attendance.history', array_filter([
                'period' => 'weekly',
                'date' => now()->toDateString(),
                'employee_id' => $employee?->id,
            ], static fn ($value) => $value !== null && $value !== ''));
        }

        $today = now()->toDateString();
        $selectedEmployeeId = (int) $request->query('employee_id', 0);

        $attendanceQuery = Attendance::with('employee.department')
            ->whereDate('date', $today)
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        $employeeScope = Employee::query()
            ->with(['department', 'user'])
            ->whereHas('user', fn ($query) => $query->where('role', '!=', 'admin'));

        if ($isAdmin) {
            if ($selectedEmployeeId > 0) {
                $attendanceQuery->where('employee_id', $selectedEmployeeId);
                $employeeScope->where('id', $selectedEmployeeId);
            }
        } elseif ($isDeptLeader) {
            $attendanceQuery = $this->excludeAdminFromAttendance($attendanceQuery);
            $attendanceQuery = $this->scopeDepartmentAttendance($attendanceQuery, $employee?->department_id);
            $employeeScope->where('department_id', $employee?->department_id);

            if ($selectedEmployeeId > 0) {
                $isInDepartment = Employee::where('id', $selectedEmployeeId)
                    ->where('department_id', $employee?->department_id)
                    ->exists();

                if (!$isInDepartment) {
                    abort(403);
                }

                $attendanceQuery->where('employee_id', $selectedEmployeeId);
                $employeeScope->where('id', $selectedEmployeeId);
            }
        } else {
            if ($selectedEmployeeId > 0 && (int) $employee?->id !== $selectedEmployeeId) {
                abort(403);
            }

            $attendanceQuery->where('employee_id', $employee?->id ?? 0);
            $employeeScope->where('id', $employee?->id ?? 0);
        }

        $mergedRows = $this->travelOrderAttendanceService->mergeTravelRows(
            $attendanceQuery->get(),
            $employeeScope->get(),
            $today,
            $today
        );
        $mergedRows = $this->attendanceCalendarService->applyLeaveRows(
            $mergedRows,
            $employeeScope->get(),
            $today,
            $today
        );
        $mergedRows = $this->attendanceCalendarService->applyHolidayRows(
            $mergedRows,
            $employeeScope->get(),
            $today,
            $today
        );

        $attendance = $this->travelOrderAttendanceService->paginateMergedRows(
            $mergedRows,
            5,
            \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage(),
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        $isHR = $user->isReadOnlyStaff();
        $attendanceSetting = \App\Models\AttendanceSetting::current();
        return view('attendance.index', compact('attendance', 'positionName', 'isAdmin', 'isHR', 'isHrHead', 'employee', 'attendanceSetting'));
    }

    public function weekly(Request $request)
    {
        return redirect()->route('attendance.history', array_filter([
            'period' => 'weekly',
            'date' => $request->input('date'),
            'employee_id' => $request->input('employee_id'),
            'search' => $request->input('search'),
        ], static fn ($value) => $value !== null && $value !== ''));
    }

    private function buildHistoryReportPayload(Request $request, bool $paginate = true): array
    {
        $user = Auth::user();
        $employee = $user->employee;
        $isAdmin = $this->canViewAllAttendance($user);
        $isDeptLeader = $this->canViewDepartmentAttendance($user);

        $filters = $this->resolveDateRange($request);
        if (!in_array($filters['period'], ['weekly', 'monthly'], true)) {
            $filters = $this->resolveDateRange(new Request(array_merge(
                $request->query(),
                ['period' => 'weekly']
            )));
        }

        $selectedEmployeeId = (int) $request->query('employee_id');
        $selectedDepartmentId = (int) $request->query('department_id');
        $search = trim((string) $request->query('search', ''));

        if ($isAdmin) {
            $attendance = Attendance::with(['employee.department', 'employee.positions.position']);
            $employeeScope = Employee::query()
                ->with(['department', 'positions.position', 'user'])
                ->whereHas('user', fn ($query) => $query->where('role', '!=', 'admin'));
        } elseif ($isDeptLeader) {
            $attendance = Attendance::with(['employee.department', 'employee.positions.position']);
            $attendance = $this->excludeAdminFromAttendance($attendance);
            $attendance = $this->scopeDepartmentAttendance($attendance, $employee?->department_id);
            $employeeScope = Employee::query()
                ->with(['department', 'positions.position', 'user'])
                ->where('department_id', $employee?->department_id)
                ->whereHas('user', fn ($query) => $query->where('role', '!=', 'admin'));
        } else {
            $attendance = Attendance::with(['employee.department', 'employee.positions.position'])
                ->where('employee_id', $employee?->id);
            $employeeScope = Employee::query()
                ->with(['department', 'positions.position', 'user'])
                ->where('id', $employee?->id ?? 0);
        }

        $departmentOptions = match (true) {
            $isAdmin => Department::query()
                ->whereHas('employees.user', fn ($query) => $query->where('role', '!=', 'admin'))
                ->orderBy('department')
                ->get(),
            $isDeptLeader => Department::query()
                ->whereKey($employee?->department_id)
                ->orderBy('department')
                ->get(),
            default => Department::query()
                ->whereKey($employee?->department_id)
                ->orderBy('department')
                ->get(),
        };

        $allowedDepartmentIds = $departmentOptions
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($selectedDepartmentId > 0 && !in_array($selectedDepartmentId, $allowedDepartmentIds, true)) {
            $selectedDepartmentId = $isAdmin
                ? 0
                : (int) ($employee?->department_id ?? 0);
        }

        if ($selectedDepartmentId > 0) {
            $attendance = $this->scopeDepartmentAttendance($attendance, $selectedDepartmentId);
            $employeeScope->where('department_id', $selectedDepartmentId);
        }

        if ($selectedEmployeeId > 0) {
            if ($isDeptLeader) {
                $isInDepartment = Employee::where('id', $selectedEmployeeId)
                    ->when(
                        $selectedDepartmentId > 0,
                        fn ($query) => $query->where('department_id', $selectedDepartmentId),
                        fn ($query) => $query->where('department_id', $employee?->department_id)
                    )
                    ->exists();
                if (!$isInDepartment) {
                    $selectedEmployeeId = 0;
                }
            }
            if (!$isAdmin && !$isDeptLeader && (int) $employee?->id !== $selectedEmployeeId) {
                abort(403);
            }
            if ($selectedEmployeeId > 0) {
                $attendance->where('employee_id', $selectedEmployeeId);
                $employeeScope->where('id', $selectedEmployeeId);
            }
        }

        if ($search !== '') {
            $searchTerms = collect(preg_split('/\s+/', $search))
                ->map(fn ($term) => trim((string) $term))
                ->filter()
                ->values();

            $applyEmployeeSearch = function (Builder $query) use ($search, $searchTerms): void {
                $query->where(function (Builder $employeeQuery) use ($search, $searchTerms) {
                    $employeeQuery->where('employee_id', 'like', '%' . $search . '%')
                        ->orWhereRaw(
                            "CONCAT_WS(' ', first_name, middle_name, last_name, suffix) like ?",
                            ['%' . $search . '%']
                        )
                        ->orWhere(function (Builder $tokenQuery) use ($searchTerms) {
                            foreach ($searchTerms as $term) {
                                $tokenQuery->where(function (Builder $fieldQuery) use ($term) {
                                    $fieldQuery->where('employee_id', 'like', '%' . $term . '%')
                                        ->orWhere('first_name', 'like', '%' . $term . '%')
                                        ->orWhere('middle_name', 'like', '%' . $term . '%')
                                        ->orWhere('last_name', 'like', '%' . $term . '%')
                                        ->orWhere('suffix', 'like', '%' . $term . '%');
                                });
                            }
                        });
                });
            };

            $attendance->whereHas('employee', fn (Builder $query) => $applyEmployeeSearch($query));
            $applyEmployeeSearch($employeeScope);
        }

        $attendance = $attendance
            ->whereDate('date', '>=', $filters['range_start'])
            ->whereDate('date', '<=', $filters['range_end'])
            ->orderBy('date', 'desc');

        $attendanceRows = $this->travelOrderAttendanceService->mergeTravelRows(
            $attendance->get(),
            $employeeScope->get(),
            $filters['range_start'],
            $filters['range_end']
        );
        $attendanceRows = $this->attendanceCalendarService->applyLeaveRows(
            $attendanceRows,
            $employeeScope->get(),
            $filters['range_start'],
            $filters['range_end']
        );
        $attendanceRows = $this->attendanceCalendarService->applyHolidayRows(
            $attendanceRows,
            $employeeScope->get(),
            $filters['range_start'],
            $filters['range_end']
        );

        $employeeOptions = $employeeScope
            ->get()
            ->sortBy(function ($employee) {
                return [
                    strtolower((string) ($employee->last_name ?? '')),
                    strtolower((string) ($employee->first_name ?? '')),
                ];
            })
            ->values();

        $summaryPayload = $this->travelOrderAttendanceService->summarize($attendanceRows);
        $summaryByEmployee = $summaryPayload['summary'];
        $totals = $summaryPayload['totals'];

        $attendanceTableRows = $paginate
            ? $this->travelOrderAttendanceService->paginateMergedRows(
                $attendanceRows,
                10,
                \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage(),
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            )
            : $attendanceRows;

        return [
            'attendance' => $attendanceTableRows,
            'summaryByEmployee' => $summaryByEmployee,
            'totals' => $totals,
            'filters' => $filters,
            'search' => $search,
            'selectedDepartmentId' => $selectedDepartmentId,
            'departmentOptions' => $departmentOptions,
            'selectedEmployeeId' => $selectedEmployeeId,
            'employeeOptions' => $employeeOptions,
            'attendanceSetting' => \App\Models\AttendanceSetting::current(),
        ];
    }

    public function history(Request $request)
    {
        Gate::authorize('view-attendance-records');

        return view('attendance.history', $this->buildHistoryReportPayload($request));
    }

    public function printHistory(Request $request)
    {
        Gate::authorize('view-attendance-records');

        $payload = $this->buildHistoryReportPayload($request, false);

        $pdf = Pdf::loadView('attendance.print', $payload + [
            'printedAt' => now(),
            'printedBy' => $request->user(),
        ])->setPaper('a4', 'landscape');

        return $pdf->stream(
            'attendance-records-' . now()->format('Ymd-His') . '.pdf',
            ['Attachment' => false]
        );
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $isAdmin = $user->isAdmin();
        $isHrHead = AccessControl::isHrHead($user);
        $shouldReturnJson = $request->expectsJson()
            || $request->wantsJson()
            || $request->ajax()
            || $request->filled('nfc_uid');

        if (!$isAdmin && !$isHrHead) {
            return $shouldReturnJson
                ? response()->json(['message' => 'Forbidden'], 403)
                : abort(403);
        }

        // ================= EMPLOYEE SELF CLOCK =================
        if (!$isAdmin) {
            $nfcUid = trim((string) $request->input('nfc_uid', ''));
            if ($nfcUid === '') {
                return $shouldReturnJson
                    ? response()->json(['message' => 'NFC UID is required.'], 422)
                    : back()->with('error', 'NFC UID is required.');
            }

            $nfcRecord = EmployeeNfc::with(['employee.user', 'employee.department'])
                ->where('nfc_uid', $nfcUid)
                ->first();
            $employee = $nfcRecord?->employee;
            
            if (!$employee) {
                return $shouldReturnJson
                    ? response()->json(['message' => 'NFC not registered to any employee.'], 404)
                    : back()->with('error', 'NFC not registered to any employee.');
            }

            if (!$this->employeeCanRecordAttendance($employee)) {
                return $shouldReturnJson
                    ? response()->json([
                        'message' => 'This employee account is inactive and cannot record attendance.',
                        'employee' => $this->buildEmployeePayload($employee),
                    ], 403)
                    : back()->with('error', 'This employee account is inactive and cannot record attendance.');
            }

            $now = now();
            $debounceKey = 'attendance_nfc_last:' . $employee->id;
            $lastTap = Cache::get($debounceKey);
            if ($lastTap) {
                $secondsSince = Carbon::parse($lastTap)->diffInSeconds($now);
                if ($secondsSince < 10) {
                    return $shouldReturnJson
                        ? response()->json([
                            'message' => 'Please wait before tapping again.',
                            'employee' => $this->buildEmployeePayload($employee),
                        ], 429)
                        : back()->with('error', 'Please wait before tapping again.');
                }
            }

            $debounceLockKey = 'attendance_nfc_debounce:' . $employee->id;
            if (!Cache::add($debounceLockKey, (string) $now->timestamp, now()->addSeconds(10))) {
                return $shouldReturnJson
                    ? response()->json([
                        'message' => 'Please wait before tapping again.',
                        'employee' => $this->buildEmployeePayload($employee),
                    ], 429)
                    : back()->with('error', 'Please wait before tapping again.');
            }

            $today = now()->toDateString();
            $employeePayload = $this->buildEmployeePayload($employee);
            try {
                $result = DB::transaction(function () use ($employee, $today, $now, $employeePayload, $request, $debounceKey, $shouldReturnJson) {
                    $attendance = Attendance::query()
                        ->where('employee_id', $employee->id)
                        ->whereDate('date', $today)
                        ->lockForUpdate()
                        ->first();

                    if (!$attendance) {
                        $attendance = new Attendance([
                            'employee_id' => $employee->id,
                            'date' => $today,
                            'status' => 'absent',
                        ]);
                    }

                    $timeSlot = $this->resolveSlotForTap($attendance, $today, $now);
                    if (!$timeSlot) {
                        Cache::put($debounceKey, $now->toDateTimeString(), now()->addMinutes(2));
                        $attendance = $attendance->fresh();
                        return $shouldReturnJson
                            ? response()->json([
                                'message' => 'Attendance already recorded for this time period.',
                                'data' => $attendance,
                                'row' => $this->buildAttendancePayload($attendance),
                                'employee' => $employeePayload,
                            ], 200)
                            : back()->with('info', 'Attendance already recorded for this time period.');
                    }

                    if ($timeSlot === 'morning_time_out' && $attendance->morning_time_in) {
                        $lastIn = Carbon::parse($today . ' ' . $attendance->morning_time_in);
                        if ($lastIn->diffInMinutes($now) < 5) {
                            Cache::put($debounceKey, $now->toDateTimeString(), now()->addMinutes(2));
                            return $shouldReturnJson
                                ? response()->json([
                                    'message' => 'Please wait before tapping out.',
                                    'employee' => $employeePayload,
                                ], 429)
                                : back()->with('error', 'Please wait before tapping out.');
                        }
                    }

                    if ($timeSlot === 'afternoon_time_out' && $attendance->afternoon_time_in) {
                        $lastIn = Carbon::parse($today . ' ' . $attendance->afternoon_time_in);
                        if ($lastIn->diffInMinutes($now) < 5) {
                            Cache::put($debounceKey, $now->toDateTimeString(), now()->addMinutes(2));
                            return $shouldReturnJson
                                ? response()->json([
                                    'message' => 'Please wait before tapping out.',
                                    'employee' => $employeePayload,
                                ], 429)
                                : back()->with('error', 'Please wait before tapping out.');
                        }
                    }

                    $attendance->{$timeSlot} = $now->toTimeString();
                    if (!$attendance->status || $attendance->status === 'absent') {
                        $attendance->status = 'present';
                    }
                    $attendance->save();

                    (new AttendancePolicyService())->applyPolicy($attendance);
                    Cache::put($debounceKey, $now->toDateTimeString(), now()->addMinutes(2));
                    $attendance = $attendance->fresh();

                    return $shouldReturnJson
                        ? response()->json([
                            'message' => 'Attendance recorded successfully',
                            'data' => $attendance,
                            'row' => $this->buildAttendancePayload($attendance),
                            'employee' => $employeePayload,
                        ], 200)
                        : back()->with('success', 'Attendance recorded successfully.');
                });
            } catch (\Throwable $exception) {
                Cache::forget($debounceLockKey);
                throw $exception;
            }

            if ($employee) {
                $this->syncHireDateFromAttendance($employee->id);
            }

            if ($result instanceof JsonResponse) {
                $payload = $result->getData(true);
                if (!empty($payload['row']) && is_array($payload['row'])) {
                    event(new AttendanceRecorded($payload['row']));
                }
            }

            return $result;
        }

        // ================= ADMIN / HEAD MANUAL =================
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date',
            'time_in' => 'nullable|date_format:H:i',
            'time_out' => 'nullable|date_format:H:i',
            'morning_time_in' => 'nullable|date_format:H:i',
            'morning_time_out' => 'nullable|date_format:H:i',
            'afternoon_time_in' => 'nullable|date_format:H:i',
            'afternoon_time_out' => 'nullable|date_format:H:i',
        ]);

        $manualEmployee = Employee::with('user')->find((int) $request->input('employee_id'));
        if (!$this->employeeCanRecordAttendance($manualEmployee)) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Inactive or archived employees cannot be given attendance records.'], 422)
                : back()->with('error', 'Inactive or archived employees cannot be given attendance records.');
        }

        $date = Carbon::parse((string) $request->input('date'))->toDateString();
        $normalized = [
            'morning_time_in' => $request->input('morning_time_in') ?: $request->input('time_in'),
            'morning_time_out' => $request->input('morning_time_out'),
            'afternoon_time_in' => $request->input('afternoon_time_in'),
            'afternoon_time_out' => $request->input('afternoon_time_out') ?: $request->input('time_out'),
            'status' => 'absent',
        ];

        if ($normalized['morning_time_in'] || $normalized['afternoon_time_in']) {
            $normalized['status'] = 'present';
        }

        $newAttendance = DB::transaction(function () use ($request, $date, $normalized) {
            $attendance = Attendance::query()
                ->where('employee_id', (int) $request->input('employee_id'))
                ->whereDate('date', $date)
                ->lockForUpdate()
                ->first();

            if (!$attendance) {
                $attendance = new Attendance([
                    'employee_id' => (int) $request->input('employee_id'),
                    'date' => $date,
                ]);
            }

            $attendance->fill($normalized);
            $attendance->save();
            (new AttendancePolicyService())->applyPolicy($attendance);

            return $attendance->fresh();
        });

        $this->syncHireDateFromAttendance((int) $request->input('employee_id'));

        return $request->expectsJson()
            ? response()->json(['message' => 'Manual entry saved', 'data' => $newAttendance], 201)
            : redirect()->route('attendance.index');
    }

    private function resolveSlotForTap(Attendance $attendance, string $date, Carbon $now): ?string
    {
        $setting = \App\Models\AttendanceSetting::current();
        if (!$setting->require_four_taps) {
            if (!$attendance->morning_time_in) {
                return 'morning_time_in';
            }
            if (!$attendance->afternoon_time_out) {
                return 'afternoon_time_out';
            }
            return null;
        }

        $shiftEnd = Carbon::parse($date . ' ' . $setting->shift_end);
        $breakStart = Carbon::parse($date . ' ' . $setting->break_start);
        $afternoonInStart = Carbon::parse($date . ' ' . $setting->break_end);

        if ($now->lessThan($breakStart)) {
            if (!$attendance->morning_time_in) {
                return 'morning_time_in';
            }

            if (!$attendance->morning_time_out) {
                return 'morning_time_out';
            }

            return null;
        }

        if ($now->lessThan($afternoonInStart)) {
            if (!$attendance->morning_time_out) {
                return 'morning_time_out';
            }

            return null;
        }

        if ($now->lessThan($shiftEnd)) {
            if (!$attendance->afternoon_time_in) {
                return 'afternoon_time_in';
            }

            if (!$attendance->afternoon_time_out) {
                return 'afternoon_time_out';
            }

            return null;
        }

        if (!$attendance->afternoon_time_out) {
            return 'afternoon_time_out';
        }

        return null;
    }

    private function buildEmployeePayload(Employee $employee): array
    {
        $user = $employee->user;
        $avatarUrl = null;
        if (!empty($user?->avatar)) {
            $parts = explode('/', $user->avatar);
            $folder = $parts[0] ?? null;
            $subfolder = $parts[1] ?? null;
            $filename = $parts[2] ?? null;
            if ($folder && $subfolder && $filename) {
                $avatarUrl = route('storage.file', [
                    'folder' => $folder,
                    'subfolder' => $subfolder,
                    'filename' => $filename,
                ]);
            }
        }

        return [
            'id' => $employee->id,
            'name' => trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')),
            'department' => $employee->department?->department,
            'avatar_url' => $avatarUrl,
        ];
    }

    private function buildAttendancePayload(Attendance $attendance): array
    {
        $attendance->loadMissing('employee.department');

        return [
            'id' => $attendance->id,
            'employee_id' => $attendance->employee_id,
            'date' => $attendance->date,
            'morning_time_in' => $attendance->morning_time_in,
            'morning_time_out' => $attendance->morning_time_out,
            'afternoon_time_in' => $attendance->afternoon_time_in,
            'afternoon_time_out' => $attendance->afternoon_time_out,
            'status' => $attendance->status,
            'employee' => [
                'id' => $attendance->employee?->id,
                'name' => trim((string) (($attendance->employee?->first_name ?? '') . ' ' . ($attendance->employee?->last_name ?? ''))),
                'department' => $attendance->employee?->department?->department,
            ],
        ];
    }

    public function update(Request $request, $id)
    {
        if (!Auth::user()->isAdmin()) {
            return $request->expectsJson() ? response()->json(['message' => 'Forbidden'], 403) : abort(403);
        }

        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date',
        ]);

        $attendance = Attendance::findOrFail($id);
        $attendance->update($request->all());
        (new AttendancePolicyService())->applyPolicy($attendance);
        $this->syncHireDateFromAttendance((int) $attendance->employee_id);

        return $request->expectsJson()
            ? response()->json(['message' => 'Attendance updated successfully', 'data' => $attendance])
            : redirect()->route('attendance.index');
    }

    // AccessControl::isHrHead handles HR head detection consistently.
}
