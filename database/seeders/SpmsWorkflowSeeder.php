<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\EmployeePosition;
use App\Models\Position;
use App\Models\SpmsCriterion;
use App\Models\SpmsCycle;
use App\Models\SpmsEvaluation;
use App\Models\SpmsProfile;
use App\Models\User;
use App\Services\IndividualDevelopmentPlanService;
use App\Services\SpmsScoringService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class SpmsWorkflowSeeder extends Seeder
{
    /**
     * @var array<int, array{name:string,description:string,category:string,weight:float,max_score:float}>
     */
    private array $criteriaBlueprint = [
        [
            'name' => 'Work Quality',
            'description' => 'Accuracy, completeness, and consistency of outputs.',
            'category' => 'core',
            'weight' => 35.00,
            'max_score' => 5.00,
        ],
        [
            'name' => 'Productivity',
            'description' => 'Ability to meet workload expectations and deliver on time.',
            'category' => 'core',
            'weight' => 25.00,
            'max_score' => 5.00,
        ],
        [
            'name' => 'Teamwork and Communication',
            'description' => 'Collaboration, responsiveness, and communication quality.',
            'category' => 'behavioral',
            'weight' => 20.00,
            'max_score' => 5.00,
        ],
        [
            'name' => 'Initiative and Professionalism',
            'description' => 'Ownership, reliability, and conduct in daily work.',
            'category' => 'behavioral',
            'weight' => 10.00,
            'max_score' => 5.00,
        ],
        [
            'name' => 'Attendance KPI',
            'description' => 'Auto-aligned attendance performance basis.',
            'category' => 'attendance_kpi',
            'weight' => 10.00,
            'max_score' => 5.00,
        ],
    ];

    public function run(): void
    {
        if (
            !Schema::hasTable('spms_cycles')
            || !Schema::hasTable('spms_criteria')
            || !Schema::hasTable('spms_evaluations')
            || !Schema::hasTable('spms_evaluation_details')
            || !Schema::hasTable('spms_profiles')
            || !Schema::hasTable('employees')
        ) {
            return;
        }

        $employees = Employee::query()
            ->with(['user', 'department', 'positions.position', 'spmsProfile'])
            ->where('status', 'active')
            ->whereHas('user', function ($query) {
                $query->where('role', '!=', 'admin')
                    ->whereNull('archived_at');
            })
            ->orderBy('department_id')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        if ($employees->isEmpty()) {
            return;
        }

        $adminUser = User::query()->where('role', 'admin')->orderBy('id')->first();
        $hrHead = $this->resolveHrHead($employees);
        $fallbackEvaluator = $hrHead?->user ?: $adminUser;

        if (!$fallbackEvaluator) {
            return;
        }

        /** @var SpmsScoringService $scoringService */
        $scoringService = app(SpmsScoringService::class);
        /** @var IndividualDevelopmentPlanService $idpService */
        $idpService = app(IndividualDevelopmentPlanService::class);

        $criteria = collect($this->criteriaBlueprint)
            ->map(function (array $criterion): SpmsCriterion {
                return SpmsCriterion::query()->updateOrCreate(
                    ['name' => $criterion['name']],
                    $criterion
                );
            })
            ->values();

        $closedCycle = SpmsCycle::query()->updateOrCreate(
            ['title' => 'SPMS Annual Review 2026'],
            [
                'period_start' => now()->startOfYear()->toDateString(),
                'period_end' => now()->copy()->subMonth()->endOfMonth()->toDateString(),
                'status' => SpmsCycle::STATUS_CLOSED,
                'ready_for_closure_at' => now()->copy()->subWeeks(2),
            ]
        );

        $activeCycle = SpmsCycle::query()->updateOrCreate(
            ['title' => 'SPMS Midyear Review 2026'],
            [
                'period_start' => now()->copy()->startOfMonth()->toDateString(),
                'period_end' => now()->copy()->addMonths(3)->endOfMonth()->toDateString(),
                'status' => SpmsCycle::STATUS_EVALUATION,
                'ready_for_closure_at' => null,
            ]
        );

        $departmentHeadMap = $this->departmentHeads($employees);

        foreach ($employees as $index => $employee) {
            $evaluator = $this->resolveEvaluator($employee, $departmentHeadMap, $fallbackEvaluator);

            SpmsProfile::query()->updateOrCreate(
                ['employee_id' => $employee->id],
                [
                    'primary_evaluator_id' => $evaluator->id,
                    'secondary_reviewer_id' => $fallbackEvaluator->id,
                    'self_assessment_enabled' => false,
                ]
            );

            $closedDetails = $this->buildDetailsForEmployee($criteria, $employee, $index, true);
            $closedTotal = $scoringService->computeTotalScore($closedDetails);
            $closedEvaluation = SpmsEvaluation::query()->updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'cycle_id' => $closedCycle->id,
                ],
                [
                    'evaluator_id' => $evaluator->id,
                    'status' => SpmsEvaluation::STATUS_FINAL,
                    'total_score' => $closedTotal,
                    'rating_label' => $scoringService->scoreLabel($closedTotal),
                ]
            );
            $this->syncEvaluationDetails($closedEvaluation, $closedDetails);

            $activeStatus = match ($index % 4) {
                0 => SpmsEvaluation::STATUS_PENDING,
                1 => SpmsEvaluation::STATUS_SUBMITTED,
                2 => SpmsEvaluation::STATUS_FINAL,
                default => SpmsEvaluation::STATUS_PENDING,
            };

            $activeDetails = $this->buildDetailsForEmployee($criteria, $employee, $index, false);
            $activeTotal = $scoringService->computeTotalScore($activeDetails);
            $activeEvaluation = SpmsEvaluation::query()->updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'cycle_id' => $activeCycle->id,
                ],
                [
                    'evaluator_id' => $evaluator->id,
                    'status' => $activeStatus,
                    'total_score' => $activeTotal,
                    'rating_label' => $scoringService->scoreLabel($activeTotal),
                ]
            );
            $this->syncEvaluationDetails($activeEvaluation, $activeDetails);
        }

        $idpService->generateDraftsForLockedCycle($closedCycle, $fallbackEvaluator->id);
    }

    private function resolveHrHead(Collection $employees): ?Employee
    {
        return $employees->first(function (Employee $employee) {
            $departmentName = strtolower(trim((string) ($employee->department?->department ?? '')));
            return str_contains($departmentName, 'hr')
                && $this->employeeHasHeadPosition($employee);
        });
    }

    /**
     * @return array<int, User>
     */
    private function departmentHeads(Collection $employees): array
    {
        $map = [];

        foreach ($employees as $employee) {
            if (!$employee->department_id || !$employee->user) {
                continue;
            }

            if (!$this->employeeHasHeadPosition($employee)) {
                continue;
            }

            $map[(int) $employee->department_id] = $employee->user;
        }

        return $map;
    }

    private function resolveEvaluator(Employee $employee, array $departmentHeadMap, User $fallbackEvaluator): User
    {
        $departmentHead = $departmentHeadMap[(int) $employee->department_id] ?? null;

        if ($departmentHead && (int) $departmentHead->id !== (int) $employee->user_id) {
            return $departmentHead;
        }

        return $fallbackEvaluator;
    }

    private function employeeHasHeadPosition(Employee $employee): bool
    {
        return $employee->positions->contains(function (EmployeePosition $positionLink) {
            $positionName = strtolower(trim((string) ($positionLink->position?->position ?? '')));

            return in_array($positionName, ['head', 'dean'], true);
        });
    }

    /**
     * @return array<int, array{criteria_id:int,score:float,remarks:string}>
     */
    private function buildDetailsForEmployee(Collection $criteria, Employee $employee, int $index, bool $finalizedCycle): array
    {
        $departmentFactor = ((int) $employee->department_id % 3) * 0.1;
        $cycleOffset = $finalizedCycle ? 0.18 : -0.06;

        return $criteria->values()->map(function (SpmsCriterion $criterion, int $criterionIndex) use ($index, $departmentFactor, $cycleOffset) {
            $baseScores = [4.85, 4.42, 3.96, 3.48, 3.12, 2.74];
            $base = $baseScores[($index + $criterionIndex) % count($baseScores)];
            $variation = (($criterionIndex % 2 === 0) ? 0.08 : -0.05) + $departmentFactor + $cycleOffset;
            $score = max(1.0, min(5.0, round($base + $variation, 2)));

            return [
                'criteria_id' => (int) $criterion->id,
                'score' => $score,
                'remarks' => $criterion->category === 'attendance_kpi'
                    ? sprintf('Seeded attendance-aligned SPMS score at %.2f.', $score)
                    : sprintf('Seeded %s assessment score at %.2f.', strtolower((string) $criterion->name), $score),
            ];
        })->all();
    }

    /**
     * @param array<int, array{criteria_id:int,score:float,remarks:string}> $details
     */
    private function syncEvaluationDetails(SpmsEvaluation $evaluation, array $details): void
    {
        foreach ($details as $detail) {
            $evaluation->details()->updateOrCreate(
                ['criteria_id' => (int) $detail['criteria_id']],
                [
                    'score' => (float) $detail['score'],
                    'remarks' => $detail['remarks'],
                ]
            );
        }
    }
}
