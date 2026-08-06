<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceSettingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->word(),
            'value' => fake()->word(),
            'require_four_taps' => false,
        ];
    }
}
