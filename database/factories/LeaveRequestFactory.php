<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaveRequestFactory extends Factory
{
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-1 year', '+1 month');
        $endDate = fake()->dateTimeBetween($startDate, '+2 weeks');

        return [
            'employee_id' => Employee::factory(),
            'leave_type_id' => LeaveType::factory(),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => fake()->randomElement(['Pending', 'Approved', 'HR Approved', 'Declined', 'Needs Revision']),
            'reason' => fake()->optional()->sentence(),
            'attachment_path' => null,
            'head_reviewed_by' => null,
            'head_reviewed_at' => null,
            'hr_reviewed_by' => null,
            'hr_reviewed_at' => null,
            'president_reviewed_by' => null,
            'president_reviewed_at' => null,
            'notes' => null,
        ];
    }
}
