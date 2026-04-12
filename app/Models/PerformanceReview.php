<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PerformanceReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'review_year',
        'spms_score',
        'rating',
        'remarks',
    ];

    protected $casts = [
        'spms_score' => 'decimal:2',
        'review_year' => 'integer',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}

