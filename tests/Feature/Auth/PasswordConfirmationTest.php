<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_password_screen_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('password.confirm'));

        $response->assertOk();
    }

    public function test_incorrect_password_shows_a_vietnamese_confirmation_error(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('security.edit'))->assertRedirect(route('password.confirm'));

        $this->from(route('password.confirm'))
            ->post(route('password.confirm.store'), ['password' => 'wrong-password'])
            ->assertRedirect(route('password.confirm'))
            ->assertSessionHasErrors(['password' => 'Mật khẩu không chính xác.'])
            ->assertSessionMissing('auth.password_confirmed_at');

        $this->withCookie(config('session.cookie'), session()->getId())
            ->get(route('password.confirm'))
            ->assertOk()
            ->assertSee('Mật khẩu không chính xác.')
            ->assertDontSee('The provided password was incorrect.');
    }
}
