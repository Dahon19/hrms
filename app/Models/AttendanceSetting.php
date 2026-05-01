<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceSetting extends Model
{
    protected $fillable = [
        'shift_start',
        'shift_end',
        'break_start',
        'break_end',
        'grace_minutes',
        'overtime_threshold_minutes',
        'weekend_overtime',
        'require_four_taps',
    ];

    protected $casts = [
        'grace_minutes' => 'integer',
        'overtime_threshold_minutes' => 'integer',
        'weekend_overtime' => 'boolean',
        'require_four_taps' => 'boolean',
    ];

    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1]);
    }
}
