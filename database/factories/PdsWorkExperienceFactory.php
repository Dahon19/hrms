<?php

namespace Database\Factories;

use App\Models\PdsProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class PdsWorkExperienceFactory extends Factory
{
    public function definition(): array
    {
        $fromDate = fake()->dateTimeBetween('-20 years', '-1 year');
        $toDate = fake()->optional()->dateTimeBetween($fromDate, 'now');

        return [
            'pds_profile_id' => PdsProfile::factory(),
            'position_title' => fake()->jobTitle(),
            'department_agency' => fake()->company(),
            'monthly_salary' => fake()->optional()->randomFloat(2, 15000, 100000),
            'salary_grade' => fake()->optional()->randomElement(['1', '5', '10', '15', '20', '25']),
            'status_of_appointment' => fake()->randomElement(['Permanent', 'Temporary', 'Contractual', 'Casual']),
            'government_service' => fake()->boolean(),
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ];
    }
}
