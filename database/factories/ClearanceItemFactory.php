<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\OffboardingRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClearanceItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'offboarding_record_id' => OffboardingRecord::factory(),
            'department_id' => Department::factory(),
            'item' => fake()->randomElement([
                'Return company laptop',
                'Return access card',
                'Clear desk',
                'Sign exit interview',
                'Return uniform',
                'Clear financial obligations',
            ]),
            'status' => fake()->randomElement(['Pending', 'Completed', 'Not Applicable']),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
