<?php

namespace App\Models;

use App\Casts\EncryptedValueCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PdsVoluntaryWork extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'pds_profile_id',
        'organization_name',
        'date_from',
        'date_to',
        'hours',
        'position_nature',
    ];

    protected $casts = [
        'organization_name' => EncryptedValueCast::class,
        'date_from' => EncryptedValueCast::class . ':date',
        'date_to' => EncryptedValueCast::class . ':date',
        'hours' => EncryptedValueCast::class . ':integer',
        'position_nature' => EncryptedValueCast::class,
    ];

    public function profile()
    {
        return $this->belongsTo(PdsProfile::class, 'pds_profile_id');
    }
}


