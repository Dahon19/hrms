<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use App\Models\Employee;

class LeaveBalance extends Model
{
    protected static ?array $yearSettingCache = null;

    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'year',
        'earned',
        'consumed',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public static function calculateEarnedForYear(int $year, ?Carbon $asOf = null): float
    {
        $configured = static::configuredStartingBalanceForYear($year);
        if ($configured !== null) {
            return $configured;
        }

        $asOf = $asOf ?? Carbon::now();
        $currentYear = (int) $asOf->year;

        if ($year < $currentYear) {
            return 17;
        }

        if ($year > $currentYear) {
            return 5;
        }

        $earned = 5 + min($asOf->month, 12);
        return min($earned, 17);
    }

    public static function calculateEarnedForEmployeeYear(Employee $employee, int $year, ?Carbon $asOf = null): float
    {
        if (!static::isEmployeeEligibleForLeave($employee, $year, $asOf)) {
            return 0;
        }

        return static::calculateEarnedForYear($year, $asOf);
    }

    public function computedEarned(?Carbon $asOf = null): int
    {
        return self::calculateEarnedForYear((int) $this->year, $asOf);
    }

    public static function computedEarnedForYear(int $year, ?Carbon $asOf = null): int
    {
        return self::calculateEarnedForYear($year, $asOf);
    }

    public static function computedRemaining(int $year, float $consumed, ?Carbon $asOf = null): float
    {
        $earned = self::calculateEarnedForYear($year, $asOf);
        return max($earned - $consumed, 0);
    }

    public static function computedRemainingForEmployee(Employee $employee, int $year, float $consumed, ?Carbon $asOf = null): float
    {
        $earned = self::calculateEarnedForEmployeeYear($employee, $year, $asOf);
        return max($earned - $consumed, 0);
    }

    public static function configuredStartingBalanceForYear(int $year): ?float
    {
        if (!Schema::hasTable('leave_balance_year_settings')) {
            return null;
        }

        static::loadYearSettings();

        return static::$yearSettingCache['starting_balance'][$year] ?? null;
    }

    public static function configuredEligibilityMonthsForYear(int $year): ?int
    {
        if (!Schema::hasTable('leave_balance_year_settings')) {
            return null;
        }

        static::loadYearSettings();

        $value = static::$yearSettingCache['eligibility_months'][$year] ?? null;
        if ($value === null) {
            return null;
        }

        return (int) $value;
    }

    public static function eligibilityMonthsForYear(int $year): int
    {
        $configured = static::configuredEligibilityMonthsForYear($year);
        if ($configured === null) {
            return 3;
        }

        return max(0, $configured);
    }

    public static function isEmployeeEligibleForLeave(Employee $employee, int $year, ?Carbon $asOf = null): bool
    {
        $hireDate = $employee->hire_date;
        if (!$hireDate) {
            return true;
        }

        $months = static::eligibilityMonthsForYear($year);
        if ($months <= 0) {
            return true;
        }

        $asOf = $asOf ?? Carbon::now();
        $asOfYear = (int) $asOf->year;
        if ($year < $asOfYear) {
            $asOf = Carbon::create($year, 12, 31);
        } elseif ($year > $asOfYear) {
            $asOf = Carbon::create($year, 1, 1);
        }

        $eligibleDate = Carbon::parse($hireDate)->startOfDay()->addMonths($months);
        return $asOf->startOfDay()->gte($eligibleDate);
    }

    private static function loadYearSettings(): void
    {
        if (static::$yearSettingCache !== null) {
            return;
        }

        $starting = [];
        $eligibility = [];

        LeaveBalanceYearSetting::query()
            ->get(['year', 'starting_balance', 'eligibility_months'])
            ->each(function ($setting) use (&$starting, &$eligibility) {
                $year = (int) $setting->year;
                if ($setting->starting_balance !== null) {
                    $starting[$year] = (float) $setting->starting_balance;
                }
                if ($setting->eligibility_months !== null) {
                    $eligibility[$year] = (int) $setting->eligibility_months;
                }
            });

        static::$yearSettingCache = [
            'starting_balance' => $starting,
            'eligibility_months' => $eligibility,
        ];
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }
}
