<?php

namespace App\Console\Commands;

use App\Models\AttendanceMonthlyScore;
use App\Services\AccessControl;
use App\Services\AttendanceKpiScoringService;
use App\Services\HrmsNotificationService;
use Illuminate\Console\Command;

class PrepareAttendanceKpiDraftCommand extends Command
{
    protected $signature = 'attendance:kpi-prepare {--month=} {--year=} {--force}';

    protected $description = 'Prepare monthly attendance KPI draft scores for the selected period.';

    public function __construct(
        private readonly AttendanceKpiScoringService $scoringService,
        private readonly HrmsNotificationService $notificationService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $period = $this->resolvePeriod();
        $month = $period['month'];
        $year = $period['year'];
        $force = (bool) $this->option('force');

        $hasLockedScores = AttendanceMonthlyScore::query()
            ->where('month', $month)
            ->where('year', $year)
            ->where('status', 'locked')
            ->exists();

        if ($hasLockedScores && !$force) {
            $this->info("Attendance KPI draft skipped for {$year}-" . str_pad((string) $month, 2, '0', STR_PAD_LEFT) . ' because the month is already locked.');
            return self::SUCCESS;
        }

        $scores = $this->scoringService->computeMonthlyScores($month, $year, $force);
        $computedCount = AttendanceMonthlyScore::query()
            ->where('month', $month)
            ->where('year', $year)
            ->count();

        $recipients = AccessControl::adminUsers()
            ->merge(AccessControl::hrHeadUsers())
            ->unique('id')
            ->values();

        $this->notificationService->notifyUsers($recipients->all(), [
            'title' => 'Attendance KPI Draft Prepared',
            'message' => 'Monthly attendance KPI draft for ' . now()->create($year, $month, 1)->format('F Y') . ' is ready with ' . $computedCount . ' score row(s).',
            'type' => 'info',
            'module' => 'attendance',
            'record_id' => ($year * 100) + $month,
            'route_name' => 'attendance.kpi.index',
            'route_params' => [
                'month' => $month,
                'year' => $year,
            ],
            'event_key' => 'attendance.kpi.draft_prepared',
            'priority' => 'normal',
            ...$this->notificationService->formatSender(null),
        ]);

        $this->info("Prepared attendance KPI draft for {$year}-" . str_pad((string) $month, 2, '0', STR_PAD_LEFT) . " with {$scores->count()} computed update(s).");

        return self::SUCCESS;
    }

    /**
     * @return array{month:int,year:int}
     */
    private function resolvePeriod(): array
    {
        $monthOption = $this->option('month');
        $yearOption = $this->option('year');

        if ($monthOption !== null || $yearOption !== null) {
            $month = max(1, min(12, (int) ($monthOption ?: now()->subMonth()->month)));
            $year = max(2000, min(2100, (int) ($yearOption ?: now()->subMonth()->year)));

            return [
                'month' => $month,
                'year' => $year,
            ];
        }

        $previousMonth = now()->subMonthNoOverflow();

        return [
            'month' => (int) $previousMonth->month,
            'year' => (int) $previousMonth->year,
        ];
    }
}
