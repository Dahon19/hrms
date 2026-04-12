<?php

use App\Models\ClearanceItem;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeNfc;
use App\Models\OffboardingRecord;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeEmployee(string $departmentName = 'HR Department', string $role = 'employee'): array
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

function assignPosition(Employee $employee, string $positionName): void
{
    $position = Position::firstOrCreate(['position' => $positionName]);
    $employee->positions()->updateOrCreate([
        'position_id' => $position->id,
    ], [
        'position_id' => $position->id,
    ]);
    $employee->load('positions.position', 'user', 'department');
}

function makeFinanceUser(): array
{
    [$user, $employee, $department] = makeEmployee('Accounting Office');
    assignPosition($employee, 'head');

    return [$user, $employee, $department];
}

test('admin can create a draft offboarding record without freezing access', function () {
    [$adminUser, $adminEmployee] = makeEmployee('HR Department', 'admin');
    assignPosition($adminEmployee, 'head');

    [, $employee] = makeEmployee('College of Information Technology');
    EmployeeNfc::create([
        'employee_id' => $employee->id,
        'nfc_uid' => 'ABC123',
    ]);

    $response = $this->actingAs($adminUser)->post(route('offboarding.store'), [
        'employee_id' => $employee->id,
        'separation_date' => now()->toDateString(),
        'last_working_day' => now()->addDays(3)->toDateString(),
        'resignation_reason' => 'Resignation',
        'remarks' => 'Test workflow',
    ]);

    $response->assertRedirect();

    $employee->refresh();
    $employee->user->refresh();

    expect($employee->status)->toBe('active');
    expect($employee->user->archived_at)->toBeNull();
    expect(EmployeeNfc::where('employee_id', $employee->id)->exists())->toBeTrue();

    $record = OffboardingRecord::where('employee_id', $employee->id)->first();
    expect($record)->not->toBeNull();
    expect($record->status)->toBe(OffboardingRecord::STATUS_DRAFT);
    expect($record->clearanceItems()->count())->toBe(4);
    expect($record->clearanceItems()->where('owner_role', 'department_head')->count())->toBe(1);
    expect($record->clearanceItems()->where('owner_role', 'finance')->count())->toBe(1);
    expect($record->clearanceItems()->where('module_key', 'resignation_notice_received')->first()?->status)->toBe(ClearanceItem::STATUS_CLEARED);
});

test('workflow cannot skip from submitted to finance before department clearance', function () {
    [$adminUser, $adminEmployee] = makeEmployee('HR Department', 'admin');
    assignPosition($adminEmployee, 'head');

    [, $employee] = makeEmployee('College of Information Technology');
    [$financeUser] = makeFinanceUser();

    $this->actingAs($adminUser)->post(route('offboarding.store'), [
        'employee_id' => $employee->id,
        'separation_date' => now()->toDateString(),
        'last_working_day' => now()->addDays(3)->toDateString(),
        'resignation_reason' => 'Retirement',
    ]);

    $record = OffboardingRecord::where('employee_id', $employee->id)->firstOrFail();

    $this->actingAs($adminUser)->post(route('offboarding.submit', $record))
        ->assertSessionHas('success');

    $financeItem = $record->clearanceItems()->where('owner_role', 'finance')->firstOrFail();

    $this->actingAs($financeUser)->patch(route('offboarding.items.update', [$record, $financeItem]), [
        'status' => ClearanceItem::STATUS_CLEARED,
        'remarks' => 'Finance tried to clear early.',
    ])->assertForbidden();

    expect($financeItem->fresh()->status)->toBe(ClearanceItem::STATUS_PENDING);
    expect($record->fresh()->status)->toBe(OffboardingRecord::STATUS_SUBMITTED);
});

