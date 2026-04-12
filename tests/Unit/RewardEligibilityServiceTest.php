<?php

namespace Tests\Unit;

use App\Models\Attendance;
use App\Models\AttendanceMonthlyScore;
use App\Models\Department;
use App\Models\Employee;
use App\Models\SpmsCycle;
use App\Models\SpmsEvaluation;
use App\Models\User;
use App\Services\RewardEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RewardEligibilityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_computes_tenure_attendance_and_performance_eligibility(): void
    {
        $department = Department::create([
            'department' => 'HR Department',
            'department_type' => 'Administrative',
        ]);

        $user = User::create([
            'name' => 'John Employee',
            'email' => 'john-eligibility@example.com',
            'password' => bcrypt('password'),
            'role' => 'employee',
        ]);

        $employee = Employee::create([
            'user_id' => $user->id,
            'employee_id' => '26-90001',
            'first_name' => 'John',
            'last_name' => 'Employee',
            'department_id' => $department->id,
            'hire_date' => now()->subYears(6)->toDateString(),
            'status' => 'active',
        ]);

        Attendance::create([
            'employee_id' => $employee->id,
            'date' => now()->toDateString(),
            'morning_time_in' => '08:00:00',
            'morning_time_out' => '12:00:00',
            'afternoon_time_in' => '13:00:00',
            'afternoon_time_out' => '17:00:00',
            'status' => 'present',
        ]);

        AttendanceMonthlyScore::create([
            'employee_id' => $employee->id,
            'month' => (int) now()->month,
            'year' => (int) now()->year,
            'total_work_days' => 22,
            'total_absences' => 0,
            'late_undertime_days' => 0,
            'attendance_rate' => 100,
            'punctuality_rate' => 100,
            'final_score' => 100,
            'rating' => 5,
            'attendance_incentive_eligible' => true,
            'status' => 'computed',
        ]);

        $cycle = SpmsCycle::create([
            'title' => 'Locked SPMS Cycle',
            'period_start' => now()->startOfYear()->toDateString(),
            'period_end' => now()->endOfYear()->toDateString(),
            'status' => 'closed',
        ]);

        SpmsEvaluation::create([
            'employee_id' => $employee->id,
            'cycle_id' => $cycle->id,
            'evaluator_id' => $user->id,
            'status' => 'final',
            'total_score' => 4.70,
            'rating_label' => 'outstanding',
        ]);

        $service = app(RewardEligibilityService::class);
        $eligibility = $service->buildEligibility($employee);

        $this->assertTrue($eligibility['tenure']['eligible']);
        $this->assertSame(5, $eligibility['tenure']['milestone']);
        $this->assertTrue($eligibility['attendance']['eligible']);
        $this->assertTrue($eligibility['performance']['eligible']);
    }
}
