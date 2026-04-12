<?php

namespace App\Http\Controllers;

use App\Http\Requests\Offboarding\StoreOffboardingRequest;
use App\Http\Requests\Offboarding\UpdateClearanceItemRequest;
use App\Models\ClearanceItem;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\LeaveBalance;
use App\Models\OffboardingRecord;
use App\Models\PdsProfile;
use App\Services\OffboardingWorkflowService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use LogicException;
use App\Services\AccessControl;

class OffboardingController extends Controller
{
    public function __construct(
        private readonly OffboardingWorkflowService $workflow,
    ) {
    }

    private function isFinanceParticipant(Request $request): bool
    {
        return $request->user() && AccessControl::isFinanceApprover($request->user());
    }

    private function isDepartmentParticipant(Request $request): bool
    {
        $user = $request->user();

        return $user
            && AccessControl::isHeadOrDean($user)
            && (int) ($user->employee?->department_id ?? 0) > 0
            && !$this->isFinanceParticipant($request)
            && !$user->canManageOffboarding();
    }

    private function isEmployeeMonitor(Request $request): bool
    {
        $user = $request->user();

        return (bool) $user?->employee
            && !$user->canManageOffboarding()
            && !$this->isFinanceParticipant($request)
            && !$this->isDepartmentParticipant($request);
    }

