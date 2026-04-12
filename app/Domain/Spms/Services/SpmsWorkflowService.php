<?php

namespace App\Domain\Spms\Services;

use App\Models\Employee;
use App\Models\SpmsCycle;
use App\Models\SpmsCriterion;
use App\Models\SpmsEvaluation;
use App\Models\SpmsProfile;
use App\Models\User;
use App\Services\AccessControl;
use App\Services\HrmsNotificationService;
use App\Services\IndividualDevelopmentPlanService;
use App\Services\SpmsScoringService;
use App\Support\Workflow\InteractsWithWorkflow;
use Illuminate\Support\Facades\DB;

class SpmsWorkflowService
{
    use InteractsWithWorkflow;

    public function __construct(
        private readonly SpmsScoringService $scoringService,
        private readonly HrmsNotificationService $notificationService,
        private readonly IndividualDevelopmentPlanService $individualDevelopmentPlanService
    ) {
    }

    public function startCycle(SpmsCycle $cycle, User $actor): void
    {
        if ($cycle->status !== SpmsCycle::STATUS_SETUP) {
            abort(422, 'Only setup cycles can be started.');
        }

        DB::transaction(function () use ($cycle, $actor) {
            $cycle->forceFill([
                'status' => SpmsCycle::STATUS_EVALUATION,
                'ready_for_closure_at' => null,
            ])->save();

            $this->generateEvaluations($cycle, $actor);
        });

        $this->logWorkflowAction('spms_cycle_status_updated', SpmsCycle::class, $cycle->id, [
            'cycle_id' => $cycle->id,
            'from' => SpmsCycle::STATUS_SETUP,
            'to' => SpmsCycle::STATUS_EVALUATION,
        ], $actor->id);
    }

    public function generateEvaluations(SpmsCycle $cycle, User $actor): int
    {
        $employees = Employee::query()
            ->with(['department', 'spmsProfile'])
            ->where('status', 'active')
            ->get();

        $createdCount = 0;
        foreach ($employees as $employee) {
            $evaluatorId = $this->resolveEvaluatorId($employee);
            if (!$evaluatorId) {
                continue;
            }

            $evaluation = SpmsEvaluation::query()->firstOrCreate(
                [
                    'employee_id' => $employee->id,
                    'cycle_id' => $cycle->id,
                ],
                [
                    'evaluator_id' => $evaluatorId,
                    'status' => SpmsEvaluation::STATUS_PENDING,
                    'total_score' => 0,
                    'rating_label' => null,
                ]
            );

            if ($evaluation->wasRecentlyCreated) {
                $createdCount++;
                $evaluator = User::query()->find($evaluatorId);
                if ($evaluator) {
                    $this->notifyWorkflowUsers($this->notificationService, [$evaluator], [
                        'title' => 'New SPMS Evaluation Assigned',
                        'message' => 'A new SPMS evaluation is ready for ' . trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')) . ' in ' . $cycle->title . '.',
                        'module' => 'spms',
                        'type' => 'info',
                        'record_id' => $evaluation->id,
                        'route_name' => 'spms.evaluation.show',
                        'route_params' => [
                            'employee' => $employee->id,
                            'cycle' => $cycle->id,
                        ],
                        'event_key' => 'spms.evaluation.assigned',
                    ], $actor);
                }
            }
        }

        $this->logWorkflowAction('spms_cycle_auto_generated_evaluations', SpmsCycle::class, $cycle->id, [
            'cycle_id' => $cycle->id,
            'created_count' => $createdCount,
        ], $actor->id);

        return $createdCount;
    }

    public function submitEvaluation(SpmsEvaluation $evaluation, array $details, string $intent, User $actor): SpmsEvaluation
    {
        $cycle = $evaluation->cycle()->firstOrFail();
        if ($cycle->status !== SpmsCycle::STATUS_EVALUATION) {
            abort(423, 'SPMS cycle is not open for evaluation.');
        }

        return DB::transaction(function () use ($evaluation, $details, $intent, $actor, $cycle) {
            foreach ($details as $entry) {
                $evaluation->details()->updateOrCreate(
                    ['criteria_id' => (int) $entry['criteria_id']],
                    ['score' => (float) $entry['score'], 'remarks' => $entry['remarks'] ?? null]
                );
            }

            $totalScore = $this->scoringService->computeTotalScore($details);
            $status = $intent === 'submitted' ? SpmsEvaluation::STATUS_SUBMITTED : SpmsEvaluation::STATUS_PENDING;

            $evaluation->forceFill([
                'status' => $status,
                'evaluator_id' => $actor->id,
                'total_score' => $totalScore,
                'rating_label' => $this->scoringService->scoreLabel($totalScore),
            ])->save();

            if ($status === SpmsEvaluation::STATUS_SUBMITTED) {
                $this->logWorkflowAction('spms_evaluation_submitted', SpmsEvaluation::class, $evaluation->id, [
                    'evaluation_id' => $evaluation->id,
                    'employee_id' => $evaluation->employee_id,
                    'cycle_id' => $evaluation->cycle_id,
                    'total_score' => $evaluation->total_score,
                    'rating_label' => $evaluation->rating_label,
                ], $actor->id);
            }

            $this->markCycleReadyForClosureIfApplicable($cycle, $actor);

            return $evaluation->fresh(['details.criteria', 'cycle', 'employee', 'evaluator']);
        });
    }

