<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Position extends Model
{
    protected $fillable = [
        'department_id',
        'position',
        'employee_limit',
    ];

    protected $casts = [
        'employee_limit' => 'integer',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function employeePositions()
    {
        return $this->hasMany(EmployeePosition::class);
    }

    public function jobPostings()
    {
        return $this->hasMany(JobPosting::class);
    }

    public function capacityLimit(): ?int
    {
        if ($this->employee_limit !== null) {
            return max((int) $this->employee_limit, 1);
        }

        return static::defaultCapacityFor((string) $this->position);
    }

    public static function defaultCapacityFor(string $name): ?int
    {
        $normalized = strtolower(trim($name));
        if ($normalized === 'dean') {
            $normalized = 'head';
        }
        if (in_array($normalized, ['head', 'secretary', 'coordinator'], true)) {
            return 1;
        }
        if (in_array($normalized, ['staff', 'staffs'], true)) {
            return 2;
        }
        if ($normalized === 'instructor') {
            return 15;
        }
        return null;
    }

    public static function capacityFor(string $name): ?int
    {
        return static::defaultCapacityFor($name);
    }
}
