<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    protected $fillable = [
        'name',
        'color_code',
        'requires_attachment',
        'max_days',
        'gender',
    ];

    protected $casts = [
        'requires_attachment' => 'boolean',
        'max_days'            => 'integer',
    ];

    /**
     * Return the maximum days allowed for this leave type in a given year.
     *
     * - If max_days is set (fixed statutory), return it directly.
     * - If max_days is null (accrual-based, e.g. VL/SL), return the
     *   computed accrual value from LeaveBalance.
     */
    public function maxDaysForYear(int $year, ?Carbon $asOf = null): int
    {
        if (!is_null($this->max_days)) {
            return (int) $this->max_days;
        }

        return LeaveBalance::calculateEarnedForYear($year, $asOf);
    }

    public function balances()
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function requests()
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
