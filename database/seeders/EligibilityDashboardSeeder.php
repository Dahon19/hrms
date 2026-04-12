<?php

namespace Database\Seeders;

use App\Models\EligibilityCache;
use App\Models\Employee;
use App\Services\RewardEligibilityService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class EligibilityDashboardSeeder extends Seeder
{
    public function run(): void
    {
        if (
            !Schema::hasTable('employees')
            || !Schema::hasTable('eligibility_cache')
        ) {
            return;
        }

        $employees = Employee::query()
            ->with('user')
            ->where('status', 'active')
            ->whereHas('user', function ($query) {
                $query->where('role', '!=', 'admin')
                    ->whereNull('archived_at');
            })
            ->get();

        if ($employees->isEmpty()) {
            return;
        }

        /** @var RewardEligibilityService $eligibilityService */
        $eligibilityService = app(RewardEligibilityService::class);
        $year = (int) now()->year;
        $hasEligibleRows = false;

        foreach ($employees as $employee) {
            $eligibility = $eligibilityService->buildEligibility($employee, $year);
            $hasEligibleRows = $hasEligibleRows || !empty($eligibility['eligible_reward_types'] ?? []);

            $this->persistEligibility($employee, $year, $eligibilityService, $eligibility);
        }

        if (!$hasEligibleRows) {
            $this->seedFallbackEligibilityRows($employees, $year, $eligibilityService);
        }
    }

    private function persistEligibility(
        Employee $employee,
        int $year,
        RewardEligibilityService $eligibilityService,
        array $eligibility
    ): void {
        EligibilityCache::query()->updateOrCreate(
            [
                'employee_id' => $employee->id,
                'year' => $year,
            ],
            $eligibilityService->toEligibilityCachePayload($eligibility)
        );
    }

    private function seedFallbackEligibilityRows(
        Collection $employees,
        int $year,
        RewardEligibilityService $eligibilityService
    ): void {
        $employeesByEmail = $employees
            ->filter(fn (Employee $employee) => filled($employee->user?->email))
            ->keyBy(fn (Employee $employee) => strtolower((string) $employee->user?->email));

        $fallbackTargets = [
            'hannah.reyes@example.com' => function (array $eligibility): array {
                $eligibility['tenure'] = [
                    'eligible' => true,
                    'milestone' => 5,
                    'years' => 5,
                    'title' => '5-Year Service Milestone',
                    'reason' => 'Seeded demo tenure eligibility for dashboard visibility.',
                ];

                return $eligibility;
            },
            'paulo.cruz@example.com' => function (array $eligibility): array {
                $eligibility['attendance'] = [
                    'eligible' => true,
                    'attendance_incentive_eligible' => true,
                    'period' => 'monthly',
                    'year' => (int) now()->year,
                    'month' => (int) now()->month,
                    'total_records' => 22,
                    'absent_days' => 0,
                    'late_undertime_days' => 0,
                    'attendance_rate' => 100.0,
                    'punctuality_rate' => 98.5,
                    'final_score' => 99.25,
                    'rating' => 5,
                    'status' => 'qualified',
                    'reason' => 'Seeded demo attendance eligibility for dashboard visibility.',
                    'title' => (string) config('rewards.attendance.title', 'Attendance Incentive Qualification'),
                ];
                $eligibility['attendance_incentive_eligible'] = true;

                return $eligibility;
            },
            'marc.jenkins@example.com' => function (array $eligibility): array {
                $eligibility['performance'] = [
                    'eligible' => true,
                    'review_year' => (int) now()->year,
                    'score' => 4.75,
                    'raw_total_score' => 4.75,
                    'rating' => 'outstanding',
                    'source_status' => 'final',
                    'minimum_score' => (float) config('rewards.performance.minimum_score', 4.50),
                    'qualifying_ratings' => collect((array) config('rewards.performance.qualifying_ratings', ['outstanding', 'very_satisfactory']))
                        ->map(fn ($rating) => strtolower((string) $rating))
                        ->values()
                        ->all(),
                    'reason' => 'Seeded demo SPMS eligibility for dashboard visibility.',
                    'title' => (string) config('rewards.performance.title', 'Performance Excellence'),
                ];

                return $eligibility;
            },
        ];

        foreach ($fallbackTargets as $email => $mutator) {
            /** @var Employee|null $employee */
            $employee = $employeesByEmail->get($email);

            if (!$employee) {
                continue;
            }

            $eligibility = $eligibilityService->buildEligibility($employee, $year);
            $eligibility = $mutator($eligibility);
            $eligibility['eligible_reward_types'] = collect([
                data_get($eligibility, 'tenure.eligible') ? 'tenure' : null,
                data_get($eligibility, 'attendance.eligible') ? 'attendance' : null,
                data_get($eligibility, 'performance.eligible') ? 'performance' : null,
            ])->filter()->values()->all();

            $eligibility['ineligible_reasons'] = collect([
                'tenure' => data_get($eligibility, 'tenure.eligible')
                    ? null
                    : (string) data_get($eligibility, 'tenure.reason', 'Did not meet eligibility criteria.'),
                'attendance' => data_get($eligibility, 'attendance.eligible')
                    ? null
                    : (string) data_get($eligibility, 'attendance.reason', 'Did not meet eligibility criteria.'),
                'performance' => data_get($eligibility, 'performance.eligible')
                    ? null
                    : (string) data_get($eligibility, 'performance.reason', 'Did not meet eligibility criteria.'),
            ])->filter()->all();

            $this->persistEligibility($employee, $year, $eligibilityService, $eligibility);
        }
    }
}
