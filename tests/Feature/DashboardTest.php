<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can view dashboard', function () {
    $department = Department::create([
        'department' => 'HR Department',
        'department_type' => 'Administrative',
    ]);
    $user = User::factory()->create(['role' => 'admin']);
    Employee::create([
        'user_id' => $user->id,
        'employee_id' => '26-90001',
        'first_name' => 'Admin',
        'last_name' => 'User',
        'department_id' => $department->id,
        'status' => 'active',
        'hire_date' => now()->subYear()->toDateString(),
    ]);
    
    $response = $this->actingAs($user)->get('/dashboard');
    
    $response->assertStatus(200);
    $response->assertSee('HRMS Control Center');
    $response->assertViewHas('dashboard', function ($dashboard) {
        return is_array($dashboard)
            && array_key_exists('actions', $dashboard)
            && array_key_exists('kpis', $dashboard)
            && array_key_exists('charts', $dashboard)
            && array_key_exists('activities', $dashboard)
            && array_key_exists('recruitment', $dashboard)
            && array_key_exists('calendar', $dashboard)
            && array_key_exists('notifications', $dashboard)
            && array_key_exists('action_center', $dashboard);
    });
});

test('employee is redirected away from dashboard', function () {
     $department = Department::create([
         'department' => 'IT Department',
         'department_type' => 'Administrative',
     ]);
     $user = User::factory()->create(['role' => 'employee']);
     Employee::create([
         'user_id' => $user->id,
         'employee_id' => '26-90002',
         'first_name' => 'Regular',
         'last_name' => 'Employee',
         'department_id' => $department->id,
         'status' => 'active',
         'hire_date' => now()->subYear()->toDateString(),
     ]);
     
    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertRedirect(route('attendance.history', [
        'period' => 'weekly',
        'date' => now()->toDateString(),
    ]));
});