test('admin can progress workflow through department, finance, hr, then complete offboarding', function () {
    [$adminUser, $adminEmployee] = makeEmployee('HR Department', 'admin');
    assignPosition($adminEmployee, 'head');

    [, $employee] = makeEmployee('College of Information Technology');
    EmployeeNfc::create([
        'employee_id' => $employee->id,
        'nfc_uid' => 'ABC123',
    ]);

    $this->actingAs($adminUser)->post(route('offboarding.store'), [
        'employee_id' => $employee->id,
        'separation_date' => now()->toDateString(),
        'last_working_day' => now()->addDays(3)->toDateString(),
        'resignation_reason' => 'End of contract',
    ]);

    $record = OffboardingRecord::where('employee_id', $employee->id)->firstOrFail();

    $this->actingAs($adminUser)->post(route('offboarding.submit', $record))
        ->assertSessionHas('success');
    expect($record->fresh()->status)->toBe(OffboardingRecord::STATUS_SUBMITTED);

    [$headUser, $headEmployee, $department] = makeEmployee('College of Information Technology');
    assignPosition($headEmployee, 'head');
    $employee->update(['department_id' => $department->id]);

    [$financeUser] = makeFinanceUser();

    $record = $record->fresh();
    $departmentItem = $record->clearanceItems()->where('module_key', 'department_interview_handover')->firstOrFail();
    $financeItem = $record->clearanceItems()->where('module_key', 'finance_clearance')->firstOrFail();
    $hrItem = $record->clearanceItems()->where('module_key', 'hr_final_review')->firstOrFail();

    $this->actingAs($headUser)->patch(route('offboarding.items.update', [$record, $departmentItem]), [
        'status' => ClearanceItem::STATUS_CLEARED,
        'remarks' => 'Department interview completed.',
    ])->assertSessionHas('success');
    expect($record->fresh()->status)->toBe(OffboardingRecord::STATUS_FINANCE_CLEARANCE);

    $this->actingAs($financeUser)->patch(route('offboarding.items.update', [$record, $financeItem]), [
        'status' => ClearanceItem::STATUS_CLEARED,
        'remarks' => 'Finance clearance completed.',
    ])->assertSessionHas('success');
    expect($record->fresh()->status)->toBe(OffboardingRecord::STATUS_HR_FINALIZATION);

    $this->actingAs($adminUser)->patch(route('offboarding.items.update', [$record, $hrItem]), [
        'status' => ClearanceItem::STATUS_CLEARED,
        'remarks' => 'HR completion.',
    ])->assertSessionHas('success');

    $this->actingAs($adminUser)->post(route('offboarding.finalize', $record))
        ->assertSessionHas('success');

    $record->refresh();
    $employee->refresh();
    $employee->user->refresh();

    expect($record->status)->toBe(OffboardingRecord::STATUS_COMPLETED);
    expect($employee->status)->toBe('active');
    expect($employee->user->archived_at)->toBeNull();
    expect(EmployeeNfc::where('employee_id', $employee->id)->exists())->toBeTrue();
});

