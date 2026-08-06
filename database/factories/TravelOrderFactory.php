<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class TravelOrderFactory extends Factory
{
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('now', '+3 months');
        $endDate = fake()->dateTimeBetween($startDate, '+2 weeks');

        return [
            'employee_id' => Employee::factory(),
            'destination' => fake()->city(),
            'purpose' => fake()->paragraph(),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => fake()->randomElement(['Pending', 'Approved', 'HR Approved', 'Declined', 'Needs Revision']),
            'budget_proposal' => fake()->optional()->randomFloat(2, 1000, 50000),
            'workflow_metadata' => null,
        ];
    }
}
