<?php

namespace App\Http\Controllers;

use App\Models\AttendanceAnomaly;
use App\Models\Department;
use App\Models\DepartmentMetric;
use App\Models\EmployeeDocument;
use App\Models\LeaveRequest;
use App\Models\TravelOrder;
use App\Services\AuditLogger;
use App\Services\ReportExportService;
use App\Services\AccessControl;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

class ReportController extends Controller
{
    private function shouldScopeToDepartment($user): bool
    {
        if (!$user || !AccessControl::isDepartmentLeader($user)) {
            return false;
        }

        if (AccessControl::isHrDepartmentLeader($user) || AccessControl::isPresidentDepartmentLeader($user)) {
            return false;
        }

        return (bool) $user->employee?->department_id;
    }

    private function departmentIdForScope($user): ?int
    {
        return $user?->employee?->department_id;
    }

    public function index()
    {
        $user = auth()->user();
        if (!$user || !$user->canViewData()) {
            abort(403);
        }

        $excludeAdmin = $user->isReadOnlyStaff() && !$user->isAdmin();
        $scopeToDepartment = $this->shouldScopeToDepartment($user);
        $departmentId = $this->departmentIdForScope($user);

        $cacheKey = 'reports.index.' . ($excludeAdmin ? 'noadmin' : 'all') . ($scopeToDepartment ? '.dept' . $departmentId : '');
        $cached = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($excludeAdmin, $scopeToDepartment, $departmentId) {
            $latestMetricsDate = DepartmentMetric::max('metric_date');
            $latestMetricsDate = $latestMetricsDate ? Carbon::parse($latestMetricsDate) : null;
            $latestMetrics = $latestMetricsDate
                ? DepartmentMetric::with('department')
                    ->whereDate('metric_date', $latestMetricsDate)
                    ->when($scopeToDepartment, function ($query) use ($departmentId) {
                        $query->where('department_id', $departmentId);
                    })
                    ->get()
                : collect();

            $kpis = [
                'employees' => (int) $latestMetrics->sum('total_employees'),
                'attendance' => round((float) ($latestMetrics->avg('attendance_rate') ?? 0), 1),
                'leave_requests' => (int) $latestMetrics->sum('leave_requests'),
                'compliance' => round((float) ($latestMetrics->avg('document_compliance_rate') ?? 0), 1),
            ];

            $attendanceTrendRows = DepartmentMetric::selectRaw('DATE(metric_date) as metric_day, AVG(attendance_rate) as attendance_rate')
                ->when($scopeToDepartment, function ($query) use ($departmentId) {
                    $query->where('department_id', $departmentId);
                })
                ->groupBy('metric_day')
                ->orderBy('metric_day', 'desc')
                ->limit(12)
                ->get()
                ->reverse()
                ->values();

            $attendanceTrend = [
                'labels' => $attendanceTrendRows->map(fn ($row) => $row->metric_day ? Carbon::parse($row->metric_day)->format('M d') : null)->filter()->values(),
                'values' => $attendanceTrendRows->map(fn ($row) => round((float) ($row->attendance_rate ?? 0), 1))->values(),
            ];

            $departmentHeadcount = [
                'labels' => $latestMetrics->map(fn ($metric) => $metric->department?->department ?? 'Unknown')->values(),
                'values' => $latestMetrics->map(fn ($metric) => (int) $metric->total_employees)->values(),
            ];

            $leaveStatusRows = LeaveRequest::select('status', DB::raw('COUNT(*) as total'))
                ->when($scopeToDepartment, function ($query) use ($departmentId) {
                    $query->whereHas('employee', function ($subQuery) use ($departmentId) {
                        $subQuery->where('department_id', $departmentId);
                    });
                })
                ->when($excludeAdmin, function ($query) {
                    $query->whereHas('employee.user', function ($subQuery) {
                        $subQuery->where('role', '!=', 'admin');
                    });
                })
                ->groupBy('status')
                ->orderByDesc('total')
                ->get();

            $leaveStatus = [
                'labels' => $leaveStatusRows->pluck('status')->values(),
                'values' => $leaveStatusRows->pluck('total')->map(fn ($value) => (int) $value)->values(),
            ];

            $travelStatusRows = TravelOrder::query()
                ->when($scopeToDepartment, function ($query) use ($departmentId) {
                    $query->where('department_id', $departmentId);
                })
                ->when($excludeAdmin, function ($query) {
                    $query->whereHas('employee.user', function ($subQuery) {
                        $subQuery->where('role', '!=', 'admin');
                    });
                })
                ->select('status', DB::raw('COUNT(*) as total'))
                ->groupBy('status')
                ->orderByDesc('total')
                ->get();

            $travelStatus = [
                'labels' => $travelStatusRows->pluck('status')->values(),
                'values' => $travelStatusRows->pluck('total')->map(fn ($value) => (int) $value)->values(),
            ];

            $expiryStart = now()->startOfMonth();
            $expiryEnd = now()->copy()->addMonths(5)->endOfMonth();
            $documentExpiryRows = EmployeeDocument::whereNotNull('expires_at')
                ->whereBetween('expires_at', [$expiryStart, $expiryEnd])
                ->when($scopeToDepartment, function ($query) use ($departmentId) {
                    $query->whereHas('employee', function ($subQuery) use ($departmentId) {
                        $subQuery->where('department_id', $departmentId);
                    });
                })
                ->when($excludeAdmin, function ($query) {
                    $query->whereHas('employee.user', function ($subQuery) {
                        $subQuery->where('role', '!=', 'admin');
                    });
                })
                ->selectRaw("DATE_FORMAT(expires_at, '%Y-%m') as month, COUNT(*) as total")
                ->groupBy('month')
                ->orderBy('month')
                ->get();

            $documentExpiry = [
                'labels' => $documentExpiryRows->map(fn ($row) => Carbon::parse($row->month . '-01')->format('M Y'))->values(),
                'values' => $documentExpiryRows->pluck('total')->map(fn ($value) => (int) $value)->values(),
            ];

            $anomalyStart = now()->copy()->subMonths(5)->startOfMonth();
            $anomalyTrendRows = AttendanceAnomaly::whereDate('date', '>=', $anomalyStart)
                ->when($scopeToDepartment, function ($query) use ($departmentId) {
                    $query->whereHas('employee', function ($subQuery) use ($departmentId) {
                        $subQuery->where('department_id', $departmentId);
                    });
                })
                ->when($excludeAdmin, function ($query) {
                    $query->whereHas('employee.user', function ($subQuery) {
                        $subQuery->where('role', '!=', 'admin');
                    });
                })
                ->selectRaw("DATE_FORMAT(date, '%Y-%m') as month, COUNT(*) as total")
                ->groupBy('month')
                ->orderBy('month')
                ->get();

            $anomalyTrend = [
                'labels' => $anomalyTrendRows->map(fn ($row) => Carbon::parse($row->month . '-01')->format('M Y'))->values(),
                'values' => $anomalyTrendRows->pluck('total')->map(fn ($value) => (int) $value)->values(),
            ];

            $pendingLeaves = LeaveRequest::where('status', 'like', 'Pending%')
                ->when($scopeToDepartment, function ($query) use ($departmentId) {
                    $query->whereHas('employee', function ($subQuery) use ($departmentId) {
                        $subQuery->where('department_id', $departmentId);
                    });
                })
                ->when($excludeAdmin, function ($query) {
                    $query->whereHas('employee.user', function ($subQuery) {
                        $subQuery->where('role', '!=', 'admin');
                    });
                })
                ->count();

            $recentAnomalyCount = AttendanceAnomaly::whereDate('date', '>=', now()->copy()->subDays(30))
                ->when($scopeToDepartment, function ($query) use ($departmentId) {
                    $query->whereHas('employee', function ($subQuery) use ($departmentId) {
                        $subQuery->where('department_id', $departmentId);
                    });
                })
                ->when($excludeAdmin, function ($query) {
                    $query->whereHas('employee.user', function ($subQuery) {
                        $subQuery->where('role', '!=', 'admin');
                    });
                })
                ->count();

            $expiringSoonCount = EmployeeDocument::whereNotNull('expires_at')
                ->whereBetween('expires_at', [now(), now()->copy()->addDays(30)])
                ->when($scopeToDepartment, function ($query) use ($departmentId) {
                    $query->whereHas('employee', function ($subQuery) use ($departmentId) {
                        $subQuery->where('department_id', $departmentId);
                    });
                })
                ->when($excludeAdmin, function ($query) {
                    $query->whereHas('employee.user', function ($subQuery) {
                        $subQuery->where('role', '!=', 'admin');
                    });
                })
                ->count();

            return [
                'latestMetricsDate' => $latestMetricsDate,
                'kpis' => $kpis,
                'attendanceTrend' => $attendanceTrend,
                'departmentHeadcount' => $departmentHeadcount,
                'leaveStatus' => $leaveStatus,
                'travelStatus' => $travelStatus,
                'documentExpiry' => $documentExpiry,
                'anomalyTrend' => $anomalyTrend,
                'pendingLeaves' => $pendingLeaves,
                'recentAnomalyCount' => $recentAnomalyCount,
                'expiringSoonCount' => $expiringSoonCount,
            ];
        });

