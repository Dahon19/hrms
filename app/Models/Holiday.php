<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Holiday extends Model
{
    protected $fillable = [
        'holiday_date',
        'name',
        'type',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'holiday_date' => 'date',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function tableAvailable(): bool
    {
        static $resolved = null;

        if ($resolved !== null) {
            return $resolved;
        }

        try {
            $resolved = Schema::hasTable((new static())->getTable());
        } catch (\Throwable) {
            $resolved = false;
        }

        return $resolved;
    }
}
