<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;


class DepartmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'department' => fake()->company() . ' Department',
            'department_type' => fake()->randomElement(['Academic', 'Administrative', 'Support']),
            'logo_path' => null,
        ];
    }
}
