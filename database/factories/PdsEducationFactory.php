<?php

namespace Database\Factories;

use App\Models\PdsProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class PdsEducationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'pds_profile_id' => PdsProfile::factory(),
            'level' => fake()->randomElement(['Elementary', 'Secondary', 'Vocational', 'College', 'Graduate Studies']),
            'school_name' => fake()->company() . ' School',
            'degree_course' => fake()->optional()->randomElement(['BS Computer Science', 'BA Psychology', 'BBA', 'BS Engineering', 'MA Education']),
            'year_graduated' => fake()->optional()->year(),
            'highest_grade' => fake()->optional()->randomElement(['1st Year', '2nd Year', '3rd Year', '4th Year']),
            'inclusive_dates' => fake()->optional()->word(),
            'academic_honors' => fake()->optional()->randomElement(['Cum Laude', 'Magna Cum Laude', 'Summa Cum Laude', 'Dean\'s Lister']),
        ];
    }
}
