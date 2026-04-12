<?php

namespace App\Models;

use App\Casts\EncryptedValueCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PdsTraining extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'pds_profile_id',
        'title',
        'date_from',
        'date_to',
        'hours',
        'training_type',
        'conducted_by',
    ];

    protected $casts = [
        'title' => EncryptedValueCast::class,
        'date_from' => EncryptedValueCast::class . ':date',
        'date_to' => EncryptedValueCast::class . ':date',
        'hours' => EncryptedValueCast::class . ':integer',
        'training_type' => EncryptedValueCast::class,
        'conducted_by' => EncryptedValueCast::class,
    ];

    public function profile()
    {
        return $this->belongsTo(PdsProfile::class, 'pds_profile_id');
    }
}


