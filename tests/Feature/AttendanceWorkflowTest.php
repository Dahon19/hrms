<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeePosition;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Position;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeAttendanceEmployee(string $departmentName = 'HR Department', string $role = 'employee'): array
{
    $department = Department::firstOrCreate([
        'department' => $departmentName,
    ], [
        'department_type' => 'Administrative',
    ]);

    $user = User::factory()->create(['role' => $role, 'archived_at' => null]);
    $employee = Employee::create([
        'user_id' => $user->id,
        'employee_id' => Employee::nextEmployeeId(),
        'first_name' => fake()->firstName(),
        'last_name' => fake()->lastName(),
        'department_id' => $department->id,
        'status' => 'active',
        'hire_date' => now()->subYear()->toDateString(),
    ]);

    return [$user, $employee, $department];
}

function assignAttendancePosition(Employee $employee, string $positionName): void
{
    $position = Position::firstOrCreate([
        'department_id' => $employee->department_id,
        'position' => $positionName,
    ]);

    EmployeePosition::create([
        'employee_id' => $employee->id,
        'position_id' => $position->id,
    ]);
}

test('approved leave request is rendered as excused in attendance history for authorized viewers', function () {
    [$employeeUser, $employee, $department] = makeAttendanceEmployee();
    [$headUser, $headEmployee] = makeAttendanceEmployee($department->department);
    assignAttendancePosition($headEmployee, 'Head');

    $leaveType = LeaveType::create([
        'name' => 'Vacation Leave',
        'color_code' => '#2563eb',
        'requires_attachment' => false,
        'max_days' => 15,
        'gender' => null,
    ]);

    LeaveRequest::create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->toDateString(),
        'status' => 'HR Approved',
        'reason' => 'Medical recovery',
    ]);

    $response = $this->actingAs($headUser)->get(route('attendance.history', [
        'period' => 'weekly',
        'date' => now()->toDateString(),
        'employee_id' => $employee->id,
    ]));

    $response->assertOk();
    $response->assertSee('Excused');
    $response->assertViewHas('attendance', function ($attendance) use ($employee) {
        $rows = method_exists($attendance, 'getCollection')
            ? $attendance->getCollection()
            : collect($attendance);

        return $rows->contains(
            fn ($row) => (int) $row->employee_id === (int) $employee->id
                && (string) $row->status === 'excused'
        );
    });
});

test('employee can access own attendance history', function () {
    [$employeeUser, $employee] = makeAttendanceEmployee('Registrar Office');

    $response = $this->actingAs($employeeUser)->get(route('attendance.history', [
        'period' => 'weekly',
        'date' => now()->toDateString(),
        'employee_id' => $employee->id,
    ]));

    $response->assertOk();
});

test('employee cannot access attendance calendar pages', function () {
    [$employeeUser] = makeAttendanceEmployee('Registrar Office');

    $this->actingAs($employeeUser)
        ->get(route('attendance.calendar'))
        ->assertForbidden();

    $this->actingAs($employeeUser)
        ->get(route('attendance.calendar.feed', [
            'start' => now()->startOfWeek()->toDateString(),
            'end' => now()->endOfWeek()->toDateString(),
        ]))
        ->assertForbidden();
});

test('only hr department users can access attendance kpi', function () {
    [$hrUser] = makeAttendanceEmployee('HR Department');
    [$headUser, $headEmployee] = makeAttendanceEmployee('Finance Office');
    assignAttendancePosition($headEmployee, 'Head');
    [$adminUser] = makeAttendanceEmployee('Registrar Office', 'admin');

    $this->actingAs($hrUser)
        ->get(route('attendance.kpi.index'))
        ->assertOk();

    $this->actingAs($headUser)
        ->get(route('attendance.kpi.index'))
        ->assertForbidden();

    $this->actingAs($adminUser)
        ->get(route('attendance.kpi.index'))
        ->assertForbidden();
});

