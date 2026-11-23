<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Livewire\Drawer\Utils;
use Livewire\Livewire;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_settings_page_requires_fortify_password_confirmation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('security.edit'))
            ->assertRedirect(route('password.confirm'));

        $this->post(route('password.confirm.store'), ['password' => 'password'])
            ->assertRedirect(route('security.edit'));

        $this->get(route('security.edit'))->assertOk()->assertSee(__('Update password'));
    }

    public function test_security_settings_page_renders_without_two_factor_when_feature_is_disabled(): void
    {
        config(['fortify.features' => []]);
        $user = User::factory()->create();
        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertOk()
            ->assertSee(__('Update password'))
            ->assertDontSee('Manage your passkeys for passwordless sign-in')
            ->assertDontSee('Add a passkey to sign in without a password')
            ->assertDontSee('Two-factor authentication');
    }

    public function test_password_can_be_updated_and_used_to_log_in(): void
    {
        $user = User::factory()->create();
        $rememberToken = $user->remember_token;
        $this->actingAs($user);

        $page = $this->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))->assertOk();

        $this->submitPasswordUpdate($page->getContent())->assertOk();

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
        $this->assertNotSame($rememberToken, $user->fresh()->remember_token);

        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => __('auth.failed')]);
        $this->assertGuest();
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'new-password'])
            ->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_password_update_requires_a_recent_confirmation_on_the_livewire_request(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $page = $this->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))->assertOk();
        $this->withSession(['auth.password_confirmed_at' => time() - config('auth.password_timeout') - 1]);

        $this->submitPasswordUpdate($page->getContent())->assertStatus(423)
            ->assertJsonPath('message', __('Password confirmation required.'));

        $this->assertSame($user->password, $user->fresh()->password);
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

        Livewire::test('pages::settings.security')
            ->set('current_password', 'wrong-password')
            ->set('password', 'new-password')
            ->set('password_confirmation', 'new-password')
            ->call('updatePassword')
            ->assertHasErrors(['current_password' => 'current_password'])
            ->assertSet('current_password', '')
            ->assertSet('password', '')
            ->assertSet('password_confirmation', '');

        $this->assertSame($user->password, $user->fresh()->password);
    }

    public function test_a_stale_current_password_cannot_overwrite_a_newer_password(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

        $newerHash = Hash::make('newer-password');
        User::whereKey($user->id)->update(['password' => $newerHash]);

        Livewire::test('pages::settings.security')
            ->set('current_password', 'password')
            ->set('password', 'other-password')
            ->set('password_confirmation', 'other-password')
            ->call('updatePassword')
            ->assertHasErrors(['current_password']);

        $this->assertSame($newerHash, $user->fresh()->password);
    }

    private function submitPasswordUpdate(string $html): TestResponse
    {
        $snapshot = Utils::extractAttributeDataFromHtml($html, 'wire:snapshot');
        $this->assertSame('settings/security', $snapshot['memo']['path']);
        Livewire::flushState();

        return $this->postJson(Livewire::getUpdateUri(), [
            'components' => [[
                'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'updates' => [
                    'current_password' => 'password',
                    'password' => 'new-password',
                    'password_confirmation' => 'new-password',
                ],
                'calls' => [['path' => '', 'method' => 'updatePassword', 'params' => []]],
            ]],
        ], ['X-Livewire' => 'true']);
    }
}
