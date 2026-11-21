<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_account_shortcuts_match_the_users_permissions(): void
    {
        $shopping = ['addresses.index', 'orders.index'];
        $operations = ['operations.orders.index', 'operations.preparation.index', 'operations.handoff.index', 'operations.payments.index'];

        foreach (UserRole::cases() as $role) {
            $user = User::factory()->create(['role' => $role]);
            $page = $this->actingAs($user)->get(route('dashboard'))->assertOk()
                ->assertSeeText($user->name)->assertSeeText($user->email)->assertSeeText($role->label())
                ->assertSee(route('profile.edit'));

            $routes = [
                ...array_fill_keys($shopping, $role === UserRole::Customer),
                ...array_fill_keys($operations, $role !== UserRole::Customer),
                'admin.dashboard' => $role === UserRole::Admin,
            ];

            foreach ($routes as $name => $visible) {
                if ($visible) {
                    $page->assertSee(route($name));
                } else {
                    $page->assertDontSee(route($name));
                }
            }
        }
    }

    public function test_guests_are_redirected_from_the_admin_dashboard(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_customers_cannot_access_the_admin_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }
}
