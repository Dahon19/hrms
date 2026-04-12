<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RewardRecord extends Model
{
    use HasFactory;

    protected $table = 'rewards_records';

    protected $fillable = [
        'employee_id',
        'award_type',
        'milestone_type',
        'eligibility_reference',
        'award_title',
        'award_date',
        'remarks',
        'assigned_by',
        'override_used',
        'override_reason',
    ];

    protected $casts = [
        'award_date' => 'date',
        'override_used' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \RuntimeException('Reward records are immutable and cannot be updated.');
        });

        static::deleting(function () {
            throw new \RuntimeException('Reward records are immutable and cannot be deleted.');
        });
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