        $latestMetricsDate = $cached['latestMetricsDate'];
        $kpis = $cached['kpis'];
        $attendanceTrend = $cached['attendanceTrend'];
        $departmentHeadcount = $cached['departmentHeadcount'];
        $leaveStatus = $cached['leaveStatus'];
        $travelStatus = $cached['travelStatus'];
        $documentExpiry = $cached['documentExpiry'];
        $anomalyTrend = $cached['anomalyTrend'];
        $pendingLeaves = $cached['pendingLeaves'];
        $recentAnomalyCount = $cached['recentAnomalyCount'];
        $expiringSoonCount = $cached['expiringSoonCount'];

        $recentAnomalies = AttendanceAnomaly::with(['employee.department'])
            ->orderByDesc('date')
            ->when($scopeToDepartment, function ($query) use ($departmentId) {
                $query->whereHas('employee', function ($subQuery) use ($departmentId) {
                    $subQuery->where('department_id', $departmentId);
                });
            })
            ->when($excludeAdmin, function ($query) {
                $query->whereHas('employee.user', function ($subQuery) {
                    $subQuery->where('role', '!=', 'admin');
                });
            })
            ->limit(6)
            ->get();

        $recentLeaves = LeaveRequest::with(['employee.department', 'leaveType'])
            ->orderByDesc('created_at')
            ->when($scopeToDepartment, function ($query) use ($departmentId) {
                $query->whereHas('employee', function ($subQuery) use ($departmentId) {
                    $subQuery->where('department_id', $departmentId);
                });
            })
            ->when($excludeAdmin, function ($query) {
                $query->whereHas('employee.user', function ($subQuery) {
                    $subQuery->where('role', '!=', 'admin');
                });
            })
            ->limit(6)
            ->get();

