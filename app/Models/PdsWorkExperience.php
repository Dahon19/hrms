<?php

namespace App\Models;

use App\Casts\EncryptedValueCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PdsWorkExperience extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'pds_profile_id',
        'date_from',
        'date_to',
        'position_title',
        'department_office',
        'salary_grade',
        'appointment_status',
        'sector',
    ];

    protected $casts = [
        'date_from' => EncryptedValueCast::class . ':date',
        'date_to' => EncryptedValueCast::class . ':date',
        'position_title' => EncryptedValueCast::class,
        'department_office' => EncryptedValueCast::class,
        'salary_grade' => EncryptedValueCast::class,
        'appointment_status' => EncryptedValueCast::class,
    ];

    public function profile()
    {
        return $this->belongsTo(PdsProfile::class, 'pds_profile_id');
    }
}


