<?php

namespace Database\Factories;

use App\Models\PdsProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class PdsVoluntaryWorkFactory extends Factory
{
    public function definition(): array
    {
        return [
            'pds_profile_id' => PdsProfile::factory(),
            'organization' => fake()->company() . ' Foundation',
            'position' => fake()->randomElement(['Volunteer', 'Coordinator', 'Board Member', 'Advisor']),
            'from_date' => fake()->dateTimeBetween('-15 years', '-1 year'),
            'to_date' => fake()->optional()->dateTimeBetween('-1 year', 'now'),
            'hours' => fake()->numberBetween(10, 500),
        ];
    }
}
