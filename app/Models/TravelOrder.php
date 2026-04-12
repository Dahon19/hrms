<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class TravelOrder extends Model
{
    use HasFactory;

    protected static ?bool $tablesAvailableCache = null;
    protected static ?array $columnListingCache = null;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_DEPARTMENT_APPROVED = 'department_approved';
    public const STATUS_HR_REVIEW = 'hr_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'employee_id',
        'department_id',
        'position_id',
        'destination',
        'purpose',
        'date_from',
        'date_to',
        'departure_time',
        'return_time',
        'transport_mode',
        'budget_proposal',
        'remarks',
        'status',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'created_by',
        'updated_by',
        'submitted_by',
        'department_approved_by',
        'department_approved_at',
        'hr_reviewed_by',
        'hr_reviewed_at',
        'final_approved_by',
        'final_approved_at',
        'cancelled_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'date_from' => 'date',
            'date_to' => 'date',
            'budget_proposal' => 'decimal:2',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'department_approved_at' => 'datetime',
            'hr_reviewed_at' => 'datetime',
            'final_approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function attachments()
    {
        return $this->hasMany(TravelOrderAttachment::class);
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function departmentApprovedBy()
    {
        return $this->belongsTo(User::class, 'department_approved_by');
    }

    public function hrReviewedBy()
    {
        return $this->belongsTo(User::class, 'hr_reviewed_by');
    }

    public function finalApprovedBy()
    {
        return $this->belongsTo(User::class, 'final_approved_by');
    }

    public static function tablesAvailable(): bool
    {
        if (static::$tablesAvailableCache !== null) {
            return static::$tablesAvailableCache;
        }

        static::$tablesAvailableCache = Schema::hasTable('travel_orders')
            && Schema::hasTable('travel_order_attachments');

        return static::$tablesAvailableCache;
    }

    public static function hasColumn(string $column): bool
    {
        if (!static::tablesAvailable()) {
            return false;
        }

        if (static::$columnListingCache === null) {
            static::$columnListingCache = Schema::getColumnListing((new static())->getTable());
        }

        return in_array($column, static::$columnListingCache, true);
    }

    public function scopeApprovedForAttendance($query)
    {
        return $query->whereIn('status', [self::STATUS_APPROVED, self::STATUS_COMPLETED]);
    }

    public function statusLabel(): string
    {
        if ($this->status === self::STATUS_REJECTED) {
            if ($this->hr_reviewed_at) {
                return 'Final rejected';
            }

            if ($this->department_approved_at) {
                return 'HR rejected';
            }

            return 'Department rejected';
        }

        if ($this->status === self::STATUS_HR_REVIEW) {
            return 'For Final Approval';
        }

        return str_replace('_', ' ', ucfirst($this->status));
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'badge-secondary',
            self::STATUS_SUBMITTED => 'badge-info',
            self::STATUS_DEPARTMENT_APPROVED => 'badge-primary',
            self::STATUS_HR_REVIEW => 'badge-warning',
            self::STATUS_APPROVED => 'badge-success',
            self::STATUS_REJECTED => 'badge-danger',
            self::STATUS_COMPLETED => 'badge-dark',
            self::STATUS_CANCELLED => 'badge-secondary',
            default => 'badge-secondary',
        };
    }
}
