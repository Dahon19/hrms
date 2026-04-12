<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\IndividualDevelopmentPlan;
use App\Models\SpmsCycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdpEmployeeViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_sees_self_service_idp_view_without_management_filters(): void
    {
        $department = Department::create([
            'department' => 'HR Department',
            'department_type' => 'Administrative',
        ]);

        $user = User::create([
            'name' => 'Employee User',
            'email' => 'idp-employee@example.com',
            'password' => bcrypt('password'),
            'role' => 'employee',
        ]);

        $employee = Employee::create([
            'user_id' => $user->id,
            'employee_id' => '26-77100',
            'first_name' => 'Regular',
            'last_name' => 'Employee',
            'department_id' => $department->id,
            'status' => 'active',
            'hire_date' => now()->subYears(2)->toDateString(),
        ]);

        $cycle = SpmsCycle::create([
            'title' => 'SPMS 2026',
            'period_start' => now()->startOfYear()->toDateString(),
            'period_end' => now()->endOfYear()->toDateString(),
            'status' => 'closed',
        ]);

        IndividualDevelopmentPlan::create([
            'employee_id' => $employee->id,
            'spms_cycle_id' => $cycle->id,
            'status' => 'draft',
            'development_goals' => 'Attend coaching sessions.',
            'employee_notes' => 'Needs communication training.',
            'final_spms_score' => 3.75,
            'final_spms_rating' => 'satisfactory',
            'competency_gaps' => [
                ['name' => 'Communication', 'score' => 3.0],
            ],
        ]);

        $response = $this->actingAs($user)->get(route('idp.index'));

        $response->assertOk();
        $response->assertSee('My Development Plans');
        $response->assertSee('Review your development plan, competency gaps, and recorded action items from finalized SPMS cycles.');
        $response->assertDontSee('IDP Directory');
        $response->assertDontSee('Search, filter, and update employee development plans.');
        $response->assertDontSee('idpDepartmentFilter', false);
        $response->assertDontSee('idpPositionFilter', false);
        $response->assertDontSee('idpSearchInput', false);
        $response->assertSee('Cycle');
        $response->assertSee('Status');
    }
}
