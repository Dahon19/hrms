<?php

namespace App\Console\Commands;

use App\Services\OffboardingWorkflowService;
use Illuminate\Console\Command;

class DeactivateDueOffboardingAccountsCommand extends Command
{
    protected $signature = 'offboarding:deactivate-due';

    protected $description = 'Deactivate completed offboarding accounts whose last working day has passed.';

    public function handle(OffboardingWorkflowService $workflow): int
    {
        $processed = $workflow->processPendingDeactivations();

        $this->info("Processed {$processed} due offboarding account deactivation(s).");

        return self::SUCCESS;
    }
}
