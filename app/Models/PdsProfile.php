<?php

namespace App\Models;

use App\Casts\EncryptedValueCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PdsProfile extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'employee_id',
        'status',
        'submitted_at',
        'submitted_by',
        'verified_at',
        'verified_by',
        'correction_requested_at',
        'correction_requested_by',
        'last_encoded_by',
        'hr_remarks',
        'section_completion',
        'notes',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'verified_at' => 'datetime',
        'correction_requested_at' => 'datetime',
        'section_completion' => 'array',
        'hr_remarks' => EncryptedValueCast::class,
        'notes' => EncryptedValueCast::class,
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function personalInfo()
    {
        return $this->hasOne(PdsPersonalInfo::class);
    }

    public function familyBackground()
    {
        return $this->hasOne(PdsFamilyBackground::class);
    }

    public function children()
    {
        return $this->hasMany(PdsChild::class);
    }

    public function educations()
    {
        return $this->hasMany(PdsEducation::class);
    }

    public function civilServiceEligibilities()
    {
        return $this->hasMany(PdsCivilServiceEligibility::class);
    }

    public function workExperiences()
    {
        return $this->hasMany(PdsWorkExperience::class);
    }

    public function voluntaryWorks()
    {
        return $this->hasMany(PdsVoluntaryWork::class);
    }

    public function trainings()
    {
        return $this->hasMany(PdsTraining::class);
    }

    public function otherInfos()
    {
        return $this->hasMany(PdsOtherInfo::class);
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function correctionRequester()
    {
        return $this->belongsTo(User::class, 'correction_requested_by');
    }

    public function lastEncoder()
    {
        return $this->belongsTo(User::class, 'last_encoded_by');
    }

    /**
     * Overall PDS completeness as a 0–100 integer percentage.
     * Derived from the section_completion JSON array where each key
     * maps to a boolean (or 1/0) indicating section fill status.
     */
    public function completenessScore(): int
    {
        $sections = $this->section_completion;
        if (empty($sections) || ! is_array($sections)) {
            return 0;
        }

        $total    = count($sections);
        $complete = count(array_filter($sections, fn ($v) => (bool) $v));

        return $total > 0 ? (int) round(($complete / $total) * 100) : 0;
    }

    /**
     * Human-readable completeness label.
     */
    public function completenessLabel(): string
    {
        $score = $this->completenessScore();
        return match (true) {
            $score === 100 => 'Complete',
            $score >= 75   => 'Almost Complete',
            $score >= 50   => 'Halfway Done',
            $score >= 25   => 'In Progress',
            default        => 'Just Started',
        };
    }

    /**
     * Bootstrap color class for the completeness badge/progress bar.
     */
    public function completenessColorClass(): string
    {
        $score = $this->completenessScore();
        return match (true) {
            $score === 100 => 'success',
            $score >= 75   => 'info',
            $score >= 50   => 'primary',
            $score >= 25   => 'warning',
            default        => 'danger',
        };
    }
}


