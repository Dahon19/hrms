<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\RewardTitle;
use App\Models\User;
use App\Services\RewardEligibilityService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class RewardsRecognitionSeeder extends Seeder
{
    public function run(): void
    {
        if (
            !Schema::hasTable('employees')
            || !Schema::hasTable('rewards_records')
            || !Schema::hasTable('reward_titles')
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
            ->orderBy('id')
            ->get();

        if ($employees->isEmpty()) {
            return;
        }

        /** @var RewardEligibilityService $eligibilityService */
        $eligibilityService = app(RewardEligibilityService::class);
        $assignedBy = User::query()->where('role', 'admin')->value('id')
            ?? User::query()->value('id');
        $year = (int) now()->year;

        $sampleAwards = [
            [
                'employee_email' => 'hannah.reyes@example.com',
                'award_type' => 'attendance',
                'award_title' => (string) config('rewards.attendance.title', 'Perfect Attendance'),
                'award_date' => Carbon::create($year, 1, 15),
                'remarks' => 'Seeded sample recognition record for dashboard and history views.',
            ],
            [
                'employee_email' => 'paulo.cruz@example.com',
                'award_type' => 'performance',
                'award_title' => (string) config('rewards.performance.title', 'Performance Excellence'),
                'award_date' => Carbon::create($year, 2, 15),
                'remarks' => 'Seeded sample recognition record for rewards history testing.',
            ],
            [
                'employee_email' => 'marc.jenkins@example.com',
                'award_type' => 'tenure',
                'award_title' => '5-Year Service Milestone',
                'award_date' => Carbon::create($year, 3, 15),
                'remarks' => 'Seeded sample recognition record for certificate generation testing.',
            ],
        ];

        foreach ($sampleAwards as $award) {
            $employee = $employees->first(function (Employee $employee) use ($award) {
                return strcasecmp((string) $employee->user?->email, (string) $award['employee_email']) === 0;
            });

            if (!$employee) {
                continue;
            }

            $rewardTitle = RewardTitle::query()->firstOrCreate([
                'award_type' => (string) $award['award_type'],
                'title' => (string) $award['award_title'],
            ]);

            $eligibilityService->assignReward(
                employee: $employee,
                rewardTitle: $rewardTitle,
                awardDate: $award['award_date'],
                assignedByUserId: $assignedBy,
                remarks: (string) $award['remarks'],
            );
        }
    }
}
