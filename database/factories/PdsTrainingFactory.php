<?php

namespace Database\Factories;

use App\Models\PdsProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class PdsTrainingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'pds_profile_id' => PdsProfile::factory(),
            'title' => fake()->randomElement([
                'Leadership Training',
                'Project Management',
                'Data Privacy',
                'Cybersecurity Awareness',
                'Customer Service Excellence',
            ]),
            'conducted_by' => fake()->company(),
            'from_date' => fake()->dateTimeBetween('-10 years', '-1 year'),
            'to_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'hours' => fake()->numberBetween(8, 40),
            'type' => fake()->randomElement(['Managerial', 'Supervisory', 'Technical', 'Social', 'Other']),
        ];
    }
}
