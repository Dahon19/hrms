<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\TravelOrder;
use App\Models\TravelOrderTransportation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeTravelTransportationEmployee(): array
{
    $department = Department::firstOrCreate([
        'department' => 'College of Information Technology',
    ], [
        'department_type' => 'Administrative',
    ]);

    $user = User::factory()->create(['role' => 'employee', 'archived_at' => null]);
    $employee = Employee::create([
        'user_id' => $user->id,
        'employee_id' => Employee::nextEmployeeId(),
        'first_name' => fake()->firstName(),
        'last_name' => fake()->lastName(),
        'department_id' => $department->id,
        'status' => 'active',
        'hire_date' => now()->subYear()->toDateString(),
    ]);

    return [$user, $employee];
}

test('admin can create update and delete travel transport options', function () {
    $admin = User::factory()->create(['role' => 'admin', 'archived_at' => null]);

    $this->actingAs($admin)
        ->post(route('travel-orders.transport-options.store'), [
            'name' => 'Shuttle Van',
            'is_active' => 1,
        ])
        ->assertRedirect(route('travel-orders.transport-options.index'))
        ->assertSessionHas('success');

    $transportation = TravelOrderTransportation::query()
        ->where('name', 'Shuttle Van')
        ->firstOrFail();

    $this->actingAs($admin)
        ->patch(route('travel-orders.transport-options.update', $transportation), [
            'name' => 'Campus Shuttle Van',
            'is_active' => 0,
        ])
        ->assertRedirect(route('travel-orders.transport-options.index'))
        ->assertSessionHas('success');

    $transportation->refresh();
    expect($transportation->name)->toBe('Campus Shuttle Van');
    expect($transportation->is_active)->toBeFalse();

    $this->actingAs($admin)
        ->delete(route('travel-orders.transport-options.destroy', $transportation))
        ->assertRedirect(route('travel-orders.transport-options.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('travel_order_transportations', [
        'id' => $transportation->id,
    ]);
});

test('non-admin cannot manage travel transport options', function () {
    [$employeeUser] = makeTravelTransportationEmployee();

    $this->actingAs($employeeUser)
        ->get(route('travel-orders.transport-options.index'))
        ->assertForbidden();
});

test('used travel transport option cannot be deleted', function () {
    $admin = User::factory()->create(['role' => 'admin', 'archived_at' => null]);
    [$employeeUser, $employee] = makeTravelTransportationEmployee();

    $transportation = TravelOrderTransportation::query()
        ->create([
            'name' => 'Research Bus',
            'sort_order' => 30,
            'is_active' => true,
        ]);

    TravelOrder::create([
        'employee_id' => $employee->id,
        'department_id' => $employee->department_id,
        'destination' => 'Main Campus',
        'purpose' => 'Testing transport option usage.',
        'date_from' => now()->addDays(2)->toDateString(),
        'date_to' => now()->addDays(2)->toDateString(),
        'transport_mode' => 'Research Bus',
        'status' => TravelOrder::STATUS_DRAFT,
        'submitted_by' => $employeeUser->id,
    ]);

    $this->actingAs($admin)
        ->delete(route('travel-orders.transport-options.destroy', $transportation))
        ->assertRedirect(route('travel-orders.transport-options.index'))
        ->assertSessionHas('error');

    $this->assertDatabaseHas('travel_order_transportations', [
        'id' => $transportation->id,
        'name' => 'Research Bus',
    ]);
});

test('travel order filing rejects transport not in configured options', function () {
    [$employeeUser] = makeTravelTransportationEmployee();

    $this->actingAs($employeeUser)
        ->post(route('travel-orders.store'), [
            'destination' => 'Main Campus',
            'purpose' => 'Validation check for transport options.',
            'date_from' => now()->addDays(2)->toDateString(),
            'date_to' => now()->addDays(2)->toDateString(),
            'transport_mode' => 'Teleport',
        ])
        ->assertSessionHasErrors('transport_mode');
});
