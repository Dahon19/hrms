<?php

namespace Database\Factories;

use App\Models\DocumentCategory;
use Illuminate\Database\Eloquent\Factories\Factory;


class DocumentSubcategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'document_category_id' => DocumentCategory::factory(),
            'name' => fake()->randomElement([
                'Primary ID',
                'Secondary ID',
                'Degree',
                'Certificate',
                'License',
            ]),
            'description' => fake()->sentence(),
        ];
    }
}
