<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReportRunFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'report_type' => fake()->randomElement([
                'attendance_summary',
                'leave_balance',
                'employee_directory',
                'department_metrics',
                'recruitment_status',
            ]),
            'parameters' => fake()->optional()->json(),
            'file_path' => fake()->optional()->filePath(),
            'status' => fake()->randomElement(['pending', 'running', 'completed', 'failed']),
            'started_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'completed_at' => fake()->optional()->dateTimeBetween('now', '+1 hour'),
        ];
    }
}
