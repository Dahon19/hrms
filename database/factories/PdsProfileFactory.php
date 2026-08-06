<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class PdsProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'status' => fake()->randomElement(['Draft', 'Submitted', 'Under Review', 'Verified', 'Rejected']),
            'submitted_at' => fake()->optional()->dateTime(),
            'verified_at' => fake()->optional()->dateTime(),
            'verified_by' => null,
            'rejection_reason' => null,
        ];
    }
}
