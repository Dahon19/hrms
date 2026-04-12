<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeLeaveWorkflowEmployee(string $departmentName = 'College of Information Technology', string $role = 'employee'): array
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

function assignLeaveWorkflowPosition(Employee $employee, string $positionName): void
{
    $position = Position::firstOrCreate(['position' => $positionName]);
    $employee->positions()->updateOrCreate([
        'position_id' => $position->id,
    ], [
        'position_id' => $position->id,
    ]);
    $employee->load('positions.position', 'department', 'user');
}

function makeLeaveWorkflowType(): LeaveType
{
    return LeaveType::firstOrCreate(
        ['name' => 'Vacation Leave'],
        [
            'color_code' => '#2563eb',
            'requires_attachment' => false,
            'max_days' => 15,
            'gender' => null,
        ]
    );
}

function makeHrApprovedLeave(Employee $employee, LeaveType $leaveType, array $overrides = []): LeaveRequest
{
    return LeaveRequest::create(array_merge([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(6)->toDateString(),
        'status' => 'HR Approved',
        'reason' => 'Family activity',
        'hr_reviewed_by' => User::factory()->create()->id,
        'hr_reviewed_at' => now(),
    ], $overrides));
}

function makePendingLeave(Employee $employee, LeaveType $leaveType, array $overrides = []): LeaveRequest
{
    return LeaveRequest::create(array_merge([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => now()->addDays(3)->toDateString(),
        'end_date' => now()->addDays(4)->toDateString(),
        'status' => 'Pending',
        'reason' => 'Needs schedule adjustment',
    ], $overrides));
}

function makeApprovedLeave(Employee $employee, LeaveType $leaveType, array $overrides = []): LeaveRequest
{
    return LeaveRequest::create(array_merge([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => now()->addDays(4)->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
        'status' => 'Approved',
        'reason' => 'Awaiting HR decision',
    ], $overrides));
}

test('president approvals page separates pending and reviewed requests', function () {
    [$presidentUser, $presidentEmployee] = makeLeaveWorkflowEmployee("President's Office");
    assignLeaveWorkflowPosition($presidentEmployee, 'head');

    [, $employee] = makeLeaveWorkflowEmployee();
    assignLeaveWorkflowPosition($employee, 'staff');

    $leaveType = makeLeaveWorkflowType();

    $pending = makeHrApprovedLeave($employee, $leaveType, [
        'start_date' => now()->addDays(14)->toDateString(),
        'end_date' => now()->addDays(15)->toDateString(),
        'president_reviewed_by' => null,
        'president_reviewed_at' => null,
    ]);

    $reviewed = makeHrApprovedLeave($employee, $leaveType, [
        'start_date' => now()->addDays(20)->toDateString(),
        'end_date' => now()->addDays(21)->toDateString(),
        'president_reviewed_by' => $presidentUser->id,
        'president_reviewed_at' => now(),
        'notes' => 'Reviewed by President',
    ]);

    $response = $this->actingAs($presidentUser)->get(route('leaves.approvals'));

    $response->assertOk();
    $response->assertViewHas('pendingRequests', fn ($pendingRequests) => $pendingRequests->contains('id', $pending->id)
        && !$pendingRequests->contains('id', $reviewed->id));
    $response->assertViewHas('historyRequests', fn ($historyRequests) => $historyRequests->contains('id', $reviewed->id)
        && !$historyRequests->contains('id', $pending->id));
});

test('president decline enforces valid status values', function () {
    [$presidentUser, $presidentEmployee] = makeLeaveWorkflowEmployee("President's Office");
    assignLeaveWorkflowPosition($presidentEmployee, 'head');

    [, $employee] = makeLeaveWorkflowEmployee();
    assignLeaveWorkflowPosition($employee, 'staff');

    $leaveType = makeLeaveWorkflowType();
    $leave = makeHrApprovedLeave($employee, $leaveType);

    $this->actingAs($presidentUser)
        ->post(route('leaves.president.decline', $leave), [
            'status' => 'Rejected',
            'notes' => 'Unsupported status',
        ])
        ->assertSessionHasErrors('status');

    $leave->refresh();
    expect($leave->status)->toBe('HR Approved');
    expect($leave->president_reviewed_by)->toBeNull();
    expect($leave->president_reviewed_at)->toBeNull();
});

test('president approve marks request reviewed and removes it from pending', function () {
    [$presidentUser, $presidentEmployee] = makeLeaveWorkflowEmployee("President's Office");
    assignLeaveWorkflowPosition($presidentEmployee, 'head');

    [, $employee] = makeLeaveWorkflowEmployee();
    assignLeaveWorkflowPosition($employee, 'staff');

    $leaveType = makeLeaveWorkflowType();
    $leave = makeHrApprovedLeave($employee, $leaveType);

    $this->actingAs($presidentUser)
        ->post(route('leaves.president.approve', $leave), [
            'notes' => 'Approved',
        ])
        ->assertSessionHas('success');

    $leave->refresh();
    expect($leave->status)->toBe('HR Approved');
    expect($leave->president_reviewed_by)->toBe($presidentUser->id);
    expect($leave->president_reviewed_at)->not->toBeNull();

    $response = $this->actingAs($presidentUser)->get(route('leaves.approvals'));
    $response->assertOk();
    $response->assertViewHas('pendingRequests', fn ($pendingRequests) => !$pendingRequests->contains('id', $leave->id));
    $response->assertViewHas('historyRequests', fn ($historyRequests) => $historyRequests->contains('id', $leave->id));
});

