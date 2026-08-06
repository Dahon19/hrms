<?php

namespace Database\Factories;

use App\Models\JobPosting;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApplicantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'job_posting_id' => JobPosting::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'gender' => fake()->randomElement(['male', 'female']),
            'birthday' => fake()->date(),
            'address' => fake()->optional()->address(),
            'status' => fake()->randomElement(['Pending', 'Shortlisted', 'Interviewed', 'Rejected', 'Hired']),
            'documents' => null,
            'password' => null,
            'password_notice_seen_at' => null,
        ];
    }
}
