<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Applicant extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_posting_id',
        'full_name',
        'email',
        'gender',
        'birthday',
        'phone',
        'address',
        'message',
        'application_letter_path',
        'resume_path',
        'transcript_path',
        'status',
        'account_status',
    ];

    public function jobPosting()
    {
        return $this->belongsTo(JobPosting::class);
    }
}
