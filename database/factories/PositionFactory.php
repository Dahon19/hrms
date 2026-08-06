<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

class PositionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'position' => fake()->jobTitle(),
            'department_id' => Department::factory(),
            'employee_limit' => fake()->optional(0.7)->numberBetween(1, 10),
        ];
    }
}
