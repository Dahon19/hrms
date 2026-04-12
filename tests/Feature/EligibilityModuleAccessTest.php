<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\PerformanceReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EligibilityModuleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_eligibility_index(): void
    {
        [$admin, $employee] = $this->makeAdminAndEmployee();
        unset($employee);

        $this->actingAs($admin)
            ->get(route('eligibility.index'))
            ->assertOk();
    }

    public function test_employee_cannot_view_global_eligibility_index(): void
    {
        [$admin, $employee] = $this->makeAdminAndEmployee('b');
        unset($admin);

        $this->actingAs($employee->user)
            ->get(route('eligibility.index'))
            ->assertForbidden();
    }

    public function test_employee_can_view_own_eligibility_but_not_others(): void
    {
        [$admin, $employeeA] = $this->makeAdminAndEmployee('c');
        [$unusedAdmin, $employeeB] = $this->makeAdminAndEmployee('d');
        unset($admin, $unusedAdmin);

        $this->actingAs($employeeA->user)
            ->get(route('eligibility.show', $employeeA))
            ->assertOk();

        $this->actingAs($employeeA->user)
            ->get(route('eligibility.show', $employeeB))
            ->assertForbidden();
    }

    public function test_admin_can_print_eligibility_report(): void
    {
        [$admin, $employee] = $this->makeAdminAndEmployee('e');
        PerformanceReview::create([
            'employee_id' => $employee->id,
            'review_year' => (int) now()->year,
            'spms_score' => 4.75,
            'rating' => 'outstanding',
        ]);

        $this->actingAs($admin)
            ->get(route('eligibility.print'))
            ->assertOk();
    }

    /**
     * @return array{0: User, 1: Employee}
     */
    private function makeAdminAndEmployee(string $suffix = 'a'): array
    {
        $department = Department::create([
            'department' => 'HR Department ' . strtoupper($suffix),
            'department_type' => 'Administrative',
        ]);

        $admin = User::create([
            'name' => 'Admin ' . strtoupper($suffix),
            'email' => 'admin-eligibility-' . $suffix . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        Employee::create([
            'user_id' => $admin->id,
            'employee_id' => '26-70' . strtoupper($suffix) . '00',
            'first_name' => 'Admin',
            'last_name' => 'User',
            'department_id' => $department->id,
            'status' => 'active',
            'hire_date' => now()->subYears(1)->toDateString(),
        ]);

        $employeeUser = User::create([
            'name' => 'Employee ' . strtoupper($suffix),
            'email' => 'employee-eligibility-' . $suffix . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'employee',
        ]);

        $employee = Employee::create([
            'user_id' => $employeeUser->id,
            'employee_id' => '26-71' . strtoupper($suffix) . '00',
            'first_name' => 'Regular',
            'last_name' => 'Employee',
            'department_id' => $department->id,
            'status' => 'active',
            'hire_date' => now()->subYears(6)->toDateString(),
        ]);

        return [$admin, $employee];
    }
}

