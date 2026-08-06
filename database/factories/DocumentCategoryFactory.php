<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement([
                'Government ID',
                'Educational',
                'Professional',
                'Medical',
                'Legal',
            ]),
            'description' => fake()->sentence(),
        ];
    }
}
