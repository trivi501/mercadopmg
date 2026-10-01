<?php

namespace Database\Factories;

use App\Models\MarketRoute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MarketRoute>
 */
class MarketRouteFactory extends Factory
{
    protected $model = MarketRoute::class;

    public function definition(): array
    {
        return [
            'name' => 'Ruta '.fake()->unique()->numerify('##'),
            'description' => fake()->optional()->sentence(),
            'zone' => fake()->randomElement(['Centro', 'Norte', 'Sur', 'Oriente', 'Poniente']),
            'itinerary' => fake()->optional()->paragraph(),
            'is_active' => true,
        ];
    }
}
