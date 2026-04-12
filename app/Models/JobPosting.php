<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobPosting extends Model
{
    use HasFactory;

    protected $fillable = [
        'position_id',
        'title',
        'department_id',
        'description',
        'requirements',
        'employment_type',
        'status',
        'required_headcount',
        'closing_date',
    ];

    protected $casts = [
        'closing_date' => 'date',
        'required_headcount' => 'integer',
    ];

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function applicants()
    {
        return $this->hasMany(Applicant::class);
    }

    public function hiredApplicants()
    {
        return $this->hasMany(Applicant::class)->where('status', 'hired');
    }

    public function getFulfilledCountAttribute(): int
    {
        if (array_key_exists('hired_count', $this->attributes)) {
            return (int) $this->attributes['hired_count'];
        }

        return (int) $this->applicants()
            ->where('status', 'hired')
            ->count();
    }

    public function getRemainingSlotsAttribute(): int
    {
        $required = max((int) ($this->required_headcount ?? 1), 1);
        $remaining = $required - $this->fulfilled_count;
        return max($remaining, 0);
    }
}
