<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\TravelOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeTravelEmployee(string $departmentName = 'College of Information Technology', string $role = 'employee'): array
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

function assignTravelPosition(Employee $employee, string $positionName): void
{
    $position = Position::firstOrCreate(['position' => $positionName]);
    $employee->positions()->updateOrCreate([
        'position_id' => $position->id,
    ], [
        'position_id' => $position->id,
    ]);
    $employee->load('positions.position', 'department', 'user');
}

test('travel order routes from employee to department, hr review, and president final approval', function () {
    [$employeeUser, $employee, $department] = makeTravelEmployee();
    assignTravelPosition($employee, 'instructor');

    [$headUser, $headEmployee] = makeTravelEmployee($department->department);
    assignTravelPosition($headEmployee, 'head');

    [$hrUser, $hrEmployee] = makeTravelEmployee('HR Department', 'admin');
    assignTravelPosition($hrEmployee, 'head');

    [$presidentUser, $presidentEmployee] = makeTravelEmployee('Presidents Office');
    assignTravelPosition($presidentEmployee, 'head');

    $this->actingAs($employeeUser)->post(route('travel-orders.store'), [
        'destination' => 'Manila Campus',
        'purpose' => 'Attend accreditation coordination meeting.',
        'date_from' => now()->addDays(3)->toDateString(),
        'date_to' => now()->addDays(4)->toDateString(),
        'transport_mode' => 'Service Vehicle',
    ])->assertRedirect();

    $travelOrder = TravelOrder::query()->firstOrFail();
    expect($travelOrder->status)->toBe(TravelOrder::STATUS_DRAFT);

    $this->actingAs($employeeUser)->post(route('travel-orders.submit', $travelOrder))
        ->assertSessionHas('success');
    expect($travelOrder->fresh()->status)->toBe(TravelOrder::STATUS_SUBMITTED);

    $this->actingAs($headUser)->post(route('travel-orders.department-approve', $travelOrder))
        ->assertSessionHas('success');
    expect($travelOrder->fresh()->status)->toBe(TravelOrder::STATUS_DEPARTMENT_APPROVED);

    $this->actingAs($hrUser)->post(route('travel-orders.hr-approve', $travelOrder))
        ->assertSessionHas('success');
    expect($travelOrder->fresh()->status)->toBe(TravelOrder::STATUS_HR_REVIEW);

    $this->actingAs($presidentUser)->post(route('travel-orders.final-approve', $travelOrder))
        ->assertSessionHas('success');
    expect($travelOrder->fresh()->status)->toBe(TravelOrder::STATUS_APPROVED);
});

test('travel order creation rejects a past start date', function () {
    [$employeeUser, $employee] = makeTravelEmployee();
    assignTravelPosition($employee, 'staff');

    $this->actingAs($employeeUser)
        ->from(route('travel-orders.index'))
        ->post(route('travel-orders.store'), [
            'destination' => 'Regional Office',
            'purpose' => 'Operational coordination.',
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->addDay()->toDateString(),
            'transport_mode' => 'Service Vehicle',
        ])
        ->assertRedirect(route('travel-orders.index'))
        ->assertSessionHasErrors('date_from');

    expect(TravelOrder::query()->count())->toBe(0);
});

test('hr approval requires a configured president approver', function () {
    [$employeeUser, $employee, $department] = makeTravelEmployee();
    assignTravelPosition($employee, 'instructor');

    [$headUser, $headEmployee] = makeTravelEmployee($department->department);
    assignTravelPosition($headEmployee, 'head');

    [$hrUser, $hrEmployee] = makeTravelEmployee('HR Department', 'admin');
    assignTravelPosition($hrEmployee, 'head');

    $this->actingAs($employeeUser)->post(route('travel-orders.store'), [
        'destination' => 'Main Campus',
        'purpose' => 'System setup check.',
        'date_from' => now()->addDays(3)->toDateString(),
        'date_to' => now()->addDays(3)->toDateString(),
        'transport_mode' => 'Service Vehicle',
    ])->assertRedirect();

    $travelOrder = TravelOrder::query()->firstOrFail();

    $this->actingAs($employeeUser)->post(route('travel-orders.submit', $travelOrder))
        ->assertSessionHas('success');
    $this->actingAs($headUser)->post(route('travel-orders.department-approve', $travelOrder))
        ->assertSessionHas('success');

    $this->actingAs($hrUser)->post(route('travel-orders.hr-approve', $travelOrder))
        ->assertSessionHas('error', 'President approval is required. Assign a President Head before HR approval.');

    expect($travelOrder->fresh()->status)->toBe(TravelOrder::STATUS_DEPARTMENT_APPROVED);
});

