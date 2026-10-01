<?php

namespace Database\Factories;

use App\Models\Tianguis;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tianguis>
 */
class TianguisFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Tianguis '.fake()->unique()->lastName(),
            'description' => fake()->optional()->sentence(),
            'location' => fake()->streetAddress(),
            'neighborhood' => 'Col. '.fake()->lastName(),
            'municipality' => 'Municipio',
            'operating_day' => fake()->randomElement(Tianguis::DAYS),
            'schedule' => '07:00 - 16:00',
            'is_active' => true,
        ];
    }
}
