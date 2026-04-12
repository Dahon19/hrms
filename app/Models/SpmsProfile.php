<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpmsProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'primary_evaluator_id',
        'secondary_reviewer_id',
        'self_assessment_enabled',
    ];

    protected $casts = [
        'self_assessment_enabled' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function primaryEvaluator()
    {
        return $this->belongsTo(User::class, 'primary_evaluator_id');
    }

    public function secondaryReviewer()
    {
        return $this->belongsTo(User::class, 'secondary_reviewer_id');
    }
}

