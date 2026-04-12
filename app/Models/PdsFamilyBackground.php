<?php

namespace App\Models;

use App\Casts\EncryptedValueCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PdsFamilyBackground extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $casts = [
        'spouse_last_name' => EncryptedValueCast::class,
        'spouse_first_name' => EncryptedValueCast::class,
        'spouse_middle_name' => EncryptedValueCast::class,
        'spouse_occupation' => EncryptedValueCast::class,
        'spouse_employer' => EncryptedValueCast::class,
        'spouse_business_address' => EncryptedValueCast::class,
        'spouse_telephone' => EncryptedValueCast::class,
        'father_last_name' => EncryptedValueCast::class,
        'father_first_name' => EncryptedValueCast::class,
        'father_middle_name' => EncryptedValueCast::class,
        'father_name_extension' => EncryptedValueCast::class,
        'mother_last_name' => EncryptedValueCast::class,
        'mother_first_name' => EncryptedValueCast::class,
        'mother_middle_name' => EncryptedValueCast::class,
    ];

    protected $fillable = [
        'pds_profile_id',
        'spouse_last_name',
        'spouse_first_name',
        'spouse_middle_name',
        'spouse_occupation',
        'spouse_employer',
        'spouse_business_address',
        'spouse_telephone',
        'father_last_name',
        'father_first_name',
        'father_middle_name',
        'father_name_extension',
        'mother_last_name',
        'mother_first_name',
        'mother_middle_name',
    ];

    public function profile()
    {
        return $this->belongsTo(PdsProfile::class, 'pds_profile_id');
    }
}


