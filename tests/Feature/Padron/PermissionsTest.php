<?php

namespace Tests\Feature\Padron;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PermissionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{Role, array<string, int>}>
     */
    public static function matrix(): array
    {
        return [
            'administrador' => [Role::Admin, [
                'merchants.index' => 200, 'merchants.create' => 200, 'charges.create' => 200, 'receipts.index' => 200,
                'receipts.reprints' => 200, 'reports.index' => 200, 'audit.index' => 200, 'users.index' => 200,
                'catalogs.concepts.index' => 200, 'settings.general' => 200,
            ]],
            'recaudador' => [Role::Collector, [
                'merchants.index' => 200, 'merchants.create' => 403, 'charges.create' => 200, 'receipts.index' => 200,
                'receipts.reprints' => 403, 'reports.index' => 403, 'audit.index' => 403, 'users.index' => 403,
                'catalogs.concepts.index' => 403, 'settings.general' => 403,
            ]],
            'inspector' => [Role::Inspector, [
                'merchants.index' => 200, 'merchants.create' => 403, 'charges.create' => 403, 'receipts.index' => 200,
                'audit.index' => 403, 'users.index' => 403, 'catalogs.business-types.index' => 403,
            ]],
            'capturista' => [Role::DataEntry, [
                'merchants.index' => 200, 'merchants.create' => 200, 'charges.create' => 403, 'receipts.index' => 403,
                'audit.index' => 403, 'users.index' => 403,
            ]],
        ];
    }

    /**
     * @param  array<string, int>  $expectations
     */
    #[DataProvider('matrix')]
    public function test_role_access(Role $role, array $expectations): void
    {
        $user = $this->userWithRole($role);

        foreach ($expectations as $route => $status) {
            $this->actingAs($user)->get(route($route))->assertStatus($status);
        }
    }

    public function test_admin_can_create_users_with_roles(): void
    {
        $admin = $this->userWithRole(Role::Admin);

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Nuevo Recaudador',
            'username' => 'Recaudador2',
            'email' => 'r2@example.com',
            'role' => 'collector',
            'is_active' => true,
            'password' => 'secreto-seguro-123',
            'password_confirmation' => 'secreto-seguro-123',
        ])->assertSessionHasNoErrors();

        $user = User::query()->where('username', 'recaudador2')->firstOrFail();
        $this->assertTrue($user->hasRole('collector'));
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotSame('secreto-seguro-123', $user->password);
    }

    public function test_deactivating_a_user_revokes_tokens(): void
    {
        $admin = $this->userWithRole(Role::Admin);
        $user = $this->userWithRole(Role::Collector);
        $user->createToken('tel');

        $this->actingAs($admin)->put(route('users.update', $user), [
            'name' => $user->name, 'username' => $user->username, 'email' => $user->email,
            'role' => 'collector', 'is_active' => false,
        ])->assertSessionHasNoErrors();

        $this->assertFalse($user->refresh()->is_active);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_admin_can_not_deactivate_themselves(): void
    {
        $admin = $this->userWithRole(Role::Admin);

        $this->actingAs($admin)->put(route('users.update', $admin), [
            'name' => $admin->name, 'username' => $admin->username, 'email' => $admin->email,
            'role' => 'admin', 'is_active' => false,
        ])->assertSessionHasErrors('role');

        $this->assertTrue($admin->refresh()->is_active);
    }

    public function test_reports_can_be_exported(): void
    {
        $admin = $this->userWithRole(Role::Admin);

        foreach (['xlsx', 'csv', 'pdf'] as $format) {
            $this->actingAs($admin)->get(route('reports.export', ['type' => 'merchants', 'format' => $format]))->assertOk();
        }

        $this->assertDatabaseHas('audit_logs', ['action' => 'export', 'user_id' => $admin->id]);
    }
}
