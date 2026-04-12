<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\EligibilityCache;
use App\Models\Employee;
use App\Models\RewardRecord;
use App\Models\RewardTitle;
use App\Models\User;
use App\Services\RewardEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RewardsAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rewards_compute_eligibility_command_populates_cache(): void
    {
        [$admin, $employee] = $this->makeAdminAndEmployee('cache', 6);
        unset($admin);

        $this->assertDatabaseCount('eligibility_cache', 0);

        $this->artisan('rewards:compute-eligibility')
            ->assertExitCode(0);

        $this->assertDatabaseHas('eligibility_cache', [
            'employee_id' => $employee->id,
            'year' => (int) now()->year,
        ]);

        $cache = EligibilityCache::query()
            ->where('employee_id', $employee->id)
            ->where('year', (int) now()->year)
            ->first();

        $this->assertNotNull($cache);
        $this->assertTrue(is_array($cache->payload));
    }

    public function test_ineligible_non_special_reward_assignment_is_rejected(): void
    {
        [$admin, $employee] = $this->makeAdminAndEmployee('override', 2);
        $service = app(RewardEligibilityService::class);
        $rewardTitle = RewardTitle::query()->firstOrCreate([
            'award_type' => 'tenure',
            'title' => 'Service Milestone Recognition',
        ]);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('not eligible');

        $service->assignReward(
            employee: $employee,
            rewardTitle: $rewardTitle,
            awardDate: now(),
            assignedByUserId: $admin->id
        );

        $this->assertDatabaseCount('rewards_records', 0);
    }

    public function test_admin_reward_assignment_stores_immutable_rr_metadata(): void
    {
        [$admin, $employee] = $this->makeAdminAndEmployee('assign', 6);
        $service = app(RewardEligibilityService::class);
        $rewardTitle = RewardTitle::query()->firstOrCreate([
            'award_type' => 'tenure',
            'title' => 'Service Milestone Recognition',
        ]);

        $service->assignReward(
            employee: $employee,
            rewardTitle: $rewardTitle,
            awardDate: now(),
            assignedByUserId: $admin->id
        );

        $this->assertDatabaseHas('rewards_records', [
            'employee_id' => $employee->id,
            'award_type' => 'tenure',
            'override_used' => 0,
            'override_reason' => null,
            'assigned_by' => $admin->id,
        ]);
    }

    public function test_reward_records_are_immutable(): void
    {
        [$admin, $employee] = $this->makeAdminAndEmployee('immutable', 6);

        $record = RewardRecord::query()->create([
            'employee_id' => $employee->id,
            'award_type' => 'tenure',
            'award_title' => '5-Year Service Milestone',
            'award_date' => now()->toDateString(),
            'assigned_by' => $admin->id,
            'override_used' => false,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('immutable');

        $record->award_title = 'Changed Title';
        $record->save();
    }

    /**
     * @return array{0: User, 1: Employee}
     */
    private function makeAdminAndEmployee(string $suffix, int $yearsInService): array
    {
        $department = Department::create([
            'department' => 'HR Department ' . strtoupper($suffix),
            'department_type' => 'Administrative',
        ]);

        $admin = User::create([
            'name' => 'Admin ' . strtoupper($suffix),
            'email' => 'admin-rr-' . $suffix . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        Employee::create([
            'user_id' => $admin->id,
            'employee_id' => '26-91' . strtoupper(substr($suffix, 0, 1)) . '00',
            'first_name' => 'Admin',
            'last_name' => 'User',
            'department_id' => $department->id,
            'status' => 'active',
            'hire_date' => now()->subYears(1)->toDateString(),
        ]);

        $employeeUser = User::create([
            'name' => 'Employee ' . strtoupper($suffix),
            'email' => 'employee-rr-' . $suffix . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'employee',
        ]);

        $employee = Employee::create([
            'user_id' => $employeeUser->id,
            'employee_id' => '26-92' . strtoupper(substr($suffix, 0, 1)) . '00',
            'first_name' => 'Regular',
            'last_name' => 'Employee',
            'department_id' => $department->id,
            'status' => 'active',
            'hire_date' => now()->subYears($yearsInService)->toDateString(),
        ]);

        return [$admin, $employee];
    }
}
