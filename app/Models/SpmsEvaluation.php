<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpmsEvaluation extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_FINAL = 'final';

    public const LEGACY_STATUS_MAP = [
        'draft' => self::STATUS_PENDING,
        'submitted' => self::STATUS_SUBMITTED,
        'reviewed' => self::STATUS_SUBMITTED,
        'verified' => self::STATUS_SUBMITTED,
        'locked' => self::STATUS_FINAL,
        self::STATUS_PENDING => self::STATUS_PENDING,
        self::STATUS_SUBMITTED => self::STATUS_SUBMITTED,
        self::STATUS_FINAL => self::STATUS_FINAL,
    ];

    protected $fillable = [
        'employee_id',
        'cycle_id',
        'evaluator_id',
        'status',
        'total_score',
        'rating_label',
    ];

    protected $casts = [
        'total_score' => 'decimal:2',
    ];

    public function getStatusAttribute($value): string
    {
        return self::LEGACY_STATUS_MAP[$value] ?? (string) $value;
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function cycle()
    {
        return $this->belongsTo(SpmsCycle::class, 'cycle_id');
    }

    public function evaluator()
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    public function details()
    {
        return $this->hasMany(SpmsEvaluationDetail::class, 'evaluation_id');
    }

}
