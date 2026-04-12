<?php

namespace App\Services;

use App\Domain\TravelOrders\Services\TravelOrderAttendanceService;
use App\Models\Attendance;
use App\Models\AttendanceAnomaly;
use App\Models\AttendanceKpi;
use App\Models\AttendanceMonthlyScore;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AttendanceKpiScoringService
{
    public function activeKpiForPeriod(int $month, int $year): ?AttendanceKpi
    {
        return AttendanceKpi::query()
            ->where('month', $month)
            ->where('year', $year)
            ->where('is_active', true)
            ->first();
    }

    public function upsertKpi(int $month, int $year, float $targetPercentage, int $createdBy): AttendanceKpi
    {
        return DB::transaction(function () use ($month, $year, $targetPercentage, $createdBy) {
            AttendanceKpi::query()
                ->where('month', $month)
                ->where('year', $year)
                ->update(['is_active' => false]);

            return AttendanceKpi::query()->updateOrCreate(
                ['month' => $month, 'year' => $year],
                [
                    'target_percentage' => $targetPercentage,
                    'is_active' => true,
                    'created_by' => $createdBy,
                ]
            );
        });
    }

    public function computeMonthlyScores(int $month, int $year, bool $force = false): Collection
    {
        $kpi = $this->activeKpiForPeriod($month, $year);
        $target = (float) ($kpi?->target_percentage ?? 100.0);

        $employees = Employee::query()
            ->where('status', 'active')
            ->orderBy('id')
            ->get();

        $scores = collect();

        DB::transaction(function () use ($employees, $month, $year, $target, $force, &$scores) {
            foreach ($employees as $employee) {
                $score = $this->computeEmployeeScore((int) $employee->id, $month, $year, $target, $force);
                if ($score) {
                    $scores->push($score);
                }
            }
        });

        return $scores;
    }

    public function computeEmployeeScore(
        int $employeeId,
        int $month,
        int $year,
        ?float $targetPercentage = null,
        bool $force = false
    ): ?AttendanceMonthlyScore {
        $score = AttendanceMonthlyScore::query()
            ->where('employee_id', $employeeId)
            ->where('month', $month)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        if ($score && $score->status === 'locked' && !$force) {
            return $score;
        }

        $employee = Employee::query()->find($employeeId);
        if (!$employee) {
            return null;
        }

        $target = $targetPercentage ?? (float) ($this->activeKpiForPeriod($month, $year)?->target_percentage ?? 100.0);
        $metrics = $this->computeMetrics($employee, $month, $year);

        return AttendanceMonthlyScore::query()->updateOrCreate(
            [
                'employee_id' => $employeeId,
                'month' => $month,
                'year' => $year,
            ],
            [
                'total_work_days' => $metrics['total_work_days'],
                'total_absences' => $metrics['total_absences'],
                'late_undertime_days' => $metrics['late_undertime_days'],
                'attendance_rate' => $metrics['attendance_rate'],
                'punctuality_rate' => $metrics['punctuality_rate'],
                'final_score' => $metrics['final_score'],
                'rating' => $this->resolveRating($metrics['final_score'], $target),
                'attendance_incentive_eligible' => $this->isEligibleForIncentiveByScore($metrics['final_score'], $target),
                'status' => $score?->status === 'locked' ? 'locked' : 'computed',
            ]
        );
    }

    public function lockMonth(int $month, int $year): int
    {
        return AttendanceMonthlyScore::query()
            ->where('month', $month)
            ->where('year', $year)
            ->where('status', 'computed')
            ->update(['status' => 'locked']);
    }

    public function getOrComputeEmployeeScore(int $employeeId, int $month, int $year): ?AttendanceMonthlyScore
    {
        $existing = AttendanceMonthlyScore::query()
            ->where('employee_id', $employeeId)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($employeeId, $month, $year) {
            return $this->computeEmployeeScore($employeeId, $month, $year);
        });
    }

    public function resolveRating(float $finalScore, float $target): int
    {
        if ($finalScore >= $target) {
            return 5;
        }

        return match (true) {
            $finalScore >= 90 => 4,
            $finalScore >= 80 => 3,
            $finalScore >= 70 => 2,
            default => 1,
        };
    }

    public function isEligibleForIncentiveByRating(int $rating): bool
    {
        return in_array($rating, [4, 5], true);
    }

    public function isEligibleForIncentiveByScore(float $finalScore, float $target): bool
    {
        return $this->isEligibleForIncentiveByRating($this->resolveRating($finalScore, $target));
    }

    /**
     * @return array{total_work_days:int,total_absences:int,late_undertime_days:int,attendance_rate:float,punctuality_rate:float,final_score:float}
     */
    private function computeMetrics(Employee $employee, int $month, int $year): array
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();
        if ($end->greaterThan(now())) {
            $end = now()->copy()->endOfDay();
        }

        $hireDate = $employee->hire_date ? Carbon::parse($employee->hire_date)->startOfDay() : null;
        if ($hireDate && $hireDate->greaterThan($start)) {
            $start = $hireDate->copy();
        }

        if ($start->greaterThan($end)) {
            return [
                'total_work_days' => 0,
                'total_absences' => 0,
                'late_undertime_days' => 0,
                'attendance_rate' => 0.0,
                'punctuality_rate' => 0.0,
                'final_score' => 0.0,
            ];
        }

        $totalWorkDays = $this->countWorkDays($start, $end);
        $holidayWorkDays = app(AttendanceCalendarService::class)->countHolidayWorkDays($start, $end);
        $totalWorkDays = max(0, $totalWorkDays - $holidayWorkDays);
        if ($totalWorkDays <= 0) {
            return [
                'total_work_days' => 0,
                'total_absences' => 0,
                'late_undertime_days' => 0,
                'attendance_rate' => 0.0,
                'punctuality_rate' => 0.0,
                'final_score' => 0.0,
            ];
        }

        $explicitAbsences = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->where('status', 'absent')
            ->count();

        $presentOrLateDays = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->whereIn('status', ['present', 'late'])
            ->distinct('date')
            ->count('date');

        $derivedAbsences = max(0, $totalWorkDays - $presentOrLateDays);
        $officialBusinessDays = app(TravelOrderAttendanceService::class)->approvedTravelWorkDays($employee, $start, $end);
        $approvedLeaveDays = app(AttendanceCalendarService::class)->approvedLeaveWorkDays($employee, $start, $end);
        $totalAbsences = max(0, max($explicitAbsences, $derivedAbsences) - $officialBusinessDays - $approvedLeaveDays);

        $lateUndertimeDays = AttendanceAnomaly::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->whereIn('type', ['late', 'undertime'])
            ->distinct('date')
            ->count('date');

        $attendanceRate = round((($totalWorkDays - min($totalAbsences, $totalWorkDays)) / $totalWorkDays) * 100, 2);
        $punctualityRate = round((($totalWorkDays - min($lateUndertimeDays, $totalWorkDays)) / $totalWorkDays) * 100, 2);
        $finalScore = round(($attendanceRate + $punctualityRate) / 2, 2);

        return [
            'total_work_days' => $totalWorkDays,
            'total_absences' => $totalAbsences,
            'late_undertime_days' => $lateUndertimeDays,
            'attendance_rate' => $attendanceRate,
            'punctuality_rate' => $punctualityRate,
            'final_score' => $finalScore,
        ];
    }

    private function countWorkDays(Carbon $start, Carbon $end): int
    {
        $days = 0;
        $cursor = $start->copy();
        while ($cursor->lessThanOrEqualTo($end)) {
            if (!$cursor->isWeekend()) {
                $days++;
            }
            $cursor->addDay();
        }

        return $days;
    }
}
