<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\IndividualDevelopmentPlan;
use App\Models\SpmsCriterion;
use App\Models\SpmsCycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpmsLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_evaluator_can_save_and_submit_spms_evaluation(): void
    {
        [$admin, $employee] = $this->seedSpmsContext();

        $cycle = SpmsCycle::create([
            'title' => 'SPMS Test Cycle',
            'period_start' => now()->startOfYear()->toDateString(),
            'period_end' => now()->endOfYear()->toDateString(),
            'status' => 'evaluation',
        ]);

        $criterion = SpmsCriterion::create([
            'name' => 'Quality of Work',
            'max_score' => 5,
            'category' => 'core',
            'weight' => 1,
        ]);

        $response = $this->actingAs($admin)->post(route('spms.evaluation.save'), [
            'employee_id' => $employee->id,
            'cycle_id' => $cycle->id,
            'intent' => 'submitted',
            'details' => [
                ['criteria_id' => $criterion->id, 'score' => 4.5, 'remarks' => 'Good quality'],
            ],
        ]);

        $response->assertRedirect(route('spms.evaluation.show', ['employee' => $employee->id, 'cycle' => $cycle->id]));
        $this->assertDatabaseHas('spms_evaluations', [
            'employee_id' => $employee->id,
            'cycle_id' => $cycle->id,
            'status' => 'submitted',
        ]);
    }

    public function test_locking_cycle_generates_idp_drafts(): void
    {
        [$admin, $employee] = $this->seedSpmsContext();

        $cycle = SpmsCycle::create([
            'title' => 'SPMS Locked Cycle',
            'period_start' => now()->startOfYear()->toDateString(),
            'period_end' => now()->endOfYear()->toDateString(),
            'status' => 'evaluation',
        ]);

        SpmsCriterion::create([
            'name' => 'Quality of Work',
            'max_score' => 5,
            'category' => 'core',
            'weight' => 1,
        ]);

        $evaluation = \App\Models\SpmsEvaluation::create([
            'employee_id' => $employee->id,
            'cycle_id' => $cycle->id,
            'evaluator_id' => $admin->id,
            'status' => 'submitted',
            'total_score' => 3.20,
            'rating_label' => 'satisfactory',
        ]);

        $response = $this->actingAs($admin)->post(route('spms.cycle.lock', $cycle));

        $response->assertRedirect();
        $this->assertDatabaseHas('spms_evaluations', [
            'id' => $evaluation->id,
            'status' => 'final',
        ]);
        $this->assertDatabaseHas('individual_development_plans', [
            'employee_id' => $employee->id,
            'spms_cycle_id' => $cycle->id,
        ]);
        $this->assertSame(1, IndividualDevelopmentPlan::query()->count());
    }

    private function seedSpmsContext(): array
    {
        $department = Department::create([
            'department' => 'HR Department',
            'department_type' => 'Administrative',
        ]);

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'spms-admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        Employee::create([
            'user_id' => $admin->id,
            'employee_id' => '26-66000',
            'first_name' => 'Admin',
            'last_name' => 'User',
            'department_id' => $department->id,
            'status' => 'active',
            'hire_date' => now()->subYears(2)->toDateString(),
        ]);

        $employeeUser = User::create([
            'name' => 'Regular Employee',
            'email' => 'spms-employee@example.com',
            'password' => bcrypt('password'),
            'role' => 'employee',
        ]);

        $employee = Employee::create([
            'user_id' => $employeeUser->id,
            'employee_id' => '26-66001',
            'first_name' => 'Regular',
            'last_name' => 'Employee',
            'department_id' => $department->id,
            'status' => 'active',
            'hire_date' => now()->subYears(3)->toDateString(),
        ]);

        return [$admin, $employee];
    }
}
