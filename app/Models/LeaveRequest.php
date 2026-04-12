<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'start_date',
        'end_date',
        'status',
        'reason',
        'attachment_path',
        'head_reviewed_by',
        'head_reviewed_at',
        'president_reviewed_by',
        'president_reviewed_at',
        'hr_reviewed_by',
        'hr_reviewed_at',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'head_reviewed_at' => 'datetime',
        'president_reviewed_at' => 'datetime',
        'hr_reviewed_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function headReviewer()
    {
        return $this->belongsTo(User::class, 'head_reviewed_by');
    }

    public function hrReviewer()
    {
        return $this->belongsTo(User::class, 'hr_reviewed_by');
    }

    public function presidentReviewer()
    {
        return $this->belongsTo(User::class, 'president_reviewed_by');
    }
}
