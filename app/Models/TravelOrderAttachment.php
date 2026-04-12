<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TravelOrderAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'travel_order_id',
        'path',
        'file_path',
        'label',
        'uploaded_by',
    ];

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getFilePathAttribute(): ?string
    {
        return $this->attributes['file_path'] ?? $this->attributes['path'] ?? null;
    }

    public function getPathAttribute(?string $value): ?string
    {
        return $value ?? ($this->attributes['file_path'] ?? null);
    }

    public function travelOrder()
    {
        return $this->belongsTo(TravelOrder::class);
    }
}
