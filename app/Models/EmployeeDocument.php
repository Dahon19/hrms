<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeDocument extends Model
{
    protected $fillable = [
        'employee_id',
        'file_path',
        'status',
        'document_id',
        'document_name',
        'document_type',
        'issued_at',
        'expires_at',
        'expiry_notified_at',
        'review_notes',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'expires_at' => 'date',
        'expiry_notified_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function documents(){
        return $this->belongsTo(Document::class, 'document_id');
    }
}

