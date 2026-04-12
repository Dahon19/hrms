<?php

namespace App\Http\Controllers;

use App\Domain\TravelOrders\Services\TravelOrderWorkflowService;
use App\Http\Requests\TravelOrders\TravelOrderDecisionRequest;
use App\Models\TravelOrder;
use App\Services\AccessControl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use LogicException;

class TravelOrderApprovalController extends Controller
{
    public function __construct(
        private readonly TravelOrderWorkflowService $workflow,
    ) {
    }

    public function approvalsIndex(Request $request)
    {
        $user = $request->user();
        abort_unless(
            $user->isAdmin() || AccessControl::isHrHead($user) || AccessControl::isHeadOrDean($user) || AccessControl::isPresidentHead($user),
            403
        );

        return redirect()->route('travel-orders.index', ['open_approvals' => 1]);
    }

    public function departmentApprove(Request $request, TravelOrder $travelOrder): RedirectResponse
    {
        abort_unless(TravelOrder::tablesAvailable(), 404);
        $this->authorize('approveDepartment', $travelOrder);

        try {
            $this->workflow->departmentApprove($travelOrder, $request->user());
        } catch (LogicException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Travel order endorsed by department.');
    }

    public function departmentReject(TravelOrderDecisionRequest $request, TravelOrder $travelOrder): RedirectResponse
    {
        abort_unless(TravelOrder::tablesAvailable(), 404);
        $this->authorize('approveDepartment', $travelOrder);

        try {
            $this->workflow->departmentReject($travelOrder, $request->user(), $request->validated('decision_reason'));
        } catch (LogicException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Travel order rejected by department.');
    }

    public function hrApprove(Request $request, TravelOrder $travelOrder): RedirectResponse
    {
        abort_unless(TravelOrder::tablesAvailable(), 404);
        $this->authorize('approveHr', $travelOrder);

        try {
            $this->workflow->hrApprove($travelOrder, $request->user());
        } catch (LogicException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Travel order endorsed by HR and forwarded for President approval.');
    }

    public function hrReject(TravelOrderDecisionRequest $request, TravelOrder $travelOrder): RedirectResponse
    {
        abort_unless(TravelOrder::tablesAvailable(), 404);
        $this->authorize('approveHr', $travelOrder);

        try {
            $this->workflow->hrReject($travelOrder, $request->user(), $request->validated('decision_reason'));
        } catch (LogicException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Travel order rejected by HR.');
    }

    public function finalApprove(Request $request, TravelOrder $travelOrder): RedirectResponse
    {
        abort_unless(TravelOrder::tablesAvailable(), 404);
        $this->authorize('finalApprove', $travelOrder);

        try {
            $this->workflow->finalApprove($travelOrder, $request->user());
        } catch (LogicException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Travel order finally approved.');
    }

    public function finalReject(TravelOrderDecisionRequest $request, TravelOrder $travelOrder): RedirectResponse
    {
        abort_unless(TravelOrder::tablesAvailable(), 404);
        $this->authorize('finalApprove', $travelOrder);

        try {
            $this->workflow->finalReject($travelOrder, $request->user(), $request->validated('decision_reason'));
        } catch (LogicException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Travel order rejected by final approver.');
    }
}
