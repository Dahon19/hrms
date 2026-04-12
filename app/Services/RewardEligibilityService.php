<?php

namespace App\Services;

use App\Domain\TravelOrders\Services\TravelOrderAttendanceService;
use App\Models\Attendance;
use App\Models\AttendanceMonthlyScore;
use App\Models\Employee;
use App\Models\RewardRecord;
use App\Models\RewardTitle;
use App\Models\SpmsEvaluation;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RewardEligibilityService
{
    public function __construct(
        private readonly AttendanceKpiScoringService $attendanceKpiScoringService
    ) {
    }

    public function buildEligibility(Employee $employee, ?int $year = null): array
    {
        $year = $year ?: (int) now()->year;

        $tenure = $this->tenureEligibility($employee);
        $attendance = $this->attendanceEligibility($employee, $year);
        $performance = $this->performanceEligibility($employee, $year);
        $eligibleRewardTypes = [];
        $ineligibleReasons = [];

        foreach ([
            'tenure' => $tenure,
            'attendance' => $attendance,
            'performance' => $performance,
        ] as $type => $result) {
            if ((bool) ($result['eligible'] ?? false)) {
                $eligibleRewardTypes[] = $type;
                continue;
            }

            $ineligibleReasons[$type] = (string) ($result['reason'] ?? 'Did not meet eligibility criteria.');
        }

        return [
            'tenure' => $tenure,
            'attendance' => $attendance,
            'attendance_incentive_eligible' => (bool) ($attendance['attendance_incentive_eligible'] ?? false),
            'performance' => $performance,
            'eligible_reward_types' => $eligibleRewardTypes,
            'ineligible_reasons' => $ineligibleReasons,
            'computed_year' => $year,
            'computed_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toEligibilityCachePayload(array $eligibility): array
    {
        return [
            'eligible_tenure' => (bool) data_get($eligibility, 'tenure.eligible', false),
            'eligible_attendance' => (bool) data_get($eligibility, 'attendance.eligible', false),
            'eligible_performance' => (bool) data_get($eligibility, 'performance.eligible', false),
            'tenure_years' => (int) data_get($eligibility, 'tenure.years', 0),
            'tenure_milestone' => data_get($eligibility, 'tenure.milestone'),
            'attendance_score' => data_get($eligibility, 'attendance.final_score'),
            'attendance_rating' => data_get($eligibility, 'attendance.rating'),
            'spms_score' => data_get($eligibility, 'performance.score'),
            'spms_rating' => data_get($eligibility, 'performance.rating'),
            'payload' => $eligibility,
            'computed_at' => now(),
        ];
    }

    public function assignReward(
        Employee $employee,
        RewardTitle $rewardTitle,
        Carbon $awardDate,
        ?int $assignedByUserId = null,
        ?string $remarks = null
    ): RewardRecord {
        $awardType = (string) $rewardTitle->award_type;
        $awardTitle = (string) $rewardTitle->title;

        // Award date is the issuance/recognition date, not the basis year for current eligibility validation.
        $eligibilityYear = (int) now()->year;
        $eligibility = $this->buildEligibility($employee, $eligibilityYear);
        $qualifies = (bool) data_get($eligibility, $awardType . '.eligible', false);

        if ($awardType !== 'special' && !$qualifies) {
            abort(422, 'Employee is not eligible for this reward type based on current criteria.');
        }

        return DB::transaction(function () use ($employee, $awardType, $awardTitle, $awardDate, $assignedByUserId, $remarks, $eligibility, $eligibilityYear) {
            $milestoneType = match ($awardType) {
                'tenure' => data_get($eligibility, 'tenure.milestone') ? ('tenure_' . data_get($eligibility, 'tenure.milestone') . '_years') : null,
                'attendance' => 'attendance_kpi',
                'performance' => (string) data_get($eligibility, 'performance.rating', 'spms_finalized'),
                'special' => 'special_recognition',
                default => null,
            };

            return RewardRecord::query()->firstOrCreate([
                'employee_id' => $employee->id,
                'award_type' => $awardType,
                'award_title' => $awardTitle,
                'award_date' => $awardDate->toDateString(),
            ], [
                'milestone_type' => $milestoneType,
                'eligibility_reference' => 'employee:' . $employee->id . '|year:' . $eligibilityYear,
                'remarks' => $remarks,
                'assigned_by' => $assignedByUserId,
                'override_used' => false,
                'override_reason' => null,
            ]);
        });
    }

    public function assignableRewardTypes(Employee $employee, ?int $year = null): array
    {
        $eligibility = $this->buildEligibility($employee, $year);

        return collect((array) data_get($eligibility, 'eligible_reward_types', []))
            ->filter(fn ($type) => in_array($type, ['tenure', 'attendance', 'performance'], true))
            ->push('special')
            ->unique()
            ->values()
            ->all();
    }

    public function assignableRewardTitles(Employee $employee, ?int $year = null): Collection
    {
        return RewardTitle::query()
            ->whereIn('award_type', $this->assignableRewardTypes($employee, $year))
            ->orderBy('award_type')
            ->orderBy('title')
            ->get();
    }

    private function tenureEligibility(Employee $employee): array
    {
        if (!$employee->hire_date) {
            return [
                'eligible' => false,
                'milestone' => null,
                'years' => 0,
                'title' => null,
                'reason' => 'Hire date is not available.',
            ];
        }

        $years = Carbon::parse($employee->hire_date)->diffInYears(now());
        $milestones = collect((array) config('rewards.tenure_milestones_years', [5, 10, 15, 20]))
            ->map(fn ($value) => (int) $value)
            ->filter(fn ($value) => $value > 0)
            ->sort()
            ->values();

        $milestone = $milestones->filter(fn ($m) => $years >= $m)->last();

        return [
            'eligible' => $milestone !== null,
            'milestone' => $milestone,
            'years' => $years,
            'title' => $milestone ? ($milestone . '-Year Service Milestone') : null,
            'reason' => $milestone !== null
                ? 'Eligible for tenure milestone reward.'
                : 'Employee has not reached a configured tenure milestone yet.',
        ];
    }

    private function attendanceEligibility(Employee $employee, int $year): array
    {
        $now = now();
        $month = $year === (int) $now->year ? (int) $now->month : 12;

        $score = AttendanceMonthlyScore::query()
            ->where('employee_id', $employee->id)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        if (!$score) {
            $score = $this->attendanceKpiScoringService->getOrComputeEmployeeScore($employee->id, $month, $year);
        }

        if ($score) {
            $eligible = (bool) $score->attendance_incentive_eligible;
            return [
                'eligible' => $eligible,
                'attendance_incentive_eligible' => $eligible,
                'period' => 'monthly',
                'year' => $year,
                'month' => $month,
                'total_records' => (int) $score->total_work_days,
                'absent_days' => (int) $score->total_absences,
                'late_undertime_days' => (int) $score->late_undertime_days,
                'attendance_rate' => (float) $score->attendance_rate,
                'punctuality_rate' => (float) $score->punctuality_rate,
                'final_score' => (float) $score->final_score,
                'rating' => (int) $score->rating,
                'status' => (string) $score->status,
                'reason' => $eligible ? 'Qualified by attendance KPI rating.' : 'Attendance KPI rating below required threshold (4/5).',
                'title' => (string) config('rewards.attendance.title', 'Attendance Incentive Qualification'),
            ];
        }

        // Fallback when no monthly score exists.
        $attendanceQuery = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereYear('date', $year)
            ->whereMonth('date', $month);
        $totalRecords = (clone $attendanceQuery)->count();
        $absentDays = (clone $attendanceQuery)->where('status', 'absent')->count();
        $officialBusinessDays = app(TravelOrderAttendanceService::class)->approvedTravelWorkDays(
            $employee,
            Carbon::create($year, $month, 1)->startOfMonth(),
            Carbon::create($year, $month, 1)->endOfMonth()
        );
        $approvedLeaveDays = app(AttendanceCalendarService::class)->approvedLeaveWorkDays(
            $employee,
            Carbon::create($year, $month, 1)->startOfMonth(),
            Carbon::create($year, $month, 1)->endOfMonth()
        );
        $holidayDays = app(AttendanceCalendarService::class)->countHolidayWorkDays(
            Carbon::create($year, $month, 1)->startOfMonth(),
            Carbon::create($year, $month, 1)->endOfMonth()
        );
        $absentDays = max(0, $absentDays - $officialBusinessDays - $approvedLeaveDays);
        $totalRecords = max(0, $totalRecords - $holidayDays) + $officialBusinessDays + $approvedLeaveDays;
        $eligible = $totalRecords > 0 && $absentDays <= 1;

        return [
            'eligible' => $eligible,
            'attendance_incentive_eligible' => $eligible,
            'period' => 'monthly',
            'year' => $year,
            'month' => $month,
            'total_records' => $totalRecords,
            'absent_days' => $absentDays,
            'rating' => $eligible ? 4 : 1,
            'reason' => $eligible ? 'Fallback monthly attendance check passed.' : 'Fallback monthly attendance check failed.',
            'title' => (string) config('rewards.attendance.title', 'Attendance Incentive Qualification'),
        ];
    }

    private function performanceEligibility(Employee $employee, int $year): array
    {
        $minimumScore = (float) config('rewards.performance.minimum_score', 4.50);
        $qualifyingRatings = collect((array) config('rewards.performance.qualifying_ratings', ['outstanding', 'very_satisfactory']))
            ->map(fn ($rating) => strtolower((string) $rating))
            ->values()
            ->all();

        $spmsEvaluation = SpmsEvaluation::query()
            ->where('employee_id', $employee->id)
            ->where('status', SpmsEvaluation::STATUS_FINAL)
            ->whereHas('cycle', function ($query) use ($year) {
                $query->whereYear('period_end', $year);
            })
            ->latest('id')
            ->first();

        if (!$spmsEvaluation) {
            return [
                'eligible' => false,
                'review_year' => null,
                'score' => null,
                'raw_total_score' => null,
                'rating' => null,
                'source_status' => null,
                'minimum_score' => $minimumScore,
                'qualifying_ratings' => $qualifyingRatings,
                'reason' => 'No finalized SPMS evaluation found.',
                'title' => (string) config('rewards.performance.title', 'Performance Excellence'),
            ];
        }

        $totalScore = (float) $spmsEvaluation->total_score;
        $normalizedScore = round($totalScore, 2);
        $derivedRating = (string) ($spmsEvaluation->rating_label ?: match (true) {
            $totalScore >= 4.50 => 'outstanding',
            $totalScore >= 3.50 => 'very_satisfactory',
            $totalScore >= 2.50 => 'satisfactory',
            $totalScore >= 1.50 => 'unsatisfactory',
            default => 'poor',
        });

        $eligible = $normalizedScore >= $minimumScore
            || in_array($derivedRating, $qualifyingRatings, true);

        return [
            'eligible' => $eligible,
            'review_year' => optional($spmsEvaluation->cycle?->period_end)->year,
            'score' => $normalizedScore,
            'raw_total_score' => $totalScore,
            'rating' => $derivedRating,
            'source_status' => (string) $spmsEvaluation->status,
            'minimum_score' => $minimumScore,
            'qualifying_ratings' => $qualifyingRatings,
            'reason' => $eligible
                ? 'Qualified by finalized SPMS evaluation.'
                : 'SPMS score/rating did not meet the configured threshold.',
            'title' => (string) config('rewards.performance.title', 'Performance Excellence'),
        ];
    }
}
