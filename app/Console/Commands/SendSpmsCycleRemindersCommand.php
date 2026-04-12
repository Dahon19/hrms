<?php

namespace App\Console\Commands;

use App\Models\SpmsCycle;
use App\Models\SpmsEvaluation;
use App\Models\User;
use App\Services\AccessControl;
use App\Services\HrmsNotificationService;
use Illuminate\Console\Command;

class SendSpmsCycleRemindersCommand extends Command
{
    protected $signature = 'spms:send-cycle-reminders';

    protected $description = 'Send SPMS cycle deadline reminders and escalations.';

    public function __construct(
        private readonly HrmsNotificationService $notificationService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $today = now()->startOfDay();
        $count = 0;

        SpmsCycle::query()
            ->where('status', SpmsCycle::STATUS_EVALUATION)
            ->get()
            ->each(function (SpmsCycle $cycle) use ($today, &$count) {
                $daysRemaining = $today->diffInDays($cycle->period_end?->copy()->startOfDay() ?? $today, false);
                if (!in_array($daysRemaining, [7, 3], true) && $daysRemaining >= 0) {
                    return;
                }

                $pendingEvaluations = SpmsEvaluation::query()
                    ->with(['employee', 'evaluator'])
                    ->where('cycle_id', $cycle->id)
                    ->where('status', SpmsEvaluation::STATUS_PENDING)
                    ->get();

                foreach ($pendingEvaluations as $evaluation) {
                    $evaluator = $evaluation->evaluator;
                    if (!$evaluator) {
                        continue;
                    }

                    $type = $daysRemaining === 7 ? 'info' : ($daysRemaining === 3 ? 'warning' : 'danger');
                    $title = $daysRemaining < 0 ? 'SPMS Deadline Escalation' : 'SPMS Evaluation Reminder';
                    $message = $daysRemaining < 0
                        ? 'SPMS cycle ' . $cycle->title . ' is overdue. Pending evaluation for ' . trim(($evaluation->employee?->first_name ?? '') . ' ' . ($evaluation->employee?->last_name ?? '')) . ' requires immediate action.'
                        : 'SPMS cycle ' . $cycle->title . ' ends in ' . $daysRemaining . ' day(s). Please complete the pending evaluation for ' . trim(($evaluation->employee?->first_name ?? '') . ' ' . ($evaluation->employee?->last_name ?? '')) . '.';

                    $recipients = collect([$evaluator]);
                    if ($daysRemaining < 0) {
                        $recipients = $recipients
                            ->merge(AccessControl::adminUsers())
                            ->merge(AccessControl::hrHeadUsers())
                            ->unique('id')
                            ->values();
                    }

                    $this->notificationService->notifyUsers($recipients->all(), [
                        'title' => $title,
                        'message' => $message,
                        'type' => $type,
                        'module' => 'spms',
                        'record_id' => $evaluation->id,
                        'route_name' => 'spms.evaluation.show',
                        'route_params' => [
                            'employee' => $evaluation->employee_id,
                            'cycle' => $evaluation->cycle_id,
                        ],
                        'event_key' => $daysRemaining < 0 ? 'spms.evaluation.escalation' : 'spms.evaluation.reminder',
                        ...$this->notificationService->formatSender($this->systemSender()),
                    ]);
                    $count++;
                }
            });

        $this->info("Sent {$count} SPMS reminder notifications.");

        return self::SUCCESS;
    }

    private function systemSender(): ?User
    {
        return AccessControl::adminUsers()->first() ?: AccessControl::hrHeadUsers()->first();
    }
}