test('completed offboarding deactivates the account only after the last working day', function () {
    [$adminUser, $adminEmployee] = makeEmployee('HR Department', 'admin');
    assignPosition($adminEmployee, 'head');

    [$employeeUser, $employee] = makeEmployee('College of Information Technology');

    $this->actingAs($adminUser)->post(route('offboarding.store'), [
        'employee_id' => $employee->id,
        'separation_date' => now()->toDateString(),
        'last_working_day' => now()->addDay()->toDateString(),
        'resignation_reason' => 'Resignation',
    ]);

    $record = OffboardingRecord::where('employee_id', $employee->id)->firstOrFail();
    $this->actingAs($adminUser)->post(route('offboarding.submit', $record));

    [$headUser, $headEmployee, $department] = makeEmployee('College of Information Technology');
    assignPosition($headEmployee, 'head');
    $employee->update(['department_id' => $department->id]);
    [$financeUser] = makeFinanceUser();

    $record = $record->fresh();
    $departmentItem = $record->clearanceItems()->where('module_key', 'department_interview_handover')->firstOrFail();
    $financeItem = $record->clearanceItems()->where('module_key', 'finance_clearance')->firstOrFail();
    $hrItem = $record->clearanceItems()->where('module_key', 'hr_final_review')->firstOrFail();

    $this->actingAs($headUser)->patch(route('offboarding.items.update', [$record, $departmentItem]), ['status' => ClearanceItem::STATUS_CLEARED]);
    $this->actingAs($financeUser)->patch(route('offboarding.items.update', [$record, $financeItem]), ['status' => ClearanceItem::STATUS_CLEARED]);
    $this->actingAs($adminUser)->patch(route('offboarding.items.update', [$record, $hrItem]), ['status' => ClearanceItem::STATUS_CLEARED]);
    $this->actingAs($adminUser)->post(route('offboarding.finalize', $record))
        ->assertSessionHas('success', 'Offboarding completed. Employee access will be deactivated on ' . now()->addDay()->format('M j, Y') . '.');

    $employee->refresh();
    $employeeUser->refresh();

    expect($employee->status)->toBe('active');
    expect($employeeUser->archived_at)->toBeNull();

    $this->travelTo(now()->addDays(2));

    $this->artisan('offboarding:deactivate-due')
        ->assertSuccessful();

    $employee->refresh();
    $employeeUser->refresh();

    expect($employee->status)->toBe('inactive');
    expect($employeeUser->archived_at)->not->toBeNull();
});

test('employee can request resignation cancellation after completion and hr can approve it before deactivation', function () {
    [$adminUser, $adminEmployee] = makeEmployee('HR Department', 'admin');
    assignPosition($adminEmployee, 'head');

    [$employeeUser, $employee] = makeEmployee('College of Information Technology');

    $this->actingAs($adminUser)->post(route('offboarding.store'), [
        'employee_id' => $employee->id,
        'separation_date' => now()->toDateString(),
        'last_working_day' => now()->addDay()->toDateString(),
        'resignation_reason' => 'Resignation',
    ]);

    $record = OffboardingRecord::where('employee_id', $employee->id)->firstOrFail();
    $this->actingAs($adminUser)->post(route('offboarding.submit', $record));

    [$headUser, $headEmployee, $department] = makeEmployee('College of Information Technology');
    assignPosition($headEmployee, 'head');
    $employee->update(['department_id' => $department->id]);
    [$financeUser] = makeFinanceUser();

    $record = $record->fresh();
    $departmentItem = $record->clearanceItems()->where('module_key', 'department_interview_handover')->firstOrFail();
    $financeItem = $record->clearanceItems()->where('module_key', 'finance_clearance')->firstOrFail();
    $hrItem = $record->clearanceItems()->where('module_key', 'hr_final_review')->firstOrFail();

    $this->actingAs($headUser)->patch(route('offboarding.items.update', [$record, $departmentItem]), ['status' => ClearanceItem::STATUS_CLEARED]);
    $this->actingAs($financeUser)->patch(route('offboarding.items.update', [$record, $financeItem]), ['status' => ClearanceItem::STATUS_CLEARED]);
    $this->actingAs($adminUser)->patch(route('offboarding.items.update', [$record, $hrItem]), ['status' => ClearanceItem::STATUS_CLEARED]);
    $this->actingAs($adminUser)->post(route('offboarding.finalize', $record));

    $this->actingAs($employeeUser)->post(route('offboarding.request-cancellation', $record), [
        'cancellation_reason' => 'I decided to stay in the institution.',
    ])->assertSessionHas('success');

    $record->refresh();
    expect($record->cancellation_request_status)->toBe(OffboardingRecord::CANCELLATION_STATUS_PENDING);

    $this->travelTo(now()->addDays(2));

    $this->artisan('offboarding:deactivate-due')
        ->assertSuccessful();

    $employee->refresh();
    $employeeUser->refresh();

    expect($employee->status)->toBe('active');
    expect($employeeUser->archived_at)->toBeNull();

    $this->actingAs($adminUser)->post(route('offboarding.approve-cancellation', $record), [
        'cancellation_review_notes' => 'HR approved the withdrawal of resignation.',
    ])->assertSessionHas('success');

    $record->refresh();
    $employee->refresh();
    $employeeUser->refresh();

    expect($record->status)->toBe(OffboardingRecord::STATUS_CANCELLED);
    expect($record->cancellation_request_status)->toBe(OffboardingRecord::CANCELLATION_STATUS_APPROVED);
    expect($employee->status)->toBe('active');
    expect($employeeUser->archived_at)->toBeNull();
});

