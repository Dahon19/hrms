<?php

namespace App\Models;

use App\Casts\EncryptedValueCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PdsOtherInfo extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $casts = [
        'description' => EncryptedValueCast::class,
    ];

    protected $fillable = [
        'pds_profile_id',
        'info_type',
        'description',
    ];

    public function profile()
    {
        return $this->belongsTo(PdsProfile::class, 'pds_profile_id');
    }
}