        $recentDocuments = EmployeeDocument::with(['employee.department', 'documents'])
            ->whereNotNull('expires_at')
            ->orderBy('expires_at')
            ->when($scopeToDepartment, function ($query) use ($departmentId) {
                $query->whereHas('employee', function ($subQuery) use ($departmentId) {
                    $subQuery->where('department_id', $departmentId);
                });
            })
            ->when($excludeAdmin, function ($query) {
                $query->whereHas('employee.user', function ($subQuery) {
                    $subQuery->where('role', '!=', 'admin');
                });
            })
            ->limit(6)
            ->get();

        $metrics = DepartmentMetric::with('department')
            ->when($scopeToDepartment, function ($query) use ($departmentId) {
                $query->where('department_id', $departmentId);
            })
            ->orderByDesc('metric_date')
            ->paginate(10, ['*'], 'metrics_page')
            ->withQueryString();

        $anomalies = AttendanceAnomaly::with(['employee.department'])
            ->orderByDesc('date')
            ->when($scopeToDepartment, function ($query) use ($departmentId) {
                $query->whereHas('employee', function ($subQuery) use ($departmentId) {
                    $subQuery->where('department_id', $departmentId);
                });
            })
            ->when($excludeAdmin, function ($query) {
                $query->whereHas('employee.user', function ($subQuery) {
                    $subQuery->where('role', '!=', 'admin');
                });
            })
            ->paginate(10, ['*'], 'anomalies_page')
            ->withQueryString();

