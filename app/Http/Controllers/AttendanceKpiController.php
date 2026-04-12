<?php

namespace App\Http\Controllers;

use App\Models\AttendanceKpi;
use App\Models\AttendanceMonthlyScore;
use App\Models\Department;
use App\Services\AccessControl;
use App\Services\AttendanceKpiScoringService;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceKpiController extends Controller
{
    public function __construct(
        private readonly AttendanceKpiScoringService $scoringService
    ) {
    }

    public function index(Request $request)
    {
        Gate::authorize('view-attendance-kpi');

        $month = max(1, min(12, (int) $request->query('month', now()->month)));
        $year = max(2000, min(2100, (int) $request->query('year', now()->year)));
        $departmentId = (int) $request->query('department_id', 0);

        $user = $request->user();
        $canManage = Gate::allows('manage-attendance-kpi');
        $canViewAll = true;
        $isDeptLeader = false;

        if ($canManage && (bool) $request->query('compute')) {
            $this->scoringService->computeMonthlyScores($month, $year);
        }

        $kpi = $this->scoringService->activeKpiForPeriod($month, $year);

        if (!$canViewAll && !$isDeptLeader) {
            $employeeId = (int) ($user?->employee?->id ?? 0);
            if ($employeeId > 0) {
                $this->scoringService->getOrComputeEmployeeScore($employeeId, $month, $year);
            }
        } elseif ($canManage) {
            $hasAny = AttendanceMonthlyScore::query()->where('month', $month)->where('year', $year)->exists();
            if (!$hasAny) {
                $this->scoringService->computeMonthlyScores($month, $year);
            }
        }

        $scoreQuery = AttendanceMonthlyScore::query()
            ->with(['employee.department'])
            ->where('month', $month)
            ->where('year', $year);

        if ($canViewAll) {
            if ($departmentId > 0) {
                $scoreQuery->whereHas('employee', fn ($q) => $q->where('department_id', $departmentId));
            }
        } elseif ($isDeptLeader) {
            $deptId = (int) ($user?->employee?->department_id ?? 0);
            $scoreQuery->whereHas('employee', fn ($q) => $q->where('department_id', $deptId));
        } else {
            $employeeId = (int) ($user?->employee?->id ?? 0);
            $scoreQuery->where('employee_id', $employeeId);
        }

        $scores = $scoreQuery
            ->orderByDesc('rating')
            ->orderByDesc('final_score')
            ->paginate(10)
            ->withQueryString();

        $summaryRows = (clone $scoreQuery)->get(['rating', 'attendance_incentive_eligible']);
        $summary = [
            'outstanding' => $summaryRows->where('rating', 5)->count(),
            'very_satisfactory' => $summaryRows->where('rating', 4)->count(),
            'eligible' => $summaryRows->where('attendance_incentive_eligible', true)->count(),
            'total' => $summaryRows->count(),
        ];

        return view('attendance.kpi', [
            'scores' => $scores,
            'summary' => $summary,
            'month' => $month,
            'year' => $year,
            'kpi' => $kpi,
            'canManage' => $canManage,
            'departments' => Department::query()->orderBy('department')->get(),
            'selectedDepartmentId' => $departmentId,
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize('manage-attendance-kpi');

        $validated = $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'target_percentage' => ['required', 'numeric', 'min:1', 'max:100'],
        ]);

        $kpi = $this->scoringService->upsertKpi(
            (int) $validated['month'],
            (int) $validated['year'],
            (float) $validated['target_percentage'],
            (int) $request->user()->id
        );

        AuditLogger::logSystem('attendance_kpi_configured', [
            'kpi_id' => $kpi->id,
            'month' => $kpi->month,
            'year' => $kpi->year,
            'target_percentage' => $kpi->target_percentage,
        ], $request->user()?->id, AttendanceKpi::class, $kpi->id);

        return back()->with('success', 'Attendance KPI configured.');
    }

    public function compute(Request $request)
    {
        Gate::authorize('manage-attendance-kpi');

        $validated = $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
        ]);

        $scores = $this->scoringService->computeMonthlyScores((int) $validated['month'], (int) $validated['year']);

        AuditLogger::logSystem('attendance_monthly_scores_computed', [
            'month' => (int) $validated['month'],
            'year' => (int) $validated['year'],
            'rows' => $scores->count(),
        ], $request->user()?->id, AttendanceMonthlyScore::class, 0);

        return back()->with('success', 'Monthly attendance KPI scores computed.');
    }

    public function lock(Request $request)
    {
        Gate::authorize('manage-attendance-kpi');

        $validated = $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
        ]);

        $updated = $this->scoringService->lockMonth((int) $validated['month'], (int) $validated['year']);

        AuditLogger::logSystem('attendance_monthly_scores_locked', [
            'month' => (int) $validated['month'],
            'year' => (int) $validated['year'],
            'rows' => $updated,
        ], $request->user()?->id, AttendanceMonthlyScore::class, 0);

        return back()->with('success', 'Monthly attendance scores locked.');
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('view-attendance-kpi');

        $month = max(1, min(12, (int) $request->query('month', now()->month)));
        $year = max(2000, min(2100, (int) $request->query('year', now()->year)));
        $departmentId = (int) $request->query('department_id', 0);

        $user = $request->user();
        $canViewAll = true;
        $isDeptLeader = false;

        $query = AttendanceMonthlyScore::query()
            ->with(['employee.department'])
            ->where('month', $month)
            ->where('year', $year);

        if ($canViewAll) {
            if ($departmentId > 0) {
                $query->whereHas('employee', fn ($q) => $q->where('department_id', $departmentId));
            }
        } elseif ($isDeptLeader) {
            $deptId = (int) ($user?->employee?->department_id ?? 0);
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $deptId));
        } else {
            $query->where('employee_id', (int) ($user?->employee?->id ?? 0));
        }

        $rows = $query->orderByDesc('rating')->orderByDesc('final_score')->get();
        $filename = 'attendance-kpi-' . $year . '-' . str_pad((string) $month, 2, '0', STR_PAD_LEFT) . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Employee ID', 'Employee Name', 'Department', 'Presence', 'On-Time', 'Overall', 'Rating', 'Eligibility', 'Status']);
            foreach ($rows as $row) {
                $name = trim(($row->employee?->first_name ?? '') . ' ' . ($row->employee?->last_name ?? ''));
                fputcsv($out, [
                    (string) ($row->employee?->employee_id ?? ''),
                    $name,
                    (string) ($row->employee?->department?->department ?? ''),
                    number_format((float) $row->attendance_rate, 2, '.', ''),
                    number_format((float) $row->punctuality_rate, 2, '.', ''),
                    number_format((float) $row->final_score, 2, '.', ''),
                    (int) $row->rating,
                    $row->attendance_incentive_eligible ? 'Eligible' : 'Not Eligible',
                    strtoupper((string) $row->status),
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
