<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceMonthlyScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'month',
        'year',
        'total_work_days',
        'total_absences',
        'late_undertime_days',
        'attendance_rate',
        'punctuality_rate',
        'final_score',
        'rating',
        'attendance_incentive_eligible',
        'status',
    ];

    protected $casts = [
        'attendance_rate' => 'decimal:2',
        'punctuality_rate' => 'decimal:2',
        'final_score' => 'decimal:2',
        'attendance_incentive_eligible' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}

