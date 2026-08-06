<?php

namespace Database\Factories;

use App\Models\PdsFamilyBackground;
use Illuminate\Database\Eloquent\Factories\Factory;

class PdsChildFactory extends Factory
{
    public function definition(): array
    {
        return [
            'pds_family_background_id' => PdsFamilyBackground::factory(),
            'name' => fake()->name(),
            'date_of_birth' => fake()->dateTimeBetween('-30 years', '-1 year'),
        ];
    }
}
