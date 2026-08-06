<?php

namespace Database\Factories;

use App\Models\TravelOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class TravelOrderAttachmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'travel_order_id' => TravelOrder::factory(),
            'file_path' => 'travel-orders/' . fake()->uuid() . '.pdf',
            'description' => fake()->optional()->sentence(),
        ];
    }
}
