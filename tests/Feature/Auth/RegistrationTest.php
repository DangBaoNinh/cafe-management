<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'John Doe',
            'email' => ' Test@Example.COM ',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'admin',
            'is_active' => false,
        ]);

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'role' => UserRole::Customer->value,
            'is_active' => true,
        ]);
        $this->assertTrue(Hash::check('password', User::firstOrFail()->password));
    }

    public function test_registration_rejects_a_duplicate_email_with_different_case(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);

        $this->from(route('register'))->post(route('register.store'), [
            'name' => 'Another customer',
            'email' => 'TEST@EXAMPLE.COM',
            'password' => 'different-password',
            'password_confirmation' => 'different-password',
        ])->assertRedirect(route('register'))
            ->assertSessionHasErrors(['email' => __('validation.unique', ['attribute' => 'email'])]);

        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
        $this->assertEquals($user->toArray(), $user->fresh()->toArray());
    }

    public function test_registration_rejects_an_email_array_with_a_form_error(): void
    {
        $this->from(route('register'))->post(route('register.store'), [
            'email' => ['user@example.com'],
        ])->assertSessionHasErrors(['email' => __('validation.string', ['attribute' => 'email'])]);

        $this->assertDatabaseCount('users', 0);
    }
}
