<?php

use App\Http\Controllers\AttendanceController;
use App\Models\Attendance;
use Carbon\Carbon;
use Tests\TestCase;

uses(TestCase::class);

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
