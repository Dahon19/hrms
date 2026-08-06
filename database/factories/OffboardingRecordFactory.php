<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class OffboardingRecordFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'status' => fake()->randomElement(['Pending', 'In Progress', 'Completed', 'Cancelled']),
            'separation_date' => fake()->optional()->date(),
            'reason' => fake()->optional()->sentence(),
            'cancellation_requested_at' => null,
            'cancellation_reason' => null,
        ];
    }
}