test('rejecting a resignation cancellation request resumes due account deactivation', function () {
    [$adminUser, $adminEmployee] = makeEmployee('HR Department', 'admin');
    assignPosition($adminEmployee, 'head');

    [$employeeUser, $employee] = makeEmployee('College of Information Technology');

    $this->actingAs($adminUser)->post(route('offboarding.store'), [
        'employee_id' => $employee->id,
        'separation_date' => now()->toDateString(),
        'last_working_day' => now()->addDay()->toDateString(),
        'resignation_reason' => 'Resignation',
    ]);

    $record = OffboardingRecord::where('employee_id', $employee->id)->firstOrFail();
    $this->actingAs($adminUser)->post(route('offboarding.submit', $record));

    [$headUser, $headEmployee, $department] = makeEmployee('College of Information Technology');
    assignPosition($headEmployee, 'head');
    $employee->update(['department_id' => $department->id]);
    [$financeUser] = makeFinanceUser();

    $record = $record->fresh();
    $departmentItem = $record->clearanceItems()->where('module_key', 'department_interview_handover')->firstOrFail();
    $financeItem = $record->clearanceItems()->where('module_key', 'finance_clearance')->firstOrFail();
    $hrItem = $record->clearanceItems()->where('module_key', 'hr_final_review')->firstOrFail();

    $this->actingAs($headUser)->patch(route('offboarding.items.update', [$record, $departmentItem]), ['status' => ClearanceItem::STATUS_CLEARED]);
    $this->actingAs($financeUser)->patch(route('offboarding.items.update', [$record, $financeItem]), ['status' => ClearanceItem::STATUS_CLEARED]);
    $this->actingAs($adminUser)->patch(route('offboarding.items.update', [$record, $hrItem]), ['status' => ClearanceItem::STATUS_CLEARED]);
    $this->actingAs($adminUser)->post(route('offboarding.finalize', $record));

    $this->actingAs($employeeUser)->post(route('offboarding.request-cancellation', $record), [
        'cancellation_reason' => 'Please stop the resignation.',
    ])->assertSessionHas('success');

    $this->travelTo(now()->addDays(2));

    $this->artisan('offboarding:deactivate-due')
        ->assertSuccessful();

    $employee->refresh();
    $employeeUser->refresh();

    expect($employee->status)->toBe('active');
    expect($employeeUser->archived_at)->toBeNull();

    $this->actingAs($adminUser)->post(route('offboarding.reject-cancellation', $record), [
        'cancellation_review_notes' => 'HR kept the resignation in effect.',
    ])->assertSessionHas('success');

    $record->refresh();
    $employee->refresh();
    $employeeUser->refresh();

    expect($record->status)->toBe(OffboardingRecord::STATUS_COMPLETED);
    expect($record->cancellation_request_status)->toBe(OffboardingRecord::CANCELLATION_STATUS_REJECTED);
    expect($employee->status)->toBe('inactive');
    expect($employeeUser->archived_at)->not->toBeNull();
});

