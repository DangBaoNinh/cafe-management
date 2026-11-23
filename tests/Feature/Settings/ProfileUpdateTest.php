<?php

namespace Tests\Feature\Settings;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Livewire\Exceptions\PublicPropertyNotFoundException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed_without_self_deletion(): void
    {
        $this->get(route('profile.edit'))->assertRedirect(route('login'));

        $user = User::factory()->create();

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee($user->email)
            ->assertDontSee(__('Delete account'));
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();
        $oldEmail = $user->email;
        Password::broker()->createToken($user);

        $this->actingAs($user);
        Livewire::test('pages::settings.profile')
            ->set('name', '  Test User  ')
            ->set('email', '  Test@Example.com  ')
            ->set('phone', ' 0901234567 ')
            ->call('updateProfileInformation')
            ->assertHasNoErrors()
            ->assertSet('name', 'Test User')
            ->assertSet('email', 'test@example.com')
            ->assertSet('phone', '0901234567');

        $this->assertSame('Test User', $user->refresh()->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertSame('0901234567', $user->phone);
        $this->assertNull($user->email_verified_at);
        $this->assertSame(UserRole::Customer, $user->role);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $oldEmail]);

        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->post(route('login.store'), [
            'email' => 'Test@Example.com',
            'password' => 'password',
        ])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_duplicate_email_keeps_the_original_profile_and_reset_token(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create();
        $original = $user->fresh()->getAttributes();
        $token = Password::broker()->createToken($user);

        $this->actingAs($user);
        Livewire::test('pages::settings.profile')
            ->set('name', 'Changed name')
            ->set('email', ' Taken@Example.com ')
            ->call('updateProfileInformation')
            ->assertHasErrors(['email' => 'unique']);

        $this->assertSame($original, $user->fresh()->getAttributes());
        $this->assertTrue(Password::broker()->tokenExists($user, $token));
    }

    public function test_email_verification_status_is_unchanged_when_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();
        $verifiedAt = $user->email_verified_at;

        $this->actingAs($user);
        Livewire::test('pages::settings.profile')
            ->set('name', 'Test User')
            ->set('email', ' '.strtoupper($user->email).' ')
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $this->assertEquals($verifiedAt, $user->refresh()->email_verified_at);
    }

    public static function extraProperties(): array
    {
        return ['user ID' => ['user_id'], 'role' => ['role']];
    }

    #[DataProvider('extraProperties')]
    public function test_profile_rejects_properties_outside_the_form(string $property): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user);

        $this->expectException(PublicPropertyNotFoundException::class);

        try {
            Livewire::test('pages::settings.profile')
                ->set([
                    'name' => 'Unexpected change',
                    $property => $property === 'user_id' ? $other->id : UserRole::Admin->value,
                ])
                ->call('updateProfileInformation');
        } finally {
            $this->assertSame($user->name, $user->fresh()->name);
            $this->assertSame($other->name, $other->fresh()->name);
            $this->assertSame(UserRole::Customer, $user->fresh()->role);
        }
    }

    public function test_the_old_delete_action_forbids_self_deletion(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test('pages::settings.delete-user-modal')
            ->call('deleteUser')
            ->assertStatus(403);

        $this->assertModelExists($user);
        $this->assertAuthenticatedAs($user);
    }
}
