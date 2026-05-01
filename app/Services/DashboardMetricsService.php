<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\JobPosting;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\OffboardingRecord;
use App\Models\RecruitmentApproval;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class DashboardMetricsService
{
    public function build(User $user): array
    {
        $cacheKey = sprintf(
            'dashboard.metrics.%d.%s.%s',
            $user->id,
            $this->roleKey($user),
            $this->departmentScopeKey($user)
        );

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($user) {
            return [
                'header' => $this->buildHeader($user),
                'actions' => $this->buildQuickActions($user),
                'action_center' => $this->buildActionCenter($user),
                'kpis' => $this->buildKpis($user),
                'progress_groups' => $this->buildProgressGroups($user),
                'charts' => $this->buildCharts($user),
                'recruitment' => $this->buildRecruitment($user),
                'calendar' => $this->buildCalendar($user),
                'offboarding' => $this->buildOffboarding($user),
            ];
        });
    }

    private function buildHeader(User $user): array
    {
        $roleKey = $this->roleKey($user);
        $roleLabel = match (true) {
            $user->isAdmin() => 'Administrator',
            AccessControl::isHrHead($user) => 'HR Head',
            AccessControl::isPresidentHead($user) => 'President Office Head',
            AccessControl::isDepartmentLeader($user) => 'Department Head',
            default => 'Employee',
        };

        if ($this->isEmployeeDashboard($user)) {
            $title = 'Personal Work Center';
            $subtitle = 'Track attendance, leave requests, assigned tasks, and personal updates from one place.';
            $scopeLabel = 'Personal access';
        } elseif ($user->isAdmin()) {
            $title = 'HRMS Control Center';
            $subtitle = 'Oversee workforce activity, approval queues, recruitment, and system-wide alerts.';
            $scopeLabel = 'System-wide access';
        } elseif (AccessControl::isHrHead($user)) {
            $title = 'HR Operations Center';
            $subtitle = 'Review workforce actions, recruitment, documents, and cross-department HR queues.';
            $scopeLabel = 'Cross-department HR access';
        } elseif (AccessControl::isPresidentHead($user)) {
            $title = 'Executive Review Center';
            $subtitle = 'Track organization-wide approvals, leadership actions, and executive-level workflow updates.';
            $scopeLabel = 'Executive approval access';
        } elseif (AccessControl::isDepartmentLeader($user)) {
            $title = 'Department Work Center';
            $subtitle = 'Focus on your department roster, approvals, attendance, and active employee tasks.';
            $scopeLabel = 'Department-scoped access';
        } else {
            $title = 'Dashboard';
            $subtitle = 'Review your assigned work and current system activity.';
            $scopeLabel = 'Standard access';
        }

        return [
            'title' => $title,
            'subtitle' => $subtitle,
            'role_key' => $roleKey,
            'role_label' => $roleLabel,
            'scope_label' => $scopeLabel,
            'date_label' => now()->format('l, F j, Y'),
        ];
    }

    private function buildQuickActions(User $user): array
    {
        $employeeId = $user->employee?->id;

        $actions = [
            [
                'label' => 'Add Employee',
                'href' => route('employees.index'),
                'icon' => 'cil-user-follow',
                'visible' => $user->isAdmin(),
            ],
            [
                'label' => 'Create Job Posting',
                'href' => route('job-postings.index'),
                'icon' => 'cil-briefcase',
                'visible' => $user->isAdmin() || AccessControl::isHrStaff($user),
            ],
            [
                'label' => 'File Leave',
                'href' => route('leaves.index'),
                'icon' => 'cil-calendar',
                'visible' => !$user->isAdmin(),
            ],
            [
                'label' => 'Upload Document',
                'href' => $employeeId
                    ? route('employee-documents.index', ['employee_id' => $employeeId])
                    : route('documents.index'),
                'icon' => 'cil-cloud-upload',
                'visible' => true,
            ],

        ];

        return collect($actions)
            ->filter(fn (array $action) => $action['visible'])
            ->values()
            ->all();
    }

    private function buildActionCenter(User $user): array
    {
        if ($this->isEmployeeDashboard($user)) {
            return array_values(array_filter([
                $this->buildActionItem(
                    'Leave Requests In Motion',
                    LeaveRequest::query()
                        ->where('employee_id', $user->employee?->id)
                        ->whereIn('status', ['Pending', 'Approved', 'HR Approved', 'Needs Revision'])
                        ->count(),
                    route('leaves.index'),
                    'cil-calendar',
                    'Action required'
                ),
                $this->buildActionItem(
                    'Documents To Reupload',
                    Schema::hasTable('employee_documents')
                        ? EmployeeDocument::query()
                            ->where('employee_id', $user->employee?->id)
                            ->where('status', 'reupload')
                            ->count()
                        : 0,
                    route('employee-documents.index', ['employee_id' => $user->employee?->id]),
                    'cil-warning',
                    'Action required'
                ),
                $this->buildActionItem(
                    'Offboarding Workflow',
                    Employee::offboardingTablesAvailable()
                        ? $this->activeOffboardingQuery($user)->count()
                        : 0,
                    route('offboarding.index'),
                    'cil-user-x',
                    'Action required'
                ),
            ]));
        }

        $items = [];

        if ($user->isAdmin()) {
            $items[] = $this->buildActionItem(
                'Recruitment Approvals',
                $this->pendingRecruitmentApprovalsCount(),
                route('job-postings.index'),
                'cil-bullhorn',
                'Queue awaiting action'
            );
            $items[] = $this->buildActionItem(
                'Department Leave Review',
                $this->visibleLeaveQuery($user)->where('status', 'Pending')->count(),
                route('leaves.approvals'),
                'cil-calendar-check',
                'Queue awaiting action'
            );
            $items[] = $this->buildActionItem(
                'HR Leave Review',
                $this->visibleLeaveQuery($user)->where('status', 'Approved')->count(),
                route('leaves.approvals'),
                'cil-calendar',
                'Queue awaiting action'
            );
            $items[] = $this->buildActionItem(
                'Final Leave Review',
                $this->visibleLeaveQuery($user)
                    ->where('status', 'HR Approved')
                    ->whereNull('president_reviewed_by')
                    ->count(),
                route('leaves.approvals'),
                'cil-check-circle',
                'Queue awaiting action'
            );
        } elseif (AccessControl::isPresidentHead($user)) {
            $items[] = $this->buildActionItem(
                'Final Leave Review',
                $this->visibleLeaveQuery($user)
                    ->where('status', 'HR Approved')
                    ->whereNull('president_reviewed_by')
                    ->count(),
                route('leaves.approvals'),
                'cil-check-circle',
                'Queue awaiting action'
            );
        } elseif (AccessControl::isHrHead($user)) {
            $items[] = $this->buildActionItem(
                'Recruitment Approvals',
                $this->pendingRecruitmentApprovalsCount(),
                route('job-postings.index'),
                'cil-bullhorn',
                'Queue awaiting action'
            );
            $items[] = $this->buildActionItem(
                'Department Leave Review',
                $this->visibleLeaveQuery($user)->where('status', 'Pending')->count(),
                route('leaves.approvals'),
                'cil-calendar-check',
                'Queue awaiting action'
            );
            $items[] = $this->buildActionItem(
                'HR Leave Review',
                $this->visibleLeaveQuery($user)->where('status', 'Approved')->count(),
                route('leaves.approvals'),
                'cil-calendar',
                'Queue awaiting action'
            );
            $items[] = $this->buildActionItem(
                'Documents To Verify',
                Schema::hasTable('employee_documents')
                    ? $this->visibleDocumentQuery($user)->where('status', 'submitted')->count()
                    : 0,
                route('employee-documents.index'),
                'cil-folder-open',
                'Queue awaiting action'
            );
        } elseif (AccessControl::isDepartmentLeader($user)) {
            $items[] = $this->buildActionItem(
                'Department Leave Review',
                $this->visibleLeaveQuery($user)->where('status', 'Pending')->count(),
                route('leaves.approvals'),
                'cil-calendar-check',
                'Queue awaiting action'
            );
        }

        if (Employee::offboardingTablesAvailable()) {
            $items[] = $this->buildActionItem(
                'Active Offboarding',
                $this->activeOffboardingQuery($user)->count(),
                route('offboarding.index'),
                'cil-user-x',
                'Workflow in progress'
            );
        }

        return array_values(array_filter($items));
    }

    private function buildKpis(User $user): array
    {
        if ($this->isEmployeeDashboard($user)) {
            $employeeId = $user->employee?->id;
            $todayAttendance = $employeeId
                ? Attendance::query()
                    ->where('employee_id', $employeeId)
                    ->whereDate('date', today())
                    ->exists()
                : false;

            $leaveBalance = Schema::hasTable('leave_balances') && $employeeId
                ? (float) LeaveBalance::query()
                    ->where('employee_id', $employeeId)
                    ->where('year', now()->year)
                    ->selectRaw('COALESCE(SUM(earned - consumed), 0) as total_balance')
                    ->value('total_balance')
                : 0.0;

            $activeRequests = LeaveRequest::query()
                ->where('employee_id', $employeeId)
                ->whereIn('status', ['Pending', 'Approved', 'HR Approved', 'Needs Revision'])
                ->count();

            $assignedTasks = (Schema::hasTable('employee_documents')
                    ? EmployeeDocument::query()
                        ->where('employee_id', $employeeId)
                        ->where('status', 'reupload')
                        ->count()
                    : 0);

            return [
                $this->metric('Today', $todayAttendance ? 'Present' : 'No Log', 'Personal attendance status', 'cil-check-circle'),
                $this->metric('Leave Balance', $leaveBalance, 'Available leave days this year', 'cil-balance-scale'),
                $this->metric('Active Requests', $activeRequests, 'Requests still moving through workflow', 'cil-calendar'),
                $this->metric('Assigned Tasks', $assignedTasks, 'Document reupload actions', 'cil-task'),
                $this->metric('Unread Alerts', (int) $user->unreadNotifications()->count(), 'Personal notifications awaiting review', 'cil-bell'),
            ];
        }

        return [
            $this->metric('Total Employees', $this->visibleEmployeeQuery($user)->count(), 'Visible active workforce records', 'cil-people'),
            $this->metric(
                'Present Today',
                $this->visibleAttendanceQuery($user)
                    ->whereDate('date', today())
                    ->distinct('employee_id')
                    ->count('employee_id'),
                'Attendance logs captured today',
                'cil-check-circle'
            ),
            $this->metric(
                'On Leave Today',
                $this->visibleLeaveQuery($user)
                    ->where('status', 'HR Approved')
                    ->whereDate('start_date', '<=', today())
                    ->whereDate('end_date', '>=', today())
                    ->count(),
                'Approved leave currently in effect',
                'cil-calendar'
            ),
            $this->metric(
                'Active Job Postings',
                Schema::hasTable('job_postings') ? JobPosting::query()->where('status', 'open')->count() : 0,
                'Open recruitment positions',
                'cil-briefcase'
            ),
            $this->metric(
                'Applicants This Week',
                Schema::hasTable('applicants') ? Applicant::query()->whereDate('created_at', '>=', now()->subDays(7))->count() : 0,
                'New applications in the last 7 days',
                'cil-user-follow'
            ),
            $this->metric(
                'Pending Approvals',
                $this->pendingApprovalsCount($user),
                'Approval items awaiting action',
                'cil-warning'
            ),
        ];
    }

    private function buildCharts(User $user): array
    {
        if ($this->isEmployeeDashboard($user)) {
            return $this->buildEmployeeCharts($user);
        }

        return $this->buildManagerCharts($user);
    }

    private function buildEmployeeCharts(User $user): array
    {
        $employeeId = $user->employee?->id;
        $days = collect(range(6, 0))->map(fn (int $offset) => now()->copy()->subDays($offset));
        $attendanceDates = Attendance::query()
            ->where('employee_id', $employeeId)
            ->whereBetween('date', [$days->first()?->toDateString(), $days->last()?->toDateString()])
            ->pluck('date')
            ->map(fn ($value) => Carbon::parse($value)->toDateString())
            ->all();

        $leaveStatus = LeaveRequest::query()
            ->where('employee_id', $employeeId)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->orderByDesc('total')
            ->get();

        $charts = [
            [
                'id' => 'attendance-trend',
                'title' => 'Attendance Trend',
                'subtitle' => 'Your attendance log across the last 7 days.',
                'type' => 'line',
                'labels' => $days->map(fn (Carbon $date) => $date->format('M d'))->all(),
                'values' => $days->map(fn (Carbon $date) => in_array($date->toDateString(), $attendanceDates, true) ? 1 : 0)->all(),
            ],
            [
                'id' => 'leave-distribution',
                'title' => 'Leave Distribution',
                'subtitle' => 'Your leave requests grouped by status.',
                'type' => 'doughnut',
                'labels' => $leaveStatus->pluck('status')->all(),
                'values' => $leaveStatus->pluck('total')->map(fn ($value) => (int) $value)->all(),
            ],
        ];

        $taskLabels = [
            'Leave Requests',
            'Docs Reupload',
            'Unread Alerts',
        ];
        $taskValues = [
            LeaveRequest::query()
                ->where('employee_id', $employeeId)
                ->whereIn('status', ['Pending', 'Approved', 'HR Approved', 'Needs Revision'])
                ->count(),
            Schema::hasTable('employee_documents')
                ? EmployeeDocument::query()
                    ->where('employee_id', $employeeId)
                    ->where('status', 'reupload')
                    ->count()
                : 0,
            (int) $user->unreadNotifications()->count(),
        ];

        if (array_sum($taskValues) > 0) {
            $charts[] = [
                'id' => 'personal-workload-mix',
                'title' => 'Personal Workload Mix',
                'subtitle' => 'Current open workload split across requests and assigned tasks.',
                'type' => 'doughnut',
                'labels' => $taskLabels,
                'values' => $taskValues,
            ];
        }

        return $charts;
    }

    private function buildManagerCharts(User $user): array
    {
        $months = collect(range(11, 0))->map(fn (int $offset) => now()->copy()->startOfMonth()->subMonths($offset));
        $rangeStart = $months->first()?->toDateString();
        $rangeEnd = $months->last()?->copy()->endOfMonth()->toDateString();
        $rangeEndDateTime = $months->last()?->copy()->endOfMonth()->endOfDay()->toDateTimeString();
        $attendanceMonthExpression = $this->monthKeyExpression('date');
        $createdAtMonthExpression = $this->monthKeyExpression('created_at');

        $attendanceTrend = $this->visibleAttendanceQuery($user)
            ->whereBetween('date', [$rangeStart, $rangeEnd])
            ->selectRaw("{$attendanceMonthExpression} as month_key, COUNT(*) as total")
            ->groupBy(DB::raw($attendanceMonthExpression))
            ->pluck('total', 'month_key');

        $leaveTrend = $this->visibleLeaveQuery($user)
            ->whereBetween('created_at', [$rangeStart, $rangeEndDateTime])
            ->selectRaw("{$createdAtMonthExpression} as month_key, COUNT(*) as total")
            ->groupBy(DB::raw($createdAtMonthExpression))
            ->pluck('total', 'month_key');

        $applicantTrend = collect();
        if (Schema::hasTable('applicants') && Schema::hasTable('job_postings')) {
            $applicantTrend = $this->visibleApplicantQuery($user)
                ->whereBetween('created_at', [$rangeStart, $rangeEndDateTime])
                ->selectRaw("{$createdAtMonthExpression} as month_key, COUNT(*) as total")
                ->groupBy(DB::raw($createdAtMonthExpression))
                ->pluck('total', 'month_key');
        }

        $offboardingTrend = collect();
        if (Employee::offboardingTablesAvailable()) {
            $offboardingTrend = $this->visibleOffboardingQuery($user)
                ->whereBetween('created_at', [$rangeStart, $rangeEndDateTime])
                ->selectRaw("{$createdAtMonthExpression} as month_key, COUNT(*) as total")
                ->groupBy(DB::raw($createdAtMonthExpression))
                ->pluck('total', 'month_key');
        }

        $leaveStatusTrendRows = $this->visibleLeaveQuery($user)
            ->whereBetween('created_at', [$rangeStart, $rangeEndDateTime])
            ->selectRaw("{$createdAtMonthExpression} as month_key, status, COUNT(*) as total")
            ->groupBy(DB::raw($createdAtMonthExpression), 'status')
            ->get();

        $pendingStatuses = ['Pending', 'Approved', 'Needs Revision'];
        $approvedStatuses = ['HR Approved'];
        $rejectedStatuses = ['Declined', 'HR Declined'];

        $pendingByMonth = [];
        $approvedByMonth = [];
        $rejectedByMonth = [];

        foreach ($months as $month) {
            $monthKey = $month->format('Y-m');
            $monthRows = $leaveStatusTrendRows->where('month_key', $monthKey);

            $pendingByMonth[] = (int) $monthRows
                ->whereIn('status', $pendingStatuses)
                ->sum('total');
            $approvedByMonth[] = (int) $monthRows
                ->whereIn('status', $approvedStatuses)
                ->sum('total');
            $rejectedByMonth[] = (int) $monthRows
                ->whereIn('status', $rejectedStatuses)
                ->sum('total');
        }

        $charts = [
            [
                'id' => 'operations-trends-12m',
                'title' => 'Operational Trends',
                'subtitle' => '12-month view of attendance, leave requests, applicants, and offboarding records.',
                'type' => 'line',
                'span' => 12,
                'labels' => $months->map(fn (Carbon $date) => $date->format('M Y'))->all(),
                'datasets' => array_values(array_filter([
                    [
                        'label' => 'Attendance Logs',
                        'data' => $months->map(fn (Carbon $date) => (int) ($attendanceTrend[$date->format('Y-m')] ?? 0))->all(),
                        'borderColor' => '#2563eb',
                        'backgroundColor' => 'rgba(37, 99, 235, 0.12)',
                        'fill' => false,
                    ],
                    [
                        'label' => 'Leave Requests',
                        'data' => $months->map(fn (Carbon $date) => (int) ($leaveTrend[$date->format('Y-m')] ?? 0))->all(),
                        'borderColor' => '#f59e0b',
                        'backgroundColor' => 'rgba(245, 158, 11, 0.15)',
                        'fill' => false,
                    ],
                    Schema::hasTable('applicants') && Schema::hasTable('job_postings')
                        ? [
                            'label' => 'Applicants',
                            'data' => $months->map(fn (Carbon $date) => (int) ($applicantTrend[$date->format('Y-m')] ?? 0))->all(),
                            'borderColor' => '#14b8a6',
                            'backgroundColor' => 'rgba(20, 184, 166, 0.15)',
                            'fill' => false,
                        ]
                        : null,
                    Employee::offboardingTablesAvailable()
                        ? [
                            'label' => 'Offboarding Records',
                            'data' => $months->map(fn (Carbon $date) => (int) ($offboardingTrend[$date->format('Y-m')] ?? 0))->all(),
                            'borderColor' => '#7c3aed',
                            'backgroundColor' => 'rgba(124, 58, 237, 0.15)',
                            'fill' => false,
                        ]
                        : null,
                ])),
            ],
            [
                'id' => 'approval-pipeline-mix',
                'title' => 'Approval Pipeline Mix',
                'subtitle' => 'Monthly leave workflow grouped by pending, approved, and rejected outcomes.',
                'type' => 'bar',
                'stacked' => true,
                'span' => 6,
                'labels' => $months->map(fn (Carbon $date) => $date->format('M Y'))->all(),
                'datasets' => [
                    [
                        'label' => 'Pending',
                        'data' => $pendingByMonth,
                        'backgroundColor' => '#f59e0b',
                        'borderColor' => '#f59e0b',
                    ],
                    [
                        'label' => 'Approved',
                        'data' => $approvedByMonth,
                        'backgroundColor' => '#22c55e',
                        'borderColor' => '#22c55e',
                    ],
                    [
                        'label' => 'Rejected',
                        'data' => $rejectedByMonth,
                        'backgroundColor' => '#ef4444',
                        'borderColor' => '#ef4444',
                    ],
                ],
            ],
        ];

        if (Schema::hasTable('job_postings') && Schema::hasTable('applicants')) {
            $recruitmentRows = JobPosting::query()
                ->withCount('applicants')
                ->where('status', 'open')
                ->orderByDesc('applicants_count')
                ->limit(6)
                ->get();

            $charts[] = [
                'id' => 'recruitment-funnel',
                'title' => 'Recruitment Snapshot',
                'subtitle' => 'Applicant volume for the most active open roles.',
                'type' => 'bar',
                'span' => 6,
                'labels' => $recruitmentRows->map(fn (JobPosting $posting) => $posting->title ?: ($posting->position?->position ?? 'Posting'))->all(),
                'values' => $recruitmentRows->pluck('applicants_count')->map(fn ($value) => (int) $value)->all(),
            ];
        }

        $actionBreakdown = collect($this->buildActionCenter($user))
            ->sortByDesc('count')
            ->take(6)
            ->values();

        if ($actionBreakdown->isNotEmpty()) {
            $charts[] = [
                'id' => 'action-queue-breakdown',
                'title' => 'Action Queue Breakdown',
                'subtitle' => 'Top pending workload queues requiring movement.',
                'type' => 'bar',
                'span' => 6,
                'labels' => $actionBreakdown->pluck('label')->all(),
                'values' => $actionBreakdown->pluck('count')->map(fn ($value) => (int) $value)->all(),
            ];
        }

        $leaveDistribution = $this->visibleLeaveQuery($user)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->orderByDesc('total')
            ->get();

        if ($leaveDistribution->isNotEmpty()) {
            $charts[] = [
                'id' => 'leave-status-overview',
                'title' => 'Leave Status Overview',
                'subtitle' => 'Current visible leave records grouped by status.',
                'type' => 'doughnut',
                'span' => 6,
                'labels' => $leaveDistribution->pluck('status')->all(),
                'values' => $leaveDistribution->pluck('total')->map(fn ($value) => (int) $value)->all(),
            ];
        }

        $recentAttendance = $this->visibleAttendanceQuery($user)
            ->whereDate('date', '>=', now()->subDays(29)->toDateString())
            ->get(['employee_id', 'date']);

        if ($recentAttendance->isNotEmpty()) {
            $weekDays = [
                1 => 'Mon',
                2 => 'Tue',
                3 => 'Wed',
                4 => 'Thu',
                5 => 'Fri',
                6 => 'Sat',
                7 => 'Sun',
            ];

            $weekDayCounts = array_fill_keys(array_keys($weekDays), 0);
            $uniqueLogs = $recentAttendance
                ->map(function ($row) {
                    return [
                        'employee_id' => (string) $row->employee_id,
                        'date' => Carbon::parse($row->date)->toDateString(),
                    ];
                })
                ->unique(fn (array $row) => $row['employee_id'] . '|' . $row['date'])
                ->values();

            foreach ($uniqueLogs as $log) {
                $dayIndex = Carbon::parse($log['date'])->dayOfWeekIso;
                $weekDayCounts[$dayIndex] = ($weekDayCounts[$dayIndex] ?? 0) + 1;
            }

            $charts[] = [
                'id' => 'attendance-weekday-pattern',
                'title' => 'Attendance Weekday Pattern',
                'subtitle' => 'Distinct attendance logs by weekday over the last 30 days.',
                'type' => 'line',
                'span' => 12,
                'labels' => array_values($weekDays),
                'values' => array_values($weekDayCounts),
            ];
        }

        return array_slice($charts, 0, 8);
    }

    private function buildProgressGroups(User $user): array
    {
        if ($this->isEmployeeDashboard($user)) {
            $employeeId = $user->employee?->id;
            $days = collect(range(6, 0))->map(fn (int $offset) => now()->copy()->subDays($offset));
            $attendanceCount = $employeeId
                ? Attendance::query()
                    ->where('employee_id', $employeeId)
                    ->whereBetween('date', [$days->first()?->toDateString(), $days->last()?->toDateString()])
                    ->distinct('date')
                    ->count('date')
                : 0;
            $attendancePercent = $days->count() > 0 ? (int) round(($attendanceCount / $days->count()) * 100) : 0;

            $documentReuploadCount = Schema::hasTable('employee_documents') && $employeeId
                ? EmployeeDocument::query()
                    ->where('employee_id', $employeeId)
                    ->where('status', 'reupload')
                    ->count()
                : 0;

            $activeTaskCount = 0;

            return [
                [
                    'label' => 'Attendance This Week',
                    'value' => sprintf('%d/%d', $attendanceCount, $days->count()),
                    'meta' => '(' . $attendancePercent . '%)',
                    'percent' => $attendancePercent,
                    'icon' => 'cil-clock',
                    'color' => 'success',
                ],
                [
                    'label' => 'Unread Notifications',
                    'value' => (int) $user->unreadNotifications()->count(),
                    'meta' => 'Needs review',
                    'percent' => min(100, (int) $user->unreadNotifications()->count() * 10),
                    'icon' => 'cil-bell',
                    'color' => 'warning',
                ],
                [
                    'label' => 'Open Personal Tasks',
                    'value' => $activeTaskCount + $documentReuploadCount,
                    'meta' => 'Document reupload actions',
                    'percent' => min(100, ($activeTaskCount + $documentReuploadCount) * 10),
                    'icon' => 'cil-task',
                    'color' => 'info',
                ],
            ];
        }

        $totalEmployees = $this->visibleEmployeeQuery($user)->count();
        $presentToday = $this->visibleAttendanceQuery($user)
            ->whereDate('date', today())
            ->distinct('employee_id')
            ->count('employee_id');
        $onLeaveToday = $this->visibleLeaveQuery($user)
            ->where('status', 'HR Approved')
            ->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today())
            ->count();
        $pendingApprovals = $this->pendingApprovalsCount($user);
        $applicantsThisWeek = Schema::hasTable('applicants')
            ? Applicant::query()->whereDate('created_at', '>=', now()->subDays(7))->count()
            : 0;
        $activePostings = Schema::hasTable('job_postings')
            ? JobPosting::query()->where('status', 'open')->count()
            : 0;

        $presentPercent = $totalEmployees > 0 ? (int) round(($presentToday / $totalEmployees) * 100) : 0;
        $leavePercent = $totalEmployees > 0 ? (int) round(($onLeaveToday / $totalEmployees) * 100) : 0;
        $approvalPercent = $totalEmployees > 0 ? (int) round((min($pendingApprovals, $totalEmployees) / $totalEmployees) * 100) : 0;
        $recruitmentPercent = $activePostings > 0 ? min(100, (int) round(($applicantsThisWeek / max($activePostings, 1)) * 20)) : 0;

        return [
            [
                'label' => 'Present Today',
                'value' => sprintf('%d/%d', $presentToday, $totalEmployees),
                'meta' => '(' . $presentPercent . '%)',
                'percent' => $presentPercent,
                'icon' => 'cil-check-circle',
                'color' => 'success',
            ],
            [
                'label' => 'On Leave Today',
                'value' => $onLeaveToday,
                'meta' => '(' . $leavePercent . '% of workforce)',
                'percent' => $leavePercent,
                'icon' => 'cil-calendar',
                'color' => 'warning',
            ],
            [
                'label' => 'Pending Approvals',
                'value' => $pendingApprovals,
                'meta' => '(' . $approvalPercent . '% of workforce)',
                'percent' => $approvalPercent,
                'icon' => 'cil-warning',
                'color' => 'danger',
            ],
            [
                'label' => 'Recruitment Activity',
                'value' => $applicantsThisWeek,
                'meta' => $activePostings . ' open postings',
                'percent' => $recruitmentPercent,
                'icon' => 'cil-briefcase',
                'color' => 'info',
            ],
        ];
    }

    private function buildRecruitment(User $user): array
    {
        if ($this->isEmployeeDashboard($user) || !Schema::hasTable('job_postings') || !Schema::hasTable('applicants')) {
            return [];
        }

        $openPostings = JobPosting::query()
            ->withCount('applicants')
            ->with(['department', 'position'])
            ->where('status', 'open')
            ->orderBy('closing_date')
            ->limit(5)
            ->get();

        $recentApplicants = Applicant::query()
            ->with('jobPosting.position')
            ->latest('created_at')
            ->limit(5)
            ->get();

        return [
            'summary' => [
                ['label' => 'Open Positions', 'value' => $openPostings->count()],
                ['label' => 'Applicants This Week', 'value' => Applicant::query()->whereDate('created_at', '>=', now()->subDays(7))->count()],
            ],
            'roles' => $openPostings->map(function (JobPosting $posting) {
                return [
                    'title' => $posting->title ?: ($posting->position?->position ?? 'Open role'),
                    'department' => $posting->department?->department ?? 'No department',
                    'count' => (int) $posting->applicants_count,
                    'href' => route('job-postings.applicants'),
                ];
            })->all(),
            'recent_applicants' => $recentApplicants->map(function (Applicant $applicant) {
                return [
                    'title' => $applicant->full_name,
                    'meta' => $applicant->jobPosting?->title ?: ($applicant->jobPosting?->position?->position ?? 'Applicant'),
                    'date' => optional($applicant->created_at)->format('M d, Y h:i A'),
                    'href' => route('job-postings.applicants'),
                ];
            })->all(),
        ];
    }

    private function buildCalendar(User $user): array
    {
        if ($this->isEmployeeDashboard($user)) {
            return $this->buildEmployeeCalendar($user);
        }

        return $this->buildManagerCalendar($user);
    }

    private function buildEmployeeCalendar(User $user): array
    {
        $employeeId = $user->employee?->id;
        $approvedLeave = LeaveRequest::query()
            ->where('employee_id', $employeeId)
            ->where('status', 'HR Approved')
            ->whereDate('start_date', '>=', today())
            ->orderBy('start_date')
            ->first();

        $expiringDocument = Schema::hasTable('employee_documents')
            ? EmployeeDocument::query()
                ->where('employee_id', $employeeId)
                ->whereNotNull('expires_at')
                ->whereDate('expires_at', '>=', today())
                ->orderBy('expires_at')
                ->first()
            : null;

        $events = [];
        if ($approvedLeave) {
            $events[] = [
                'title' => 'Upcoming Approved Leave',
                'meta' => optional($approvedLeave->leaveType)->name ?? 'Leave',
                'date' => $approvedLeave->start_date?->format('M d, Y'),
            ];
        }
        if ($expiringDocument) {
            $events[] = [
                'title' => 'Document Expiry',
                'meta' => $expiringDocument->document_name ?? 'Employee document',
                'date' => $expiringDocument->expires_at?->format('M d, Y'),
            ];
        }

        return [
            'summary' => [
                ['label' => 'Today', 'value' => Attendance::query()->where('employee_id', $employeeId)->whereDate('date', today())->exists() ? 'Present' : 'No Log'],
                ['label' => 'Next Leave', 'value' => $approvedLeave?->start_date?->format('M d') ?? 'None'],
            ],
            'events' => $events,
        ];
    }

    private function buildManagerCalendar(User $user): array
    {
        $totalEmployees = $this->visibleEmployeeQuery($user)->count();
        $presentToday = $this->visibleAttendanceQuery($user)
            ->whereDate('date', today())
            ->distinct('employee_id')
            ->count('employee_id');
        $upcomingLeaves = $this->visibleLeaveQuery($user)
            ->with(['employee.department', 'leaveType'])
            ->where('status', 'HR Approved')
            ->whereDate('start_date', '>=', today())
            ->orderBy('start_date')
            ->limit(4)
            ->get();
        $deadlines = collect();

        if (Schema::hasTable('job_postings')) {
            $deadlines = $deadlines->merge(
                JobPosting::query()
                    ->where('status', 'open')
                    ->whereNotNull('closing_date')
                    ->whereDate('closing_date', '>=', today())
                    ->whereDate('closing_date', '<=', now()->addDays(21))
                    ->orderBy('closing_date')
                    ->limit(3)
                    ->get()
                    ->map(function (JobPosting $posting) {
                        return [
                            'title' => 'Closing Date',
                            'meta' => $posting->title ?: ($posting->position?->position ?? 'Job posting'),
                            'date' => $posting->closing_date?->format('M d, Y'),
                        ];
                    })
            );
        }



        $events = $upcomingLeaves->map(function (LeaveRequest $leave) {
            $name = trim(($leave->employee?->first_name ?? '') . ' ' . ($leave->employee?->last_name ?? ''));

            return [
                'title' => $name !== '' ? $name : 'Employee leave',
                'meta' => ($leave->leaveType?->name ?? 'Leave') . ' • ' . ($leave->employee?->department?->department ?? 'No department'),
                'date' => $leave->start_date?->format('M d, Y'),
            ];
        })->all();

        return [
            'summary' => [
                ['label' => 'Present Today', 'value' => $presentToday],
                ['label' => 'Absent / No Log', 'value' => max($totalEmployees - $presentToday, 0)],
            ],
            'events' => array_slice(array_merge($events, $deadlines->all()), 0, 6),
        ];
    }

    private function buildOffboarding(User $user): array
    {
        $employee = $user->employee;
        if (!$employee || !Employee::offboardingTablesAvailable()) {
            return [];
        }

        $record = OffboardingRecord::query()
            ->with('clearanceItems')
            ->where('employee_id', $employee->id)
            ->latest('created_at')
            ->first();

        if (!$record || !$record->isOpen()) {
            return [];
        }

        return [
            'title' => 'Offboarding Workflow Active',
            'status' => $record->stage_label,
            'meta' => 'Last working day ' . (optional($record->display_last_working_day)->format('F d, Y') ?: 'Not set'),
            'href' => route('offboarding.show', $record),
            'print_href' => route('offboarding.export', $record),
        ];
    }

    private function buildActionItem(string $label, int $count, string $href, string $icon, string $meta): ?array
    {
        if ($count <= 0) {
            return null;
        }

        return compact('label', 'count', 'href', 'icon', 'meta');
    }

    private function metric(string $label, mixed $value, string $meta, string $icon): array
    {
        return compact('label', 'value', 'meta', 'icon');
    }

    private function pendingApprovalsCount(User $user): int
    {
        if ($this->isEmployeeDashboard($user)) {
            return 0;
        }

        if ($user->isAdmin()) {
            return $this->pendingRecruitmentApprovalsCount()
                + $this->visibleLeaveQuery($user)->where('status', 'Pending')->count()
                + $this->visibleLeaveQuery($user)->where('status', 'Approved')->count()
                + $this->visibleLeaveQuery($user)
                    ->where('status', 'HR Approved')
                    ->whereNull('president_reviewed_by')
                    ->count();
        }

        if (AccessControl::isPresidentHead($user)) {
            return $this->visibleLeaveQuery($user)
                ->where('status', 'HR Approved')
                ->whereNull('president_reviewed_by')
                ->count();
        }

        if (AccessControl::isHrHead($user)) {
            return $this->pendingRecruitmentApprovalsCount()
                + $this->visibleLeaveQuery($user)->where('status', 'Pending')->count()
                + $this->visibleLeaveQuery($user)->where('status', 'Approved')->count()
                + (Schema::hasTable('employee_documents')
                    ? $this->visibleDocumentQuery($user)->where('status', 'submitted')->count()
                    : 0);
        }

        if (AccessControl::isDepartmentLeader($user)) {
            return $this->visibleLeaveQuery($user)->where('status', 'Pending')->count();
        }

        return 0;
    }

    private function pendingRecruitmentApprovalsCount(): int
    {
        return Schema::hasTable('recruitment_approvals')
            ? RecruitmentApproval::query()->where('status', RecruitmentApproval::STATUS_PENDING)->count()
            : 0;
    }

    private function isEmployeeDashboard(User $user): bool
    {
        return !$user->canViewData();
    }

    private function roleKey(User $user): string
    {
        return match (true) {
            $user->isAdmin() => 'admin',
            AccessControl::isHrHead($user) => 'hr-head',
            AccessControl::isPresidentHead($user) => 'president-head',
            AccessControl::isDepartmentLeader($user) => 'department-head',
            default => 'employee',
        };
    }

    private function departmentScopeKey(User $user): string
    {
        return $this->scopeToDepartment($user)
            ? ('dept-' . (string) ($user->employee?->department_id ?? 0))
            : 'global';
    }

    private function scopeToDepartment(User $user): bool
    {
        if (!AccessControl::isDepartmentLeader($user)) {
            return false;
        }

        if (AccessControl::isHrDepartmentLeader($user) || AccessControl::isPresidentDepartmentLeader($user)) {
            return false;
        }

        return (bool) $user->employee?->department_id;
    }

    private function visibleEmployeeQuery(User $user): Builder
    {
        return Employee::query()
            ->nonAdmin()
            ->when($this->scopeToDepartment($user), function (Builder $query) use ($user) {
                $query->where('department_id', $user->employee?->department_id);
            });
    }

    private function visibleAttendanceQuery(User $user): Builder
    {
        return Attendance::query()
            ->when(!$user->isAdmin(), function (Builder $query) {
                $query->whereHas('employee.user', function (Builder $userQuery) {
                    $userQuery->where('role', '!=', 'admin');
                });
            })
            ->when($this->scopeToDepartment($user), function (Builder $query) use ($user) {
                $query->whereHas('employee', function (Builder $employeeQuery) use ($user) {
                    $employeeQuery->where('department_id', $user->employee?->department_id);
                });
            });
    }

    private function visibleLeaveQuery(User $user): Builder
    {
        return LeaveRequest::query()
            ->when(!$user->isAdmin(), function (Builder $query) {
                $query->whereHas('employee.user', function (Builder $userQuery) {
                    $userQuery->where('role', '!=', 'admin');
                });
            })
            ->when($this->scopeToDepartment($user), function (Builder $query) use ($user) {
                $query->whereHas('employee', function (Builder $employeeQuery) use ($user) {
                    $employeeQuery->where('department_id', $user->employee?->department_id);
                });
            });
    }

    private function visibleDocumentQuery(User $user): Builder
    {
        return EmployeeDocument::query()
            ->when(!$user->isAdmin(), function (Builder $query) {
                $query->whereHas('employee.user', function (Builder $userQuery) {
                    $userQuery->where('role', '!=', 'admin');
                });
            })
            ->when($this->scopeToDepartment($user), function (Builder $query) use ($user) {
                $query->whereHas('employee', function (Builder $employeeQuery) use ($user) {
                    $employeeQuery->where('department_id', $user->employee?->department_id);
                });
            });
    }

    private function visibleApplicantQuery(User $user): Builder
    {
        return Applicant::query()
            ->when($this->scopeToDepartment($user), function (Builder $query) use ($user) {
                $query->whereHas('jobPosting', function (Builder $jobPostingQuery) use ($user) {
                    $jobPostingQuery->where('department_id', $user->employee?->department_id);
                });
            });
    }

    private function visibleOffboardingQuery(User $user): Builder
    {
        return OffboardingRecord::query()
            ->when($this->scopeToDepartment($user), function (Builder $query) use ($user) {
                $query->whereHas('employee', function (Builder $employeeQuery) use ($user) {
                    $employeeQuery->where('department_id', $user->employee?->department_id);
                });
            });
    }

    private function activeOffboardingQuery(User $user): Builder
    {
        return OffboardingRecord::query()
            ->active()
            ->when($this->isEmployeeDashboard($user), function (Builder $query) use ($user) {
                $query->where('employee_id', $user->employee?->id);
            })
            ->when(!$this->isEmployeeDashboard($user) && $this->scopeToDepartment($user), function (Builder $query) use ($user) {
                $query->whereHas('employee', function (Builder $employeeQuery) use ($user) {
                    $employeeQuery->where('department_id', $user->employee?->department_id);
                });
            });
    }


    private function monthKeyExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            'pgsql' => "to_char({$column}, 'YYYY-MM')",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }
}
