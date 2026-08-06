<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceFactory extends Factory
{
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-6 months', 'now');
        $morningIn = fake()->optional(0.9)->dateTimeBetween('07:00', '09:00');
        $morningOut = fake()->optional(0.8)->dateTimeBetween('11:30', '13:00');
        $afternoonIn = fake()->optional(0.8)->dateTimeBetween('13:00', '14:00');
        $afternoonOut = fake()->optional(0.9)->dateTimeBetween('16:30', '18:30');

        return [
            'employee_id' => Employee::factory(),
            'date' => $date,
            'morning_time_in' => $morningIn,
            'morning_time_out' => $morningOut,
            'afternoon_time_in' => $afternoonIn,
            'afternoon_time_out' => $afternoonOut,
            'status' => fake()->randomElement(['Present', 'Late', 'Absent', 'On Leave', 'Half Day']),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
