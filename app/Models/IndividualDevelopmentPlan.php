<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IndividualDevelopmentPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'spms_cycle_id',
        'spms_evaluation_id',
        'status',
        'final_spms_score',
        'final_spms_rating',
        'competency_gaps',
        'development_goals',
        'employee_notes',
        'created_by',
    ];

    protected $casts = [
        'final_spms_score' => 'decimal:2',
        'competency_gaps' => 'array',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function cycle()
    {
        return $this->belongsTo(SpmsCycle::class, 'spms_cycle_id');
    }

    public function evaluation()
    {
        return $this->belongsTo(SpmsEvaluation::class, 'spms_evaluation_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
