<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $table = 'attendance';

    protected $fillable = [
        'employee_id',
        'date',
        'morning_time_in',
        'morning_time_out',
        'afternoon_time_in',
        'afternoon_time_out',
        'status'
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function firstInTime(): ?string
    {
        return $this->morning_time_in ?: $this->afternoon_time_in;
    }

    public function lastOutTime(): ?string
    {
        return $this->afternoon_time_out ?: $this->morning_time_out;
    }

    /**
     * Total minutes worked across morning and afternoon sessions.
     */
    public function totalMinutesWorked(): int
    {
        $minutes = 0;

        if ($this->morning_time_in && $this->morning_time_out) {
            $in  = Carbon::parse($this->morning_time_in);
            $out = Carbon::parse($this->morning_time_out);
            if ($out->gt($in)) {
                $minutes += $in->diffInMinutes($out);
            }
        }

        if ($this->afternoon_time_in && $this->afternoon_time_out) {
            $in  = Carbon::parse($this->afternoon_time_in);
            $out = Carbon::parse($this->afternoon_time_out);
            if ($out->gt($in)) {
                $minutes += $in->diffInMinutes($out);
            }
        }

        return $minutes;
    }

    /**
     * Human-readable hours worked, e.g. "7h 45m".
     */
    protected function hoursWorked(): Attribute
    {
        return Attribute::get(function () {
            $total = $this->totalMinutesWorked();
            if ($total === 0) {
                return null;
            }
            $h = intdiv($total, 60);
            $m = $total % 60;
            return $m > 0 ? "{$h}h {$m}m" : "{$h}h";
        });
    }

    /**
     * Whether the employee clocked in late.
     * Standard start: 08:00 AM (configurable via ATTENDANCE_START_TIME in .env).
     */
    protected function isLate(): Attribute
    {
        return Attribute::get(function () {
            $firstIn = $this->firstInTime();
            if (! $firstIn) {
                return false;
            }
            $cutoff = Carbon::parse(
                $this->date->format('Y-m-d') . ' ' . config('hrms.attendance.start_time', '08:05')
            );
            return Carbon::parse($firstIn)->gt($cutoff);
        });
    }

    /**
     * Whether the employee left early (undertime).
     * Standard end: 05:00 PM (configurable via ATTENDANCE_END_TIME in .env).
     */
    protected function isUndertime(): Attribute
    {
        return Attribute::get(function () {
            $lastOut = $this->lastOutTime();
            if (! $lastOut) {
                return false;
            }
            $cutoff = Carbon::parse(
                $this->date->format('Y-m-d') . ' ' . config('hrms.attendance.end_time', '17:00')
            );
            return Carbon::parse($lastOut)->lt($cutoff);
        });
    }

    /**
     * Minutes arrived late past the standard start time.
     */
    public function lateMinutes(): int
    {
        $firstIn = $this->firstInTime();
        if (! $firstIn || ! $this->is_late) {
            return 0;
        }
        $cutoff = Carbon::parse(
            $this->date->format('Y-m-d') . ' ' . config('hrms.attendance.start_time', '08:05')
        );
        return (int) $cutoff->diffInMinutes(Carbon::parse($firstIn));
    }
}
