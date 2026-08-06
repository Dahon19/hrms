<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeDocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'document_id' => Document::factory(),
            'file_path' => 'documents/' . fake()->uuid() . '.pdf',
            'status' => fake()->randomElement(['pending', 'approved', 'rejected', 'expired']),
            'expiry_date' => fake()->optional()->date(),
            'issued_at' => fake()->optional()->date(),
            'expiry_notified_at' => null,
        ];
    }
}