    public function finalizeEvaluation(SpmsEvaluation $evaluation, User $actor): void
    {
        if ($evaluation->status !== SpmsEvaluation::STATUS_SUBMITTED) {
            abort(422, 'Only submitted evaluations can be finalized.');
        }

        $evaluation->forceFill(['status' => SpmsEvaluation::STATUS_FINAL])->save();

        $this->logWorkflowAction('spms_evaluation_finalized', SpmsEvaluation::class, $evaluation->id, [
            'evaluation_id' => $evaluation->id,
            'employee_id' => $evaluation->employee_id,
            'cycle_id' => $evaluation->cycle_id,
        ], $actor->id);
    }

    public function closeCycle(SpmsCycle $cycle, User $actor): void
    {
        if ($cycle->status !== SpmsCycle::STATUS_EVALUATION) {
            abort(422, 'Only evaluation cycles can be closed.');
        }

        DB::transaction(function () use ($cycle, $actor) {
            $this->finalizeSubmittedEvaluations($cycle, $actor);

            $cycle->forceFill([
                'status' => SpmsCycle::STATUS_CLOSED,
                'ready_for_closure_at' => $cycle->ready_for_closure_at ?: now(),
            ])->save();

            $generatedPlans = $this->individualDevelopmentPlanService->generateDraftsForLockedCycle($cycle, $actor->id);
            $this->notifyEmployeesForClosedCycle($cycle, $actor);

            $this->logWorkflowAction('spms_cycle_closed', SpmsCycle::class, $cycle->id, [
                'cycle_id' => $cycle->id,
                'title' => $cycle->title,
                'idp_drafts_created' => $generatedPlans,
            ], $actor->id);
        });
    }

    public function autoCloseCycleIfEligible(SpmsCycle $cycle, User $actor): bool
    {
        $cycle->refresh();

        if ($cycle->status !== SpmsCycle::STATUS_EVALUATION) {
            return false;
        }

        $hasPending = SpmsEvaluation::query()
            ->where('cycle_id', $cycle->id)
            ->where('status', SpmsEvaluation::STATUS_PENDING)
            ->exists();

        if ($hasPending) {
            return false;
        }

        $this->closeCycle($cycle, $actor);

        return true;
    }

    public function finalizeSubmittedEvaluations(SpmsCycle $cycle, User $actor): int
    {
        $finalized = 0;

        SpmsEvaluation::query()
            ->where('cycle_id', $cycle->id)
            ->where('status', SpmsEvaluation::STATUS_SUBMITTED)
            ->each(function (SpmsEvaluation $evaluation) use ($actor, &$finalized) {
                $evaluation->forceFill(['status' => SpmsEvaluation::STATUS_FINAL])->save();

                $this->logWorkflowAction('spms_evaluation_finalized', SpmsEvaluation::class, $evaluation->id, [
                    'evaluation_id' => $evaluation->id,
                    'employee_id' => $evaluation->employee_id,
                    'cycle_id' => $evaluation->cycle_id,
                ], $actor->id);

                $finalized++;
            });

        if ($finalized > 0) {
            $this->notifyEmployeesForClosedCycle($cycle, $actor);
        }

        return $finalized;
    }

