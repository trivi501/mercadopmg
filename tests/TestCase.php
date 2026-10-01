<?php

namespace Tests;

use App\Enums\ConceptType;
use App\Enums\Role;
use App\Models\Concept;
use App\Models\User;
use Database\Seeders\TestingSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * Roles, permisos y catálogos base se cargan en todas las pruebas que usan RefreshDatabase.
     */
    protected bool $seed = true;

    protected string $seeder = TestingSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    protected function userWithRole(Role $role, array $attributes = []): User
    {
        return User::factory()->role($role)->create($attributes);
    }

    protected function ticketConcept(): Concept
    {
        return Concept::query()->ofType(ConceptType::Ticket)->firstOrFail();
    }

    protected function annualityConcept(): Concept
    {
        return Concept::query()->ofType(ConceptType::Annuality)->firstOrFail();
    }
}
