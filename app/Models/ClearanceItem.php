<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClearanceItem extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_CLEARED = 'cleared';
    public const STATUS_BLOCKED = 'blocked';

    protected $fillable = [
        'offboarding_record_id',
        'unit_name',
        'owner_role',
        'module_key',
        'item_name',
        'status',
        'approved_by_user_id',
        'approved_at',
        'required',
        'notes',
        'remarks',
        'cleared_by',
        'cleared_at',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'required' => 'boolean',
            'approved_at' => 'datetime',
            'cleared_at' => 'datetime',
        ];
    }

    public function offboardingRecord()
    {
        return $this->belongsTo(OffboardingRecord::class);
    }

    public function clearedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}