    public function remindPendingEvaluators(SpmsCycle $cycle, User $actor): int
    {
        $pendingEvaluations = SpmsEvaluation::query()
            ->with(['employee.department', 'evaluator'])
            ->where('cycle_id', $cycle->id)
            ->where('status', SpmsEvaluation::STATUS_PENDING)
            ->get()
            ->groupBy('evaluator_id');

        $reminded = 0;

        foreach ($pendingEvaluations as $evaluatorId => $evaluations) {
            $evaluator = $evaluations->first()?->evaluator;
            if (!$evaluator) {
                continue;
            }

            $count = $evaluations->count();
            $employeePreview = $evaluations
                ->take(3)
                ->map(fn (SpmsEvaluation $evaluation) => trim(($evaluation->employee?->first_name ?? '') . ' ' . ($evaluation->employee?->last_name ?? '')))
                ->filter()
                ->implode(', ');

            $message = $count === 1
                ? 'You have 1 pending SPMS evaluation in ' . $cycle->title . '.'
                : 'You have ' . $count . ' pending SPMS evaluations in ' . $cycle->title . '.';

            if ($employeePreview !== '') {
                $message .= ' Pending: ' . $employeePreview;
                if ($count > 3) {
                    $message .= ' and more.';
                } else {
                    $message .= '.';
                }
            }

            $this->notifyWorkflowUsers($this->notificationService, [$evaluator], [
                'title' => 'SPMS Reminder',
                'message' => $message,
                'module' => 'spms',
                'type' => 'warning',
                'record_id' => $cycle->id,
                'route_name' => 'spms.cycle.show',
                'route_params' => ['id' => $cycle->id],
                'event_key' => 'spms.cycle.pending_reminder',
                'priority' => 'high',
            ], $actor);

            $reminded++;
        }

        if ($reminded > 0) {
            $this->logWorkflowAction('spms_pending_reminders_sent', SpmsCycle::class, $cycle->id, [
                'cycle_id' => $cycle->id,
                'reminded_evaluators' => $reminded,
                'pending_evaluations' => $pendingEvaluations->flatten(1)->count(),
            ], $actor->id);
        }

        return $reminded;
    }