test('president cannot reprocess an already reviewed request', function () {
    [$presidentUser, $presidentEmployee] = makeLeaveWorkflowEmployee("President's Office");
    assignLeaveWorkflowPosition($presidentEmployee, 'head');

    [, $employee] = makeLeaveWorkflowEmployee();
    assignLeaveWorkflowPosition($employee, 'staff');

    $leaveType = makeLeaveWorkflowType();
    $leave = makeHrApprovedLeave($employee, $leaveType, [
        'president_reviewed_by' => $presidentUser->id,
        'president_reviewed_at' => now()->subMinute(),
        'notes' => 'Already decided',
    ]);

    $this->actingAs($presidentUser)
        ->post(route('leaves.president.approve', $leave), [
            'notes' => 'Should not apply',
        ])
        ->assertSessionHas('error');

    $leave->refresh();
    expect($leave->notes)->toBe('Already decided');
    expect($leave->president_reviewed_by)->toBe($presidentUser->id);

    $this->actingAs($presidentUser)
        ->post(route('leaves.president.decline', $leave), [
            'status' => 'Declined',
            'notes' => 'Should not apply either',
        ])
        ->assertSessionHas('error');

    $leave->refresh();
    expect($leave->status)->toBe('HR Approved');
    expect($leave->notes)->toBe('Already decided');
    expect($leave->president_reviewed_by)->toBe($presidentUser->id);
});

test('president second immediate submission is rejected', function () {
    [$presidentUser, $presidentEmployee] = makeLeaveWorkflowEmployee("President's Office");
    assignLeaveWorkflowPosition($presidentEmployee, 'head');

    [, $employee] = makeLeaveWorkflowEmployee();
    assignLeaveWorkflowPosition($employee, 'staff');

    $leaveType = makeLeaveWorkflowType();
    $leave = makeHrApprovedLeave($employee, $leaveType);

    $this->actingAs($presidentUser)
        ->post(route('leaves.president.approve', $leave), [
            'notes' => 'First decision',
        ])
        ->assertSessionHas('success');

    $this->actingAs($presidentUser)
        ->post(route('leaves.president.decline', $leave), [
            'status' => 'Declined',
            'notes' => 'Second decision should fail',
        ])
        ->assertSessionHas('error');

    $leave->refresh();
    expect($leave->status)->toBe('HR Approved');
    expect($leave->notes)->toBe('First decision');
    expect($leave->president_reviewed_by)->toBe($presidentUser->id);
});

test('head decline requires notes for revision decision', function () {
    [$headUser, $headEmployee, $department] = makeLeaveWorkflowEmployee('College of Information Technology');
    assignLeaveWorkflowPosition($headEmployee, 'head');

    [, $employee] = makeLeaveWorkflowEmployee($department->department);
    assignLeaveWorkflowPosition($employee, 'staff');

    $leaveType = makeLeaveWorkflowType();
    $leave = makePendingLeave($employee, $leaveType);

    $this->actingAs($headUser)
        ->post(route('leaves.head.decline', $leave), [
            'suggested_start_date' => now()->addDays(6)->toDateString(),
            'suggested_end_date' => now()->addDays(7)->toDateString(),
            'notes' => '',
        ])
        ->assertSessionHasErrors('notes');

    $leave->refresh();
    expect($leave->status)->toBe('Pending');
    expect($leave->head_reviewed_by)->toBeNull();
    expect($leave->head_reviewed_at)->toBeNull();
});

test('hr decline requires notes for revision decision', function () {
    [$hrUser, $hrEmployee] = makeLeaveWorkflowEmployee('HR Department', 'admin');
    assignLeaveWorkflowPosition($hrEmployee, 'head');

    [, $employee] = makeLeaveWorkflowEmployee('College of Information Technology');
    assignLeaveWorkflowPosition($employee, 'staff');

    $leaveType = makeLeaveWorkflowType();
    $leave = makeApprovedLeave($employee, $leaveType);

    $this->actingAs($hrUser)
        ->post(route('leaves.hr.decline', $leave), [
            'status' => 'Needs Revision',
            'notes' => '',
        ])
        ->assertSessionHasErrors('notes');

    $leave->refresh();
    expect($leave->status)->toBe('Approved');
    expect($leave->hr_reviewed_by)->toBeNull();
    expect($leave->hr_reviewed_at)->toBeNull();
});

test('department head filing own leave skips self-approval and routes directly to hr review', function () {
    [$headUser, $headEmployee] = makeLeaveWorkflowEmployee('College of Information Technology');
    assignLeaveWorkflowPosition($headEmployee, 'head');

    [$hrUser, $hrEmployee] = makeLeaveWorkflowEmployee('HR Department');
    assignLeaveWorkflowPosition($hrEmployee, 'head');

    $leaveType = makeLeaveWorkflowType();

    $this->actingAs($headUser)->post(route('leaves.store'), [
        'leave_type_id' => $leaveType->id,
        'start_date' => now()->addDays(7)->toDateString(),
        'end_date' => now()->addDays(8)->toDateString(),
        'reason' => 'Department planning workload balance.',
    ])->assertSessionHas('success', 'Leave request submitted and forwarded to HR review.');

    $leave = LeaveRequest::query()->firstOrFail();
    expect($leave->status)->toBe('Approved');
    expect((int) $leave->head_reviewed_by)->toBe((int) $headUser->id);
    expect($leave->head_reviewed_at)->not->toBeNull();
    expect($leave->hr_reviewed_by)->toBeNull();

    $headApprovals = $this->actingAs($headUser)->get(route('leaves.approvals'));
    $headApprovals->assertOk();
    $headApprovals->assertViewHas('pendingRequests', fn ($pendingRequests) => $pendingRequests->doesntContain('id', $leave->id));

    $hrApprovals = $this->actingAs($hrUser)->get(route('leaves.approvals'));
    $hrApprovals->assertOk();
    $hrApprovals->assertViewHas('pendingRequests', fn ($pendingRequests) => $pendingRequests->contains('id', $leave->id));
});
