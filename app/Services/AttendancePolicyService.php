<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceAnomaly;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

use App\Models\AttendanceSetting;

class AttendancePolicyService
{

    public function applyPolicy(Attendance $attendance): void
    {
        DB::transaction(function () use ($attendance): void {
            $analysis = $this->analyze($attendance);
            if ($analysis['status']) {
                $attendance->status = $analysis['status'];
                $attendance->save();
            }

            $currentTypes = collect($analysis['anomalies'])->pluck('type')->values()->all();
            $anomalyQuery = AttendanceAnomaly::query()
                ->where('employee_id', $attendance->employee_id)
                ->whereDate('date', $attendance->date);

            if (empty($currentTypes)) {
                $anomalyQuery->delete();
                return;
            }

            $anomalyQuery->whereNotIn('type', $currentTypes)->delete();

            foreach ($analysis['anomalies'] as $anomaly) {
                AttendanceAnomaly::updateOrCreate(
                    [
                        'employee_id' => $attendance->employee_id,
                        'date' => $attendance->date,
                        'type' => $anomaly['type'],
                    ],
                    [
                        'minutes' => $anomaly['minutes'],
                        'metadata' => $anomaly['metadata'] ?? [],
                    ]
                );
            }
        });
    }

    private function analyze(Attendance $attendance): array
    {
        $dateString = Carbon::parse($attendance->date)->toDateString();
        $anomalies = [];
        $status = 'absent';

        $firstIn = $this->firstIn($attendance, $dateString);
        $lastOut = $this->lastOut($attendance, $dateString);
        $workedMinutes = $this->workedMinutes($attendance, $dateString);
        $setting = AttendanceSetting::current();
        
        $shiftStart = Carbon::parse($dateString . ' ' . $setting->shift_start);
        $shiftEnd = Carbon::parse($dateString . ' ' . $setting->shift_end);
        $breakStart = Carbon::parse($dateString . ' ' . $setting->break_start);
        $breakEnd = Carbon::parse($dateString . ' ' . $setting->break_end);
        $graceMinutes = $setting->grace_minutes;
        $overtimeThreshold = $setting->overtime_threshold_minutes;

        if (!$firstIn) {
            $anomalies[] = [
                'type' => 'missing_time_in',
                'minutes' => 0,
            ];

            if ($lastOut) {
                $anomalies[] = [
                    'type' => 'missing_time_out',
                    'minutes' => 0,
                ];
            }

            return [
                'status' => $status,
                'anomalies' => $anomalies,
            ];
        }

        $status = 'present';

        $lateThreshold = $shiftStart->copy()->addMinutes($graceMinutes);
        if ($firstIn->greaterThan($lateThreshold)) {
            $minutesLate = $lateThreshold->diffInMinutes($firstIn);
            $status = 'late';
            $anomalies[] = [
                'type' => 'late',
                'minutes' => $minutesLate,
                'metadata' => [
                    'shift_start' => $shiftStart->format('H:i:s'),
                    'grace_minutes' => $graceMinutes,
                ],
            ];
        }

        if (!$lastOut) {
            $anomalies[] = [
                'type' => 'missing_time_out',
                'minutes' => 0,
            ];

            return [
                'status' => $status,
                'anomalies' => $anomalies,
            ];
        }

        $isWeekend = in_array(Carbon::parse($attendance->date)->dayOfWeekIso, [6, 7], true);
        if ($isWeekend && $setting->weekend_overtime) {
            $anomalies[] = [
                'type' => 'weekend_overtime',
                'minutes' => $workedMinutes,
                'metadata' => [
                    'shift_start' => $shiftStart->format('H:i:s'),
                    'shift_end' => $shiftEnd->format('H:i:s'),
                ],
            ];

            return [
                'status' => $status,
                'anomalies' => $anomalies,
            ];
        }

        $expectedMinutes = $this->expectedShiftMinutes($shiftStart, $shiftEnd, $breakStart, $breakEnd);

        if ($workedMinutes < $expectedMinutes) {
            $anomalies[] = [
                'type' => 'undertime',
                'minutes' => $expectedMinutes - $workedMinutes,
                'metadata' => [
                    'expected_minutes' => $expectedMinutes,
                    'worked_minutes' => $workedMinutes,
                ],
            ];
        }

        if ($workedMinutes > ($expectedMinutes + $overtimeThreshold)) {
            $anomalies[] = [
                'type' => 'overtime',
                'minutes' => $workedMinutes - $expectedMinutes,
                'metadata' => [
                    'expected_minutes' => $expectedMinutes,
                    'worked_minutes' => $workedMinutes,
                    'threshold_minutes' => $overtimeThreshold,
                ],
            ];
        }

        return [
            'status' => $status,
            'anomalies' => $anomalies,
        ];
    }

