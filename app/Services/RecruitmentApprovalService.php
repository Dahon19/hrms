<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\JobPosting;
use App\Models\RecruitmentApproval;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RecruitmentApprovalService
{
    public function __construct(
        private readonly RecruitmentActionService $recruitmentActionService,
        private readonly HrmsNotificationService $notificationService,
    ) {
    }

    public function requiresApproval(User $user): bool
    {
        return AccessControl::isHrStaff($user)
            && !AccessControl::isHrHead($user)
            && !$user->isAdmin();
    }

    public function canReview(User $user): bool
    {
        return $user->isAdmin() || AccessControl::isHrHead($user);
    }

    public function createRequest(
        User $requester,
        string $actionType,
        string $summary,
        array $payload = [],
        ?string $subjectType = null,
        ?int $subjectId = null,
    ): RecruitmentApproval {
        $approval = RecruitmentApproval::create([
            'action_type' => $actionType,
            'status' => RecruitmentApproval::STATUS_PENDING,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'requested_by_user_id' => $requester->id,
            'summary' => $summary,
            'payload' => $payload,
        ]);

        [$routeName, $routeParams] = match ($actionType) {
            RecruitmentApproval::ACTION_APPLICANT_COMPLETE,
            RecruitmentApproval::ACTION_APPLICANT_ACTIVATE,
            RecruitmentApproval::ACTION_APPLICANT_ARCHIVE => ['job-postings.applicants', ['view' => 'active']],
            default => ['job-postings.index', []],
        };

        $this->notificationService->notifyUsers($this->reviewRecipients($requester), [
            'title' => 'Recruitment Approval Requested',
            'message' => $summary,
            'type' => 'warning',
            'module' => 'recruitment',
            'record_id' => $approval->id,
            'route_name' => $routeName,
            'route_params' => $routeParams,
            'event_key' => 'recruitment.approval.requested.' . $approval->id,
            'priority' => 'high',
            ...$this->notificationService->formatSender($requester),
        ]);

        return $approval;
    }

    public function approve(RecruitmentApproval $approval, User $reviewer, ?string $notes = null): RecruitmentApproval
    {
        if (!$approval->isPending()) {
            throw new RuntimeException('Only pending requests can be approved.');
        }

        DB::transaction(function () use ($approval, $reviewer, $notes) {
            $resolvedSubject = $this->applyApprovedAction($approval);

            $approval->forceFill([
                'status' => RecruitmentApproval::STATUS_APPROVED,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
                'subject_type' => $approval->subject_type ?: ($resolvedSubject ? $resolvedSubject::class : null),
                'subject_id' => $approval->subject_id ?: ($resolvedSubject?->getKey()),
            ])->save();
        });

        $approval->refresh();

        if ($approval->requester) {
            $this->notificationService->notifyUsers([$approval->requester], [
                'title' => 'Recruitment Request Approved',
                'message' => $approval->summary,
                'type' => 'success',
                'module' => 'recruitment',
                'record_id' => $approval->id,
                'route_name' => 'job-postings.index',
                'route_params' => [],
                'event_key' => 'recruitment.approval.approved.' . $approval->id,
                'priority' => 'high',
                ...$this->notificationService->formatSender($reviewer),
            ]);
        }

        return $approval;
    }

    public function reject(RecruitmentApproval $approval, User $reviewer, ?string $notes = null): RecruitmentApproval
    {
        if (!$approval->isPending()) {
            throw new RuntimeException('Only pending requests can be rejected.');
        }

        $approval->forceFill([
            'status' => RecruitmentApproval::STATUS_REJECTED,
            'reviewed_by_user_id' => $reviewer->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ])->save();

        if ($approval->requester) {
            $this->notificationService->notifyUsers([$approval->requester], [
                'title' => 'Recruitment Request Rejected',
                'message' => $approval->summary,
                'type' => 'error',
                'module' => 'recruitment',
                'record_id' => $approval->id,
                'route_name' => 'job-postings.index',
                'route_params' => [],
                'event_key' => 'recruitment.approval.rejected.' . $approval->id,
                'priority' => 'high',
                ...$this->notificationService->formatSender($reviewer),
            ]);
        }

        return $approval;
    }

    private function applyApprovedAction(RecruitmentApproval $approval): JobPosting|Applicant|null
    {
        $payload = $approval->payload ?? [];
        $auditMetadata = [
            'recruitment_approval_id' => $approval->id,
            'requested_by_user_id' => $approval->requested_by_user_id,
        ];

        return match ($approval->action_type) {
            RecruitmentApproval::ACTION_JOB_POSTING_CREATE => $this->recruitmentActionService->createJobPosting(
                (array) ($payload['job_posting'] ?? []),
                $auditMetadata
            ),
            RecruitmentApproval::ACTION_JOB_POSTING_UPDATE => $this->recruitmentActionService->updateJobPosting(
                JobPosting::findOrFail((int) ($approval->subject_id ?? 0)),
                (array) ($payload['job_posting'] ?? []),
                $auditMetadata
            ),
            RecruitmentApproval::ACTION_JOB_POSTING_DELETE => tap(
                JobPosting::findOrFail((int) ($approval->subject_id ?? 0)),
                fn (JobPosting $jobPosting) => $this->recruitmentActionService->deleteJobPosting($jobPosting, $auditMetadata)
            ),
            RecruitmentApproval::ACTION_APPLICANT_COMPLETE => tap(
                Applicant::findOrFail((int) ($approval->subject_id ?? 0)),
                fn (Applicant $applicant) => $this->recruitmentActionService->completeApplicant($applicant, $auditMetadata)
            ),
            RecruitmentApproval::ACTION_APPLICANT_ACTIVATE => tap(
                Applicant::findOrFail((int) ($approval->subject_id ?? 0)),
                fn (Applicant $applicant) => $this->recruitmentActionService->activateApplicant($applicant, $auditMetadata)
            ),
            RecruitmentApproval::ACTION_APPLICANT_ARCHIVE => tap(
                Applicant::findOrFail((int) ($approval->subject_id ?? 0)),
                fn (Applicant $applicant) => $this->recruitmentActionService->archiveApplicant($applicant, $auditMetadata)
            ),
            default => throw new RuntimeException('Unsupported recruitment approval action.'),
        };
    }

    /**
     * @return Collection<int, User>
     */
    private function reviewRecipients(User $requester): Collection
    {
        return AccessControl::adminUsers()
            ->merge(AccessControl::hrHeadUsers())
            ->filter(fn (User $user) => (int) $user->id !== (int) $requester->id)
            ->unique('id')
            ->values();
    }
}
