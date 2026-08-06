<?php

namespace Database\Factories;

use App\Models\PdsProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class PdsCivilServiceEligibilityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'pds_profile_id' => PdsProfile::factory(),
            'career_service' => fake()->randomElement([
                'Professional',
                'Sub-Professional',
                'Barangay Official Eligibility',
                'Honor Graduate Eligibility',
            ]),
            'rating' => fake()->optional()->randomFloat(2, 80, 99),
            'exam_date' => fake()->optional()->date(),
            'exam_place' => fake()->optional()->city(),
            'license_number' => fake()->optional()->numerify('########'),
            'license_validity' => fake()->optional()->date(),
        ];
    }
}
