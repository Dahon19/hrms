<?php

namespace App\Models;

use App\Casts\EncryptedValueCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PdsPersonalInfo extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'pds_profile_id',
        'last_name',
        'first_name',
        'middle_name',
        'name_extension',
        'birth_date',
        'birth_place',
        'sex',
        'civil_status',
        'citizenship',
        'height_m',
        'weight_kg',
        'blood_type',
        'gsis_no',
        'sss_no',
        'tin_no',
        'philhealth_no',
        'residential_address',
        'permanent_address',
        'telephone_no',
        'mobile_no',
        'email_address',
    ];

    protected $casts = [
        'last_name' => EncryptedValueCast::class,
        'first_name' => EncryptedValueCast::class,
        'middle_name' => EncryptedValueCast::class,
        'name_extension' => EncryptedValueCast::class,
        'birth_date' => EncryptedValueCast::class . ':date',
        'birth_place' => EncryptedValueCast::class,
        'sex' => EncryptedValueCast::class,
        'civil_status' => EncryptedValueCast::class,
        'citizenship' => EncryptedValueCast::class,
        'height_m' => EncryptedValueCast::class . ':decimal,2',
        'weight_kg' => EncryptedValueCast::class . ':decimal,2',
        'blood_type' => EncryptedValueCast::class,
        'gsis_no' => EncryptedValueCast::class,
        'sss_no' => EncryptedValueCast::class,
        'tin_no' => EncryptedValueCast::class,
        'philhealth_no' => EncryptedValueCast::class,
        'residential_address' => EncryptedValueCast::class,
        'permanent_address' => EncryptedValueCast::class,
        'telephone_no' => EncryptedValueCast::class,
        'mobile_no' => EncryptedValueCast::class,
        'email_address' => EncryptedValueCast::class,
    ];

    public function profile()
    {
        return $this->belongsTo(PdsProfile::class, 'pds_profile_id');
    }
}