test('blocking a clearance item requires notes', function () {
    [$adminUser, $adminEmployee] = makeEmployee('HR Department', 'admin');
    assignPosition($adminEmployee, 'head');

    [$headUser, $headEmployee, $department] = makeEmployee('College of Information Technology');
    assignPosition($headEmployee, 'head');

    [, $employee] = makeEmployee('College of Information Technology');
    $employee->update(['department_id' => $department->id]);

    $this->actingAs($adminUser)->post(route('offboarding.store'), [
        'employee_id' => $employee->id,
        'separation_date' => now()->toDateString(),
        'last_working_day' => now()->addDays(3)->toDateString(),
        'resignation_reason' => 'Resignation',
    ]);

    $record = OffboardingRecord::where('employee_id', $employee->id)->firstOrFail();
    $this->actingAs($adminUser)->post(route('offboarding.submit', $record))
        ->assertSessionHas('success');

    $departmentItem = $record->clearanceItems()->where('owner_role', 'department_head')->firstOrFail();

    $this->actingAs($headUser)->patch(route('offboarding.items.update', [$record, $departmentItem]), [
        'status' => ClearanceItem::STATUS_BLOCKED,
        'notes' => '',
    ])->assertSessionHasErrors('notes');

    $departmentItem->refresh();
    expect($departmentItem->status)->toBe(ClearanceItem::STATUS_PENDING);
    expect($departmentItem->notes)->toBeNull();
});

test('rejecting resignation cancellation requires review notes', function () {
    [$adminUser, $adminEmployee] = makeEmployee('HR Department', 'admin');
    assignPosition($adminEmployee, 'head');

    [$employeeUser, $employee] = makeEmployee('College of Information Technology');

    $this->actingAs($adminUser)->post(route('offboarding.store'), [
        'employee_id' => $employee->id,
        'separation_date' => now()->toDateString(),
        'last_working_day' => now()->addDay()->toDateString(),
        'resignation_reason' => 'Resignation',
    ]);

    $record = OffboardingRecord::where('employee_id', $employee->id)->firstOrFail();
    $this->actingAs($adminUser)->post(route('offboarding.submit', $record));

    [$headUser, $headEmployee, $department] = makeEmployee('College of Information Technology');
    assignPosition($headEmployee, 'head');
    $employee->update(['department_id' => $department->id]);
    [$financeUser] = makeFinanceUser();

    $record = $record->fresh();
    $departmentItem = $record->clearanceItems()->where('module_key', 'department_interview_handover')->firstOrFail();
    $financeItem = $record->clearanceItems()->where('module_key', 'finance_clearance')->firstOrFail();
    $hrItem = $record->clearanceItems()->where('module_key', 'hr_final_review')->firstOrFail();

    $this->actingAs($headUser)->patch(route('offboarding.items.update', [$record, $departmentItem]), ['status' => ClearanceItem::STATUS_CLEARED, 'notes' => 'Department done']);
    $this->actingAs($financeUser)->patch(route('offboarding.items.update', [$record, $financeItem]), ['status' => ClearanceItem::STATUS_CLEARED, 'notes' => 'Finance done']);
    $this->actingAs($adminUser)->patch(route('offboarding.items.update', [$record, $hrItem]), ['status' => ClearanceItem::STATUS_CLEARED, 'notes' => 'HR done']);
    $this->actingAs($adminUser)->post(route('offboarding.finalize', $record));

    $this->actingAs($employeeUser)->post(route('offboarding.request-cancellation', $record), [
        'cancellation_reason' => 'Please cancel my resignation.',
    ])->assertSessionHas('success');

    $record->refresh();
    expect($record->cancellation_request_status)->toBe(OffboardingRecord::CANCELLATION_STATUS_PENDING);

    $this->actingAs($adminUser)->post(route('offboarding.reject-cancellation', $record), [
        'cancellation_review_notes' => '',
        'cancellation_review_action' => 'reject',
    ])->assertSessionHasErrors('cancellation_review_notes');

    $record->refresh();
    expect($record->cancellation_request_status)->toBe(OffboardingRecord::CANCELLATION_STATUS_PENDING);
    expect($record->cancellation_reviewed_at)->toBeNull();
});

