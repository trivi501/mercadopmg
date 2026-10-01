<?php

namespace Database\Seeders;

use App\Enums\CommerceType;
use App\Enums\ConceptType;
use App\Enums\Role;
use App\Models\BusinessType;
use App\Models\Concept;
use App\Models\MarketRoute;
use App\Models\Merchant;
use App\Models\Tianguis;
use App\Models\User;
use App\Services\MerchantService;
use App\Services\PaymentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Datos de demostración para desarrollo (no ejecutar en producción).
 * Usuarios: recaudador / inspector / capturista, contraseña "password".
 */
class DemoSeeder extends Seeder
{
    public function run(MerchantService $merchants, PaymentService $payments): void
    {
        foreach ([Role::Collector->value => ['recaudador', 'Recaudador Demo'], Role::Inspector->value => ['inspector', 'Inspector Demo'], Role::DataEntry->value => ['capturista', 'Capturista Demo']] as $role => [$username, $name]) {
            $user = User::query()->firstOrCreate(['username' => $username], [
                'name' => $name, 'email' => "{$username}@example.com", 'password' => 'password', 'is_active' => true,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->syncRoles([$role]);
        }

        if (Merchant::query()->exists()) {
            return;
        }

        $tianguis = collect([Tianguis::query()->where('name', 'Tianguis Municipal')->firstOrFail()])
            ->merge(Tianguis::factory()->count(3)->create());
        $routes = collect([MarketRoute::query()->where('name', 'Ruta 01')->firstOrFail()])
            ->merge(MarketRoute::factory()->count(2)->sequence(['name' => 'Ruta 02'], ['name' => 'Ruta 03'])->create());
        $types = BusinessType::all();
        $capturista = User::query()->where('username', 'capturista')->firstOrFail();

        $created = collect(range(1, 60))->map(function () use ($merchants, $tianguis, $routes, $types, $capturista) {
            $type = fake()->randomElement([CommerceType::Tianguis, CommerceType::Tianguis, CommerceType::Route, CommerceType::PublicRoad, CommerceType::Plaza]);

            return $merchants->create([
                'taxpayer_name' => fake('es_MX')->name(),
                'trade_name' => fake()->boolean(40) ? fake('es_MX')->company() : null,
                'business_type_id' => $types->random()->id,
                'phone' => fake()->numerify('55########'),
                'address' => fake('es_MX')->streetAddress(),
                'neighborhood' => 'Centro',
                'municipality' => 'Municipio',
                'location' => fake('es_MX')->streetName(),
                'latitude' => fake()->latitude(19.40, 19.45),
                'longitude' => fake()->longitude(-99.16, -99.11),
                'tianguis_id' => $type === CommerceType::Tianguis ? $tianguis->random()->id : null,
                'route_id' => $type === CommerceType::Route ? $routes->random()->id : (fake()->boolean(30) ? $routes->random()->id : null),
                'meters' => fake()->randomElement([2, 3, 4, 5, 6, 10]),
                'commerce_type' => $type->value,
                'registered_at' => fake()->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
            ], $capturista);
        });

        $ticket = Concept::query()->ofType(ConceptType::Ticket)->firstOrFail();
        $annuality = Concept::query()->ofType(ConceptType::Annuality)->firstOrFail();
        $collector = User::query()->where('username', 'recaudador')->firstOrFail();

        // Cobros de los últimos 60 días.
        foreach (range(60, 0) as $daysAgo) {
            $created->random(fake()->numberBetween(3, 10))->each(function (Merchant $merchant) use ($payments, $collector, $ticket, $annuality, $daysAgo) {
                $items = [['concept_id' => $ticket->id, 'quantity' => fake()->numberBetween(1, 6)]];
                if (! $merchant->annualities()->where('fiscal_year', now()->year)->where('status', 'paid')->exists() && fake()->boolean(25)) {
                    $items[] = ['concept_id' => $annuality->id, 'fiscal_year' => (int) now()->year];
                }

                $payments->charge([
                    'uuid' => (string) Str::uuid(),
                    'merchant_id' => $merchant->id,
                    'payment_method' => fake()->randomElement(['cash', 'cash', 'cash', 'transfer', 'card']),
                    'issued_at' => now()->subDays($daysAgo)->setTime(fake()->numberBetween(7, 15), fake()->numberBetween(0, 59))->toDateTimeString(),
                    'items' => $items,
                    'source' => fake()->boolean(70) ? 'android' : 'web',
                ], $collector);
            });
        }
    }
}
