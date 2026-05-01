<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;

test('profile page is displayed', function () {
    $user = makeUserWithEmployee('profile-page');

    $response = $this
        ->actingAs($user)
        ->get('/profile');

    $response->assertOk();
});

test('profile email can be updated while account name remains unchanged', function () {
    $user = makeUserWithEmployee('profile-update');
    $originalName = $user->name;

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $user->refresh();

    $this->assertSame($originalName, $user->name);
    $this->assertSame('test@example.com', $user->email);
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = makeUserWithEmployee('profile-email');

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');
});

test('profile can be updated from a plain post submission', function () {
    $user = makeUserWithEmployee('profile-post-update');

    $response = $this
        ->actingAs($user)
        ->post('/profile', [
            'email' => 'post-profile@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertSame('post-profile@example.com', $user->fresh()->email);
});

test('account name cannot be updated directly from profile', function () {
    $user = makeUserWithEmployee('profile-name-locked');
    $originalName = $user->name;

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->patch('/profile', [
            'name' => 'Changed Name',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasErrors(['name'])
        ->assertRedirect('/profile');

    $this->assertSame($originalName, $user->fresh()->name);
});

test('hr staff can update own account name from profile', function () {
    $user = makeUserWithEmployee('profile-name-hr', 'HR Department');

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'HR Updated Name',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertSame('HR Updated Name', $user->fresh()->name);
});

test('user can delete their account', function () {
    $user = makeUserWithEmployee('profile-delete');

    $response = $this
        ->actingAs($user)
        ->delete('/profile', [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    $this->assertNotNull($user->fresh());
    $this->assertNotNull($user->fresh()->archived_at);
});

test('correct password must be provided to delete account', function () {
    $user = makeUserWithEmployee('profile-delete-invalid');

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->delete('/profile', [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrorsIn('userDeletion', 'password')
        ->assertRedirect('/profile');

    $this->assertNotNull($user->fresh());
});

function makeUserWithEmployee(string $suffix, ?string $departmentName = null): User
{
    $department = Department::create([
        'department' => $departmentName ?? ('Operations Department ' . strtoupper(substr($suffix, 0, 3))),
        'department_type' => 'Administrative',
    ]);

    $user = User::factory()->create([
        'email' => $suffix . '@example.com',
    ]);

    Employee::create([
        'user_id' => $user->id,
        'employee_id' => '26-' . str_pad((string) random_int(10000, 99999), 5, '0', STR_PAD_LEFT),
        'first_name' => 'Test',
        'last_name' => 'User',
        'department_id' => $department->id,
        'status' => 'active',
        'hire_date' => now()->subYears(1)->toDateString(),
    ]);

    return $user;
}