test('reactivation is blocked while offboarding is active and after completion', function () {
    [$adminUser, $adminEmployee] = makeEmployee('HR Department', 'admin');
    assignPosition($adminEmployee, 'head');

    [$employeeUser, $employee] = makeEmployee('College of Information Technology');

    $this->actingAs($adminUser)->post(route('offboarding.store'), [
        'employee_id' => $employee->id,
        'separation_date' => now()->toDateString(),
        'last_working_day' => now()->addDays(3)->toDateString(),
        'resignation_reason' => 'Resignation',
    ]);

    $record = OffboardingRecord::where('employee_id', $employee->id)->firstOrFail();
    $this->actingAs($adminUser)->post(route('offboarding.submit', $record));

    $this->actingAs($adminUser)->post(route('users.activate', $employeeUser))
        ->assertSessionHas('error');

    [$headUser, $headEmployee, $department] = makeEmployee('College of Information Technology');
    assignPosition($headEmployee, 'head');
    $employee->update(['department_id' => $department->id]);
    [$financeUser] = makeFinanceUser();

    $record = $record->fresh();
    $departmentItem = $record->clearanceItems()->where('module_key', 'department_interview_handover')->firstOrFail();
    $financeItem = $record->clearanceItems()->where('module_key', 'finance_clearance')->firstOrFail();
    $hrItem = $record->clearanceItems()->where('module_key', 'hr_final_review')->firstOrFail();

    $this->actingAs($headUser)->patch(route('offboarding.items.update', [$record, $departmentItem]), ['status' => ClearanceItem::STATUS_CLEARED]);
    $this->actingAs($financeUser)->patch(route('offboarding.items.update', [$record, $financeItem]), ['status' => ClearanceItem::STATUS_CLEARED]);
    $this->actingAs($adminUser)->patch(route('offboarding.items.update', [$record, $hrItem]), ['status' => ClearanceItem::STATUS_CLEARED]);
    $this->actingAs($adminUser)->post(route('offboarding.finalize', $record));

    $this->actingAs($adminUser)->post(route('users.activate', $employeeUser))
        ->assertSessionHas('error');
});

test('reactivation is blocked when assigned position is already filled', function () {
    [$adminUser, $adminEmployee] = makeEmployee('HR Department', 'admin');
    assignPosition($adminEmployee, 'head');

    [$archivedUser, $archivedEmployee, $department] = makeEmployee('College of Information Technology');
    assignPosition($archivedEmployee, 'head');
    $archivedUser->forceFill(['archived_at' => now()])->save();
    $archivedEmployee->update(['status' => 'inactive']);

    [, $replacementEmployee] = makeEmployee('College of Information Technology');
    $replacementEmployee->update(['department_id' => $department->id]);
    assignPosition($replacementEmployee, 'head');

    $this->actingAs($adminUser)
        ->post(route('users.activate', $archivedUser))
        ->assertSessionHas('error', 'This account cannot be reactivated because the assigned position is already filled or not vacant.');

    expect($archivedUser->fresh()->archived_at)->not->toBeNull();
    expect($archivedEmployee->fresh()->status)->toBe('inactive');
});

test('department head can clear only department head clearance items after submission', function () {
    [$adminUser, $adminEmployee] = makeEmployee('HR Department', 'admin');
    assignPosition($adminEmployee, 'head');

    [$headUser, $headEmployee, $department] = makeEmployee('College of Information Technology');
    assignPosition($headEmployee, 'head');

    [, $employee] = makeEmployee('College of Information Technology');
    $employee->update(['department_id' => $department->id]);

    $this->actingAs($adminUser)->post(route('offboarding.store'), [
        'employee_id' => $employee->id,
        'separation_date' => now()->toDateString(),
        'last_working_day' => now()->addDays(3)->toDateString(),
        'resignation_reason' => 'Resignation',
    ]);

    $record = OffboardingRecord::where('employee_id', $employee->id)->firstOrFail();
    $this->actingAs($adminUser)->post(route('offboarding.submit', $record));

    $departmentHeadItem = $record->clearanceItems()->where('owner_role', 'department_head')->firstOrFail();
    $hrItem = $record->clearanceItems()->where('module_key', 'hr_final_review')->firstOrFail();

    $response = $this->actingAs($headUser)->patch(route('offboarding.items.update', [$record, $departmentHeadItem]), [
        'status' => ClearanceItem::STATUS_CLEARED,
        'remarks' => 'Department handover completed.',
    ]);

    expect(in_array($response->getStatusCode(), [200, 302], true))->toBeTrue();
    expect($departmentHeadItem->fresh()->status)->toBe(ClearanceItem::STATUS_CLEARED);

    $this->actingAs($headUser)->patch(route('offboarding.items.update', [$record, $hrItem]), [
        'status' => ClearanceItem::STATUS_CLEARED,
    ])->assertForbidden();
    expect($hrItem->fresh()->status)->toBe(ClearanceItem::STATUS_PENDING);
});