    private function firstIn(Attendance $attendance, string $date): ?Carbon
    {
        foreach (['morning_time_in', 'afternoon_time_in'] as $field) {
            if ($attendance->{$field}) {
                return Carbon::parse($date . ' ' . $attendance->{$field});
            }
        }

        return null;
    }

    private function lastOut(Attendance $attendance, string $date): ?Carbon
    {
        foreach (['afternoon_time_out', 'morning_time_out'] as $field) {
            if ($attendance->{$field}) {
                return Carbon::parse($date . ' ' . $attendance->{$field});
            }
        }

        return null;
    }

    private function workedMinutes(Attendance $attendance, string $date): int
    {
        $setting = AttendanceSetting::current();
        
        if (!$setting->require_four_taps) {
            $totalMins = $this->sessionMinutes($attendance->morning_time_in, $attendance->afternoon_time_out, $date);
            if ($totalMins === 0) {
                return 0;
            }

            $breakStart = Carbon::parse($date . ' ' . $setting->break_start);
            $breakEnd = Carbon::parse($date . ' ' . $setting->break_end);
            $clockIn = Carbon::parse($date . ' ' . $attendance->morning_time_in);
            $clockOut = Carbon::parse($date . ' ' . $attendance->afternoon_time_out);
            
            $overlapStart = $clockIn->greaterThan($breakStart) ? $clockIn : $breakStart;
            $overlapEnd = $clockOut->lessThan($breakEnd) ? $clockOut : $breakEnd;
            
            $breakMinutes = $overlapEnd->greaterThan($overlapStart)
                ? $overlapStart->diffInMinutes($overlapEnd)
                : 0;

            return max(0, $totalMins - $breakMinutes);
        }

        return $this->sessionMinutes($attendance->morning_time_in, $attendance->morning_time_out, $date)
            + $this->sessionMinutes($attendance->afternoon_time_in, $attendance->afternoon_time_out, $date);
    }

    private function sessionMinutes(?string $in, ?string $out, string $date): int
    {
        if (!$in || !$out) {
            return 0;
        }

        $clockIn = Carbon::parse($date . ' ' . $in);
        $clockOut = Carbon::parse($date . ' ' . $out);

        if ($clockOut->lessThanOrEqualTo($clockIn)) {
            return 0;
        }

        return $clockIn->diffInMinutes($clockOut);
    }

    private function expectedShiftMinutes(Carbon $shiftStart, Carbon $shiftEnd, Carbon $breakStart, Carbon $breakEnd): int
    {
        if ($shiftEnd->lessThanOrEqualTo($shiftStart)) {
            return 0;
        }

        $shiftMinutes = $shiftStart->diffInMinutes($shiftEnd);
        if ($breakEnd->lessThanOrEqualTo($breakStart)) {
            return $shiftMinutes;
        }

        $overlapStart = $shiftStart->greaterThan($breakStart) ? $shiftStart : $breakStart;
        $overlapEnd = $shiftEnd->lessThan($breakEnd) ? $shiftEnd : $breakEnd;
        $breakMinutes = $overlapEnd->greaterThan($overlapStart)
            ? $overlapStart->diffInMinutes($overlapEnd)
            : 0;

        return max(0, $shiftMinutes - $breakMinutes);
    }
    public function getShiftEnd(): string
    {
        return AttendanceSetting::current()->shift_end;
    }

    public function getBreakStart(): string
    {
        return AttendanceSetting::current()->break_start;
    }
}
