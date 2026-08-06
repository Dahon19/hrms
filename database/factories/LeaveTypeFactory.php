<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class LeaveTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement([
                'Vacation Leave',
                'Sick Leave',
                'Emergency Leave',
                'Maternity Leave',
                'Paternity Leave',
                'Bereavement Leave',
                'Study Leave',
            ]),
            'description' => fake()->sentence(),
            'max_days' => fake()->optional()->numberBetween(1, 30),
            'gender' => fake()->optional()->randomElement(['male', 'female']),
            'requires_attachment' => fake()->boolean(30),
            'color_code' => fake()->hexColor(),
        ];
    }
}