test('admin cannot directly clear department head or finance owned items', function () {
    [$adminUser, $adminEmployee] = makeEmployee('HR Department', 'admin');
    assignPosition($adminEmployee, 'head');

    [$headUser, $headEmployee, $department] = makeEmployee('College of Information Technology');
    assignPosition($headEmployee, 'head');

    [, $employee] = makeEmployee('College of Information Technology');
    $employee->update(['department_id' => $department->id]);

    $this->actingAs($adminUser)->post(route('offboarding.store'), [
        'employee_id' => $employee->id,
        'separation_date' => now()->toDateString(),
        'last_working_day' => now()->addDays(3)->toDateString(),
        'resignation_reason' => 'Resignation',
    ]);

    $record = OffboardingRecord::where('employee_id', $employee->id)->firstOrFail();
    $this->actingAs($adminUser)->post(route('offboarding.submit', $record));

    $departmentHeadItem = $record->clearanceItems()->where('owner_role', 'department_head')->firstOrFail();
    $financeItem = $record->clearanceItems()->where('owner_role', 'finance')->firstOrFail();

    $this->actingAs($adminUser)->patch(route('offboarding.items.update', [$record, $departmentHeadItem]), [
        'status' => ClearanceItem::STATUS_CLEARED,
    ])->assertForbidden();
    expect($departmentHeadItem->fresh()->status)->toBe(ClearanceItem::STATUS_PENDING);

    $this->actingAs($adminUser)->patch(route('offboarding.items.update', [$record, $financeItem]), [
        'status' => ClearanceItem::STATUS_CLEARED,
    ])->assertForbidden();
    expect($financeItem->fresh()->status)->toBe(ClearanceItem::STATUS_PENDING);
});

test('accounting participant can access offboarding records with finance clearance responsibility', function () {
    [$adminUser, $adminEmployee] = makeEmployee('HR Department', 'admin');
    assignPosition($adminEmployee, 'head');

    [$financeUser] = makeFinanceUser();
    [, $employee, $department] = makeEmployee('College of Information Technology');
    [$departmentHeadUser, $departmentHeadEmployee] = makeEmployee($department->department);
    assignPosition($departmentHeadEmployee, 'head');

    $this->actingAs($adminUser)->post(route('offboarding.store'), [
        'employee_id' => $employee->id,
        'separation_date' => now()->toDateString(),
        'last_working_day' => now()->addDays(3)->toDateString(),
        'resignation_reason' => 'Resignation',
    ]);

    $record = OffboardingRecord::where('employee_id', $employee->id)->firstOrFail();
    $this->actingAs($adminUser)->post(route('offboarding.submit', $record))
        ->assertSessionHas('success');

    $departmentItem = $record->clearanceItems()->where('module_key', 'department_interview_handover')->firstOrFail();
    $this->actingAs($departmentHeadUser)->patch(route('offboarding.items.update', [$record, $departmentItem]), [
        'status' => ClearanceItem::STATUS_CLEARED,
        'notes' => 'Department stage complete.',
    ])->assertSessionHas('success');

    $this->actingAs($financeUser)
        ->get(route('offboarding.index'))
        ->assertOk()
        ->assertSee((string) $employee->employee_id);

    $this->actingAs($financeUser)
        ->get(route('offboarding.show', $record))
        ->assertOk();
});

