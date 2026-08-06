<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceAnomalyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'date' => fake()->dateTimeBetween('-3 months', 'now'),
            'type' => fake()->randomElement(['Late', 'Undertime', 'No Clock-out', 'No Clock-in', 'Invalid Sequence']),
            'description' => fake()->sentence(),
            'resolved_at' => fake()->optional(0.3)->dateTime(),
        ];
    }
}
