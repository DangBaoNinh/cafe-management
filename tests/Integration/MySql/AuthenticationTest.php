<?php

namespace Tests\Integration\MySql;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class AuthenticationTest extends MySqlTestCase
{
    public function test_password_reset_and_database_session_on_mysql(): void
    {
        DB::setDefaultConnection($this->primary->getName());
        config(['session.driver' => 'database']);
        Notification::fake();

        $this->primary->beginTransaction();

        try {
            $email = 'Legacy-'.Str::uuid().'@example.test';
            $user = User::factory()->create(['email' => $email]);

            $this->post(route('password.email'), ['email' => strtoupper($email)])
                ->assertSessionHas('status', __('passwords.sent'));

            $token = Notification::sent($user, ResetPassword::class)->sole()->token;
            $this->assertDatabaseHas('password_reset_tokens', ['email' => $email]);

            $this->post(route('password.update'), [
                'token' => $token,
                'email' => strtolower($email),
                'password' => 'updated-password',
                'password_confirmation' => 'updated-password',
            ])->assertRedirect(route('login'))->assertSessionHasNoErrors();

            $this->assertTrue(Hash::check('updated-password', $user->fresh()->password));
            $this->assertDatabaseMissing('password_reset_tokens', ['email' => $email]);

            $this->post(route('password.update'), [
                'token' => $token,
                'email' => strtoupper($email),
                'password' => 'invalid-replay',
                'password_confirmation' => 'invalid-replay',
            ])->assertSessionHasErrors(['email' => __('passwords.token')]);

            $this->post(route('login.store'), ['email' => strtoupper($email), 'password' => 'updated-password'])
                ->assertRedirect(route('dashboard'));

            $sessionId = session()->getId();
            $this->assertDatabaseHas('sessions', ['id' => $sessionId, 'user_id' => $user->id]);

            Auth::forgetGuards();
            $this->withCookie(config('session.cookie'), $sessionId)->get(route('dashboard'))->assertOk();
            $this->post(route('logout'))->assertRedirect(route('home'));
            $this->assertDatabaseMissing('sessions', ['id' => $sessionId]);
        } finally {
            $this->primary->rollBack();
        }
    }
}
