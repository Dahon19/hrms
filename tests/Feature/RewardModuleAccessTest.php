<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\RewardRecord;
use App\Models\RewardTitle;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RewardModuleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_assign_reward(): void
    {
        [$admin, $employee] = $this->makeAdminAndEmployee('x', 6);
        RewardTitle::firstOrCreate([
            'award_type' => 'tenure',
            'title' => 'Service Milestone Recognition',
        ]);

        $response = $this->actingAs($admin)->post(route('rewards.store'), [
            'employee_id' => $employee->id,
            'reward_title_id' => RewardTitle::query()->where('title', 'Service Milestone Recognition')->value('id'),
            'award_date' => now()->toDateString(),
        ]);

        $response->assertRedirect(route('rewards.show', $employee));
        $this->assertDatabaseHas('rewards_records', [
            'employee_id' => $employee->id,
            'award_type' => 'tenure',
            'award_title' => 'Service Milestone Recognition',
        ]);
    }

    public function test_employee_cannot_assign_reward(): void
    {
        [$admin, $employee] = $this->makeAdminAndEmployee();
        unset($admin);
        RewardTitle::firstOrCreate([
            'award_type' => 'attendance',
            'title' => 'Perfect Attendance',
        ]);

        $response = $this->actingAs($employee->user)->post(route('rewards.store'), [
            'employee_id' => $employee->id,
            'reward_title_id' => RewardTitle::query()->where('title', 'Perfect Attendance')->value('id'),
            'award_date' => now()->toDateString(),
        ]);

        $response->assertForbidden();
    }

    public function test_admin_can_manage_reward_titles(): void
    {
        [$admin] = $this->makeAdminAndEmployee('crud');

        $this->actingAs($admin)
            ->post(route('rewards.titles.store'), [
                'award_type' => 'special',
                'title' => 'Leadership Excellence',
            ])
            ->assertRedirect();

        $title = RewardTitle::query()->where('title', 'Leadership Excellence')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('rewards.titles.update', $title), [
                'award_type' => 'performance',
                'title' => 'Leadership Excellence Updated',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('reward_titles', [
            'id' => $title->id,
            'award_type' => 'performance',
            'title' => 'Leadership Excellence Updated',
        ]);

        $this->actingAs($admin)
            ->delete(route('rewards.titles.destroy', $title))
            ->assertRedirect();

        $this->assertDatabaseMissing('reward_titles', [
            'id' => $title->id,
        ]);
    }

    public function test_employee_can_only_view_own_rewards(): void
    {
        [$admin, $employeeA] = $this->makeAdminAndEmployee('a');
        [$unusedAdmin, $employeeB] = $this->makeAdminAndEmployee('b');
        unset($admin, $unusedAdmin);

        RewardRecord::create([
            'employee_id' => $employeeA->id,
            'award_type' => 'performance',
            'award_title' => 'Performance Excellence',
            'award_date' => now()->toDateString(),
            'remarks' => 'Test',
        ]);

        $this->actingAs($employeeA->user)
            ->get(route('rewards.show', $employeeA))
            ->assertOk();

        $this->actingAs($employeeA->user)
            ->get(route('rewards.show', $employeeB))
            ->assertForbidden();
    }

    public function test_assignment_rejects_ineligible_regular_recognition_title(): void
    {
        [$admin, $employee] = $this->makeAdminAndEmployee('type');
        RewardTitle::firstOrCreate([
            'award_type' => 'attendance',
            'title' => 'Perfect Attendance',
        ]);

        $this->actingAs($admin)
            ->from(route('rewards.index'))
            ->post(route('rewards.store'), [
                'employee_id' => $employee->id,
                'reward_title_id' => RewardTitle::query()->where('title', 'Perfect Attendance')->value('id'),
                'award_date' => now()->toDateString(),
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('rewards_records', 0);
    }

    public function test_admin_can_assign_reward_with_past_award_date(): void
    {
        [$admin, $employee] = $this->makeAdminAndEmployee('past', 6);
        RewardTitle::firstOrCreate([
            'award_type' => 'tenure',
            'title' => 'Service Milestone Recognition',
        ]);

        $awardDate = now()->subYear()->startOfYear()->toDateString();

        $response = $this->actingAs($admin)->post(route('rewards.store'), [
            'employee_id' => $employee->id,
            'reward_title_id' => RewardTitle::query()->where('title', 'Service Milestone Recognition')->value('id'),
            'award_date' => $awardDate,
        ]);

        $response->assertRedirect(route('rewards.show', $employee));
        $this->assertDatabaseHas('rewards_records', [
            'employee_id' => $employee->id,
            'award_type' => 'tenure',
            'award_title' => 'Service Milestone Recognition',
            'award_date' => Carbon::parse($awardDate)->toDateTimeString(),
        ]);
    }

    public function test_admin_can_assign_special_recognition_without_eligibility_override(): void
    {
        [$admin, $employee] = $this->makeAdminAndEmployee('special', 2);
        RewardTitle::firstOrCreate([
            'award_type' => 'special',
            'title' => 'Special Recognition',
        ]);

        $response = $this->actingAs($admin)->post(route('rewards.store'), [
            'employee_id' => $employee->id,
            'reward_title_id' => RewardTitle::query()->where('title', 'Special Recognition')->value('id'),
            'award_date' => now()->toDateString(),
        ]);

        $response->assertRedirect(route('rewards.show', $employee));
        $this->assertDatabaseHas('rewards_records', [
            'employee_id' => $employee->id,
            'award_type' => 'special',
            'award_title' => 'Special Recognition',
        ]);
    }

    /**
     * @return array{0: User, 1: Employee}
     */
    private function makeAdminAndEmployee(string $suffix = 'x', int $yearsInService = 2): array
    {
        $department = Department::create([
            'department' => 'HR Department ' . strtoupper($suffix),
            'department_type' => 'Administrative',
        ]);

        $admin = User::create([
            'name' => 'Admin ' . strtoupper($suffix),
            'email' => 'admin-' . $suffix . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        Employee::create([
            'user_id' => $admin->id,
            'employee_id' => '26-80' . strtoupper($suffix) . '00',
            'first_name' => 'Admin',
            'last_name' => 'User',
            'department_id' => $department->id,
            'status' => 'active',
            'hire_date' => now()->subYears(1)->toDateString(),
        ]);

        $employeeUser = User::create([
            'name' => 'Employee ' . strtoupper($suffix),
            'email' => 'employee-' . $suffix . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'employee',
        ]);

        $employee = Employee::create([
            'user_id' => $employeeUser->id,
            'employee_id' => '26-81' . strtoupper($suffix) . '00',
            'first_name' => 'Regular',
            'last_name' => 'Employee',
            'department_id' => $department->id,
            'status' => 'active',
            'hire_date' => now()->subYears($yearsInService)->toDateString(),
        ]);

        return [$admin, $employee];
    }
}
