<?php

namespace Database\Factories;

use App\Models\PdsProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class PdsPersonalInfoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'pds_profile_id' => PdsProfile::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'middle_name' => fake()->optional()->firstName(),
            'name_extension' => fake()->optional()->suffix(),
            'date_of_birth' => fake()->date(),
            'place_of_birth' => fake()->city(),
            'sex' => fake()->randomElement(['Male', 'Female']),
            'civil_status' => fake()->randomElement(['Single', 'Married', 'Widowed', 'Separated', 'Annulled']),
            'height' => fake()->optional()->randomFloat(2, 150, 200),
            'weight' => fake()->optional()->randomFloat(2, 45, 120),
            'blood_type' => fake()->optional()->randomElement(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']),
            'gsis_id' => fake()->optional()->numerify('###########'),
            'pagibig_id' => fake()->optional()->numerify('############'),
            'philhealth_id' => fake()->optional()->numerify('#############'),
            'sss_id' => fake()->optional()->numerify('#########'),
            'tin' => fake()->optional()->numerify('###########'),
            'agency_employee_no' => fake()->optional()->numerify('########'),
            'citizenship' => fake()->randomElement(['Filipino', 'Dual Citizen']),
            'residential_address' => fake()->address(),
            'permanent_address' => fake()->optional()->address(),
            'telephone_no' => fake()->optional()->phoneNumber(),
            'mobile_no' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
        ];
    }
}
