<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class HolidayFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement([
                'New Year\'s Day',
                'Independence Day',
                'Christmas Day',
                'Labor Day',
                'Good Friday',
                'Eid al-Fitr',
                'National Heroes Day',
            ]),
            'date' => fake()->dateTimeBetween('now', '+1 year'),
            'type' => fake()->randomElement(['Regular', 'Special Non-Working', 'Special Working']),
        ];
    }
}
