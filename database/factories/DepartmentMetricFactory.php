<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;


class DepartmentMetricFactory extends Factory
{
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'metric_type' => fake()->randomElement(['attendance', 'performance', 'retention', 'productivity']),
            'metric_value' => fake()->randomFloat(2, 0, 100),
            'recorded_at' => fake()->dateTimeBetween('-1 year', 'now'),
        ];
    }
}
