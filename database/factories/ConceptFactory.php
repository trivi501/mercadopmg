<?php

namespace Database\Factories;

use App\Enums\ConceptType;
use App\Enums\Periodicity;
use App\Models\Concept;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Concept>
 */
class ConceptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('OTR-###')),
            'name' => fake()->words(3, true),
            'type' => ConceptType::Other,
            'amount' => fake()->randomFloat(2, 10, 500),
            'periodicity' => Periodicity::PerEvent,
            'is_active' => true,
        ];
    }

    /** Anualidad de uso de suelo en vía pública: $155.00 */
    public function annuality(): static
    {
        return $this->state([
            'code' => 'USV-001',
            'name' => 'Plaza Uso de Suelo Vía Pública',
            'type' => ConceptType::Annuality,
            'amount' => '155.00',
            'periodicity' => Periodicity::Annual,
        ]);
    }

    /** Boleto de tianguista: $40.00 */
    public function ticket(): static
    {
        return $this->state([
            'code' => 'BOL-001',
            'name' => 'Boleto Tianguista',
            'type' => ConceptType::Ticket,
            'amount' => '40.00',
            'periodicity' => Periodicity::PerEvent,
        ]);
    }
}
