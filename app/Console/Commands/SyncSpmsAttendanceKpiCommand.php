<?php

namespace App\Console\Commands;

use App\Domain\Spms\Services\SpmsWorkflowService;
use App\Models\SpmsCycle;
use App\Services\AttendanceKpiScoringService;
use Illuminate\Console\Command;

class SyncSpmsAttendanceKpiCommand extends Command
{
    protected $signature = 'spms:sync-attendance-kpi';

    protected $description = 'Sync attendance KPI scores into active SPMS evaluations.';

    public function __construct(
        private readonly SpmsWorkflowService $workflowService,
        private readonly AttendanceKpiScoringService $attendanceKpiScoringService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = 0;

        SpmsCycle::query()
            ->where('status', SpmsCycle::STATUS_EVALUATION)
            ->get()
            ->each(function (SpmsCycle $cycle) use (&$count) {
                $this->workflowService->syncAttendanceScoresForCycle(
                    $cycle,
                    fn ($employee, $spmsCycle) => $this->attendanceKpiScoringService->getOrComputeEmployeeScore(
                        $employee->id,
                        (int) ($spmsCycle->period_end?->month ?? now()->month),
                        (int) ($spmsCycle->period_end?->year ?? now()->year)
                    )
                );
                $count++;
            });

        $this->info("Synced attendance KPI for {$count} SPMS cycle(s).");

        return self::SUCCESS;
    }
}
