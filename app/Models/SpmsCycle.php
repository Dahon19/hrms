<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpmsCycle extends Model
{
    use HasFactory;

    public const STATUS_SETUP = 'setup';
    public const STATUS_EVALUATION = 'evaluation';
    public const STATUS_CLOSED = 'closed';

    public const LEGACY_STATUS_MAP = [
        'draft' => self::STATUS_SETUP,
        'submitted' => self::STATUS_EVALUATION,
        'reviewed' => self::STATUS_EVALUATION,
        'locked' => self::STATUS_CLOSED,
        self::STATUS_SETUP => self::STATUS_SETUP,
        self::STATUS_EVALUATION => self::STATUS_EVALUATION,
        self::STATUS_CLOSED => self::STATUS_CLOSED,
    ];

    protected $fillable = [
        'title',
        'period_start',
        'period_end',
        'status',
        'ready_for_closure_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'ready_for_closure_at' => 'datetime',
    ];

    public function getStatusAttribute($value): string
    {
        return self::LEGACY_STATUS_MAP[$value] ?? (string) $value;
    }

    public function evaluations()
    {
        return $this->hasMany(SpmsEvaluation::class, 'cycle_id');
    }

    public function individualDevelopmentPlans()
    {
        return $this->hasMany(IndividualDevelopmentPlan::class, 'spms_cycle_id');
    }

    public function isReadyForClosure(): bool
    {
        return !is_null($this->ready_for_closure_at) && $this->status === self::STATUS_EVALUATION;
    }
}
