<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class Employee extends Model
{
    protected static ?bool $offboardingTablesAvailableCache = null;

    protected $fillable = [
        'user_id',
        'employee_id',
        'rfid',
        'first_name',
        'last_name',
        'address',
        'department_id',
        'hire_date',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function positions()
    {
        return $this->hasMany(EmployeePosition::class);
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function employeeDocument()
    {
        return $this->belongsTo(EmployeeDocument::class);
    }

    public function nfc()
    {
        return $this->hasOne(EmployeeNfc::class);
    }

    public function pdsProfile()
    {
        return $this->hasOne(PdsProfile::class);
    }



    public function attendanceMonthlyScores()
    {
        return $this->hasMany(AttendanceMonthlyScore::class, 'employee_id');
    }

    public function offboardingRecords()
    {
        return $this->hasMany(OffboardingRecord::class);
    }

    public function travelOrders()
    {
        return $this->hasMany(TravelOrder::class);
    }

    public function activeOffboardingRecord()
    {
        return $this->hasOne(OffboardingRecord::class)
            ->whereIn('status', OffboardingRecord::activeStatuses())
            ->latestOfMany();
    }

    public static function offboardingTablesAvailable(): bool
    {
        if (static::$offboardingTablesAvailableCache !== null) {
            return static::$offboardingTablesAvailableCache;
        }

        static::$offboardingTablesAvailableCache = Schema::hasTable('offboarding_records')
            && Schema::hasTable('clearance_items');

        return static::$offboardingTablesAvailableCache;
    }

    public function activeOffboardingRecordSafe(): ?OffboardingRecord
    {
        if (!static::offboardingTablesAvailable()) {
            return null;
        }

        if ($this->relationLoaded('activeOffboardingRecord')) {
            return $this->getRelation('activeOffboardingRecord');
        }

        return $this->activeOffboardingRecord()->first();
    }

    public function hasActiveOffboardingRecord(): bool
    {
        if (!static::offboardingTablesAvailable()) {
            return false;
        }

        if ($this->relationLoaded('activeOffboardingRecord')) {
            return $this->getRelation('activeOffboardingRecord') !== null;
        }

        return $this->activeOffboardingRecord()->exists();
    }

    public function scopeNonAdmin($query)
    {
        return $query->whereHas('user', function ($userQuery) {
            $userQuery->where('role', '!=', 'admin');
        });
    }

    public static function nextEmployeeId(): string
    {
        $year = Carbon::now()->format('y');
        $prefix = $year . '-';
        $latest = self::where('employee_id', 'like', $prefix . '%')
            ->orderBy('employee_id', 'desc')
            ->value('employee_id');

        $nextNumber = 1;
        if ($latest) {
            $parts = explode('-', $latest);
            $sequence = isset($parts[1]) ? (int) $parts[1] : 0;
            $nextNumber = $sequence + 1;
        }

        return $prefix . str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);
    }
}



