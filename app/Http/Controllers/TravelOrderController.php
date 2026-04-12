<?php

namespace App\Http\Controllers;

use App\Domain\TravelOrders\Services\TravelOrderWorkflowService;
use App\Http\Requests\TravelOrders\StoreTravelOrderRequest;
use App\Http\Requests\TravelOrders\UpdateTravelOrderRequest;
use App\Models\TravelOrder;
use App\Models\TravelOrderTransportation;
use App\Services\AccessControl;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use LogicException;

class TravelOrderController extends Controller
{
    public function __construct(
        private readonly TravelOrderWorkflowService $workflow,
    ) {
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', TravelOrder::class);

        $user = $request->user();
        $openApprovals = $request->boolean('open_approvals');
        $status = trim((string) $request->query('status', ''));
        $search = trim((string) $request->query('search', ''));
        $showMineOnly = !$user->isAdmin() && !AccessControl::isHrHead($user) && !AccessControl::isHeadOrDean($user);
        $canReview = $user->isAdmin() || AccessControl::isHrHead($user) || AccessControl::isHeadOrDean($user) || AccessControl::isPresidentHead($user);
        $travelOrdersAvailable = TravelOrder::tablesAvailable();
        $transportOptions = TravelOrderTransportation::activeNames();
        $openCreateModal = $request->boolean('open_create') || $request->session()->getOldInput('destination') !== null;
        $prefill = [];

        if (!$travelOrdersAvailable) {
            $travelOrders = new LengthAwarePaginator(
                collect(),
                0,
                10,
                LengthAwarePaginator::resolveCurrentPage(),
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );

            $canCreate = $request->user()->can('create', TravelOrder::class);
            $pending = collect();

            return view('travel-orders.index', compact(
                'travelOrders',
                'pending',
                'status',
                'search',
                'canCreate',
                'canReview',
                'travelOrdersAvailable',
                'transportOptions',
                'openCreateModal',
                'openApprovals',
                'prefill',
            ))
                ->with('error', 'Travel Order module is not available until its database migrations are run.');
        }

        $pending = $this->pendingApprovalsFor($user);
        $pendingIds = $pending->pluck('id')->map(fn ($id) => (int) $id)->all();

        $travelOrders = TravelOrder::query()
            ->with(['employee.department', 'position', 'submittedBy', 'attachments'])
            ->when($showMineOnly, fn ($builder) => $builder->where('employee_id', $user->employee?->id))
            ->when(!$showMineOnly && AccessControl::isHeadOrDean($user) && !AccessControl::isHrHead($user) && !$user->isAdmin(), function ($builder) use ($user) {
                $builder->where(function ($scoped) use ($user) {
                    $scoped->where('employee_id', $user->employee?->id)
                        ->orWhere('department_id', $user->employee?->department_id);
                });
            })
            ->when($openApprovals && $canReview, function ($builder) use ($pendingIds) {
                if ($pendingIds === []) {
                    $builder->whereRaw('1 = 0');

                    return;
                }

                $builder->whereKey($pendingIds);
            })
            ->when($status !== '', fn ($builder) => $builder->where('status', $status))
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($nested) use ($search) {
                    $nested->where('destination', 'like', '%' . $search . '%')
                        ->orWhere('purpose', 'like', '%' . $search . '%')
                        ->orWhereHas('employee', function ($employeeQuery) use ($search) {
                            $employeeQuery->where('employee_id', 'like', '%' . $search . '%')
                                ->orWhere('first_name', 'like', '%' . $search . '%')
                                ->orWhere('last_name', 'like', '%' . $search . '%');
                        });
                });
            })
            ->latest('created_at')
            ->paginate(10)
            ->withQueryString();

        $canCreate = $request->user()->can('create', TravelOrder::class);

        return view('travel-orders.index', compact(
            'travelOrders',
            'pending',
            'status',
            'search',
            'canCreate',
            'canReview',
            'travelOrdersAvailable',
            'transportOptions',
            'openCreateModal',
            'openApprovals',
            'prefill',
        ));
    }

    private function pendingApprovalsFor($user)
    {
        if (!TravelOrder::tablesAvailable()) {
            return collect();
        }

        $pending = collect();
        $baseWith = ['employee.department', 'position', 'submittedBy'];

        if (AccessControl::isHeadOrDean($user) && !AccessControl::isHrHead($user) && !$user->isAdmin()) {
            $pending = $pending->merge(
                TravelOrder::query()
                    ->with($baseWith)
                    ->where('status', TravelOrder::STATUS_SUBMITTED)
                    ->where('department_id', $user->employee?->department_id)
                    ->latest('updated_at')
                    ->get()
            );
        }

        if ($user->isAdmin() || AccessControl::isHrHead($user)) {
            $pending = $pending->merge(
                TravelOrder::query()
                    ->with($baseWith)
                    ->where('status', TravelOrder::STATUS_DEPARTMENT_APPROVED)
                    ->when($user->employee?->id, fn ($query, $employeeId) => $query->where('employee_id', '!=', $employeeId))
                    ->latest('updated_at')
                    ->get()
            );
        }

        if (AccessControl::isPresidentHead($user)) {
            $pending = $pending->merge(
                TravelOrder::query()
                    ->with($baseWith)
                    ->where('status', TravelOrder::STATUS_HR_REVIEW)
                    ->latest('updated_at')
                    ->get()
            );
        }

        return $pending
            ->unique('id')
            ->sortByDesc('updated_at')
            ->values();
    }

    public function create()
    {
        $this->authorize('create', TravelOrder::class);

        return redirect()->route('travel-orders.index', ['open_create' => 1]);
    }

    public function store(StoreTravelOrderRequest $request): RedirectResponse
    {
        $this->authorize('create', TravelOrder::class);

        if (!TravelOrder::tablesAvailable()) {
            return back()->withInput()->with('error', 'Run the travel order migrations before filing requests.');
        }

        $employee = $request->user()->employee()->with(['user', 'department', 'positions.position'])->firstOrFail();

        try {
            $travelOrder = $this->workflow->createDraft($employee, $request->user(), $request->validated());
        } catch (LogicException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('travel-orders.show', $travelOrder)->with('success', 'Travel order draft created.');
    }

    public function show(TravelOrder $travelOrder)
    {
        abort_unless(TravelOrder::tablesAvailable(), 404);
        $this->authorize('view', $travelOrder);

        $travelOrder->load(['employee.department', 'position', 'attachments', 'submittedBy', 'departmentApprovedBy', 'hrReviewedBy', 'finalApprovedBy', 'updatedBy']);
        $transportOptions = TravelOrderTransportation::activeNames();

        return view('travel-orders.show', compact('travelOrder', 'transportOptions'));
    }

    public function update(UpdateTravelOrderRequest $request, TravelOrder $travelOrder): RedirectResponse
    {
        abort_unless(TravelOrder::tablesAvailable(), 404);
        $this->authorize('update', $travelOrder);

        try {
            $travelOrder = $this->workflow->updateDraft($travelOrder, $request->user(), $request->validated());
        } catch (LogicException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('travel-orders.show', $travelOrder)->with('success', 'Travel order draft updated.');
    }

    public function submit(Request $request, TravelOrder $travelOrder): RedirectResponse
    {
        abort_unless(TravelOrder::tablesAvailable(), 404);
        $this->authorize('submit', $travelOrder);

        try {
            $travelOrder = $this->workflow->submit($travelOrder, $request->user());
        } catch (LogicException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        if ($travelOrder->status === TravelOrder::STATUS_DEPARTMENT_APPROVED) {
            return back()->with('success', 'Travel order submitted and forwarded to HR review.');
        }

        if ($travelOrder->status === TravelOrder::STATUS_HR_REVIEW) {
            return back()->with('success', 'Travel order submitted and forwarded for final approval.');
        }

        return back()->with('success', 'Travel order submitted for approval.');
    }

    public function cancel(Request $request, TravelOrder $travelOrder): RedirectResponse
    {
        abort_unless(TravelOrder::tablesAvailable(), 404);
        $this->authorize('cancel', $travelOrder);

        try {
            $this->workflow->cancel($travelOrder, $request->user());
        } catch (LogicException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Travel order cancelled.');
    }

    public function print(TravelOrder $travelOrder)
    {
        abort_unless(TravelOrder::tablesAvailable(), 404);
        $this->authorize('view', $travelOrder);

        $travelOrder->load(['employee.department', 'position', 'submittedBy', 'departmentApprovedBy', 'hrReviewedBy', 'finalApprovedBy']);

        return Pdf::loadView('travel-orders.print', compact('travelOrder'))
            ->setPaper('a4')
            ->stream('travel-order-' . $travelOrder->id . '.pdf');
    }

    public function complete(Request $request, TravelOrder $travelOrder): RedirectResponse
    {
        abort_unless(TravelOrder::tablesAvailable(), 404);
        $this->authorize('complete', $travelOrder);

        try {
            $this->workflow->complete($travelOrder, $request->user());
        } catch (LogicException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Travel order marked completed.');
    }

    public function remindPending(Request $request, TravelOrder $travelOrder): RedirectResponse
    {
        abort_unless(TravelOrder::tablesAvailable(), 404);
        $this->authorize('remind', $travelOrder);

        $count = $this->workflow->remindPendingApprovers($travelOrder, $request->user());

        if ($count === 0) {
            return back()->with('info', 'No pending approver was available to remind for this travel order.');
        }

        return back()->with('success', 'Reminder sent to ' . $count . ' approver(s).');
    }
}
