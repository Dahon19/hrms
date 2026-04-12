<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepartmentMetric extends Model
{
    protected $fillable = [
        'department_id',
        'metric_date',
        'total_employees',
        'attendance_rate',
        'leave_requests',
        'leave_approved',
        'document_compliance_rate',
    ];

    protected $casts = [
        'metric_date' => 'date',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}