    public function index(Request $request)
    {
        Gate::authorize('viewAny', OffboardingRecord::class);

        $isEmployeeMonitor = $this->isEmployeeMonitor($request);

        $status = trim((string) $request->query('status', ''));
        $search = trim((string) $request->query('search', ''));
        $ownerRole = trim((string) $request->query('owner_role', ''));
        $selectedEmployeeId = (int) $request->query('employee_id', 0);
        $shouldOpenCreateModal = $request->boolean('start') || ($request->session()->getOldInput('employee_id') !== null);

        $availableEmployees = collect();

        if (!Employee::offboardingTablesAvailable()) {
            $records = new LengthAwarePaginator(
                collect(),
                0,
                10,
                LengthAwarePaginator::resolveCurrentPage(),
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );

            return view('offboarding.index', compact('records', 'status', 'search', 'ownerRole', 'availableEmployees', 'selectedEmployeeId', 'shouldOpenCreateModal', 'isEmployeeMonitor'))
                ->with('error', 'Offboarding module is not available until its database migrations are run.');
        }

        $availableEmployees = Employee::query()
            ->with(['department', 'user', 'positions.position'])
            ->whereHas('user', fn ($query) => $query->where('role', '!=', 'admin'))
            ->whereDoesntHave('activeOffboardingRecord')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $records = OffboardingRecord::query()
            ->with(['employee.department', 'employee.user', 'initiatedBy'])
            ->withCount([
                'clearanceItems as pending_items_count' => fn ($query) => $query->where('status', ClearanceItem::STATUS_PENDING),
                'clearanceItems as blocked_items_count' => fn ($query) => $query->where('status', ClearanceItem::STATUS_BLOCKED),
                'clearanceItems as cleared_items_count' => fn ($query) => $query->where('status', ClearanceItem::STATUS_CLEARED),
            ])
            ->when(!$request->user()->canManageOffboarding() && $this->isFinanceParticipant($request), function ($query) {
                $query->whereHas('clearanceItems', fn ($items) => $items->where('owner_role', 'finance'));
            })
            ->when($this->isDepartmentParticipant($request), function ($query) use ($request) {
                $query
                    ->whereHas('clearanceItems', fn ($items) => $items->where('owner_role', 'department_head'));
            })
            ->when($this->isEmployeeMonitor($request), function ($query) use ($request) {
                $query->where('employee_id', (int) ($request->user()?->employee?->id ?? 0));
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($ownerRole !== '', function ($query) use ($ownerRole) {
                $query->whereHas('clearanceItems', fn ($items) => $items->where('owner_role', $ownerRole));
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('employee', function ($employeeQuery) use ($search) {
                    $employeeQuery->where('employee_id', 'like', '%' . $search . '%')
                        ->orWhere('first_name', 'like', '%' . $search . '%')
                        ->orWhere('last_name', 'like', '%' . $search . '%');
                });
            })
            ->latest('created_at')
            ->paginate(10)
            ->withQueryString();

        return view('offboarding.index', compact('records', 'status', 'search', 'ownerRole', 'availableEmployees', 'selectedEmployeeId', 'shouldOpenCreateModal', 'isEmployeeMonitor'));
    }

    public function show(OffboardingRecord $offboarding)
    {
        abort_unless(Employee::offboardingTablesAvailable(), 404);
        $this->authorize('view', $offboarding);

        $ownerRole = trim((string) request()->query('owner_role', ''));
        $itemStatus = trim((string) request()->query('item_status', ''));
        $itemSearch = trim((string) request()->query('item_search', ''));

        $offboarding->load([
            'employee.user',
            'employee.department',
            'employee.positions.position',
            'clearanceItems.clearedBy',
            'initiatedBy',
            'reopenedBy',
            'finalizedBy',
        ]);

        $employee = $offboarding->employee;
        $overview = [
            'documents_count' => $employee ? EmployeeDocument::where('employee_id', $employee->id)->count() : 0,
            'leave_balances_count' => $employee ? LeaveBalance::where('employee_id', $employee->id)->count() : 0,
            'pds_status' => $employee ? (PdsProfile::where('employee_id', $employee->id)->value('status') ?? 'No PDS') : 'No PDS',
        ];

        $filteredItems = $offboarding->clearanceItems
            ->when($ownerRole !== '', fn ($items) => $items->where('owner_role', $ownerRole))
            ->when($itemStatus !== '', fn ($items) => $items->where('status', $itemStatus))
            ->when($itemSearch !== '', function ($items) use ($itemSearch) {
                $needle = mb_strtolower($itemSearch);

                return $items->filter(function ($item) use ($needle) {
                    return str_contains(mb_strtolower($item->item_name), $needle)
                        || str_contains(mb_strtolower($item->unit_name), $needle)
                        || str_contains(mb_strtolower((string) $item->remarks), $needle);
                });
            })
            ->sortBy('display_order')
            ->values();

        $ownerSummaries = $offboarding->clearanceItems
            ->groupBy('owner_role')
            ->map(function ($items, $role) {
                return [
                    'role' => $role,
                    'label' => str_replace('_', ' ', ucfirst((string) $role)),
                    'pending' => $items->where('status', ClearanceItem::STATUS_PENDING)->count(),
                    'blocked' => $items->where('status', ClearanceItem::STATUS_BLOCKED)->count(),
                    'cleared' => $items->where('status', ClearanceItem::STATUS_CLEARED)->count(),
                ];
            })
            ->sortBy('label')
            ->values();

        $stageSummaries = $offboarding->clearanceItems
            ->groupBy(fn ($item) => OffboardingWorkflowService::stageForModuleKey((string) $item->module_key)['key'])
            ->map(function ($items, $stageKey) {
                $stage = OffboardingWorkflowService::stageDefinitions()[$stageKey] ?? OffboardingWorkflowService::stageDefinitions()['hr_finalization'];

                return [
                    'key' => $stageKey,
                    'label' => $stage['label'],
                    'description' => $stage['description'],
                    'pending' => $items->where('status', ClearanceItem::STATUS_PENDING)->count(),
                    'blocked' => $items->where('status', ClearanceItem::STATUS_BLOCKED)->count(),
                    'cleared' => $items->where('status', ClearanceItem::STATUS_CLEARED)->count(),
                    'items_count' => $items->count(),
                    'order' => $stage['order'],
                ];
            })
            ->sortBy('order')
            ->values();

        $groupedItems = $filteredItems
            ->groupBy(fn ($item) => OffboardingWorkflowService::stageForModuleKey((string) $item->module_key)['key'])
            ->map(function ($items, $stageKey) {
                $stage = OffboardingWorkflowService::stageDefinitions()[$stageKey] ?? OffboardingWorkflowService::stageDefinitions()['hr_finalization'];

                return [
                    'key' => $stageKey,
                    'label' => $stage['label'],
                    'description' => $stage['description'],
                    'order' => $stage['order'],
                    'items' => $items->sortBy('display_order')->values(),
                ];
            })
            ->sortBy('order')
            ->values();

        return view('offboarding.show', compact(
            'offboarding',
            'overview',
            'filteredItems',
            'groupedItems',
            'ownerRole',
            'itemStatus',
            'itemSearch',
            'ownerSummaries',
            'stageSummaries',
        ));
    }

    public function store(StoreOffboardingRequest $request): RedirectResponse
    {
        if (!Employee::offboardingTablesAvailable()) {
            return back()->with('error', 'Run the offboarding migrations before starting a clearance workflow.');
        }

        $employee = Employee::with(['user', 'department'])->findOrFail((int) $request->validated('employee_id'));
        $this->authorize('create', [OffboardingRecord::class, $employee]);

        try {
            $record = $this->workflow->initiate($employee, $request->user(), $request->validated());
        } catch (LogicException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('offboarding.show', $record)->with('success', 'Offboarding draft created.');
    }

    public function submit(Request $request, OffboardingRecord $offboarding): RedirectResponse
    {
        abort_unless(Employee::offboardingTablesAvailable(), 404);
        $this->authorize('submit', $offboarding);

        try {
            $this->workflow->submit($offboarding, $request->user());
        } catch (LogicException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Offboarding record sent for review.');
    }

    public function updateItem(UpdateClearanceItemRequest $request, OffboardingRecord $offboarding, ClearanceItem $item)
    {
        abort_unless(Employee::offboardingTablesAvailable(), 404);
        abort_unless((int) $item->offboarding_record_id === (int) $offboarding->id, 404);

        try {
            $updated = $this->workflow->updateClearanceItem($item, $request->user(), $request->validated());
        } catch (LogicException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Clearance item updated.',
                'item' => $updated,
                'record_status' => $updated->offboardingRecord?->fresh()?->status,
            ]);
        }

        return back()->with('success', 'Clearance item updated.');
    }

    public function finalize(Request $request, OffboardingRecord $offboarding): RedirectResponse
    {
        abort_unless(Employee::offboardingTablesAvailable(), 404);
        $this->authorize('finalize', $offboarding);

        try {
            $record = $this->workflow->finalize($offboarding, $request->user());
        } catch (LogicException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        if ($record->employee?->user?->archived_at !== null || strtolower((string) $record->employee?->status) === 'inactive') {
            return back()->with('success', 'Offboarding completed and employee access has been deactivated.');
        }

        $lastWorkingDay = $record->display_last_working_day;
        $dateLabel = $lastWorkingDay ? $lastWorkingDay->format('M j, Y') : 'the recorded last working day';

        return back()->with('success', 'Offboarding completed. Employee access will be deactivated on ' . $dateLabel . '.');
    }

    public function reopen(Request $request, OffboardingRecord $offboarding): RedirectResponse
    {
        abort_unless(Employee::offboardingTablesAvailable(), 404);
        $this->authorize('reopen', $offboarding);

        try {
            $this->workflow->reopen($offboarding, $request->user());
        } catch (LogicException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Offboarding record reopened for clearance review.');
    }

    public function close(Request $request, OffboardingRecord $offboarding): RedirectResponse
    {
        abort_unless(Employee::offboardingTablesAvailable(), 404);
        $this->authorize('close', $offboarding);

        try {
            $this->workflow->close($offboarding, $request->user());
        } catch (LogicException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Offboarding record archived.');
    }

    public function remind(Request $request, OffboardingRecord $offboarding): RedirectResponse
    {
        abort_unless(Employee::offboardingTablesAvailable(), 404);
        $this->authorize('remind', $offboarding);

        try {
            $recipientCount = $this->workflow->remindCurrentStage($offboarding, $request->user());
        } catch (LogicException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Reminder sent to ' . $recipientCount . ' current stage owner' . ($recipientCount === 1 ? '' : 's') . '.');
    }

    public function export(OffboardingRecord $offboarding)
    {
        abort_unless(Employee::offboardingTablesAvailable(), 404);
        $this->authorize('view', $offboarding);

        $offboarding->load([
            'employee.user',
            'employee.department',
            'clearanceItems.clearedBy',
            'initiatedBy',
            'reopenedBy',
            'finalizedBy',
        ]);

        return Pdf::loadView('offboarding.print', compact('offboarding'))
            ->setPaper('a4')
            ->stream('offboarding-clearance-' . $offboarding->id . '.pdf');
    }

    public function requestCancellation(Request $request, OffboardingRecord $offboarding): RedirectResponse
    {
        abort_unless(Employee::offboardingTablesAvailable(), 404);
        $this->authorize('requestCancellation', $offboarding);

        $validated = $request->validate([
            'cancellation_reason' => 'nullable|string|max:2000',
        ]);

        try {
            $this->workflow->requestCancellation($offboarding, $request->user(), $validated['cancellation_reason'] ?? null);
        } catch (LogicException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Resignation cancellation request submitted to HR.');
    }

    public function approveCancellation(Request $request, OffboardingRecord $offboarding): RedirectResponse
    {
        abort_unless(Employee::offboardingTablesAvailable(), 404);
        $this->authorize('reviewCancellation', $offboarding);

        $validated = $request->validate([
            'cancellation_review_notes' => 'nullable|string|max:2000',
        ]);

        try {
            $this->workflow->approveCancellation($offboarding, $request->user(), $validated['cancellation_review_notes'] ?? null);
        } catch (LogicException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Resignation cancellation approved. Offboarding has been closed.');
    }

    public function rejectCancellation(Request $request, OffboardingRecord $offboarding): RedirectResponse
    {
        abort_unless(Employee::offboardingTablesAvailable(), 404);
        $this->authorize('reviewCancellation', $offboarding);

        $validated = $request->validate([
            'cancellation_review_notes' => 'required|string|max:2000',
        ]);

        try {
            $this->workflow->rejectCancellation($offboarding, $request->user(), $validated['cancellation_review_notes'] ?? null);
        } catch (LogicException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Resignation cancellation request declined.');
    }
}


