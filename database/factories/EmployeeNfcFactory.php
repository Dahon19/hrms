<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeNfcFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'nfc_uid' => fake()->unique()->numerify('########'),
            'is_active' => true,
        ];
    }
}