test('department head can access offboarding records for employees in their department', function () {
    [$adminUser, $adminEmployee] = makeEmployee('HR Department', 'admin');
    assignPosition($adminEmployee, 'head');

    [$headUser, $headEmployee, $department] = makeEmployee('College of Information Technology');
    assignPosition($headEmployee, 'head');

    [, $employee] = makeEmployee('College of Information Technology');
    $employee->update(['department_id' => $department->id]);

    $this->actingAs($adminUser)->post(route('offboarding.store'), [
        'employee_id' => $employee->id,
        'separation_date' => now()->toDateString(),
        'last_working_day' => now()->addDays(3)->toDateString(),
        'resignation_reason' => 'Resignation',
    ]);

    $record = OffboardingRecord::where('employee_id', $employee->id)->firstOrFail();
    $this->actingAs($adminUser)->post(route('offboarding.submit', $record))
        ->assertSessionHas('success');

    $this->actingAs($headUser)
        ->get(route('offboarding.index'))
        ->assertOk()
        ->assertSee((string) $employee->employee_id);

    $this->actingAs($headUser)
        ->get(route('offboarding.show', $record))
        ->assertOk();
});

test('department head from another department can still access offboarding records during department review', function () {
    [$adminUser, $adminEmployee] = makeEmployee('HR Department', 'admin');
    assignPosition($adminEmployee, 'head');

    [$otherHeadUser, $otherHeadEmployee] = makeEmployee('College of Business Administration');
    assignPosition($otherHeadEmployee, 'head');

    [, $employee] = makeEmployee('College of Information Technology');

    $this->actingAs($adminUser)->post(route('offboarding.store'), [
        'employee_id' => $employee->id,
        'separation_date' => now()->toDateString(),
        'last_working_day' => now()->addDays(3)->toDateString(),
        'resignation_reason' => 'Resignation',
    ]);

    $record = OffboardingRecord::where('employee_id', $employee->id)->firstOrFail();
    $this->actingAs($adminUser)->post(route('offboarding.submit', $record))
        ->assertSessionHas('success');

    $departmentItem = $record->clearanceItems()->where('module_key', 'department_interview_handover')->firstOrFail();

    $this->actingAs($otherHeadUser)
        ->get(route('offboarding.index'))
        ->assertOk()
        ->assertSee((string) $employee->employee_id);

    $this->actingAs($otherHeadUser)
        ->get(route('offboarding.show', $record))
        ->assertOk();

    $this->actingAs($otherHeadUser)->patch(route('offboarding.items.update', [$record, $departmentItem]), [
        'status' => ClearanceItem::STATUS_CLEARED,
        'notes' => 'Reviewed by department head participant.',
    ])->assertSessionHas('success');
});

test('regular employee can monitor only their own offboarding record', function () {
    [$adminUser, $adminEmployee] = makeEmployee('HR Department', 'admin');
    assignPosition($adminEmployee, 'head');

    [$employeeUser, $employee] = makeEmployee('College of Information Technology');
    [, $otherEmployee] = makeEmployee('College of Information Technology');

    foreach ([$employee, $otherEmployee] as $subject) {
        $this->actingAs($adminUser)->post(route('offboarding.store'), [
            'employee_id' => $subject->id,
            'separation_date' => now()->toDateString(),
            'last_working_day' => now()->addDays(3)->toDateString(),
            'resignation_reason' => 'Resignation',
        ])->assertRedirect();
    }

    $ownRecord = OffboardingRecord::where('employee_id', $employee->id)->firstOrFail();
    $otherRecord = OffboardingRecord::where('employee_id', $otherEmployee->id)->firstOrFail();

    $this->actingAs($employeeUser)
        ->get(route('offboarding.index'))
        ->assertOk()
        ->assertSee((string) $employee->employee_id)
        ->assertDontSee((string) $otherEmployee->employee_id);

    $this->actingAs($employeeUser)
        ->get(route('offboarding.show', $ownRecord))
        ->assertOk();

    $this->actingAs($employeeUser)
        ->get(route('offboarding.show', $otherRecord))
        ->assertForbidden();
});



