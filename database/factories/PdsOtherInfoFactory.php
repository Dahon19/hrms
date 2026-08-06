<?php

namespace Database\Factories;

use App\Models\PdsProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class PdsOtherInfoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'pds_profile_id' => PdsProfile::factory(),
            'special_skills' => fake()->optional()->sentence(),
            'non_academic_distinctions' => fake()->optional()->sentence(),
            'membership_in_association' => fake()->optional()->sentence(),
        ];
    }
}