test('department head can monitor only own department attendance history', function () {
    [$headUser, $headEmployee, $department] = makeAttendanceEmployee('Finance Office');
    assignAttendancePosition($headEmployee, 'Head');

    [, $sameDepartmentEmployee] = makeAttendanceEmployee('Finance Office');
    [, $otherDepartmentEmployee] = makeAttendanceEmployee('Library Office');

    $today = Carbon::today()->toDateString();

    \App\Models\Attendance::create([
        'employee_id' => $sameDepartmentEmployee->id,
        'date' => $today,
        'status' => 'present',
    ]);

    \App\Models\Attendance::create([
        'employee_id' => $otherDepartmentEmployee->id,
        'date' => $today,
        'status' => 'present',
    ]);

    $response = $this->actingAs($headUser)->get(route('attendance.history', [
        'period' => 'weekly',
        'date' => $today,
    ]));

    $response->assertOk();
    $response->assertViewHas('attendance', function ($attendance) use ($sameDepartmentEmployee, $otherDepartmentEmployee) {
        $rows = method_exists($attendance, 'getCollection')
            ? $attendance->getCollection()
            : collect($attendance);

        return $rows->contains(fn ($row) => (int) $row->employee_id === (int) $sameDepartmentEmployee->id)
            && !$rows->contains(fn ($row) => (int) $row->employee_id === (int) $otherDepartmentEmployee->id);
    });
});

test('hr head can monitor attendance across departments', function () {
    [$hrHeadUser, $hrHeadEmployee] = makeAttendanceEmployee('HR Department');
    assignAttendancePosition($hrHeadEmployee, 'Head');

    [, $departmentOneEmployee] = makeAttendanceEmployee('Finance Office');
    [, $departmentTwoEmployee] = makeAttendanceEmployee('Library Office');

    $today = Carbon::today()->toDateString();

    \App\Models\Attendance::create([
        'employee_id' => $departmentOneEmployee->id,
        'date' => $today,
        'status' => 'present',
    ]);

    \App\Models\Attendance::create([
        'employee_id' => $departmentTwoEmployee->id,
        'date' => $today,
        'status' => 'present',
    ]);

    $response = $this->actingAs($hrHeadUser)->get(route('attendance.history', [
        'period' => 'weekly',
        'date' => $today,
    ]));

    $response->assertOk();
    $response->assertViewHas('attendance', function ($attendance) use ($departmentOneEmployee, $departmentTwoEmployee) {
        $rows = method_exists($attendance, 'getCollection')
            ? $attendance->getCollection()
            : collect($attendance);

        return $rows->contains(fn ($row) => (int) $row->employee_id === (int) $departmentOneEmployee->id)
            && $rows->contains(fn ($row) => (int) $row->employee_id === (int) $departmentTwoEmployee->id);
    });
});

test('hr staff can monitor attendance records across departments', function () {
    [$hrUser] = makeAttendanceEmployee('HR Department');

    [, $departmentOneEmployee] = makeAttendanceEmployee('Finance Office');
    [, $departmentTwoEmployee] = makeAttendanceEmployee('Library Office');

    $today = Carbon::today()->toDateString();

    \App\Models\Attendance::create([
        'employee_id' => $departmentOneEmployee->id,
        'date' => $today,
        'status' => 'present',
    ]);

    \App\Models\Attendance::create([
        'employee_id' => $departmentTwoEmployee->id,
        'date' => $today,
        'status' => 'present',
    ]);

    $response = $this->actingAs($hrUser)->get(route('attendance.history', [
        'period' => 'weekly',
        'date' => $today,
    ]));

    $response->assertOk();
    $response->assertViewHas('attendance', function ($attendance) use ($departmentOneEmployee, $departmentTwoEmployee) {
        $rows = method_exists($attendance, 'getCollection')
            ? $attendance->getCollection()
            : collect($attendance);

        return $rows->contains(fn ($row) => (int) $row->employee_id === (int) $departmentOneEmployee->id)
            && $rows->contains(fn ($row) => (int) $row->employee_id === (int) $departmentTwoEmployee->id);
    });
});
