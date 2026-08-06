<?php

namespace Database\Factories;

use App\Models\DocumentCategory;
use App\Models\DocumentSubcategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement([
                'Passport',
                'Driver\'s License',
                'Birth Certificate',
                'NBI Clearance',
                'Police Clearance',
                'Medical Certificate',
                'Transcript of Records',
                'Diploma',
            ]),
            'description' => fake()->sentence(),
            'category_id' => DocumentCategory::factory(),
            'subcategory_id' => DocumentSubcategory::factory(),
            'gender' => fake()->optional()->randomElement(['male', 'female']),
        ];
    }
}
