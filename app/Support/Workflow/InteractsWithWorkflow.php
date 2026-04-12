<?php

namespace App\Support\Workflow;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\HrmsNotificationService;
use Illuminate\Support\Collection;

trait InteractsWithWorkflow
{
    protected function logWorkflowAction(
        string $action,
        string $modelClass,
        int|string $recordId,
        array $metadata = [],
        ?int $actorId = null,
    ): void {
        AuditLogger::logSystem($action, $metadata, $actorId, $modelClass, $recordId);
    }

    protected function transitionMetadata(?string $previousStatus, string $newStatus, array $metadata = []): array
    {
        return [
            ...$metadata,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
        ];
    }

    protected function notifyWorkflowUsers(
        HrmsNotificationService $notificationService,
        iterable $users,
        array $payload,
        ?User $actor = null,
    ): void {
        $recipients = Collection::make($users)
            ->filter()
            ->unique('id')
            ->values();

        if ($recipients->isEmpty()) {
            return;
        }

        if ($actor) {
            $payload = [
                ...$notificationService->formatSender($actor),
                ...$payload,
            ];
        }

        $notificationService->notifyUsers($recipients, $payload);
    }
}