        $leaves = LeaveRequest::with(['employee.department', 'leaveType'])
            ->orderByDesc('created_at')
            ->when($scopeToDepartment, function ($query) use ($departmentId) {
                $query->whereHas('employee', function ($subQuery) use ($departmentId) {
                    $subQuery->where('department_id', $departmentId);
                });
            })
            ->when($excludeAdmin, function ($query) {
                $query->whereHas('employee.user', function ($subQuery) {
                    $subQuery->where('role', '!=', 'admin');
                });
            })
            ->paginate(10, ['*'], 'leaves_page')
            ->withQueryString();

        $documents = EmployeeDocument::with(['employee.department', 'documents'])
            ->whereNotNull('expires_at')
            ->orderBy('expires_at')
            ->when($scopeToDepartment, function ($query) use ($departmentId) {
                $query->whereHas('employee', function ($subQuery) use ($departmentId) {
                    $subQuery->where('department_id', $departmentId);
                });
            })
            ->when($excludeAdmin, function ($query) {
                $query->whereHas('employee.user', function ($subQuery) {
                    $subQuery->where('role', '!=', 'admin');
                });
            })
            ->paginate(10, ['*'], 'documents_page')
            ->withQueryString();

        $travelOrders = TravelOrder::with(['employee.department', 'position'])
            ->orderByDesc('date_from')
            ->when($scopeToDepartment, function ($query) use ($departmentId) {
                $query->where('department_id', $departmentId);
            })
            ->when($excludeAdmin, function ($query) {
                $query->whereHas('employee.user', function ($subQuery) {
                    $subQuery->where('role', '!=', 'admin');
                });
            })
            ->paginate(10, ['*'], 'travel_orders_page')
            ->withQueryString();

        $departments = Department::query()
            ->when($scopeToDepartment, function ($query) use ($departmentId) {
                $query->where('id', $departmentId);
            })
            ->orderBy('department')
            ->get();

        return view('reports.index', compact(
            'latestMetricsDate',
            'kpis',
            'attendanceTrend',
            'departmentHeadcount',
            'leaveStatus',
            'travelStatus',
            'documentExpiry',
            'anomalyTrend',
            'recentAnomalies',
            'recentLeaves',
            'recentDocuments',
            'metrics',
            'anomalies',
            'leaves',
            'documents',
            'travelOrders',
            'departments',
            'pendingLeaves',
            'recentAnomalyCount',
            'expiringSoonCount'
        ));
    }

    public function departmentMetrics()
    {
        return redirect()->to(route('reports.index') . '#table-department-metrics');
    }

    public function attendanceAnomalies()
    {
        return redirect()->to(route('reports.index') . '#table-attendance-anomalies');
    }

    public function leaveSummary()
    {
        return redirect()->to(route('reports.index') . '#table-leave-summary');
    }

    public function documentExpiry()
    {
        return redirect()->to(route('reports.index') . '#table-document-expiry');
    }

    public function export(Request $request, string $type)
    {
        $user = $request->user();
        if (!$user || !$user->canViewData()) {
            abort(403);
        }

        try {
            $run = (new ReportExportService())->export($type, $user);
        } catch (InvalidArgumentException $exception) {
            return redirect()->back()->with('error', 'Unknown report type requested.');
        } catch (Throwable $exception) {
            return redirect()->back()->with('error', 'Report export failed. Please try again.');
        }

        if ($run->status !== 'completed' || !$run->file_path) {
            return redirect()->back()->with('error', 'Report export failed. Please try again.');
        }

        AuditLogger::log('download', $run, [
            'report_type' => $type,
            'path' => $run->file_path,
        ]);

        return Storage::disk('local')->download($run->file_path);
    }
}
