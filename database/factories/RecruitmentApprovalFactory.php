<?php

namespace Database\Factories;

use App\Models\Applicant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RecruitmentApprovalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'applicant_id' => Applicant::factory(),
            'approver_id' => User::factory(),
            'status' => fake()->randomElement(['Pending', 'Approved', 'Rejected']),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
