<?php

namespace App\Http\Controllers;

use App\Http\Requests\RewardAssignRequest;
use App\Http\Requests\RewardTitleRequest;
use App\Models\Employee;
use App\Models\EligibilityCache;
use App\Models\RewardRecord;
use App\Models\RewardTitle;
use App\Services\AuditLogger;
use App\Services\RewardEligibilityService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RewardController extends Controller
{
    public function __construct(
        private readonly RewardEligibilityService $eligibilityService
    ) {
    }

    public function index(Request $request)
    {
        Gate::authorize('view-rewards');

        $search = trim((string) $request->query('search', ''));
        $type = trim((string) $request->query('type', ''));

        $query = RewardRecord::query()
            ->with(['employee.department', 'employee.user'])
            ->when($type !== '', fn ($q) => $q->where('award_type', $type))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($subQuery) use ($search) {
                    $subQuery->where('award_title', 'like', '%' . $search . '%')
                        ->orWhereHas('employee', function ($employeeQuery) use ($search) {
                            $employeeQuery->where('employee_id', 'like', '%' . $search . '%')
                                ->orWhere('first_name', 'like', '%' . $search . '%')
                                ->orWhere('last_name', 'like', '%' . $search . '%');
                        });
                });
            })
            ->latest('award_date')
            ->latest('id');

        if (Gate::denies('manage-rewards')) {
            $query->where('employee_id', (int) ($request->user()?->employee?->id ?? 0));
        }

        $records = $query->paginate(10)->withQueryString();
        $employeesForManual = Gate::allows('manage-rewards')
            ? Employee::query()
                ->with('department')
                ->where('status', 'active')
                ->whereHas('user', fn ($q) => $q->whereNull('archived_at'))
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get()
            : collect();
        $summary = $this->buildSummary();

        return view('rewards.index', [
            'records' => $records,
            'search' => $search,
            'type' => $type,
            'summary' => $summary,
        ]);
    }

    public function show(Request $request, Employee $employee)
    {
        Gate::authorize('view-rewards');
        if (!$this->canViewEmployee($request->user(), $employee)) {
            abort(403);
        }

        $employee->loadMissing(['department', 'user', 'performanceReviews']);
        $records = RewardRecord::query()
            ->where('employee_id', $employee->id)
            ->latest('award_date')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $eligibility = $this->eligibilityService->buildEligibility($employee);

        return view('rewards.show', [
            'employee' => $employee,
            'records' => $records,
            'eligibility' => $eligibility,
            'canManage' => Gate::allows('manage-rewards'),
        ]);
    }

    public function store(RewardAssignRequest $request)
    {
        Gate::authorize('manage-rewards');

        $employee = Employee::query()
            ->with(['department', 'user'])
            ->where('status', 'active')
            ->findOrFail((int) $request->validated('employee_id'));
        $rewardTitle = RewardTitle::query()->findOrFail((int) $request->validated('reward_title_id'));

        $record = $this->eligibilityService->assignReward(
            employee: $employee,
            rewardTitle: $rewardTitle,
            awardDate: Carbon::parse((string) $request->validated('award_date')),
            assignedByUserId: (int) ($request->user()?->id ?? 0),
        );

        AuditLogger::logSystem('reward_assigned', [
            'reward_record_id' => $record->id,
            'employee_id' => $employee->id,
            'award_type' => $record->award_type,
            'award_title' => $record->award_title,
            'award_date' => optional($record->award_date)->toDateString(),
            'override_used' => (bool) $record->override_used,
            'override_reason' => $record->override_reason,
            'assigned_by' => $record->assigned_by,
        ], $request->user()?->id, RewardRecord::class, $record->id);

        return redirect()
            ->route('rewards.show', $employee)
            ->with('success', 'Recognition record assigned successfully.');
    }

    public function storeTitle(RewardTitleRequest $request)
    {
        Gate::authorize('manage-rewards');

        RewardTitle::query()->create($request->validated());

        return back()->with('success', 'Reward title saved.');
    }

    public function updateTitle(RewardTitleRequest $request, RewardTitle $rewardTitle)
    {
        Gate::authorize('manage-rewards');

        $rewardTitle->update($request->validated());

        return back()->with('success', 'Reward title updated.');
    }

    public function destroyTitle(RewardTitle $rewardTitle)
    {
        Gate::authorize('manage-rewards');

        $rewardTitle->delete();

        return back()->with('success', 'Reward title deleted.');
    }

    public function print(Request $request, Employee $employee)
    {
        Gate::authorize('view-rewards');
        if (!$this->canViewEmployee($request->user(), $employee)) {
            abort(403);
        }

        $rewardId = (int) $request->query('reward_id', 0);
        $reward = RewardRecord::query()
            ->where('employee_id', $employee->id)
            ->when($rewardId > 0, fn ($q) => $q->where('id', $rewardId))
            ->latest('award_date')
            ->latest('id')
            ->first();

        if (!$reward) {
            return back()->with('error', 'No recognition record found for certificate generation.');
        }

        $employee->loadMissing(['department', 'positions.position']);
        $pdf = Pdf::loadView('rewards.certificate', compact('employee', 'reward'))
            ->setPaper('a4', 'portrait');

        return $pdf->stream(
            'recognition-certificate-' . $employee->employee_id . '-' . $reward->id . '.pdf',
            ['Attachment' => false]
        );
    }

    private function canViewEmployee($user, Employee $employee): bool
    {
        if (!$user) {
            return false;
        }

        if (Gate::allows('manage-rewards')) {
            return true;
        }

        return (int) ($user->employee?->id ?? 0) === (int) $employee->id;
    }

    private function buildSummary(): array
    {
        if (Gate::denies('manage-rewards')) {
            return [
                'total_rewards' => 0,
                'eligible_employees' => 0,
                'by_department' => collect(),
                'by_type' => collect(),
                'by_milestone' => collect(),
            ];
        }

        $totalRewards = RewardRecord::query()->count();
        $eligibleEmployees = EligibilityCache::query()
            ->where('year', (int) now()->year)
            ->where(function ($query) {
                $query->where('eligible_tenure', true)
                    ->orWhere('eligible_attendance', true)
                    ->orWhere('eligible_performance', true);
            })
            ->count();

        $byDepartment = RewardRecord::query()
            ->join('employees', 'employees.id', '=', 'rewards_records.employee_id')
            ->leftJoin('departments', 'departments.id', '=', 'employees.department_id')
            ->selectRaw('COALESCE(departments.department, ?) as department_name, COUNT(*) as total', ['Unassigned'])
            ->groupBy('department_name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $byType = RewardRecord::query()
            ->selectRaw('award_type, COUNT(*) as total')
            ->groupBy('award_type')
            ->orderByDesc('total')
            ->get();
        $byMilestone = RewardRecord::query()
            ->whereNotNull('milestone_type')
            ->selectRaw('milestone_type, COUNT(*) as total')
            ->groupBy('milestone_type')
            ->orderByDesc('total')
            ->get();

        return [
            'total_rewards' => $totalRewards,
            'eligible_employees' => $eligibleEmployees,
            'by_department' => $byDepartment,
            'by_type' => $byType,
            'by_milestone' => $byMilestone,
        ];
    }
}
