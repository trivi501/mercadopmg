<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $this->actingAs($this->userWithRole(Role::Inspector));

        $response = $this->get(route('dashboard'));
        $response->assertOk()->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('indicators.merchants_total')
            ->has('indicators.revenue_today'));
    }

    public function test_users_without_role_can_not_visit_the_dashboard()
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('dashboard'))->assertForbidden();
    }
}