test('approved travel order is rendered as official business in attendance history for authorized viewers', function () {
    $initialOutputBufferLevel = ob_get_level();

    [$employeeUser, $employee, $department] = makeTravelEmployee();
    assignTravelPosition($employee, 'staff');
    [$headUser, $headEmployee] = makeTravelEmployee($department->department);
    assignTravelPosition($headEmployee, 'head');

    TravelOrder::create([
        'employee_id' => $employee->id,
        'department_id' => $employee->department_id,
        'position_id' => $employee->positions()->value('position_id'),
        'destination' => 'Regional Office',
        'purpose' => 'Submit compliance documents.',
        'date_from' => now()->toDateString(),
        'date_to' => now()->toDateString(),
        'status' => TravelOrder::STATUS_APPROVED,
        'submitted_by' => $employeeUser->id,
        'final_approved_by' => $employeeUser->id,
        'final_approved_at' => now(),
    ]);

    $response = $this->actingAs($headUser)->get(route('attendance.history', [
        'period' => 'weekly',
        'date' => now()->toDateString(),
        'employee_id' => $employee->id,
    ]));

    $response->assertOk();
    $response->assertSee('Official Business');

    while (ob_get_level() > $initialOutputBufferLevel) {
        ob_end_clean();
    }
});

test('department-rejected travel order stops the approval flow while remaining visible to hr history', function () {
    [$employeeUser, $employee, $department] = makeTravelEmployee();
    assignTravelPosition($employee, 'instructor');

    [$headUser, $headEmployee] = makeTravelEmployee($department->department);
    assignTravelPosition($headEmployee, 'head');

    [$hrUser, $hrEmployee] = makeTravelEmployee('HR Department', 'admin');
    assignTravelPosition($hrEmployee, 'head');

    $this->actingAs($employeeUser)->post(route('travel-orders.store'), [
        'destination' => 'Main Campus',
        'purpose' => 'Department-level coordination meeting.',
        'date_from' => now()->addDays(5)->toDateString(),
        'date_to' => now()->addDays(5)->toDateString(),
        'transport_mode' => 'Service Vehicle',
    ])->assertRedirect();

    $travelOrder = TravelOrder::query()->firstOrFail();

    $this->actingAs($employeeUser)
        ->post(route('travel-orders.submit', $travelOrder))
        ->assertSessionHas('success');

    $this->actingAs($headUser)
        ->post(route('travel-orders.department-reject', $travelOrder), [
            'decision_reason' => 'Department requirements were not met.',
            'reject_action' => route('travel-orders.department-reject', $travelOrder),
        ])
        ->assertSessionHas('success');

    expect($travelOrder->fresh()->status)->toBe(TravelOrder::STATUS_REJECTED);

    $this->actingAs($hrUser)
        ->post(route('travel-orders.hr-approve', $travelOrder))
        ->assertSessionHas('error');

    expect($travelOrder->fresh()->status)->toBe(TravelOrder::STATUS_REJECTED);

    $response = $this->actingAs($hrUser)->get(route('travel-orders.index', [
        'open_approvals' => 1,
    ]));

    $response->assertOk();
    $response->assertViewHas('pending', fn ($pending) => $pending->doesntContain('id', $travelOrder->id));
    $response->assertViewHas('travelOrders', fn ($travelOrders) => $travelOrders->getCollection()->doesntContain('id', $travelOrder->id));
});

