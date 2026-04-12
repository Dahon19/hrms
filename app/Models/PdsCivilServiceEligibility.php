<?php

namespace App\Models;

use App\Casts\EncryptedValueCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PdsCivilServiceEligibility extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'pds_profile_id',
        'eligibility_type',
        'rating',
        'exam_date',
        'exam_place',
        'license_number',
        'validity_date',
    ];

    protected $casts = [
        'eligibility_type' => EncryptedValueCast::class,
        'rating' => EncryptedValueCast::class,
        'exam_date' => EncryptedValueCast::class . ':date',
        'exam_place' => EncryptedValueCast::class,
        'license_number' => EncryptedValueCast::class,
        'validity_date' => EncryptedValueCast::class . ':date',
    ];

    public function profile()
    {
        return $this->belongsTo(PdsProfile::class, 'pds_profile_id');
    }
}


