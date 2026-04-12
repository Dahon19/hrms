<?php

namespace App\Services;

use App\Models\SpmsCriterion;
use App\Models\SpmsCycle;
use App\Models\SpmsEvaluation;
use Illuminate\Support\Collection;

class SpmsScoringService
{
    /**
     * @param array<int, array{criteria_id:int,score:numeric,remarks?:string|null}> $detailsPayload
     */
    public function computeTotalScore(array $detailsPayload): float
    {
        $criteriaIds = collect($detailsPayload)->pluck('criteria_id')->map(fn ($id) => (int) $id)->filter()->values();
        $criteria = SpmsCriterion::query()
            ->whereIn('id', $criteriaIds)
            ->get()
            ->keyBy('id');

        $weightedScoreSum = 0.0;
        $weightSum = 0.0;

        foreach ($detailsPayload as $entry) {
            $criteriaId = (int) ($entry['criteria_id'] ?? 0);
            $score = (float) ($entry['score'] ?? 0);
            /** @var SpmsCriterion|null $criterion */
            $criterion = $criteria->get($criteriaId);
            if (!$criterion) {
                continue;
            }

            $weight = max((float) $criterion->weight, 0.0);
            $boundedScore = min(max($score, 1.0), 5.0);

            $weightedScoreSum += $boundedScore * $weight;
            $weightSum += $weight;
        }

        if ($weightSum <= 0) {
            return 0.0;
        }

        return round($weightedScoreSum / $weightSum, 2);
    }

    public function scoreLabel(float $totalScore): string
    {
        return match (true) {
            $totalScore >= 4.50 => 'outstanding',
            $totalScore >= 3.50 => 'very_satisfactory',
            $totalScore >= 2.50 => 'satisfactory',
            $totalScore >= 1.50 => 'unsatisfactory',
            default => 'poor',
        };
    }

    public function completionRateForCycle(Collection $evaluations, int $employeeCount): float
    {
        if ($employeeCount <= 0) {
            return 0.0;
        }

        $completed = $evaluations->filter(fn ($evaluation) => in_array((string) $evaluation->status, [SpmsEvaluation::STATUS_SUBMITTED, SpmsEvaluation::STATUS_FINAL], true))->count();
        return round(($completed / $employeeCount) * 100, 2);
    }

    public function canBeEditedByEvaluator(SpmsEvaluation $evaluation, int $userId): bool
    {
        return (int) $evaluation->evaluator_id === $userId
            && $evaluation->status === SpmsEvaluation::STATUS_PENDING
            && $evaluation->cycle
            && $evaluation->cycle->status === SpmsCycle::STATUS_EVALUATION;
    }
}
