<?php

use App\Http\Controllers\AttendanceController;
use App\Models\Attendance;
use App\Models\AttendanceSetting;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    AttendanceSetting::current()->update([
        'shift_start' => '08:00:00',
        'shift_end' => '17:00:00',
        'break_start' => '12:00:00',
        'break_end' => '12:31:00',
        'grace_minutes' => 15,
        'overtime_threshold_minutes' => 60,
        'weekend_overtime' => true,
        'require_four_taps' => true,
    ]);
});

test('noon taps before 12 31 pm still resolve to morning time out', function () {
    $controller = app(AttendanceController::class);
    $method = new ReflectionMethod($controller, 'resolveSlotForTap');
    $method->setAccessible(true);

    $attendance = new Attendance([
        'employee_id' => 1,
        'date' => '2026-04-07',
    ]);

    expect($method->invoke($controller, $attendance, '2026-04-07', Carbon::parse('2026-04-07 12:00:00')))
        ->toBe('morning_time_out');

    expect($method->invoke($controller, $attendance, '2026-04-07', Carbon::parse('2026-04-07 12:30:00')))
        ->toBe('morning_time_out');
});

test('afternoon time in only starts at 12 31 pm', function () {
    $controller = app(AttendanceController::class);
    $method = new ReflectionMethod($controller, 'resolveSlotForTap');
    $method->setAccessible(true);

    $attendance = new Attendance([
        'employee_id' => 1,
        'date' => '2026-04-07',
        'morning_time_out' => '12:00:00',
    ]);

    expect($method->invoke($controller, $attendance, '2026-04-07', Carbon::parse('2026-04-07 12:30:00')))
        ->toBeNull();

    expect($method->invoke($controller, $attendance, '2026-04-07', Carbon::parse('2026-04-07 12:31:00')))
        ->toBe('afternoon_time_in');
});
