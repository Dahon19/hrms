<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $employees = Employee::query()
            ->where('status', 'active')
            ->whereHas('user', function ($query) {
                $query->where('role', '!=', 'admin')
                    ->whereNull('archived_at');
            })
            ->get();

        if ($employees->isEmpty()) {
            return;
        }

        $startDate = now()->subDays(30)->startOfDay();
        $endDate = now()->startOfDay();

        foreach ($employees as $employee) {
            $hireDate = $employee->hire_date ? Carbon::parse($employee->hire_date)->startOfDay() : null;
            $seedStart = $startDate->copy();

            if ($hireDate && $hireDate->gt($endDate)) {
                // Fresh demo accounts can have today's hire date; backfill a short period for sample data.
                $seedStart = $endDate->copy()->subDays(14);
            } elseif ($hireDate && $hireDate->gt($seedStart)) {
                $seedStart = $hireDate->copy();
            }

            if ($seedStart->gt($endDate)) {
                continue;
            }

            for ($date = $seedStart->copy(); $date->lte($endDate); $date->addDay()) {
                if ($date->isWeekend()) {
                    continue;
                }

                $statusRoll = random_int(1, 100);
                $isAbsent = $statusRoll <= 10;
                $isLate = !$isAbsent && $statusRoll <= 30;

                $payload = [
                    'status' => $isAbsent ? 'absent' : ($isLate ? 'late' : 'present'),
                    'morning_time_in' => null,
                    'morning_time_out' => null,
                    'afternoon_time_in' => null,
                    'afternoon_time_out' => null,
                ];

                if (!$isAbsent) {
                    $morningIn = $isLate
                        ? $date->copy()->setTime(8, random_int(16, 45))
                        : $date->copy()->setTime(7, random_int(45, 59));
                    if (!$isLate && random_int(1, 100) <= 35) {
                        $morningIn = $date->copy()->setTime(8, random_int(0, 15));
                    }

                    $morningOut = $date->copy()->setTime(12, random_int(0, 20));
                    $afternoonIn = $date->copy()->setTime(13, random_int(0, 20));
                    $afternoonOut = $date->copy()->setTime(17, random_int(0, 20));

                    $payload['morning_time_in'] = $morningIn->toTimeString();
                    $payload['morning_time_out'] = $morningOut->toTimeString();
                    $payload['afternoon_time_in'] = $afternoonIn->toTimeString();
                    $payload['afternoon_time_out'] = $afternoonOut->toTimeString();
                }

                Attendance::updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'date' => $date->toDateString(),
                    ],
                    $payload
                );
            }
        }
    }
}
