<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccountManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_and_staff_cannot_manage_accounts(): void
    {
        $target = User::factory()->create();
        $updateUrl = route('admin.accounts.update', $target);
        $editUrl = route('admin.accounts.edit', $target);

        $this->get(route('admin.accounts.index'))->assertRedirect(route('login'));
        $this->patch($updateUrl)->assertRedirect(route('login'));

        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $this->actingAs($staff)->get(route('admin.accounts.index'))->assertForbidden();
        $this->get($editUrl)->assertForbidden();
        $this->patch($updateUrl, $this->changes($target))->assertForbidden();

        $this->assertSame(UserRole::Staff, $staff->fresh()->role);
        $this->assertSame(UserRole::Customer, $target->fresh()->role);
    }

    public function test_admin_can_search_and_filter_accounts(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $match = User::factory()->create(['email' => 'cafe-match@example.test', 'role' => UserRole::Staff, 'is_active' => false]);
        $active = User::factory()->create(['email' => 'cafe-active@example.test', 'role' => UserRole::Staff]);
        $customer = User::factory()->create(['email' => 'cafe-customer@example.test', 'is_active' => false]);

        $this->actingAs($admin)->get(route('admin.accounts.index', [
            'search' => 'cafe-', 'role' => 'staff', 'status' => 'locked',
        ]))->assertOk()
            ->assertSee($match->email)
            ->assertDontSee($active->email)
            ->assertDontSee($customer->email)
            ->assertDontSee($match->password)
            ->assertDontSee($match->remember_token);
    }

    public function test_admin_must_confirm_password_to_change_access(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $target = User::factory()->create();
        $editUrl = route('admin.accounts.edit', $target);
        $updateUrl = route('admin.accounts.update', $target);

        $this->actingAs($admin)->get($editUrl)->assertRedirect(route('password.confirm'));
        $this->patch($updateUrl, $this->changes($target))->assertRedirect(route('password.confirm'));
        $this->assertSame(UserRole::Customer, $target->fresh()->role);

        $this->post(route('password.confirm.store'), ['password' => 'password'])->assertRedirect($editUrl);
        $this->get($editUrl)->assertOk()->assertSee('Lưu quyền truy cập');

        $this->patch($updateUrl, $this->changes($target, ['role' => 'admin', 'is_active' => '0']))
            ->assertRedirect($editUrl)->assertSessionHasNoErrors();
        $target->refresh();
        $this->assertSame(UserRole::Admin, $target->role);
        $this->assertFalse($target->is_active);

        $this->patch($updateUrl, $this->changes($target, ['role' => 'staff', 'is_active' => '1']))
            ->assertRedirect($editUrl)->assertSessionHasNoErrors();
        $this->assertSame(UserRole::Staff, $target->fresh()->role);
        $this->assertTrue($target->fresh()->is_active);
    }

    public function test_invalid_role_or_status_does_not_change_access(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $target = User::factory()->create();

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('admin.accounts.update', $target), $this->changes($target, [
                'role' => 'owner', 'is_active' => 'yes',
            ]))->assertSessionHasErrors(['role', 'is_active']);

        $this->assertSame(UserRole::Customer, $target->fresh()->role);
        $this->assertTrue($target->fresh()->is_active);
    }

    public static function forbiddenSelfChanges(): array
    {
        return [
            'demotion' => [['role' => 'staff']],
            'lock' => [['role' => 'admin', 'is_active' => '0']],
        ];
    }

    #[DataProvider('forbiddenSelfChanges')]
    public function test_admin_cannot_downgrade_or_lock_their_own_account(array $changes): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('admin.accounts.update', $admin), $this->changes($admin, $changes))
            ->assertSessionHasErrors('account');

        $this->assertSame(1, User::where('role', UserRole::Admin)->where('is_active', true)->count());
        $this->assertSame(UserRole::Admin, $admin->fresh()->role);
    }

    public function test_a_stale_form_cannot_overwrite_a_newer_change(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $target = User::factory()->create();
        $staleForm = $this->changes($target, ['role' => 'admin']);

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);
        $url = route('admin.accounts.update', $target);
        $this->patch($url, $this->changes($target, ['is_active' => '0']))->assertSessionHasNoErrors();
        $this->patch($url, $staleForm)
            ->assertSessionHasErrors(['account' => 'Tài khoản đã thay đổi. Hãy tải lại trang trước khi lưu.']);

        $this->assertSame(UserRole::Staff, $target->fresh()->role);
        $this->assertFalse($target->fresh()->is_active);
    }

    public function test_a_locked_customer_cannot_use_an_existing_session(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $customer = User::factory()->create();
        $rememberToken = $customer->remember_token;

        $this->post(route('login.store'), ['email' => $customer->email, 'password' => 'password'])
            ->assertRedirect(route('home'));
        $this->get(route('profile.edit'))->assertOk();
        $sessionId = session()->getId();

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('admin.accounts.update', $customer), $this->changes($customer, [
                'role' => 'customer', 'is_active' => '0',
            ]))->assertSessionHasNoErrors();

        $this->assertNotSame($rememberToken, $customer->fresh()->remember_token);
        Auth::forgetGuards();
        $this->withCookie(config('session.cookie'), $sessionId)
            ->get(route('profile.edit'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    private function changes(User $target, array $overrides = []): array
    {
        return array_replace([
            'role' => 'staff',
            'is_active' => '1',
            'previous_role' => $target->role->value,
            'previous_is_active' => (string) (int) $target->is_active,
        ], $overrides);
    }
}
