<?php

namespace App\Http\Controllers;

use App\Models\RecruitmentApproval;
use App\Services\RecruitmentApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RecruitmentApprovalController extends Controller
{
    public function __construct(
        private readonly RecruitmentApprovalService $recruitmentApprovalService
    ) {
    }

    private function redirectRouteFor(RecruitmentApproval $approval): array
    {
        return match ($approval->action_type) {
            RecruitmentApproval::ACTION_APPLICANT_COMPLETE,
            RecruitmentApproval::ACTION_APPLICANT_ACTIVATE,
            RecruitmentApproval::ACTION_APPLICANT_ARCHIVE => ['job-postings.applicants', ['view' => 'active']],
            default => ['job-postings.index', []],
        };
    }

    public function approve(Request $request, RecruitmentApproval $approval)
    {
        $user = Auth::user();
        abort_unless($user && $this->recruitmentApprovalService->canReview($user), 403);

        $data = $request->validate([
            'review_notes' => 'nullable|string|max:2000',
        ]);

        $this->recruitmentApprovalService->approve($approval, $user, $data['review_notes'] ?? null);
        [$routeName, $routeParams] = $this->redirectRouteFor($approval);

        return redirect()
            ->route($routeName, $routeParams)
            ->with('success', 'Recruitment request approved and applied.');
    }

    public function reject(Request $request, RecruitmentApproval $approval)
    {
        $user = Auth::user();
        abort_unless($user && $this->recruitmentApprovalService->canReview($user), 403);

        $data = $request->validate([
            'review_notes' => 'nullable|string|max:2000',
        ]);

        $this->recruitmentApprovalService->reject($approval, $user, $data['review_notes'] ?? null);
        [$routeName, $routeParams] = $this->redirectRouteFor($approval);

        return redirect()
            ->route($routeName, $routeParams)
            ->with('success', 'Recruitment request rejected.');
    }
}
