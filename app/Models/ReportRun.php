<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportRun extends Model
{
    protected $fillable = [
        'type',
        'status',
        'file_path',
        'metadata',
        'run_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'run_at' => 'datetime',
    ];
}
