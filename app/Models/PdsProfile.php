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

}


