<?php

namespace App\Http\Controllers;

use App\Http\Requests\LeaveApprovalActionRequest;
use App\Jobs\RecomputeLeaveBalanceJob;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\LeaveBalance;
use App\Models\Employee;
use App\Models\User;
use App\Services\AccessControl;
use App\Services\HrmsNotificationService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LeaveRequestController extends Controller
{
    public function __construct(
        private readonly HrmsNotificationService $notificationService
    ) {
    }

    private function isLeaveTypeAllowedForGender(LeaveType $leaveType, ?string $gender): bool
    {
        if (is_null($leaveType->gender)) {
            return true;
        }

        if (!$gender) {
            return false;
        }

        return strtolower($leaveType->gender) === strtolower($gender);
    }

    private function filterLeaveTypesForGender($types, ?string $gender)
    {
        return $types->filter(function (LeaveType $leaveType) use ($gender) {
            return $this->isLeaveTypeAllowedForGender($leaveType, $gender);
        })->values();
    }

    private function excludeAdminEmployees($query)
    {
        return $query->whereHas('employee.user', function ($q) {
            $q->where('role', '!=', 'admin');
        });
    }

    private function ensureLeaveEditingAllowed(Employee $employee): void
    {
        if ($employee->hasActiveOffboardingRecord()) {
            abort(423, 'Leave requests are read-only while the employee is in offboarding.');
        }
    }

    public function index()
    {
        $user = Auth::user();
        if ($user->isAdmin()) {
            abort(403);
        }

        $employee = $user->employee;
        if (!$employee) {
            abort(403);
        }

        $canFileLeave = !$this->isPresidentOfficeApprover($user);
        $leaveLockReason = null;
        if (!$canFileLeave) {
            $leaveLockReason = "President's Office head does not file leave requests and does not have leave balances.";
        }

        if ($canFileLeave && $employee->hasActiveOffboardingRecord()) {
            $canFileLeave = false;
            $leaveLockReason = 'Leave requests are unavailable while the employee is in offboarding.';
        }

        $requests = LeaveRequest::with(['leaveType', 'employee.department'])
            ->where('employee_id', $employee->id)
            ->orderByDesc('created_at')
            ->get();

        $currentYear = (int) now()->year;
        $totalConsumed = LeaveBalance::where('employee_id', $employee->id)
            ->where('year', $currentYear)
            ->sum('consumed');

        $isHead = $this->isHead($user);
        $isHrHead = $this->isHrHead($user);
        $isPresidentApprover = $this->isPresidentOfficeApprover($user);
        $pendingRequests = collect();
        $historyRequests = collect();
        $calendarEvents = [];
        $types = $this->filterLeaveTypesForGender(
            LeaveType::orderBy('name')->get(),
            $user->gender
        );
        $years = [$currentYear, $currentYear + 1];
        $remainingByTypeYear = $this->remainingByTypeYear($employee, $types, $years);

        if (!$canFileLeave) {
            $requests = collect();
            $totalConsumed = 0;
            $types = collect();
            $remainingByTypeYear = [];
        }

        if ($isPresidentApprover) {
            $pendingRequests = LeaveRequest::with(['leaveType', 'employee.department'])
                ->where('status', 'HR Approved')
                ->whereNull('president_reviewed_by')
                ->orderBy('start_date')
                ->get();

            $historyRequests = LeaveRequest::with(['leaveType', 'employee.department'])
                ->whereIn('status', $this->processedLeaveStatuses())
                ->where(function ($query) {
                    $query->where('status', '!=', 'HR Approved')
                        ->orWhereNotNull('president_reviewed_by');
                })
                ->orderByDesc('updated_at')
                ->get();
        } elseif ($isHrHead) {
            $departmentId = $user->employee?->department_id;

            // HR Head acts as BOTH department head (sees Pending from own dept)
            // AND HR approver (sees Approved from all depts).
            $deptPending = LeaveRequest::with(['leaveType', 'employee.department'])
                ->where('status', 'Pending')
                ->whereHas('employee', function ($q) use ($departmentId) {
                    $q->where('department_id', $departmentId);
                })
                ->orderBy('start_date')
                ->get();

            $hrPending = LeaveRequest::with(['leaveType', 'employee.department'])
                ->where('status', 'Approved')
                ->orderBy('start_date');
            $hrPending = $this->excludeAdminEmployees($hrPending)->get();

            $pendingRequests = $deptPending->merge($hrPending)->sortBy('start_date')->values();

            $historyRequests = LeaveRequest::with(['leaveType', 'employee.department'])
                ->whereIn('status', ['HR Approved', 'Needs Revision'])
                ->orderByDesc('updated_at');
            $historyRequests = $this->excludeAdminEmployees($historyRequests)->get();

            $calendarLeaves = LeaveRequest::with(['leaveType', 'employee.department'])
                ->whereIn('status', ['Approved', 'HR Approved'])
                ->orderBy('start_date');
            $calendarLeaves = $this->excludeAdminEmployees($calendarLeaves)->get();

            $calendarEvents = $calendarLeaves->map(function ($leave) {
                $start = $leave->start_date?->toDateString();
                $end = $leave->end_date?->copy()->addDay()->toDateString();
                $employeeName = trim(($leave->employee->first_name ?? '') . ' ' . ($leave->employee->last_name ?? ''));
                $typeName = $leave->leaveType->name ?? 'Leave';
                $color = $leave->leaveType->color_code ?? '#3c8dbc';

                return [
                    'title'           => $employeeName . ' - ' . $typeName,
                    'start'           => $start,
                    'end'             => $end,
                    'backgroundColor' => $color,
                    'borderColor'     => $color,
                    'textColor'       => '#ffffff',
                    'extendedProps'   => [
                        'status'     => $leave->status,
                        'department' => $leave->employee->department->department ?? '-',
                        'type'       => $typeName,
                    ],
                ];
            })->values();
        } elseif ($isHead) {
            $departmentId = $user->employee?->department_id;
            if ($departmentId) {
                $pendingRequests = LeaveRequest::with(['leaveType', 'employee.department'])
                    ->where('status', 'Pending')
                    ->whereHas('employee', function ($query) use ($departmentId) {
                        $query->where('department_id', $departmentId);
                    })
                    ->orderBy('start_date')
                    ->get();

                $historyRequests = LeaveRequest::with(['leaveType', 'employee.department'])
                    ->whereIn('status', $this->departmentHeadHistoryStatuses())
                    ->whereHas('employee', function ($query) use ($departmentId) {
                        $query->where('department_id', $departmentId);
                    })
                    ->orderByDesc('updated_at')
                    ->get();
            }
        }

        return view('leaves.index', compact(
            'employee',
            'requests',
            'totalConsumed',
            'currentYear',
            'pendingRequests',
            'historyRequests',
            'isHrHead',
            'isPresidentApprover',
            'calendarEvents',
            'isHead',
            'types',
            'remainingByTypeYear',
            'canFileLeave',
            'leaveLockReason'
        ));
    }

    public function store(Request $request)
    {
        if (Auth::user()->isAdmin()) {
            abort(403);
        }

        if ($this->isPresidentOfficeApprover(Auth::user())) {
            return redirect()->route('leaves.index')
                ->with('error', "President's Office head does not file leave requests.");
        }

        $employee = Auth::user()->employee;
        if (!$employee) {
            abort(403);
        }
        $this->ensureLeaveEditingAllowed($employee);

        $leaveTypeName = LeaveType::where('id', $request->input('leave_type_id'))->value('name');
        if ($leaveTypeName && !$this->canFileBeforePreviousEnds($employee->id, $leaveTypeName)) {
            return back()->withErrors([
                'start_date' => 'You must finish your current leave before filing another request.',
            ])->withInput();
        }

        $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:2000',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $leaveType = LeaveType::findOrFail($request->leave_type_id);
        if (!$this->isLeaveTypeAllowedForGender($leaveType, $employee->user?->gender)) {
            return back()->withErrors([
                'leave_type_id' => 'Selected leave type is not applicable to your gender.',
            ])->withInput();
        }
        $applicationError = $this->validateApplicationSpan($leaveType, $startDate, Carbon::parse($request->end_date)->startOfDay());
        if ($applicationError) {
            return back()->withErrors(['start_date' => $applicationError])->withInput();
        }
        $days = Carbon::parse($request->start_date)->diffInDays(Carbon::parse($request->end_date)) + 1;

        $year = (int) Carbon::parse($request->start_date)->format('Y');
        $remaining = $this->remainingLeaveBalance($employee->id, $leaveType->id, $year);
        if ($days > $remaining) {
            return back()->withErrors([
                'end_date' => "Requested leave exceeds remaining balance ({$remaining} day(s) available).",
            ])->withInput();
        }

        if ($leaveType->requires_attachment && $days > 2 && !$request->hasFile('attachment')) {
            return back()->withErrors(['attachment' => 'Attachment is required for this leave type.'])->withInput();
        }

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $folder = 'leave_attachments/' . $employee->id;
            $attachmentPath = $request->file('attachment')->store($folder, 'local');
        }

        $user = Auth::user();
        $isDepartmentApprover = $this->isDepartmentApprover($user);
        $isHrHead = $this->isHrHead($user);
        $initialStatus = $isHrHead ? 'HR Approved' : ($isDepartmentApprover ? 'Approved' : 'Pending');
        $leaveData = [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'status' => $initialStatus,
            'reason' => $request->reason,
            'attachment_path' => $attachmentPath,
        ];

        if ($isDepartmentApprover && !$isHrHead && $this->isOwnEmployee($user, $employee)) {
            $leaveData['head_reviewed_by'] = $user->id;
            $leaveData['head_reviewed_at'] = now();
        }

        if ($isHrHead && $this->isOwnEmployee($user, $employee)) {
            $leaveData['head_reviewed_by'] = $user->id;
            $leaveData['head_reviewed_at'] = now();
            $leaveData['hr_reviewed_by'] = $user->id;
            $leaveData['hr_reviewed_at'] = now();
            $leaveData['president_reviewed_by'] = $user->id;
            $leaveData['president_reviewed_at'] = now();
        }

        $leave = LeaveRequest::create($leaveData);

        if ($isHrHead && $this->isOwnEmployee($user, $employee)) {
            $this->consumeLeaveBalance($leave);
        }

        $this->notifyApproversOnSubmit($leave, $user);

        if ($isHrHead && $this->isOwnEmployee($user, $employee)) {
            return redirect()->route('leaves.index')->with('success', 'Leave request submitted and approved.');
        }

        if ($isDepartmentApprover && $this->isOwnEmployee($user, $employee)) {
            return redirect()->route('leaves.index')->with('success', 'Leave request submitted and forwarded to HR review.');
        }

        return redirect()->route('leaves.index')->with('success', 'Leave request submitted.');
    }

    public function update(Request $request, LeaveRequest $leave)
    {
        $this->ensureLeaveEditingAllowed($leave->employee()->firstOrFail());
        if (Auth::user()->isAdmin()) {
            abort(403);
        }

        if ($this->isPresidentOfficeApprover(Auth::user())) {
            return redirect()->route('leaves.index')
                ->with('error', "President's Office head does not file leave requests.");
        }

        $employee = Auth::user()->employee;
        if (!$employee || !$this->canEmployeeModify($leave, $employee)) {
            if ($leave->status === 'Needs Revision') {
                return redirect()->route('leaves.index')
                    ->with('error', 'This leave request was returned for revision. Please file a new leave request.');
            }
            abort(403);
        }

        $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:2000',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $leaveType = LeaveType::findOrFail($request->leave_type_id);
        if (!$this->isLeaveTypeAllowedForGender($leaveType, $employee->user?->gender)) {
            return back()->withErrors([
                'leave_type_id' => 'Selected leave type is not applicable to your gender.',
            ])->withInput();
        }
        $applicationError = $this->validateApplicationSpan($leaveType, $startDate, Carbon::parse($request->end_date)->startOfDay());
        if ($applicationError) {
            return back()->withErrors(['start_date' => $applicationError])->withInput();
        }
        $days = Carbon::parse($request->start_date)->diffInDays(Carbon::parse($request->end_date)) + 1;

        $year = (int) Carbon::parse($request->start_date)->format('Y');
        $remaining = $this->remainingLeaveBalance($employee->id, $leaveType->id, $year);
        if ($days > $remaining) {
            return back()->withErrors([
                'end_date' => "Requested leave exceeds remaining balance ({$remaining} day(s) available).",
            ])->withInput();
        }

        if ($leaveType->requires_attachment && $days > 2 && !$request->hasFile('attachment') && !$leave->attachment_path) {
            return back()->withErrors(['attachment' => 'Attachment is required for this leave type.'])->withInput();
        }

        $attachmentPath = $leave->attachment_path;
        if ($request->hasFile('attachment')) {
            $folder = 'leave_attachments/' . $employee->id;
            $attachmentPath = $request->file('attachment')->store($folder, 'local');
            if ($leave->attachment_path && \Illuminate\Support\Facades\Storage::disk('local')->exists($leave->attachment_path)) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($leave->attachment_path);
            }
        }

        $user = Auth::user();
        $isDepartmentApprover = $this->isDepartmentApprover($user);
        $isHrHead = $this->isHrHead($user);
        $resubmitStatus = $isHrHead ? 'HR Approved' : ($isDepartmentApprover ? 'Approved' : 'Pending');
        $updateData = [
            'leave_type_id' => $leaveType->id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'status' => $resubmitStatus,
            'reason' => $request->reason,
            'attachment_path' => $attachmentPath,
            'notes' => null,
            'head_reviewed_by' => null,
            'head_reviewed_at' => null,
            'president_reviewed_by' => null,
            'president_reviewed_at' => null,
            'hr_reviewed_by' => null,
            'hr_reviewed_at' => null,
        ];

        if ($isDepartmentApprover && !$isHrHead && $this->isOwnEmployee($user, $employee)) {
            $updateData['head_reviewed_by'] = $user->id;
            $updateData['head_reviewed_at'] = now();
        }

        if ($isHrHead && $this->isOwnEmployee($user, $employee)) {
            $updateData['head_reviewed_by'] = $user->id;
            $updateData['head_reviewed_at'] = now();
            $updateData['hr_reviewed_by'] = $user->id;
            $updateData['hr_reviewed_at'] = now();
            $updateData['president_reviewed_by'] = $user->id;
            $updateData['president_reviewed_at'] = now();
        }

        $leave->update($updateData);

        if ($isHrHead && $this->isOwnEmployee($user, $employee)) {
            $this->consumeLeaveBalance($leave->fresh());
        }

        $this->notifyApproversOnSubmit($leave, $user);

        if ($isHrHead && $this->isOwnEmployee($user, $employee)) {
            return redirect()->route('leaves.index')->with('success', 'Leave request updated and approved.');
        }

        if ($isDepartmentApprover && $this->isOwnEmployee($user, $employee)) {
            return redirect()->route('leaves.index')->with('success', 'Leave request updated and forwarded to HR review.');
        }

        return redirect()->route('leaves.index')->with('success', 'Leave request resubmitted.');
    }

    public function cancel(LeaveRequest $leave)
    {
        if (Auth::user()->isAdmin()) {
            abort(403);
        }

        if ($this->isPresidentOfficeApprover(Auth::user())) {
            return redirect()->route('leaves.index')
                ->with('error', "President's Office head does not file leave requests.");
        }

        $employee = Auth::user()->employee;
        if (!$employee || $leave->employee_id !== $employee->id || !$this->canEmployeeCancel($leave)) {
            abort(403);
        }
        $this->ensureLeaveEditingAllowed($employee);

        $leave->update([
            'status' => 'Declined',
            'notes' => 'Cancelled by employee.',
        ]);

        $this->notifyEmployeeStatus($leave, 'Leave Request: Updated');

        return redirect()->route('leaves.index')->with('success', 'Leave request canceled.');
    }

    public function approvalsIndex()
    {
        $user = Auth::user();
        $isHead = $this->isHead($user);
        $isHrHead = $this->isHrHead($user);
        $isAdmin = $user->isAdmin();
        $isPresidentApprover = $this->isPresidentOfficeApprover($user);
        $calendarEvents = [];
        if (!$isHead && !$isHrHead && !$isPresidentApprover && !$isAdmin) {
            abort(403);
        }

        $departmentId = $user->employee?->department_id;
        $isHrApprover = $isHrHead || $isAdmin;

        if ($isPresidentApprover) {
            $pendingRequests = LeaveRequest::with(['leaveType', 'employee.department'])
                ->where('status', 'HR Approved')
                ->whereNull('president_reviewed_by')
                ->orderBy('start_date')
                ->get();

            $historyRequests = LeaveRequest::with(['leaveType', 'employee.department'])
                ->whereIn('status', $this->processedLeaveStatuses())
                ->where(function ($query) {
                    $query->where('status', '!=', 'HR Approved')
                        ->orWhereNotNull('president_reviewed_by');
                })
                ->orderByDesc('updated_at')
                ->get();
        } elseif ($isHrApprover) {
            $departmentId = $user->employee?->department_id;

            // HR Head: also show their dept's Pending requests (as dept head)
            $deptPending = LeaveRequest::with(['leaveType', 'employee.department'])
                ->where('status', 'Pending')
                ->whereHas('employee', function ($q) use ($departmentId) {
                    $q->where('department_id', $departmentId);
                })
                ->orderBy('start_date')
                ->get();

            $hrPending = LeaveRequest::with(['leaveType', 'employee.department'])
                ->where('status', 'Approved')
                ->orderBy('start_date')
                ->get();

            $pendingRequests = $deptPending->merge($hrPending)->sortBy('start_date')->values();

            $historyRequests = LeaveRequest::with(['leaveType', 'employee.department'])
                ->whereIn('status', ['HR Approved', 'Needs Revision'])
                ->orderByDesc('updated_at')
                ->get();

            $calendarLeaves = LeaveRequest::with(['leaveType', 'employee.department'])
                ->whereIn('status', ['Approved', 'HR Approved'])
                ->orderBy('start_date')
                ->get();

            $calendarEvents = $calendarLeaves->map(function ($leave) {
                $start = $leave->start_date?->toDateString();
                $end = $leave->end_date?->copy()->addDay()->toDateString();
                $employeeName = trim(($leave->employee->first_name ?? '') . ' ' . ($leave->employee->last_name ?? ''));
                $typeName = $leave->leaveType->name ?? 'Leave';
                $color = $leave->leaveType->color_code ?? '#3c8dbc';

                return [
                    'title'           => $employeeName . ' - ' . $typeName,
                    'start'           => $start,
                    'end'             => $end,
                    'backgroundColor' => $color,
                    'borderColor'     => $color,
                    'textColor'       => '#ffffff',
                    'extendedProps'   => [
                        'status'     => $leave->status,
                        'department' => $leave->employee->department->department ?? '-',
                        'type'       => $typeName,
                    ],
                ];
            })->values();
        } else {
            $pendingRequests = LeaveRequest::with(['leaveType', 'employee.department'])
                ->where('status', 'Pending')
                ->whereHas('employee', function ($query) use ($departmentId) {
                    $query->where('department_id', $departmentId);
                })
                ->orderBy('start_date')
                ->get();

            $historyRequests = LeaveRequest::with(['leaveType', 'employee.department'])
                ->whereIn('status', $this->departmentHeadHistoryStatuses())
                ->whereHas('employee', function ($query) use ($departmentId) {
                    $query->where('department_id', $departmentId);
                })
                ->orderByDesc('updated_at')
                ->get();
        }

        $isHrHead = $isHrApprover;

        return view('leaves.approvals', compact('pendingRequests', 'historyRequests', 'isHrHead', 'calendarEvents', 'isPresidentApprover'));
    }

    public function headApprove(LeaveApprovalActionRequest $request, LeaveRequest $leave)
    {
        $user = Auth::user();
        if (!$this->isHead($user)) {
            abort(403);
        }

        if ($leave->status !== 'Pending') {
            return back()->with('error', 'Only pending requests can be approved.');
        }

        if ($leave->employee?->department_id !== $user->employee?->department_id) {
            abort(403);
        }

        $leave->update([
            'status' => 'Approved',
            'head_reviewed_by' => $user->id,
            'head_reviewed_at' => now(),
            'notes' => $request->input('notes'),
        ]);

        $this->notifyEmployeeStatus($leave, 'Leave Request: Updated');
        $this->notifyHrHeads($leave, $user, route('leaves.approvals', [], false));

        return back()->with('success', 'Request approved and sent to HR.');
    }

    public function headDecline(LeaveApprovalActionRequest $request, LeaveRequest $leave)
    {
        $user = Auth::user();
        if (!$this->isHead($user)) {
            abort(403);
        }

        if ($leave->status !== 'Pending') {
            return back()->with('error', 'Only pending requests can be updated.');
        }

        if ($leave->employee?->department_id !== $user->employee?->department_id) {
            abort(403);
        }

        $status = 'Needs Revision';
        $notes = trim((string) $request->input('notes', ''));
        $suggestedStart = $request->input('suggested_start_date');
        $suggestedEnd = $request->input('suggested_end_date');

        $suggestedText = '';
        if ($suggestedStart || $suggestedEnd) {
            $suggestedText = 'Suggested dates: ' . ($suggestedStart ?: '-') . ' to ' . ($suggestedEnd ?: '-');
        }

        $sourceTag = 'From Head';
        $timestampTag = 'Reviewed at: ' . now()->format('Y-m-d H:i');
        $combinedNotes = trim(implode(' | ', array_filter([$sourceTag, $suggestedText, $timestampTag, $notes])));

        $leave->update([
            'status' => $status,
            'head_reviewed_by' => $user->id,
            'head_reviewed_at' => now(),
            'notes' => $combinedNotes !== '' ? $combinedNotes : null,
        ]);

        $this->notifyEmployeeStatus($leave, 'Leave Request: Updated');

        return back()->with('success', 'Request updated.');
    }

    public function hrApprove(LeaveApprovalActionRequest $request, LeaveRequest $leave)
    {
        if (!$this->canAccessHrActions(Auth::user())) {
            abort(403);
        }

        if ($leave->status !== 'Approved') {
            return back()->with('error', 'Only approved requests can be processed.');
        }

        $conflict = $this->hasConflict($leave);
        if ($conflict) {
            return back()->with('error', 'Conflict detected for department type. Please review.');
        }

        $leave->update([
            'status' => 'HR Approved',
            'hr_reviewed_by' => Auth::id(),
            'hr_reviewed_at' => now(),
            'notes' => $request->input('notes'),
        ]);

        $this->consumeLeaveBalance($leave->fresh());

        // Recompute balance in background to correct any rounding drift
        RecomputeLeaveBalanceJob::dispatch(
            $leave->employee_id,
            (int) $leave->start_date->format('Y')
        )->delay(now()->addSeconds(5));

        $this->notifyEmployeeStatus($leave, 'Leave Request: Updated');
        $this->notifyPresidentHeads($leave, Auth::user(), route('leaves.approvals', [], false));

        return back()->with('success', 'Leave approved by HR and sent to President.');
    }

    public function hrDecline(LeaveApprovalActionRequest $request, LeaveRequest $leave)
    {
        if (!$this->canAccessHrActions(Auth::user())) {
            abort(403);
        }

        if ($leave->status !== 'Approved') {
            return back()->with('error', 'Only approved requests can be processed.');
        }

        $notes = trim((string) $request->input('notes', ''));
        $suggestedStart = $request->input('suggested_start_date');
        $suggestedEnd = $request->input('suggested_end_date');

        $suggestedText = '';
        if ($suggestedStart || $suggestedEnd) {
            $suggestedText = 'Suggested dates: ' . ($suggestedStart ?: '-') . ' to ' . ($suggestedEnd ?: '-');
        }

        $sourceTag = 'From HR';
        $timestampTag = 'Reviewed at: ' . now()->format('Y-m-d H:i');
        $combinedNotes = trim(implode(' | ', array_filter([$sourceTag, $suggestedText, $timestampTag, $notes])));

        $leave->update([
            'status' => 'Needs Revision',
            'hr_reviewed_by' => Auth::id(),
            'hr_reviewed_at' => now(),
            'notes' => $combinedNotes !== '' ? $combinedNotes : null,
        ]);

        $this->notifyEmployeeStatus($leave, 'Leave Request: Updated');

        return back()->with('success', 'Leave request returned for revision.');
    }

    public function presidentApprove(LeaveApprovalActionRequest $request, LeaveRequest $leave)
    {
        $user = Auth::user();
        if (!$this->isPresidentOfficeApprover($user)) {
            abort(403);
        }

        if ($leave->status !== 'HR Approved') {
            return back()->with('error', 'Only pending president requests can be approved.');
        }

        $year = (int) $leave->start_date->format('Y');
        $days = $leave->start_date->diffInDays($leave->end_date) + 1;
        $remaining = $this->remainingLeaveBalance($leave->employee_id, $leave->leave_type_id, $year);
        if ($days > $remaining) {
            return back()->with('error', "Insufficient leave balance ({$remaining} day(s) available).");
        }

        $updated = LeaveRequest::query()
            ->whereKey($leave->id)
            ->where('status', 'HR Approved')
            ->whereNull('president_reviewed_by')
            ->update([
            'status' => 'HR Approved',
            'president_reviewed_by' => $user->id,
            'president_reviewed_at' => now(),
            'notes' => $request->input('notes'),
        ]);

        if ($updated === 0) {
            return back()->with('error', 'This request has already been reviewed by President.');
        }

        $leave->refresh();
        $this->notifyEmployeeStatus($leave, 'Leave Request: Updated');

        return back()->with('success', 'Request approved by President.');
    }

    public function presidentDecline(LeaveApprovalActionRequest $request, LeaveRequest $leave)
    {
        $user = Auth::user();
        if (!$this->isPresidentOfficeApprover($user)) {
            abort(403);
        }

        if ($leave->status !== 'HR Approved') {
            return back()->with('error', 'Only pending president requests can be updated.');
        }

        $status = $request->input('status') === 'Needs Revision' ? 'Needs Revision' : 'Declined';
        $notes = trim((string) $request->input('notes', ''));
        $sourceTag = 'From President';
        $decisionTag = 'Decision: ' . $status;
        $timestampTag = 'Reviewed at: ' . now()->format('Y-m-d H:i');
        $combinedNotes = trim(implode(' | ', array_filter([$sourceTag, $decisionTag, $timestampTag, $notes])));

        $updated = LeaveRequest::query()
            ->whereKey($leave->id)
            ->where('status', 'HR Approved')
            ->whereNull('president_reviewed_by')
            ->update([
            'status' => $status,
            'president_reviewed_by' => $user->id,
            'president_reviewed_at' => now(),
            'notes' => $combinedNotes !== '' ? $combinedNotes : null,
        ]);

        if ($updated === 0) {
            return back()->with('error', 'This request has already been reviewed by President.');
        }

        $leave->refresh();
        $this->notifyEmployeeStatus($leave, 'Leave Request: Updated');

        return back()->with('success', 'Request updated.');
    }

    /**
     * Return teammates with overlapping approved leave for conflict warning.
     * Used client-side when filing a leave request.
     */
    public function teamConflicts(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();
        $employee = $user?->employee;
        if (! $employee) {
            return response()->json(['conflicts' => []]);
        }

        $start = $request->query('start');
        $end   = $request->query('end', $start);

        if (! $start) {
            return response()->json(['conflicts' => []]);
        }

        $conflicts = LeaveRequest::query()
            ->with('employee')
            ->whereIn('status', ['Approved', 'HR Approved'])
            ->whereHas('employee', fn ($q) => $q->where('department_id', $employee->department_id)
                ->where('id', '!=', $employee->id))
            ->where('start_date', '<=', $end)
            ->where('end_date', '>=', $start)
            ->get()
            ->map(fn ($l) => [
                'name'  => trim(($l->employee?->first_name ?? '') . ' ' . ($l->employee?->last_name ?? '')),
                'start' => optional($l->start_date)->toDateString(),
                'end'   => optional($l->end_date)->toDateString(),
            ]);

        return response()->json(['conflicts' => $conflicts]);
    }

    private function hasConflict(LeaveRequest $leave): bool
    {
        $departmentType = $leave->employee?->department?->department_type;
        if (!$departmentType) {
            return false;
        }

        $maxOffPerType = 2;
        $period = CarbonPeriod::create($leave->start_date, $leave->end_date);

        foreach ($period as $date) {
            $count = LeaveRequest::where('status', 'HR Approved')
                ->where('id', '!=', $leave->id)
                ->whereDate('start_date', '<=', $date)
                ->whereDate('end_date', '>=', $date)
                ->whereHas('employee.department', function ($query) use ($departmentType) {
                    $query->where('department_type', $departmentType);
                })
                ->count();

            if ($count >= $maxOffPerType) {
                return true;
            }
        }

        return false;
    }

    private function consumeLeaveBalance(LeaveRequest $leave): void
    {
        $year  = (int) $leave->start_date->format('Y');
        $days  = $leave->start_date->diffInDays($leave->end_date) + 1;
        $leaveType = LeaveType::find($leave->leave_type_id);
        $maxDays   = $leaveType ? $leaveType->maxDaysForYear($year) : 0;

        $balance = LeaveBalance::firstOrCreate(
            [
                'employee_id'   => $leave->employee_id,
                'leave_type_id' => $leave->leave_type_id,
                'year'          => $year,
            ],
            [
                'earned'   => $maxDays,
                'consumed' => 0,
            ]
        );

        $balance->increment('consumed', $days);
    }

    private function remainingLeaveBalance(int $employeeId, int $leaveTypeId, int $year): float
    {
        $employee = Employee::find($employeeId);
        if ($employee && !LeaveBalance::isEmployeeEligibleForLeave($employee, $year)) {
            return 0;
        }

        $leaveType = LeaveType::find($leaveTypeId);
        if (!$leaveType) {
            return 0;
        }

        if (!is_null($leaveType->max_days)) {
            $maxDays = (int) $leaveType->max_days;
        } else {
            $maxDays = $employee
                ? (int) LeaveBalance::calculateEarnedForEmployeeYear($employee, $year)
                : (int) LeaveBalance::calculateEarnedForYear($year);
        }

        $consumed = LeaveBalance::where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('year', $year)
            ->sum('consumed');

        return max($maxDays - $consumed, 0);
    }

    private function processedLeaveStatuses(): array
    {
        return ['Approved', 'Needs Revision', 'Declined', 'HR Approved', 'HR Declined'];
    }

    private function departmentHeadHistoryStatuses(): array
    {
        return ['Needs Revision', 'HR Approved', 'HR Declined'];
    }

    private function remainingByTypeYear(Employee $employee, $types, array $years): array
    {
        // Fetch all consumed per (leave_type_id, year) for this employee in one query
        $consumed = LeaveBalance::where('employee_id', $employee->id)
            ->whereIn('year', $years)
            ->get()
            ->groupBy('leave_type_id');

        $remainingByTypeYear = [];
        foreach ($years as $year) {
            foreach ($types as $type) {
                if (!LeaveBalance::isEmployeeEligibleForLeave($employee, $year)) {
                    $maxDays = 0;
                } elseif (!is_null($type->max_days)) {
                    $maxDays = (int) $type->max_days;
                } else {
                    $maxDays = (int) LeaveBalance::calculateEarnedForEmployeeYear($employee, $year);
                }
                $typeRows   = $consumed->get($type->id, collect());
                $typeConsumed = $typeRows->where('year', $year)->sum('consumed');
                $remainingByTypeYear[$year][$type->id] = max($maxDays - $typeConsumed, 0);
            }
        }

        return $remainingByTypeYear;
    }

    private function validateApplicationSpan(LeaveType $leaveType, Carbon $startDate, ?Carbon $endDate = null): ?string
    {
        $rules = $this->applicationSpanRules($leaveType->name ?? '');
        if ($rules['mode'] === 'anytime') {
            return null;
        }

        $today = Carbon::today();
        $daysBefore = $today->diffInDays($startDate, false);

        if ($rules['mode'] === 'on_return') {
            $endDate = $endDate?->copy()->startOfDay() ?? $startDate->copy()->startOfDay();

            if ($daysBefore > 0 || $endDate->gt($today)) {
                return $rules['message'];
            }
            return null;
        }

        if ($rules['min'] !== null && $daysBefore < $rules['min']) {
            return $rules['message'];
        }

        if ($rules['max'] !== null && $daysBefore > $rules['max']) {
            return $rules['message'];
        }

        return null;
    }

    private function applicationSpanRules(string $typeName): array
    {
        $normalized = strtolower(trim($typeName));
        $normalized = preg_replace('/[^a-z0-9 ]/i', '', $normalized);
        $normalized = trim(preg_replace('/\s+/', ' ', $normalized));

        $anytime = [
            'mode' => 'anytime',
            'min' => null,
            'max' => null,
            'message' => '',
        ];

        $onReturn = function (string $message) {
            return [
                'mode' => 'on_return',
                'min' => null,
                'max' => null,
                'message' => $message,
            ];
        };

        $advance = function (?int $min, ?int $max, string $message) {
            return [
                'mode' => 'advance',
                'min' => $min,
                'max' => $max,
                'message' => $message,
            ];
        };

        return match ($normalized) {
            'vacation leave' => $advance(5, 15, 'Vacation Leave must be filed 5 to 15 days before the start date.'),
            'sick leave' => $onReturn('Sick Leave must be filed immediately upon return (no advance filing).'),
            'service credit' => $advance(5, null, 'Service Credit must be filed at least 5 days before the start date.'),
            'study leave' => $advance(30, 90, 'Study Leave must be filed 1 to 3 months before the start date.'),
            'sabbatical leave' => $advance(180, null, 'Sabbatical Leave must be filed at least 6 months before the start date.'),
            'maternity leave' => $advance(30, null, 'Maternity Leave must be filed at least 30 days before the start date.'),
            'paternity leave' => $anytime,
            'solo parent leave' => $onReturn('Solo Parent Leave may be filed upon return in emergency cases.'),
            'bereavement leave' => $onReturn('Bereavement Leave must be filed upon return (no advance filing).'),
            'magna carta for women' => $onReturn('Magna Carta for Women leave may be filed upon return in emergency cases.'),
            'vawc leave' => $onReturn('VAWC Leave may be filed upon return in emergency cases.'),
            'official business' => $advance(3, null, 'Official Business must be filed at least 3 days before the start date.'),
            'emergency leave' => $onReturn('Emergency Leave must be filed upon return (no advance filing).'),
            'birthday leave' => $advance(3, null, 'Birthday Leave must be filed at least 3 days before the start date.'),
            default => $advance(7, null, 'Leave requests must be filed at least 7 days before the start date.'),
        };
    }

    private function isHead($user): bool
    {
        return $user->positionName() === 'head';
    }

    private function isDepartmentApprover($user): bool
    {
        return $user instanceof User && AccessControl::isHeadOrDean($user);
    }

    private function isPresidentOfficeApprover($user): bool
    {
        if (!$this->isHead($user)) {
            return false;
        }

        $departmentName = strtolower(trim($user->employee?->department?->department ?? ''));
        if ($departmentName === '') {
            return false;
        }

        $normalized = preg_replace('/[^a-z0-9 ]/i', '', $departmentName);
        $normalized = trim(preg_replace('/\s+/', ' ', $normalized));

        return $normalized === 'presidents office';
    }

    private function isHrHead($user): bool
    {
        if (!$this->isHead($user)) {
            return false;
        }

        $departmentName = strtolower(trim($user->employee?->department?->department ?? ''));
        if ($departmentName === '') {
            return false;
        }

        $normalized = preg_replace('/[^a-z0-9 ]/i', '', $departmentName);
        $normalized = trim(preg_replace('/\s+/', ' ', $normalized));

        return $normalized === 'hr department';
    }

    private function canAccessHrActions($user): bool
    {
        return $user && ($user->isAdmin() || $this->isHrHead($user));
    }

    private function canEmployeeModify(LeaveRequest $leave, Employee $employee): bool
    {
        if ($leave->employee_id !== $employee->id) {
            return false;
        }

        if ($leave->status === 'Pending') {
            return $leave->head_reviewed_by === null
                && $leave->hr_reviewed_by === null
                && $leave->president_reviewed_by === null;
        }

        return false;
    }

    private function canEmployeeCancel(LeaveRequest $leave): bool
    {
        if ($leave->status !== 'Pending') {
            return false;
        }

        return $leave->head_reviewed_by === null
            && $leave->hr_reviewed_by === null
            && $leave->president_reviewed_by === null;
    }

    private function canFileBeforePreviousEnds(int $employeeId, string $leaveTypeName): bool
    {
        $normalized = strtolower(trim($leaveTypeName));
        $normalized = preg_replace('/[^a-z0-9 ]/i', '', $normalized);
        $normalized = trim(preg_replace('/\s+/', ' ', $normalized));

        $exceptions = [
            'sick leave',
            'emergency leave',
            'bereavement leave',
            'vawc leave',
            'solo parent leave',
            'paternity leave',
            'magna carta for women',
        ];

        if (in_array($normalized, $exceptions, true)) {
            return true;
        }

        $hasActiveLeave = LeaveRequest::where('employee_id', $employeeId)
            ->whereDate('end_date', '>=', now()->toDateString())
            ->whereIn('status', ['Pending', 'Approved', 'HR Approved', 'Needs Revision'])
            ->exists();

        return !$hasActiveLeave;
    }

    private function notifyApproversOnSubmit(LeaveRequest $leave, $actor): void
    {
        $employee = $leave->employee;
        if (!$employee) {
            return;
        }

        if ($actor instanceof User && $this->isHrHead($actor) && $this->isOwnEmployee($actor, $employee)) {
            return;
        }

        $actionUrl = route('leaves.approvals');
        $employeeName = trim(($employee->first_name ?? '').' '.($employee->last_name ?? ''));
        $leaveType = $leave->leaveType?->name ?? 'Leave';

        $approvers = collect();
        if (!$this->isDepartmentApprover($actor)) {
            $approvers = $approvers->merge(AccessControl::headApproversForDepartment($employee->department_id));
        }

        if ($this->isDepartmentApprover($actor) && !$this->isHrHead($actor)) {
            $approvers = $approvers->merge(AccessControl::hrHeadUsers());
        }

        if ($this->isHrHead($actor)) {
            $approvers = $approvers->merge(AccessControl::presidentHeadUsers());
        }

        if ($approvers->isEmpty()) {
            $approvers = AccessControl::adminUsers();
        }

        $this->notifyApprovers($approvers->unique('id')->values(), $actor, $leave, $actionUrl);

        $this->notifyAdmins(
            $leave,
            $employeeName.' submitted '.$leaveType.' from '
            .$leave->start_date?->toDateString().' to '.$leave->end_date?->toDateString().'.',
            $actionUrl
        );

    }

    private function notifyEmployeeStatus(LeaveRequest $leave, string $message): void
    {
        $employeeUser = $leave->employee?->user;
        if (!$employeeUser) {
            return;
        }

        $status = strtolower((string) $leave->status);
        $type = in_array($status, ['declined', 'hr declined', 'needs revision'], true) ? 'warning' : 'success';
        $this->notificationService->notifyUsers([$employeeUser], [
            'title' => $message,
            'message' => 'Your leave request is now marked as '.$leave->status.'.',
            'type' => $type,
            'module' => 'leave',
            'record_id' => $leave->id,
            'route_name' => 'leaves.index',
            'route_params' => [],
            'event_key' => 'leave.request.status.updated',
            'priority' => $type === 'success' ? 'normal' : 'high',
            ...$this->notificationService->formatSender(Auth::user()),
        ]);
    }

    private function notifyHrHeads(LeaveRequest $leave, $actor, ?string $actionUrl = null): void
    {
        $actionUrl = $actionUrl ?: route('leaves.approvals');
        $employeeName = trim(($leave->employee?->first_name ?? '').' '.($leave->employee?->last_name ?? ''));
        $leaveType = $leave->leaveType?->name ?? 'Leave';

        $this->notificationService->notifyUsers(AccessControl::hrHeadUsers(), [
            'title' => 'Approval Request Pending',
            'message' => 'Head-approved '.$leaveType.' for '.$employeeName.' is pending HR review.',
            'type' => 'info',
            'module' => 'leave',
            'record_id' => $leave->id,
            'route_name' => 'leaves.approvals',
            'route_params' => [],
            'event_key' => 'leave.request.hr.pending',
            'priority' => 'high',
            ...$this->notificationService->formatSender($actor instanceof User ? $actor : null),
        ]);
    }

    private function isOwnEmployee($user, ?Employee $employee): bool
    {
        return $user && $employee && (int) optional($user->employee)->id === (int) $employee->id;
    }

    private function notifyPresidentHeads(LeaveRequest $leave, $actor, ?string $actionUrl = null): void
    {
        $actionUrl = $actionUrl ?: route('leaves.approvals');
        $employeeName = trim(($leave->employee?->first_name ?? '').' '.($leave->employee?->last_name ?? ''));
        $leaveType = $leave->leaveType?->name ?? 'Leave';

        $this->notificationService->notifyUsers(AccessControl::presidentHeadUsers(), [
            'title' => 'Approval Request Pending',
            'message' => 'HR-approved '.$leaveType.' for '.$employeeName.' is pending President review.',
            'type' => 'info',
            'module' => 'leave',
            'record_id' => $leave->id,
            'route_name' => 'leaves.approvals',
            'route_params' => [],
            'event_key' => 'leave.request.president.pending',
            'priority' => 'high',
            ...$this->notificationService->formatSender($actor instanceof User ? $actor : null),
        ]);
    }

    private function notifyApprovers($users, $actor, LeaveRequest $leave, ?string $actionUrl = null): void
    {
        $actionUrl = $actionUrl ?: route('leaves.approvals');
        $employeeName = trim(($leave->employee?->first_name ?? '').' '.($leave->employee?->last_name ?? ''));
        $leaveType = $leave->leaveType?->name ?? 'Leave';

        $this->notificationService->notifyUsers($users, [
            'title' => 'Approval Request Pending',
            'message' => $employeeName.' submitted '.$leaveType.' and is awaiting review.',
            'type' => 'info',
            'module' => 'leave',
            'record_id' => $leave->id,
            'route_name' => 'leaves.approvals',
            'route_params' => [],
            'event_key' => 'leave.request.approval.pending',
            'priority' => 'normal',
            ...$this->notificationService->formatSender($actor instanceof User ? $actor : null),
        ]);
    }

    private function notifyAdmins(LeaveRequest $leave, string $message, ?string $actionUrl = null): void
    {
        $this->notificationService->notifyUsers(AccessControl::adminUsers(), [
            'title' => 'Leave Workflow Update',
            'message' => $message,
            'type' => 'info',
            'module' => 'leave',
            'record_id' => $leave->id,
            'route_name' => 'leaves.approvals',
            'route_params' => [],
            'event_key' => 'leave.request.admin.update',
            'priority' => 'normal',
            ...$this->notificationService->formatSender(Auth::user()),
        ]);
    }
}