    public function syncEvaluatorsForCycle(SpmsCycle $cycle, User $actor): array
    {
        $updated = 0;
        $skipped = 0;

        $evaluations = SpmsEvaluation::query()
            ->with(['employee.department', 'employee.spmsProfile'])
            ->where('cycle_id', $cycle->id)
            ->get();

        foreach ($evaluations as $evaluation) {
            $employee = $evaluation->employee;
            if (!$employee) {
                $skipped++;
                continue;
            }

            $head = AccessControl::headApproversForDepartment((int) $employee->department_id)->first();
            if (!$head) {
                $skipped++;
                continue;
            }

            SpmsProfile::query()->updateOrCreate(
                ['employee_id' => $employee->id],
                ['primary_evaluator_id' => (int) $head->id]
            );

            if ((int) $evaluation->evaluator_id !== (int) $head->id) {
                $evaluation->forceFill(['evaluator_id' => (int) $head->id])->save();
                $updated++;
            }
        }

        $this->logWorkflowAction('spms_cycle_evaluators_synced', SpmsCycle::class, $cycle->id, [
            'cycle_id' => $cycle->id,
            'updated_evaluations' => $updated,
            'skipped_evaluations' => $skipped,
        ], $actor->id);

        return [
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }

    public function seedFromPreviousCycle(SpmsEvaluation $evaluation): int
    {
        $previousEvaluation = SpmsEvaluation::query()
            ->with('details')
            ->where('employee_id', $evaluation->employee_id)
            ->where('cycle_id', '!=', $evaluation->cycle_id)
            ->whereIn('status', [SpmsEvaluation::STATUS_SUBMITTED, SpmsEvaluation::STATUS_FINAL])
            ->latest('updated_at')
            ->first();

        if (!$previousEvaluation || $previousEvaluation->details->isEmpty()) {
            return 0;
        }

        $seeded = 0;

        foreach ($previousEvaluation->details as $detail) {
            $current = $evaluation->details()->firstOrNew([
                'criteria_id' => (int) $detail->criteria_id,
            ]);

            if ($current->exists) {
                continue;
            }

            $current->fill([
                'score' => (float) $detail->score,
                'remarks' => $detail->remarks,
            ]);
            $current->save();
            $seeded++;
        }

        if ($seeded > 0) {
            $payload = $evaluation->details()->get(['criteria_id', 'score', 'remarks'])->map(fn ($detail) => [
                'criteria_id' => (int) $detail->criteria_id,
                'score' => (float) $detail->score,
                'remarks' => $detail->remarks,
            ])->all();

            $totalScore = $this->scoringService->computeTotalScore($payload);
            $evaluation->forceFill([
                'total_score' => $totalScore,
                'rating_label' => $this->scoringService->scoreLabel($totalScore),
            ])->save();
        }

        return $seeded;
    }

    public function syncAttendanceScoresForCycle(SpmsCycle $cycle, callable $resolver): void
    {
        $criteria = SpmsCriterion::query()->orderBy('category')->orderBy('name')->get();
        $attendanceCriterion = collect($criteria)->first(function (SpmsCriterion $criterion) {
            $name = strtolower(trim((string) $criterion->name));
            $category = strtolower(trim((string) $criterion->category));
            return in_array($category, ['attendance', 'attendance_kpi'], true)
                || in_array($name, ['attendance', 'attendance kpi'], true);
        });

        if (!$attendanceCriterion) {
            return;
        }

        SpmsEvaluation::query()
            ->with(['employee', 'details'])
            ->where('cycle_id', $cycle->id)
            ->whereIn('status', [SpmsEvaluation::STATUS_PENDING, SpmsEvaluation::STATUS_SUBMITTED])
            ->get()
            ->each(function (SpmsEvaluation $evaluation) use ($attendanceCriterion, $resolver) {
                $kpi = $resolver($evaluation->employee, $cycle);
                if (!$kpi) {
                    return;
                }

                $score = round(min(5.0, max(1.0, (float) $kpi->rating)), 2);
                $evaluation->details()->updateOrCreate(
                    ['criteria_id' => $attendanceCriterion->id],
                    [
                        'score' => $score,
                        'remarks' => sprintf(
                            'Auto-computed from Attendance KPI: attendance %.2f%%, punctuality %.2f%%, final %.2f%%, rating %d.',
                            (float) $kpi->attendance_rate,
                            (float) $kpi->punctuality_rate,
                            (float) $kpi->final_score,
                            (int) $kpi->rating
                        ),
                    ]
                );

                $payload = $evaluation->details()->get(['criteria_id', 'score', 'remarks'])->map(fn ($detail) => [
                    'criteria_id' => (int) $detail->criteria_id,
                    'score' => (float) $detail->score,
                    'remarks' => $detail->remarks,
                ])->all();

                $totalScore = $this->scoringService->computeTotalScore($payload);
                $evaluation->forceFill([
                    'total_score' => $totalScore,
                    'rating_label' => $this->scoringService->scoreLabel($totalScore),
                ])->save();
            });
    }

    public function markCycleReadyForClosureIfApplicable(SpmsCycle $cycle, User $actor): void
    {
        $hasPending = SpmsEvaluation::query()
            ->where('cycle_id', $cycle->id)
            ->where('status', SpmsEvaluation::STATUS_PENDING)
            ->exists();

        if ($hasPending || $cycle->ready_for_closure_at) {
            return;
        }

        $cycle->forceFill(['ready_for_closure_at' => now()])->save();

        $recipients = AccessControl::adminUsers()->merge(AccessControl::hrHeadUsers())->unique('id')->values();
        if ($recipients->isNotEmpty()) {
            $this->notifyWorkflowUsers($this->notificationService, $recipients->all(), [
                'title' => 'SPMS Cycle Ready for Closure',
                'message' => $cycle->title . ' now has all evaluations submitted and can be closed.',
                'module' => 'spms',
                'type' => 'info',
                'record_id' => $cycle->id,
                'route_name' => 'spms.cycle.show',
                'route_params' => ['id' => $cycle->id],
                'event_key' => 'spms.cycle.ready_for_closure',
            ], $actor);
        }

    }

    private function notifyEmployeesForClosedCycle(SpmsCycle $cycle, User $actor): void
    {
        SpmsEvaluation::query()
            ->with(['employee.user'])
            ->where('cycle_id', $cycle->id)
            ->where('status', SpmsEvaluation::STATUS_FINAL)
            ->get()
            ->each(function (SpmsEvaluation $evaluation) use ($cycle, $actor) {
                $employeeUser = $evaluation->employee?->user;
                if (!$employeeUser) {
                    return;
                }

                $this->notifyWorkflowUsers($this->notificationService, [$employeeUser], [
                    'title' => 'SPMS Evaluation Finalized',
                    'message' => 'Your SPMS evaluation for ' . $cycle->title . ' has been finalized.',
                    'module' => 'spms',
                    'type' => 'success',
                    'record_id' => $evaluation->id,
                    'route_name' => 'spms.evaluation.show',
                    'route_params' => [
                        'employee' => $evaluation->employee_id,
                        'cycle' => $evaluation->cycle_id,
                    ],
                    'event_key' => 'spms.evaluation.finalized',
                ], $actor);
            });
    }

    private function resolveEvaluatorId(Employee $employee): ?int
    {
        $profile = $employee->spmsProfile;
        if ($profile && $profile->primary_evaluator_id) {
            return (int) $profile->primary_evaluator_id;
        }

        $head = AccessControl::headApproversForDepartment((int) $employee->department_id)->first();
        if ($head) {
            \App\Models\SpmsProfile::query()->updateOrCreate(
                ['employee_id' => $employee->id],
                ['primary_evaluator_id' => (int) $head->id]
            );
            return (int) $head->id;
        }

        return null;
    }
}
