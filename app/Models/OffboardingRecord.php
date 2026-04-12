<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OffboardingRecord extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_DEPARTMENT_REVIEW = 'department_review';
    public const STATUS_FINANCE_CLEARANCE = 'finance_clearance';
    public const STATUS_HR_FINALIZATION = 'hr_finalization';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_ARCHIVED = 'archived';

    public const CANCELLATION_STATUS_PENDING = 'pending';
    public const CANCELLATION_STATUS_APPROVED = 'approved';
    public const CANCELLATION_STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'employee_id',
        'initiated_by',
        'initiated_by_hr_id',
        'reopened_by',
        'finalized_by',
        'cancellation_requested_by',
        'cancellation_reviewed_by',
        'separation_date',
        'effective_last_working_day',
        'resignation_reason',
        'last_working_day',
        'resignation_letter_attachment',
        'reason',
        'remarks',
        'status',
        'submitted_at',
        'completed_at',
        'finalized_at',
        'reopened_at',
        'cancellation_requested_at',
        'cancellation_reason',
        'cancellation_request_status',
        'cancellation_reviewed_at',
        'cancellation_review_notes',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'separation_date' => 'date',
            'effective_last_working_day' => 'date',
            'last_working_day' => 'date',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
            'finalized_at' => 'datetime',
            'reopened_at' => 'datetime',
            'cancellation_requested_at' => 'datetime',
            'cancellation_reviewed_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function initiatedBy()
    {
        return $this->belongsTo(User::class, 'initiated_by_hr_id');
    }

    public function reopenedBy()
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    public function finalizedBy()
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function cancellationRequestedBy()
    {
        return $this->belongsTo(User::class, 'cancellation_requested_by');
    }

    public function cancellationReviewedBy()
    {
        return $this->belongsTo(User::class, 'cancellation_reviewed_by');
    }

    public function clearanceItems()
    {
        return $this->hasMany(ClearanceItem::class)->orderBy('display_order');
    }

    public function activeClearanceItems()
    {
        return $this->clearanceItems()->where('required', true);
    }

    public function isFinalized(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED, self::STATUS_ARCHIVED], true);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::activeStatuses(), true);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', self::activeStatuses());
    }

    public static function activeStatuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_SUBMITTED,
            self::STATUS_DEPARTMENT_REVIEW,
            self::STATUS_FINANCE_CLEARANCE,
            self::STATUS_HR_FINALIZATION,
        ];
    }

    public function hasPendingCancellationRequest(): bool
    {
        return $this->cancellation_request_status === self::CANCELLATION_STATUS_PENDING;
    }

    public function canEmployeeRequestCancellation(): bool
    {
        return in_array($this->status, [
            self::STATUS_DRAFT,
            self::STATUS_SUBMITTED,
            self::STATUS_DEPARTMENT_REVIEW,
            self::STATUS_FINANCE_CLEARANCE,
            self::STATUS_HR_FINALIZATION,
            self::STATUS_COMPLETED,
        ], true) && !$this->hasPendingCancellationRequest();
    }

    public function getDisplayReasonAttribute(): ?string
    {
        return $this->resignation_reason ?: $this->reason;
    }

    public function getDisplayLastWorkingDayAttribute()
    {
        return $this->last_working_day ?: $this->effective_last_working_day;
    }

    public function getStageLabelAttribute(): string
    {
        return str_replace('_', ' ', ucfirst($this->status));
    }
}