test('travel order reject actions require decision reason', function () {
    [$employeeUser, $employee, $department] = makeTravelEmployee();
    assignTravelPosition($employee, 'instructor');

    [$headUser, $headEmployee] = makeTravelEmployee($department->department);
    assignTravelPosition($headEmployee, 'head');

    $this->actingAs($employeeUser)->post(route('travel-orders.store'), [
        'destination' => 'Main Campus',
        'purpose' => 'Review schedules.',
        'date_from' => now()->addDays(3)->toDateString(),
        'date_to' => now()->addDays(3)->toDateString(),
        'transport_mode' => 'Service Vehicle',
    ])->assertRedirect();

    $travelOrder = TravelOrder::query()->firstOrFail();
    $this->actingAs($employeeUser)->post(route('travel-orders.submit', $travelOrder))
        ->assertSessionHas('success');

    $this->actingAs($headUser)
        ->post(route('travel-orders.department-reject', $travelOrder), [
            'decision_reason' => '',
            'reject_action' => route('travel-orders.department-reject', $travelOrder),
        ])
        ->assertSessionHasErrors('decision_reason');

    $travelOrder->refresh();
    expect($travelOrder->status)->toBe(TravelOrder::STATUS_SUBMITTED);
    expect($travelOrder->rejected_at)->toBeNull();
});

test('open approvals view returns role-scoped pending queue', function () {
    [$employeeUser, $employee, $department] = makeTravelEmployee();
    assignTravelPosition($employee, 'instructor');

    [$headUser, $headEmployee] = makeTravelEmployee($department->department);
    assignTravelPosition($headEmployee, 'head');

    [$hrUser, $hrEmployee] = makeTravelEmployee('HR Department', 'admin');
    assignTravelPosition($hrEmployee, 'head');

    $this->actingAs($employeeUser)->post(route('travel-orders.store'), [
        'destination' => 'Manila',
        'purpose' => 'Coordination meeting.',
        'date_from' => now()->addDays(4)->toDateString(),
        'date_to' => now()->addDays(4)->toDateString(),
        'transport_mode' => 'Service Vehicle',
    ])->assertRedirect();

    $travelOrder = TravelOrder::query()->firstOrFail();

    $this->actingAs($employeeUser)->post(route('travel-orders.submit', $travelOrder))
        ->assertSessionHas('success');

    $this->actingAs($headUser)->post(route('travel-orders.department-approve', $travelOrder))
        ->assertSessionHas('success');

    $response = $this->actingAs($hrUser)->get(route('travel-orders.index', [
        'open_approvals' => 1,
    ]));

    $response->assertOk();
    $response->assertViewHas('pending', fn ($pending) => $pending->contains('id', $travelOrder->id));
});

test('employee can cancel own travel order beyond draft status', function () {
    [$employeeUser, $employee] = makeTravelEmployee();
    assignTravelPosition($employee, 'staff');

    $travelOrder = TravelOrder::create([
        'employee_id' => $employee->id,
        'department_id' => $employee->department_id,
        'position_id' => $employee->positions()->value('position_id'),
        'destination' => 'Regional Office',
        'purpose' => 'Operational coordination.',
        'date_from' => now()->addDays(2)->toDateString(),
        'date_to' => now()->addDays(2)->toDateString(),
        'status' => TravelOrder::STATUS_DEPARTMENT_APPROVED,
        'submitted_by' => $employeeUser->id,
        'submitted_at' => now(),
    ]);

    $this->actingAs($employeeUser)
        ->post(route('travel-orders.cancel', $travelOrder))
        ->assertSessionHas('success', 'Travel order cancelled.');

    expect($travelOrder->fresh()->status)->toBe(TravelOrder::STATUS_CANCELLED);
    expect($travelOrder->fresh()->cancelled_at)->not->toBeNull();
});

