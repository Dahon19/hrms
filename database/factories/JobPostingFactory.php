<?php

namespace Database\Factories;

use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

class JobPostingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'position_id' => Position::factory(),
            'title' => fake()->jobTitle(),
            'description' => fake()->paragraphs(3, true),
            'required_headcount' => fake()->numberBetween(1, 5),
            'status' => fake()->randomElement(['Open', 'Closed', 'On Hold']),
        ];
    }
}
