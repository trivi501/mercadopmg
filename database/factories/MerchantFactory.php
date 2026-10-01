<?php

namespace Database\Factories;

use App\Enums\CommerceType;
use App\Enums\MerchantStatus;
use App\Models\BusinessType;
use App\Models\Merchant;
use App\Models\Tianguis;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Crea comerciantes directamente (sin MerchantService). Para probar la
 * generación de folio y mediciones use el servicio.
 *
 * @extends Factory<Merchant>
 */
class MerchantFactory extends Factory
{
    private static int $sequence = 900000;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'folio' => 'COM-'.str_pad((string) ++self::$sequence, 6, '0', STR_PAD_LEFT),
            'taxpayer_name' => fake()->name(),
            'trade_name' => fake()->optional()->company(),
            'business_type_id' => BusinessType::factory(),
            'business_description' => fake()->optional()->sentence(3),
            'phone' => fake()->numerify('##########'),
            'address' => fake()->streetAddress(),
            'neighborhood' => 'Col. '.fake()->lastName(),
            'municipality' => 'Municipio',
            'state' => 'Estado',
            'location' => fake()->streetAddress(),
            'latitude' => fake()->latitude(19.3, 19.5),
            'longitude' => fake()->longitude(-99.2, -99.0),
            'tianguis_id' => Tianguis::factory(),
            'meters' => fake()->randomElement([2, 3, 4, 5, 6, 8, 10]),
            'commerce_type' => CommerceType::Tianguis,
            'status' => MerchantStatus::Active,
            'registered_at' => fake()->dateTimeBetween('-3 years', 'now'),
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => MerchantStatus::Inactive]);
    }
}