test('department head filing own travel order skips self-approval and forwards to hr review', function () {
    [$headUser, $headEmployee, $department] = makeTravelEmployee();
    assignTravelPosition($headEmployee, 'head');

    [$hrUser, $hrEmployee] = makeTravelEmployee('HR Department', 'admin');
    assignTravelPosition($hrEmployee, 'head');

    $this->actingAs($headUser)->post(route('travel-orders.store'), [
        'destination' => 'Main Campus',
        'purpose' => 'Department planning session.',
        'date_from' => now()->addDays(3)->toDateString(),
        'date_to' => now()->addDays(3)->toDateString(),
        'transport_mode' => 'Service Vehicle',
    ])->assertRedirect();

    $travelOrder = TravelOrder::query()->firstOrFail();
    expect($travelOrder->status)->toBe(TravelOrder::STATUS_DRAFT);
    expect($travelOrder->department_id)->toBe($department->id);

    $this->actingAs($headUser)->post(route('travel-orders.submit', $travelOrder))
        ->assertSessionHas('success', 'Travel order submitted and forwarded to HR review.');

    $travelOrder->refresh();
    expect($travelOrder->status)->toBe(TravelOrder::STATUS_DEPARTMENT_APPROVED);
    expect((int) $travelOrder->department_approved_by)->toBe((int) $headUser->id);
    expect($travelOrder->department_approved_at)->not->toBeNull();

    $headApprovals = $this->actingAs($headUser)->get(route('travel-orders.index', [
        'open_approvals' => 1,
    ]));
    $headApprovals->assertOk();
    $headApprovals->assertViewHas('pending', fn ($pending) => $pending->doesntContain('id', $travelOrder->id));

    $hrApprovals = $this->actingAs($hrUser)->get(route('travel-orders.index', [
        'open_approvals' => 1,
    ]));
    $hrApprovals->assertOk();
    $hrApprovals->assertViewHas('pending', fn ($pending) => $pending->contains('id', $travelOrder->id));
});

test('hr head filing own travel order skips hr self-approval and goes to final approval queue', function () {
    [$hrUser, $hrEmployee] = makeTravelEmployee('HR Department', 'admin');
    assignTravelPosition($hrEmployee, 'head');

    [$presidentUser, $presidentEmployee] = makeTravelEmployee('Presidents Office');
    assignTravelPosition($presidentEmployee, 'head');

    $this->actingAs($hrUser)->post(route('travel-orders.store'), [
        'destination' => 'Regional Office',
        'purpose' => 'HR compliance coordination.',
        'date_from' => now()->addDays(4)->toDateString(),
        'date_to' => now()->addDays(4)->toDateString(),
        'transport_mode' => 'Service Vehicle',
    ])->assertRedirect();

    $travelOrder = TravelOrder::query()->firstOrFail();
    expect($travelOrder->status)->toBe(TravelOrder::STATUS_DRAFT);

    $this->actingAs($hrUser)->post(route('travel-orders.submit', $travelOrder))
        ->assertSessionHas('success', 'Travel order submitted and forwarded for final approval.');

    $travelOrder->refresh();
    expect($travelOrder->status)->toBe(TravelOrder::STATUS_HR_REVIEW);
    expect((int) $travelOrder->department_approved_by)->toBe((int) $hrUser->id);
    expect($travelOrder->department_approved_at)->not->toBeNull();
    expect((int) $travelOrder->hr_reviewed_by)->toBe((int) $hrUser->id);
    expect($travelOrder->hr_reviewed_at)->not->toBeNull();

    $hrApprovals = $this->actingAs($hrUser)->get(route('travel-orders.index', [
        'open_approvals' => 1,
    ]));
    $hrApprovals->assertOk();
    $hrApprovals->assertViewHas('pending', fn ($pending) => $pending->doesntContain('id', $travelOrder->id));

    $presidentApprovals = $this->actingAs($presidentUser)->get(route('travel-orders.index', [
        'open_approvals' => 1,
    ]));
    $presidentApprovals->assertOk();
    $presidentApprovals->assertViewHas('pending', fn ($pending) => $pending->contains('id', $travelOrder->id));
});
