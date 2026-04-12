<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RecruitmentApproval extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const ACTION_JOB_POSTING_CREATE = 'job_posting.create';
    public const ACTION_JOB_POSTING_UPDATE = 'job_posting.update';
    public const ACTION_JOB_POSTING_DELETE = 'job_posting.delete';
    public const ACTION_APPLICANT_COMPLETE = 'applicant.complete';
    public const ACTION_APPLICANT_ACTIVATE = 'applicant.activate';
    public const ACTION_APPLICANT_ARCHIVE = 'applicant.archive';

    protected $fillable = [
        'action_type',
        'status',
        'subject_type',
        'subject_id',
        'requested_by_user_id',
        'reviewed_by_user_id',
        'summary',
        'payload',
        'review_notes',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'subject_type', 'subject_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function actionLabel(): string
    {
        return match ($this->action_type) {
            self::ACTION_JOB_POSTING_CREATE => 'Create Job Posting',
            self::ACTION_JOB_POSTING_UPDATE => 'Update Job Posting',
            self::ACTION_JOB_POSTING_DELETE => 'Delete Job Posting',
            self::ACTION_APPLICANT_COMPLETE => 'Complete Applicant',
            self::ACTION_APPLICANT_ACTIVATE => 'Activate Applicant',
            self::ACTION_APPLICANT_ARCHIVE => 'Archive Applicant',
            default => 'Recruitment Action',
        };
    }
}
