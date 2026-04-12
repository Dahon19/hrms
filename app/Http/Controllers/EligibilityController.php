<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EligibilityCache;
use App\Models\PerformanceReview;
use App\Models\RewardTitle;
use App\Services\AuditLogger;
use App\Services\RewardEligibilityService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EligibilityController extends Controller
{
    public function __construct(
        private readonly RewardEligibilityService $eligibilityService
    ) {
    }

    public function index(Request $request)
    {
        Gate::authorize('view-eligibility-list');

        $filters = [
            'department_id' => (int) $request->query('department_id', 0),
            'milestone' => (int) $request->query('milestone', 0),
            'attendance_category' => trim((string) $request->query('attendance_category', '')),
            'spms_rating' => trim((string) $request->query('spms_rating', '')),
            'employee_id' => (int) $request->query('employee_id', 0),
            'search' => trim((string) $request->query('search', '')),
        ];

        $year = (int) now()->year;
        if (!EligibilityCache::query()->where('year', $year)->exists()) {
            $this->warmEligibilityCacheForYear($year);
        }

        $query = EligibilityCache::query()
            ->where('year', $year)
            ->where(function ($q) {
                $q->where('eligible_tenure', true)
                    ->orWhere('eligible_attendance', true)
                    ->orWhere('eligible_performance', true);
            })
            ->with(['employee.department', 'employee.user'])
            ->whereHas('employee', function ($employeeQuery) {
                $employeeQuery->where('status', 'active')
                    ->whereHas('user', fn ($q) => $q->whereNull('archived_at'));
            });

        if ($filters['department_id'] > 0) {
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $filters['department_id']));
        }

        if ($filters['employee_id'] > 0) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if ($filters['search'] !== '') {
            $search = $filters['search'];
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('employee_id', 'like', '%' . $search . '%')
                    ->orWhere('first_name', 'like', '%' . $search . '%')
                    ->orWhere('last_name', 'like', '%' . $search . '%');
            });
        }

        if ($filters['milestone'] > 0) {
            $query->where('tenure_milestone', $filters['milestone']);
        }

        if ($filters['attendance_category'] !== '') {
            if ($filters['attendance_category'] === 'perfect') {
                $query->where('eligible_attendance', true);
            } elseif ($filters['attendance_category'] === 'not_qualified') {
                $query->where('eligible_attendance', false);
            }
        }

        if ($filters['spms_rating'] !== '') {
            $query->whereRaw('LOWER(spms_rating) = ?', [strtolower($filters['spms_rating'])]);
        }

        $records = $query
            ->orderByDesc('eligible_performance')
            ->orderByDesc('eligible_attendance')
            ->orderByDesc('eligible_tenure')
            ->orderBy('id')
            ->paginate(10)
            ->withQueryString();

        $rows = collect($records->items())->map(function (EligibilityCache $cache) use ($year) {
            $employee = $cache->employee;
            if (!$employee) {
                return null;
            }

            $eligibility = $cache->payload;
            if (!is_array($eligibility) || empty($eligibility)) {
                $eligibility = $this->eligibilityService->buildEligibility($employee, $year);
                $cache->update($this->eligibilityService->toEligibilityCachePayload($eligibility));
            }

            return [
                'employee' => $employee,
                'eligibility' => $eligibility,
            ];
        })->filter()->values();

        $records->setCollection($rows);

        AuditLogger::logSystem('eligibility_list_generated', [
            'filters' => $filters,
            'rows_count' => $rows->count(),
        ], $request->user()?->id, 'eligibility', 0);

        return view('eligibility.index', [
            'records' => $records,
            'filters' => $filters,
            'departments' => Department::query()->orderBy('department')->get(),
            'employeesForManual' => Gate::allows('manage-rewards')
                ? Employee::query()
                    ->with('department')
                    ->where('status', 'active')
                    ->whereHas('user', fn ($q) => $q->whereNull('archived_at'))
                    ->orderBy('last_name')
                    ->orderBy('first_name')
                    ->get()
                : collect(),
            'rewardTitles' => Gate::allows('manage-rewards')
                ? RewardTitle::query()->orderBy('award_type')->orderBy('title')->get()
                : collect(),
            'rewardTitleOptionsByType' => Gate::allows('manage-rewards')
                ? RewardTitle::groupedOptionsForForm()
                : [],
            'assignableRewardTypesByEmployee' => Gate::allows('manage-rewards')
                ? $this->assignableRewardTypesByEmployee(
                    Employee::query()
                        ->with('department')
                        ->where('status', 'active')
                        ->whereHas('user', fn ($q) => $q->whereNull('archived_at'))
                        ->orderBy('last_name')
                        ->orderBy('first_name')
                        ->get()
                )
                : [],
            'year' => $year,
        ]);
    }

    public function show(Request $request, Employee $employee)
    {
        Gate::authorize('view-eligibility');

        if (Gate::denies('view-eligibility-list') && (int) ($request->user()?->employee?->id ?? 0) !== (int) $employee->id) {
            abort(403);
        }

        $employee->loadMissing(['department', 'user', 'performanceReviews']);
        $year = (int) now()->year;
        $cache = EligibilityCache::query()
            ->where('employee_id', $employee->id)
            ->where('year', $year)
            ->first();

        $eligibility = is_array($cache?->payload) && !empty($cache->payload)
            ? $cache->payload
            : $this->eligibilityService->buildEligibility($employee, $year);

        if (!$cache) {
            EligibilityCache::query()->updateOrCreate(
                ['employee_id' => $employee->id, 'year' => $year],
                $this->eligibilityService->toEligibilityCachePayload($eligibility)
            );
        }

        AuditLogger::logSystem('eligibility_employee_viewed', [
            'employee_id' => $employee->id,
        ], $request->user()?->id, 'eligibility', $employee->id);

        return view('eligibility.show', [
            'employee' => $employee,
            'eligibility' => $eligibility,
            'performanceReview' => PerformanceReview::query()
                ->where('employee_id', $employee->id)
                ->latest('review_year')
                ->latest('id')
                ->first(),
        ]);
    }

    public function print(Request $request)
    {
        Gate::authorize('view-eligibility-list');

        $departmentId = (int) $request->query('department_id', 0);
        $year = (int) now()->year;
        if (!EligibilityCache::query()->where('year', $year)->exists()) {
            $this->warmEligibilityCacheForYear($year);
        }

        $employees = Employee::query()
            ->select('id')
            ->where('status', 'active')
            ->whereHas('user', fn ($q) => $q->whereNull('archived_at'))
            ->when($departmentId > 0, fn ($q) => $q->where('department_id', $departmentId))
            ->limit(500)
            ->get();

        $cacheRows = EligibilityCache::query()
            ->with(['employee.department', 'employee.user'])
            ->where('year', $year)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->where(function ($q) {
                $q->where('eligible_tenure', true)
                    ->orWhere('eligible_attendance', true)
                    ->orWhere('eligible_performance', true);
            })
            ->orderBy('id')
            ->get();

        $rows = $cacheRows->map(function (EligibilityCache $cache) use ($year) {
            $employee = $cache->employee;
            if (!$employee) {
                return null;
            }

            $eligibility = is_array($cache->payload) && !empty($cache->payload)
                ? $cache->payload
                : $this->eligibilityService->buildEligibility($employee, $year);

            return [
                'employee' => $employee,
                'eligibility' => $eligibility,
            ];
        })->filter()->values();

        AuditLogger::logSystem('eligibility_report_printed', [
            'department_id' => $departmentId,
            'rows_count' => $rows->count(),
        ], $request->user()?->id, 'eligibility', 0);

        $pdf = Pdf::loadView('eligibility.print', [
            'rows' => $rows,
            'year' => $year,
            'department' => $departmentId ? Department::find($departmentId) : null,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('eligibility-report-' . now()->format('Ymd-His') . '.pdf', ['Attachment' => false]);
    }

    private function warmEligibilityCacheForYear(int $year): void
    {
        Employee::query()
            ->where('status', 'active')
            ->whereHas('user', fn ($q) => $q->whereNull('archived_at'))
            ->orderBy('id')
            ->chunkById(100, function ($employees) use ($year) {
                foreach ($employees as $employee) {
                    $eligibility = $this->eligibilityService->buildEligibility($employee, $year);
                    EligibilityCache::query()->updateOrCreate(
                        [
                            'employee_id' => $employee->id,
                            'year' => $year,
                        ],
                        $this->eligibilityService->toEligibilityCachePayload($eligibility)
                    );
                }
            });
    }

    private function assignableRewardTypesByEmployee($employees): array
    {
        return collect($employees)
            ->mapWithKeys(fn (Employee $employee) => [
                (string) $employee->id => $this->eligibilityService->assignableRewardTypes($employee),
            ])
            ->all();
    }

}
