<?php

namespace App\Console\Commands;

use App\Models\EligibilityCache;
use App\Models\Employee;
use App\Services\RewardEligibilityService;
use Illuminate\Console\Command;

class ComputeEligibilityCacheCommand extends Command
{
    protected $signature = 'rewards:compute-eligibility {--year=} {--chunk=100}';

    protected $description = 'Compute and cache Rewards & Recognition eligibility for active employees';

    public function handle(RewardEligibilityService $eligibilityService): int
    {
        $year = (int) ($this->option('year') ?: now()->year);
        $chunkSize = max(10, (int) $this->option('chunk'));
        $processed = 0;

        Employee::query()
            ->where('status', 'active')
            ->whereHas('user', fn ($q) => $q->whereNull('archived_at'))
            ->orderBy('id')
            ->chunkById($chunkSize, function ($employees) use (&$processed, $year, $eligibilityService) {
                foreach ($employees as $employee) {
                    $eligibility = $eligibilityService->buildEligibility($employee, $year);
                    $payload = $eligibilityService->toEligibilityCachePayload($eligibility);

                    EligibilityCache::query()->updateOrCreate(
                        [
                            'employee_id' => $employee->id,
                            'year' => $year,
                        ],
                        $payload
                    );

                    $processed++;
                }
            });

        $this->info("Eligibility cache computed for {$processed} employees ({$year}).");

        return self::SUCCESS;
    }
}

