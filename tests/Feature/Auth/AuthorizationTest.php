<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Drawer\Utils;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public static function activeRoles(): array
    {
        return [
            'customer' => [UserRole::Customer, 403, false],
            'staff' => [UserRole::Staff, 403, true],
            'admin' => [UserRole::Admin, 200, true],
        ];
    }

    #[DataProvider('activeRoles')]
    public function test_active_roles_can_only_use_their_permissions(UserRole $role, int $adminStatus, bool $canOperateOrders): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)->get(route('admin.dashboard'))->assertStatus($adminStatus);
        $this->assertSame($canOperateOrders, Gate::allows('operate-orders'));
    }

    public function test_locked_user_cannot_log_in_even_with_valid_credentials(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
            'remember' => true,
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertNull(Auth::guard()->getProvider()->retrieveByToken($user->id, $user->remember_token));
    }

    public function test_locked_admin_loses_access_on_the_next_request(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasNoErrors();
        $this->get(route('admin.dashboard'))->assertOk();

        $user->is_active = false;
        $user->save();
        Auth::guard()->forgetUser();

        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertFalse(Gate::forUser($user)->allows('operate-orders'));
        $this->assertFalse(Gate::forUser($user)->allows('access-admin'));
    }

    public function test_locked_user_cannot_submit_an_existing_livewire_profile_form(): void
    {
        $user = User::factory()->create();
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasNoErrors();
        $page = $this->get(route('profile.edit'))->assertOk();
        $snapshot = Utils::extractAttributeDataFromHtml($page->getContent(), 'wire:snapshot');
        $this->assertSame('settings/profile', $snapshot['memo']['path']);

        $user->is_active = false;
        $user->save();
        Auth::guard()->forgetUser();
        Livewire::flushState();

        $this->postJson(Livewire::getUpdateUri(), [
            'components' => [[
                'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'updates' => ['name' => 'Updated after locking'],
                'calls' => [['path' => '', 'method' => 'updateProfileInformation', 'params' => []]],
            ]],
        ], ['X-Livewire' => 'true'])->assertUnauthorized();

        $this->assertSame($user->name, $user->fresh()->name);
    }
}
