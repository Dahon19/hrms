<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $response = $this->post('/login', [
        'login' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('regular employees are redirected to attendance after login', function () {
    $department = Department::create([
        'department' => 'Registrar Office',
        'department_type' => 'Administrative',
    ]);

    $user = User::factory()->create(['role' => 'employee']);

    Employee::create([
        'user_id' => $user->id,
        'employee_id' => '26-91001',
        'first_name' => 'Regular',
        'last_name' => 'Employee',
        'department_id' => $department->id,
        'status' => 'active',
        'hire_date' => now()->subYear()->toDateString(),
    ]);

    $response = $this->post('/login', [
        'login' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('attendance.history', [
        'period' => 'weekly',
        'date' => now()->toDateString(),
    ], absolute: false));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'login' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/login');
});
