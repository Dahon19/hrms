<?php

namespace Database\Factories;

use App\Models\TravelOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class TravelOrderTransportationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'travel_order_id' => TravelOrder::factory(),
            'type' => fake()->randomElement(['Air', 'Land', 'Sea', 'Company Vehicle']),
            'details' => fake()->sentence(),
        ];
    }
}
