<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Usuario administrador inicial. En producción defina ADMIN_PASSWORD;
 * si no existe se genera una contraseña aleatoria y se muestra una sola vez.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (User::query()->where('username', 'admin')->exists()) {
            return;
        }

        $configured = config('padron.admin.password');
        $password = is_string($configured) && $configured !== '' ? $configured : (app()->isProduction() ? Str::password(16) : 'password');

        $admin = User::query()->create([
            'name' => 'Administrador',
            'username' => 'admin',
            'email' => config('padron.admin.email'),
            'password' => $password,
            'is_active' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::Admin->value);

        if ($password !== $configured && $this->command !== null) {
            $this->command->warn("Usuario: admin  Contraseña: {$password}  (cámbiela al iniciar sesión)");
        }
    }
}
