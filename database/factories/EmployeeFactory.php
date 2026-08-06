<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'employee_id' => fake()->unique()->numerify('##-#####'),
            'rfid' => fake()->optional()->unique()->numerify('########'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'address' => fake()->optional()->address(),
            'department_id' => Department::factory(),
            'hire_date' => fake()->optional()->date(),
            'status' => fake()->randomElement(['active', 'inactive', 'on_leave']),
        ];
    }
}
