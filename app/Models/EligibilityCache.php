<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EligibilityCache extends Model
{
    use HasFactory;

    protected $table = 'eligibility_cache';

    protected $fillable = [
        'employee_id',
        'year',
        'eligible_tenure',
        'eligible_attendance',
        'eligible_performance',
        'tenure_years',
        'tenure_milestone',
        'attendance_score',
        'attendance_rating',
        'spms_score',
        'spms_rating',
        'payload',
        'computed_at',
    ];

    protected $casts = [
        'eligible_tenure' => 'boolean',
        'eligible_attendance' => 'boolean',
        'eligible_performance' => 'boolean',
        'attendance_score' => 'decimal:2',
        'spms_score' => 'decimal:2',
        'payload' => 'array',
        'computed_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}

