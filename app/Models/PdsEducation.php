<?php

namespace App\Models;

use App\Casts\EncryptedValueCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PdsEducation extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'pds_educations';

    protected $fillable = [
        'pds_profile_id',
        'education_level',
        'school_name',
        'degree_course',
        'date_from',
        'date_to',
        'highest_level_units',
        'year_graduated',
        'honors_received',
    ];

    protected $casts = [
        'school_name' => EncryptedValueCast::class,
        'degree_course' => EncryptedValueCast::class,
        'date_from' => EncryptedValueCast::class . ':date',
        'date_to' => EncryptedValueCast::class . ':date',
        'highest_level_units' => EncryptedValueCast::class,
        'year_graduated' => EncryptedValueCast::class,
        'honors_received' => EncryptedValueCast::class,
    ];

    public function profile()
    {
        return $this->belongsTo(PdsProfile::class, 'pds_profile_id');
    }
}


