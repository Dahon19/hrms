<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class DepartmentTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Academic', 'Administrative', 'Support']),
            'description' => fake()->sentence(),
        ];
    }
}
