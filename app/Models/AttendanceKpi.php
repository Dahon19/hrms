<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceKpi extends Model
{
    use HasFactory;

    protected $fillable = [
        'month',
        'year',
        'target_percentage',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'target_percentage' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

