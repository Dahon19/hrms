<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeePositionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'position_id' => Position::factory(),
        ];
    }
}
