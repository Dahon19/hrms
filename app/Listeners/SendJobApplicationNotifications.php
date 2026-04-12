<?php

namespace App\Listeners;

use App\Events\JobApplicationSubmitted;
use App\Services\AccessControl;
use App\Services\HrmsNotificationService;

class SendJobApplicationNotifications
{
    public function __construct(
        private readonly HrmsNotificationService $notificationService
    ) {
    }

    public function handle(JobApplicationSubmitted $event): void
    {
        $applicant = $event->applicant;
        $jobPosting = $event->jobPosting;

        $recipients = AccessControl::adminUsers()
            ->merge(AccessControl::hrUsers())
            ->unique('id')
            ->values();

        $this->notificationService->notifyUsers($recipients, [
            'title' => 'New Job Application Submitted',
            'message' => trim($applicant->full_name.' applied for '.$jobPosting->title.'.'),
            'type' => 'info',
            'module' => 'recruitment',
            'record_id' => $applicant->id,
            'route_name' => 'job-postings.applicants',
            'route_params' => [],
            'event_key' => 'recruitment.application.submitted',
            'priority' => 'normal',
            ...$this->notificationService->formatSender(null),
        ]);
    }
}
