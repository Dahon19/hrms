<?php

namespace Database\Factories;

use App\Models\PdsProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class PdsFamilyBackgroundFactory extends Factory
{
    public function definition(): array
    {
        return [
            'pds_profile_id' => PdsProfile::factory(),
            'spouse_first_name' => fake()->optional()->firstName(),
            'spouse_last_name' => fake()->optional()->lastName(),
            'spouse_middle_name' => fake()->optional()->firstName(),
            'spouse_occupation' => fake()->optional()->jobTitle(),
            'spouse_employer' => fake()->optional()->company(),
            'spouse_business_address' => fake()->optional()->address(),
            'father_first_name' => fake()->firstNameMale(),
            'father_last_name' => fake()->lastName(),
            'father_middle_name' => fake()->optional()->firstNameMale(),
            'mother_first_name' => fake()->firstNameFemale(),
            'mother_last_name' => fake()->lastName(),
            'mother_middle_name' => fake()->optional()->firstNameFemale(),
        ];
    }
}
