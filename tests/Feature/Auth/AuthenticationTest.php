<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get(route('login'))->assertOk();
    }

    public function test_storefront_has_one_mobile_account_action_per_auth_state(): void
    {
        $guest = $this->get(route('home'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($guest, 'class="french-mobile-register"'));
        $this->assertStringNotContainsString('class="french-mobile-logout"', $guest);

        $customer = $this->actingAs(User::factory()->create())->get(route('home'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($customer, 'class="french-mobile-logout"'));
        $this->assertStringNotContainsString('class="french-mobile-register"', $customer);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => ' '.strtoupper($user->email).' ',
            'password' => 'password',
        ])->assertSessionHasNoErrors()->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors(['email' => __('auth.failed')]);

        $this->assertGuest();
    }

    public function test_admin_login_defaults_to_the_admin_dashboard(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirectToRoute('admin.dashboard');
        $this->assertAuthenticatedAs($admin);
        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_admin_login_returns_to_the_requested_page(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $orders = route('admin.orders.index', ['status' => 'ready']);
        $this->get($orders)->assertRedirectToRoute('login');

        $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect($orders);
    }

    public function test_admin_json_login_preserves_the_fortify_response(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->postJson(route('login.store'), ['email' => $admin->email, 'password' => 'password'])
            ->assertOk()->assertExactJson(['two_factor' => false]);
        $this->assertAuthenticatedAs($admin);
    }

    public function test_staff_login_defaults_to_order_operations(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);

        $this->post(route('login.store'), ['email' => $staff->email, 'password' => 'password'])
            ->assertRedirectToRoute('operations.orders.index');
        $this->assertAuthenticatedAs($staff);
        $this->get(route('operations.orders.index'))->assertOk();
    }

    public function test_staff_login_returns_to_the_requested_operations_page(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $this->get(route('operations.preparation.index'))->assertRedirectToRoute('login');

        $this->post(route('login.store'), ['email' => $staff->email, 'password' => 'password'])
            ->assertRedirectToRoute('operations.preparation.index');
    }

    public function test_staff_json_login_preserves_the_fortify_response(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);

        $this->postJson(route('login.store'), ['email' => $staff->email, 'password' => 'password'])
            ->assertOk()->assertExactJson(['two_factor' => false]);
        $this->assertAuthenticatedAs($staff);
    }

    public function test_login_rate_limit_uses_the_same_bucket_for_email_case_variants(): void
    {
        $user = User::factory()->create();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password'])
                ->assertSessionHasErrors(['email' => __('auth.failed')]);
        }

        $credentials = ['email' => strtoupper($user->email), 'password' => 'password'];

        $this->post(route('login.store'), $credentials)
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email')
            ->assertSessionMissing('_old_input.password');
        $this->withCookie(config('session.cookie'), session()->getId())
            ->get(route('login'))
            ->assertSee('Bạn đã thử đăng nhập quá nhiều lần.');
        $this->postJson(route('login.store'), $credentials)
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');
        $this->assertGuest();

        $this->travel(61)->seconds();

        $this->post(route('login.store'), $credentials)->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
        $this->get(route('dashboard'))->assertOk();
        $previousId = session()->getId();
        $previousToken = session()->token();

        $this->post(route('logout'))->assertRedirect(route('home'));

        $this->assertNotSame($previousId, session()->getId());
        $this->assertNotSame($previousToken, session()->token());
        $this->assertGuest();
        Auth::forgetGuards();
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->post(route('logout'))->assertRedirect(route('login'));
    }
}
