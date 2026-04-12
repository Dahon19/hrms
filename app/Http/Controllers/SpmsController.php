<?php

namespace App\Http\Controllers;

use App\Domain\Spms\Services\SpmsWorkflowService;
use App\Http\Requests\SpmsSaveEvaluationRequest;
use App\Models\AttendanceMonthlyScore;
use App\Models\Department;
use App\Models\Employee;
use App\Models\SpmsCriterion;
use App\Models\SpmsCycle;
use App\Models\SpmsEvaluation;
use App\Models\SpmsProfile;
use App\Services\AccessControl;
use App\Services\AttendanceKpiScoringService;
use App\Services\AuditLogger;
use App\Services\HrmsNotificationService;
use App\Services\IndividualDevelopmentPlanService;
use App\Services\SpmsScoringService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SpmsController extends Controller
{
    public function __construct(
        private readonly SpmsScoringService $scoringService,
        private readonly HrmsNotificationService $notificationService,
        private readonly AttendanceKpiScoringService $attendanceKpiScoringService,
        private readonly IndividualDevelopmentPlanService $individualDevelopmentPlanService,
        private readonly SpmsWorkflowService $workflowService
    ) {
    }

    public function cycles(Request $request)
    {
        Gate::authorize('view-spms');

        $cycles = SpmsCycle::query()
            ->withCount('evaluations')
            ->latest('period_start')
            ->paginate(10)
            ->withQueryString();

        $employeeCount = Employee::query()->where('status', 'active')->count();
        $cycleRows = $cycles->getCollection()->map(function (SpmsCycle $cycle) use ($employeeCount) {
            $evaluations = SpmsEvaluation::query()->where('cycle_id', $cycle->id)->get(['status']);
            return [
                'cycle' => $cycle,
                'completion_rate' => $this->scoringService->completionRateForCycle($evaluations, $employeeCount),
                'pending_count' => $evaluations->where('status', SpmsEvaluation::STATUS_PENDING)->count(),
                'submitted_count' => $evaluations->where('status', SpmsEvaluation::STATUS_SUBMITTED)->count(),
                'final_count' => $evaluations->where('status', SpmsEvaluation::STATUS_FINAL)->count(),
            ];
        });
        $cycles->setCollection($cycleRows);

        return view('spms.cycles.index', [
            'cycles' => $cycles,
            'employeeCount' => $employeeCount,
            'canManage' => Gate::allows('manage-spms'),
            'canEvaluate' => Gate::allows('evaluate-spms'),
        ]);
    }

    public function storeCycle(Request $request)
    {
        Gate::authorize('manage-spms');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
        ]);

        $cycle = SpmsCycle::create([
            'title' => $validated['title'],
            'period_start' => $validated['period_start'],
            'period_end' => $validated['period_end'],
            'status' => SpmsCycle::STATUS_SETUP,
            'ready_for_closure_at' => null,
        ]);

        AuditLogger::logSystem('spms_cycle_created', [
            'cycle_id' => $cycle->id,
            'title' => $cycle->title,
        ], $request->user()?->id, SpmsCycle::class, $cycle->id);

        return back()->with('success', 'SPMS cycle created.');
    }

    public function evaluations(Request $request)
    {
        Gate::authorize('view-spms');

        $query = SpmsEvaluation::query()
            ->with(['employee.department', 'cycle', 'evaluator'])
            ->latest('updated_at');

        $user = $request->user();
        if (Gate::denies('manage-spms') && Gate::denies('evaluate-spms')) {
            $query->where('employee_id', (int) ($user?->employee?->id ?? 0))
                ->whereIn('status', [SpmsEvaluation::STATUS_SUBMITTED, SpmsEvaluation::STATUS_FINAL]);
        } elseif (Gate::allows('evaluate-spms') && Gate::denies('manage-spms') && AccessControl::isHeadOrDean($user)) {
            $query->whereHas('employee', fn ($employeeQuery) => $employeeQuery->where('department_id', (int) ($user?->employee?->department_id ?? 0)));
        }

        return view('spms.evaluations.index', [
            'evaluations' => $query->paginate(10)->withQueryString(),
        ]);
    }

    public function myPerformance(Request $request)
    {
        Gate::authorize('view-spms');

        $employeeId = (int) ($request->user()?->employee?->id ?? 0);
        abort_if($employeeId <= 0, 403);

        $evaluations = SpmsEvaluation::query()
            ->with('cycle')
            ->where('employee_id', $employeeId)
            ->whereIn('status', [SpmsEvaluation::STATUS_SUBMITTED, SpmsEvaluation::STATUS_FINAL])
            ->latest('updated_at')
            ->paginate(10)
            ->withQueryString();

        return view('spms.my-performance', [
            'evaluations' => $evaluations,
            'scoringService' => $this->scoringService,
        ]);
    }

    public function cycleShow(Request $request, int $id)
    {
        Gate::authorize('view-spms');

        $cycle = SpmsCycle::query()->findOrFail($id);
        $departmentId = (int) $request->query('department_id', 0);
        $search = trim((string) $request->query('search', ''));

        $employeesQuery = Employee::query()
            ->with(['department', 'user'])
            ->where('status', 'active')
            ->when($departmentId > 0, fn ($q) => $q->where('department_id', $departmentId))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('employee_id', 'like', '%' . $search . '%')
                        ->orWhere('first_name', 'like', '%' . $search . '%')
                        ->orWhere('last_name', 'like', '%' . $search . '%');
                });
            });

        $user = $request->user();
        if (Gate::denies('evaluate-spms') && Gate::denies('manage-spms')) {
            $employeesQuery->where('id', (int) ($request->user()?->employee?->id ?? 0));
        } elseif (Gate::allows('evaluate-spms') && Gate::denies('manage-spms') && AccessControl::isHeadOrDean($user)) {
            $employeesQuery->where('department_id', (int) ($user?->employee?->department_id ?? 0));
        }

        $employees = $employeesQuery
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(10)
            ->withQueryString();

        $evaluationMap = SpmsEvaluation::query()
            ->where('cycle_id', $cycle->id)
            ->whereIn('employee_id', collect($employees->items())->pluck('id')->all())
            ->get()
            ->keyBy('employee_id');

        $cycleStatusCounts = SpmsEvaluation::query()
            ->where('cycle_id', $cycle->id)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return view('spms.cycles.show', [
            'cycle' => $cycle,
            'employees' => $employees,
            'evaluationMap' => $evaluationMap,
            'nextPendingEvaluationUrl' => $this->buildNextEvaluationUrl($request->user(), $cycle, pendingOnly: true),
            'cycleStatusCounts' => [
                'pending' => (int) ($cycleStatusCounts[SpmsEvaluation::STATUS_PENDING] ?? 0),
                'submitted' => (int) ($cycleStatusCounts[SpmsEvaluation::STATUS_SUBMITTED] ?? 0),
                'final' => (int) ($cycleStatusCounts[SpmsEvaluation::STATUS_FINAL] ?? 0),
            ],
            'departments' => Department::query()->orderBy('department')->get(),
            'filters' => [
                'department_id' => $departmentId,
                'search' => $search,
            ],
            'canManage' => Gate::allows('manage-spms'),
        ]);
    }

    public function evaluationShow(Request $request, int $employeeId, int $cycleId)
    {
        Gate::authorize('view-spms');

        $employee = Employee::query()->with(['department', 'user', 'positions.position'])->findOrFail($employeeId);
        $cycle = SpmsCycle::query()->findOrFail($cycleId);
        $previousCycleSeededCount = 0;
        $evaluation = SpmsEvaluation::query()
            ->with(['details.criteria', 'evaluator'])
            ->where('employee_id', $employee->id)
            ->where('cycle_id', $cycle->id)
            ->first();

        if (!$evaluation && $cycle->status === SpmsCycle::STATUS_EVALUATION) {
            $resolvedEvaluatorId = $this->resolveEvaluatorId($employee);
            if ($resolvedEvaluatorId && (Gate::allows('manage-spms') || (int) $request->user()->id === $resolvedEvaluatorId)) {
                $evaluation = SpmsEvaluation::query()->firstOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'cycle_id' => $cycle->id,
                    ],
                    [
                        'evaluator_id' => $resolvedEvaluatorId,
                        'status' => SpmsEvaluation::STATUS_PENDING,
                        'total_score' => 0,
                        'rating_label' => null,
                    ]
                );
                if ($evaluation->wasRecentlyCreated) {
                    $previousCycleSeededCount = $this->workflowService->seedFromPreviousCycle($evaluation);
                }
                $evaluation->load(['details.criteria', 'evaluator']);
            }
        }

        if (!$this->canViewEvaluation($request->user(), $employee, $evaluation)) {
            abort(403);
        }

        $criteria = SpmsCriterion::query()->orderBy('category')->orderBy('name')->get();
        $attendanceCriterion = $this->resolveAttendanceCriterion($criteria);
        $attendanceKpiScore = $attendanceCriterion
            ? $this->resolveAttendanceKpiForCycle($employee, $cycle)
            : null;
        $attendanceCriterionScore = null;
        if ($attendanceCriterion && $attendanceKpiScore) {
            $attendanceCriterionScore = round(min(5.0, max(1.0, (float) $attendanceKpiScore->rating)), 2);
        }

        return view('spms.evaluations.show', [
            'employee' => $employee,
            'cycle' => $cycle,
            'evaluation' => $evaluation,
            'previousCycleSeededCount' => $previousCycleSeededCount,
            'nextEvaluationUrl' => $this->buildNextEvaluationUrl($request->user(), $cycle, $employee),
            'nextPendingEvaluationUrl' => $this->buildNextEvaluationUrl($request->user(), $cycle, $employee, pendingOnly: true),
            'criteria' => $criteria,
            'attendanceCriterionId' => $attendanceCriterion?->id,
            'attendanceKpiScore' => $attendanceKpiScore,
            'attendanceCriterionScore' => $attendanceCriterionScore,
            'canEdit' => $evaluation
                ? $this->canEditEvaluation($request->user(), $evaluation)
                : false,
            'canManage' => Gate::allows('manage-spms'),
        ]);
    }

    public function evaluationShowById(Request $request, SpmsEvaluation $evaluation)
    {
        return redirect()->route('spms.evaluation.show', [
            'employee' => $evaluation->employee_id,
            'cycle' => $evaluation->cycle_id,
        ]);
    }

    public function saveEvaluation(SpmsSaveEvaluationRequest $request)
    {
        Gate::authorize('evaluate-spms');

        $employee = Employee::query()->with('department')->findOrFail((int) $request->validated('employee_id'));
        if ($employee->status !== 'active') {
            abort(422, 'Inactive employee cannot be evaluated.');
        }

        if (Gate::denies('manage-spms')) {
            $expectedEvaluatorId = $this->resolveEvaluatorId($employee);
            if ($expectedEvaluatorId <= 0 || (int) $request->user()->id !== $expectedEvaluatorId) {
                abort(403, 'You are not the assigned evaluator for this employee.');
            }
        }

        $cycle = SpmsCycle::query()->findOrFail((int) $request->validated('cycle_id'));
        if ($cycle->status !== SpmsCycle::STATUS_EVALUATION) {
            abort(423, 'SPMS cycle is not open for evaluation.');
        }

        $intent = (string) $request->validated('intent');
        $nextAfterSave = str_ends_with($intent, '_next');
        $normalizedIntent = match ($intent) {
            'draft_next' => 'draft',
            'submitted_next' => 'submitted',
            default => $intent,
        };
        $details = collect((array) $request->validated('details'))
            ->map(fn ($detail) => [
                'criteria_id' => (int) ($detail['criteria_id'] ?? 0),
                'score' => (float) ($detail['score'] ?? 0),
                'remarks' => trim((string) ($detail['remarks'] ?? '')) ?: null,
            ])->values()->all();

        $criteriaSet = SpmsCriterion::query()
            ->whereIn('id', collect($details)->pluck('criteria_id')->all())
            ->get();
        $attendanceCriterion = $this->resolveAttendanceCriterion($criteriaSet);
        if ($attendanceCriterion) {
            $attendanceKpiScore = $this->resolveAttendanceKpiForCycle($employee, $cycle);
            if ($attendanceKpiScore) {
                $systemScore = round(min(5.0, max(1.0, (float) $attendanceKpiScore->rating)), 2);
                $details = collect($details)->map(function (array $entry) use ($attendanceCriterion, $systemScore, $attendanceKpiScore) {
                    if ((int) $entry['criteria_id'] !== (int) $attendanceCriterion->id) {
                        return $entry;
                    }

                    return [
                        'criteria_id' => (int) $entry['criteria_id'],
                        'score' => $systemScore,
                        'remarks' => sprintf(
                            'Auto-computed from Attendance KPI: attendance %.2f%%, punctuality %.2f%%, final %.2f%%, rating %d.',
                            (float) $attendanceKpiScore->attendance_rate,
                            (float) $attendanceKpiScore->punctuality_rate,
                            (float) $attendanceKpiScore->final_score,
                            (int) $attendanceKpiScore->rating
                        ),
                    ];
                })->values()->all();

                $hasAttendanceEntry = collect($details)->contains(fn (array $entry) => (int) $entry['criteria_id'] === (int) $attendanceCriterion->id);
                if (!$hasAttendanceEntry) {
                    $details[] = [
                        'criteria_id' => (int) $attendanceCriterion->id,
                        'score' => $systemScore,
                        'remarks' => sprintf(
                            'Auto-computed from Attendance KPI: attendance %.2f%%, punctuality %.2f%%, final %.2f%%, rating %d.',
                            (float) $attendanceKpiScore->attendance_rate,
                            (float) $attendanceKpiScore->punctuality_rate,
                            (float) $attendanceKpiScore->final_score,
                            (int) $attendanceKpiScore->rating
                        ),
                    ];
                }
            }
        }

        $evaluation = DB::transaction(function () use ($request, $employee, $cycle, $details, $normalizedIntent) {
            $evaluation = SpmsEvaluation::query()
                ->where('employee_id', $employee->id)
                ->where('cycle_id', $cycle->id)
                ->lockForUpdate()
                ->first();

            if (!$evaluation) {
                $resolvedEvaluatorId = $this->resolveEvaluatorId($employee) ?: (int) $request->user()->id;
                $evaluation = SpmsEvaluation::create([
                    'employee_id' => $employee->id,
                    'cycle_id' => $cycle->id,
                    'evaluator_id' => $resolvedEvaluatorId,
                    'status' => SpmsEvaluation::STATUS_PENDING,
                    'total_score' => 0,
                    'rating_label' => null,
                ]);

                $this->workflowService->seedFromPreviousCycle($evaluation);

                AuditLogger::logSystem('spms_evaluation_created', [
                    'evaluation_id' => $evaluation->id,
                    'employee_id' => $evaluation->employee_id,
                    'cycle_id' => $evaluation->cycle_id,
                    'status' => $evaluation->status,
                ], $request->user()?->id, SpmsEvaluation::class, $evaluation->id);
            }

            if (!$this->canEditEvaluation($request->user(), $evaluation->loadMissing('cycle'))) {
                abort(403);
            }

            if (in_array($evaluation->status, [SpmsEvaluation::STATUS_SUBMITTED, SpmsEvaluation::STATUS_FINAL], true)) {
                abort(423, 'Evaluation is already finalized.');
            }

            foreach ($details as $entry) {
                if ((float) $entry['score'] < 1 || (float) $entry['score'] > 5) {
                    throw ValidationException::withMessages([
                        'details' => 'Score must be between 1 and 5 for all criteria.',
                    ]);
                }
            }

            return $this->workflowService->submitEvaluation($evaluation, $details, $normalizedIntent, $request->user());
        });

        AuditLogger::logSystem('spms_evaluation_saved', [
            'evaluation_id' => $evaluation->id,
            'employee_id' => $evaluation->employee_id,
            'cycle_id' => $evaluation->cycle_id,
            'status' => $evaluation->status,
            'total_score' => $evaluation->total_score,
            'rating_label' => $evaluation->rating_label,
        ], $request->user()?->id, SpmsEvaluation::class, $evaluation->id);

        AuditLogger::logSystem('spms_evaluation_updated', [
            'evaluation_id' => $evaluation->id,
            'employee_id' => $evaluation->employee_id,
            'cycle_id' => $evaluation->cycle_id,
            'status' => $evaluation->status,
        ], $request->user()?->id, SpmsEvaluation::class, $evaluation->id);

        if ($nextAfterSave) {
            $nextUrl = $this->buildNextEvaluationUrl(
                $request->user(),
                $cycle,
                $employee,
                pendingOnly: true,
            );

            if ($nextUrl) {
                return redirect($nextUrl)
                    ->with('success', 'SPMS evaluation saved (' . strtoupper($evaluation->status) . '). Opened next pending evaluation.');
            }
        }

        return redirect()
            ->route('spms.evaluation.show', ['employee' => $evaluation->employee_id, 'cycle' => $evaluation->cycle_id])
            ->with('success', 'SPMS evaluation saved (' . strtoupper($evaluation->status) . ').');
    }

    public function submitEvaluation(Request $request, SpmsEvaluation $evaluation)
    {
        Gate::authorize('evaluate-spms');
        $evaluation->loadMissing(['employee', 'cycle', 'details']);

        if (Gate::denies('manage-spms') && (int) $evaluation->evaluator_id !== (int) $request->user()->id) {
            abort(403);
        }
        if ($evaluation->status !== SpmsEvaluation::STATUS_PENDING) {
            return back()->with('error', 'Only pending evaluations can be submitted.');
        }
        if ($evaluation->cycle?->status !== SpmsCycle::STATUS_EVALUATION) {
            return back()->with('error', 'Cycle is not open for submission.');
        }

        $detailCount = $evaluation->details()->count();
        $criteriaCount = SpmsCriterion::query()->count();
        if ($detailCount < $criteriaCount) {
            return back()->with('error', 'Please score all criteria before submission.');
        }

        $payload = $evaluation->details->map(fn ($detail) => [
            'criteria_id' => (int) $detail->criteria_id,
            'score' => (float) $detail->score,
            'remarks' => $detail->remarks,
        ])->all();
        $this->workflowService->submitEvaluation($evaluation, $payload, 'submitted', $request->user());

        return redirect()->route('spms.evaluation.show', [
            'employee' => $evaluation->employee_id,
            'cycle' => $evaluation->cycle_id,
        ])->with('success', 'Evaluation submitted.');
    }

    public function finalizeEvaluation(Request $request, SpmsEvaluation $evaluation)
    {
        if (!$this->canReviewEvaluation($request->user(), $evaluation->loadMissing(['employee.spmsProfile', 'cycle']))) {
            abort(403);
        }
        if ($evaluation->status !== SpmsEvaluation::STATUS_SUBMITTED) {
            abort(422, 'Only submitted evaluations can be finalized.');
        }
        if ($evaluation->cycle?->status !== SpmsCycle::STATUS_EVALUATION) {
            abort(423, 'Cycle is not open.');
        }

        $this->workflowService->finalizeEvaluation($evaluation, $request->user());

        return back()->with('success', 'Evaluation finalized.');
    }

    public function reviewEvaluation(Request $request, SpmsEvaluation $evaluation)
    {
        return $this->finalizeEvaluation($request, $evaluation);
    }

    public function closeCycle(Request $request, SpmsCycle $cycle)
    {
        Gate::authorize('manage-spms');
        $this->workflowService->closeCycle($cycle, $request->user());
        return back()->with('success', 'SPMS cycle closed and IDP drafts generated.');
    }

    public function lockCycle(Request $request, SpmsCycle $cycle)
    {
        return $this->closeCycle($request, $cycle);
    }

    public function finalizeSubmitted(Request $request, SpmsCycle $cycle)
    {
        Gate::authorize('manage-spms');

        if ($cycle->status !== SpmsCycle::STATUS_EVALUATION) {
            return back()->with('error', 'Only active evaluation cycles can finalize submitted evaluations.');
        }

        $finalized = $this->workflowService->finalizeSubmittedEvaluations($cycle, $request->user());

        if ($finalized === 0) {
            return back()->with('info', 'No submitted evaluations were waiting for finalization.');
        }

        $this->workflowService->markCycleReadyForClosureIfApplicable($cycle->fresh(), $request->user());

        return back()->with('success', $finalized . ' submitted evaluation(s) finalized.');
    }

    public function remindPending(Request $request, SpmsCycle $cycle)
    {
        Gate::authorize('manage-spms');

        if ($cycle->status !== SpmsCycle::STATUS_EVALUATION) {
            return back()->with('error', 'Only active evaluation cycles can send pending reminders.');
        }

        $reminded = $this->workflowService->remindPendingEvaluators($cycle, $request->user());

        if ($reminded === 0) {
            return back()->with('info', 'No pending evaluators were found for this cycle.');
        }

        return back()->with('success', 'Reminder sent to ' . $reminded . ' evaluator(s).');
    }

    public function syncEvaluators(Request $request, SpmsCycle $cycle)
    {
        Gate::authorize('manage-spms');

        $result = $this->workflowService->syncEvaluatorsForCycle($cycle, $request->user());
        $updated = (int) ($result['updated'] ?? 0);
        $skipped = (int) ($result['skipped'] ?? 0);

        if ($updated === 0 && $skipped === 0) {
            return back()->with('info', 'No evaluations were available for reassignment.');
        }

        $message = $updated > 0
            ? $updated . ' evaluation(s) reassigned to current department heads.'
            : 'No evaluator changes were needed.';

        if ($skipped > 0) {
            $message .= ' ' . $skipped . ' evaluation(s) were skipped because no department head was found.';
        }

        return back()->with('success', $message);
    }

    public function transitionCycle(Request $request, SpmsCycle $cycle)
    {
        Gate::authorize('manage-spms');

        $validated = $request->validate([
            'status' => ['required', 'in:draft,submitted,reviewed,locked,setup,evaluation,closed'],
        ]);

        $target = match ((string) $validated['status']) {
            'draft' => SpmsCycle::STATUS_SETUP,
            'submitted', 'reviewed' => SpmsCycle::STATUS_EVALUATION,
            'locked' => SpmsCycle::STATUS_CLOSED,
            default => (string) $validated['status'],
        };

        if ($cycle->status === SpmsCycle::STATUS_SETUP && $target === SpmsCycle::STATUS_EVALUATION) {
            $this->workflowService->startCycle($cycle, $request->user());
            return back()->with('success', 'Cycle moved to EVALUATION.');
        }

        if ($cycle->status === SpmsCycle::STATUS_EVALUATION && $target === SpmsCycle::STATUS_CLOSED) {
            return $this->closeCycle($request, $cycle);
        }

        abort(422, 'Invalid cycle status transition.');
    }

    public function updateCycleStatus(Request $request, SpmsCycle $cycle)
    {
        return $this->transitionCycle($request, $cycle);
    }

    public function report(Request $request, SpmsCycle $cycle)
    {
        Gate::authorize('manage-spms');

        $evaluations = SpmsEvaluation::query()
            ->with(['employee.department', 'details.criteria', 'evaluator'])
            ->where('cycle_id', $cycle->id)
            ->orderByDesc('total_score')
            ->get();

        $pdf = Pdf::loadView('spms.report', [
            'cycle' => $cycle,
            'evaluations' => $evaluations,
            'scoringService' => $this->scoringService,
        ])->setPaper('a4', 'landscape');

        AuditLogger::logSystem('spms_report_generated', [
            'cycle_id' => $cycle->id,
            'evaluations' => $evaluations->count(),
        ], $request->user()?->id, SpmsCycle::class, $cycle->id);

        return $pdf->stream('spms-report-cycle-' . $cycle->id . '.pdf', ['Attachment' => false]);
    }

    public function reportExcel(Request $request, SpmsCycle $cycle): StreamedResponse
    {
        Gate::authorize('manage-spms');

        $evaluations = SpmsEvaluation::query()
            ->with(['employee.department', 'evaluator'])
            ->where('cycle_id', $cycle->id)
            ->orderByDesc('total_score')
            ->get();

        AuditLogger::logSystem('spms_report_excel_generated', [
            'cycle_id' => $cycle->id,
            'evaluations' => $evaluations->count(),
        ], $request->user()?->id, SpmsCycle::class, $cycle->id);

        $filename = 'spms-report-cycle-' . $cycle->id . '-' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($evaluations) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Employee ID', 'Employee Name', 'Department', 'Status', 'Total Score', 'Rating', 'Evaluator']);

            foreach ($evaluations as $evaluation) {
                $name = trim(($evaluation->employee?->first_name ?? '') . ' ' . ($evaluation->employee?->last_name ?? ''));
                fputcsv($handle, [
                    (string) ($evaluation->employee?->employee_id ?? ''),
                    $name,
                    (string) ($evaluation->employee?->department?->department ?? ''),
                    strtoupper((string) $evaluation->status),
                    number_format((float) $evaluation->total_score, 2, '.', ''),
                    strtoupper((string) ($evaluation->rating_label ?: $this->scoringService->scoreLabel((float) $evaluation->total_score))),
                    (string) ($evaluation->evaluator?->name ?? ''),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function resolveEvaluatorId(Employee $employee): ?int
    {
        $profile = $employee->spmsProfile;
        if ($profile && $profile->primary_evaluator_id) {
            return (int) $profile->primary_evaluator_id;
        }

        $head = AccessControl::headApproversForDepartment((int) $employee->department_id)->first();
        if ($head) {
            SpmsProfile::query()->updateOrCreate(
                ['employee_id' => $employee->id],
                ['primary_evaluator_id' => (int) $head->id]
            );
            return (int) $head->id;
        }

        return null;
    }

    private function accessibleCycleEmployeesQuery($user, SpmsCycle $cycle): Builder
    {
        $query = Employee::query()
            ->where('status', 'active');

        if (Gate::allows('manage-spms')) {
            return $query;
        }

        if (Gate::allows('evaluate-spms')) {
            return $query->where(function (Builder $employeeQuery) use ($user) {
                $employeeQuery->whereHas('spmsProfile', function (Builder $profileQuery) use ($user) {
                    $profileQuery->where('primary_evaluator_id', (int) $user->id);
                });

                if (AccessControl::isHeadOrDean($user)) {
                    $employeeQuery->orWhere('department_id', (int) ($user?->employee?->department_id ?? 0));
                }
            });
        }

        return $query->where('id', (int) ($user?->employee?->id ?? 0));
    }

    private function buildNextEvaluationUrl($user, SpmsCycle $cycle, ?Employee $currentEmployee = null, bool $pendingOnly = false): ?string
    {
        if (!$user || (!Gate::allows('manage-spms') && !Gate::allows('evaluate-spms'))) {
            return null;
        }

        $employees = $this->accessibleCycleEmployeesQuery($user, $cycle)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id']);

        if ($employees->isEmpty()) {
            return null;
        }

        $employeeIds = $employees->pluck('id')->values();
        $currentIndex = $currentEmployee
            ? $employeeIds->search((int) $currentEmployee->id)
            : false;

        $evaluationStatuses = SpmsEvaluation::query()
            ->where('cycle_id', $cycle->id)
            ->whereIn('employee_id', $employeeIds->all())
            ->get(['employee_id', 'status'])
            ->keyBy('employee_id');

        $orderedIds = $currentIndex === false
            ? $employeeIds
            : $employeeIds->slice($currentIndex + 1)->values()->concat($employeeIds->slice(0, $currentIndex + 1)->values());

        $nextId = $orderedIds->first(function ($employeeId) use ($evaluationStatuses, $pendingOnly) {
            $status = strtolower((string) ($evaluationStatuses->get((int) $employeeId)?->status ?? SpmsEvaluation::STATUS_PENDING));

            if ($pendingOnly) {
                return $status === SpmsEvaluation::STATUS_PENDING;
            }

            return in_array($status, [SpmsEvaluation::STATUS_PENDING, SpmsEvaluation::STATUS_SUBMITTED], true);
        });

        if (!$nextId) {
            return null;
        }

        return route('spms.evaluation.show', ['employee' => $nextId, 'cycle' => $cycle->id]);
    }

    private function canViewEvaluation($user, Employee $employee, ?SpmsEvaluation $evaluation): bool
    {
        if (!$user) {
            return false;
        }

        if (Gate::allows('manage-spms')) {
            return true;
        }

        if (Gate::allows('evaluate-spms')) {
            $resolvedEvaluatorId = $this->resolveEvaluatorId($employee);
            return $resolvedEvaluatorId > 0 && (int) $user->id === $resolvedEvaluatorId;
        }

        if ((int) ($user->employee?->id ?? 0) !== (int) $employee->id) {
            return false;
        }

        return in_array((string) ($evaluation?->status ?? ''), [SpmsEvaluation::STATUS_SUBMITTED, SpmsEvaluation::STATUS_FINAL], true);
    }

    private function canEditEvaluation($user, SpmsEvaluation $evaluation): bool
    {
        if (!$user) {
            return false;
        }

        return $this->scoringService->canBeEditedByEvaluator($evaluation, (int) $user->id);
    }

    private function canReviewEvaluation($user, SpmsEvaluation $evaluation): bool
    {
        if (!$user) {
            return false;
        }

        return Gate::allows('manage-spms');
    }

    private function resolveAttendanceCriterion(iterable $criteria): ?SpmsCriterion
    {
        $collection = collect($criteria);
        return $collection->first(function ($criterion) {
            $name = strtolower(trim((string) ($criterion->name ?? '')));
            $category = strtolower(trim((string) ($criterion->category ?? '')));
            return $category === 'attendance_kpi'
                || $category === 'attendance'
                || $name === 'attendance'
                || $name === 'attendance kpi';
        });
    }

    private function resolveAttendanceKpiForCycle(Employee $employee, SpmsCycle $cycle): ?AttendanceMonthlyScore
    {
        $periodRef = $cycle->period_end ? $cycle->period_end->copy() : now();
        $month = (int) $periodRef->month;
        $year = (int) $periodRef->year;

        return $this->attendanceKpiScoringService->getOrComputeEmployeeScore($employee->id, $month, $year);
    }
}
