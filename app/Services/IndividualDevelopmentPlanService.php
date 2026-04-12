<?php

namespace App\Services;

use App\Models\IndividualDevelopmentPlan;
use App\Models\SpmsCycle;
use App\Models\SpmsEvaluation;

class IndividualDevelopmentPlanService
{
    public function __construct(
        private readonly SpmsScoringService $scoringService
    ) {
    }

    public function generateDraftsForLockedCycle(SpmsCycle $cycle, ?int $createdBy = null): int
    {
        $evaluations = SpmsEvaluation::query()
            ->with(['details.criteria'])
            ->where('cycle_id', $cycle->id)
            ->where('status', SpmsEvaluation::STATUS_FINAL)
            ->get();

        $createdCount = 0;

        foreach ($evaluations as $evaluation) {
            $plan = IndividualDevelopmentPlan::query()->firstOrNew([
                'employee_id' => $evaluation->employee_id,
                'spms_cycle_id' => $cycle->id,
            ]);

            if (!$plan->exists) {
                $createdCount++;
            }

            $plan->fill([
                'spms_evaluation_id' => $evaluation->id,
                'status' => $plan->status ?: 'draft',
                'final_spms_score' => (float) $evaluation->total_score,
                'final_spms_rating' => $evaluation->rating_label ?: $this->scoringService->scoreLabel((float) $evaluation->total_score),
                'competency_gaps' => $this->extractCompetencyGaps($evaluation),
                'created_by' => $plan->created_by ?: $createdBy,
            ]);

            $plan->save();
        }

        return $createdCount;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractCompetencyGaps(SpmsEvaluation $evaluation): array
    {
        return $evaluation->details
            ->filter(fn ($detail) => (float) $detail->score < 3.5)
            ->map(fn ($detail) => [
                'criteria_id' => (int) $detail->criteria_id,
                'name' => (string) ($detail->criteria?->name ?? 'Unnamed Criterion'),
                'category' => (string) ($detail->criteria?->category ?? 'general'),
                'score' => (float) $detail->score,
                'weight' => (float) ($detail->criteria?->weight ?? 0),
                'remarks' => $detail->remarks,
            ])
            ->values()
            ->all();
    }
}
