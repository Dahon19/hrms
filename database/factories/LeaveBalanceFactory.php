<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaveBalanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'leave_type_id' => LeaveType::factory(),
            'year' => fake()->year(),
            'earned' => fake()->numberBetween(5, 30),
            'consumed' => fake()->numberBetween(0, 5),
        ];
    }
}
