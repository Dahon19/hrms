<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuditLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'action' => fake()->randomElement(['create', 'update', 'delete', 'view', 'export', 'login', 'logout']),
            'entity_type' => fake()->randomElement(['Employee', 'Department', 'LeaveRequest', 'Attendance', 'User']),
            'entity_id' => fake()->numberBetween(1, 1000),
            'old_values' => null,
            'new_values' => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'created_at' => fake()->dateTimeBetween('-6 months', 'now'),
        ];
    }
}
