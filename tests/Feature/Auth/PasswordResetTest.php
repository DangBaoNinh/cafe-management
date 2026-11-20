<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Features;
use RuntimeException;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::resetPasswords());
    }

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $this->get(route('password.request'))->assertOk();
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => ' '.strtoupper($user->email).' '])
            ->assertSessionHas('status', __('passwords.sent'));

        $messages = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $message = $messages[0]->getOriginalMessage();
        $this->assertSame($user->email, $message->getTo()[0]->getAddress());
        $this->assertSame('Đặt lại mật khẩu Coffee Shop', $message->getSubject());
        $this->assertStringContainsString('Liên kết đặt lại mật khẩu sẽ hết hạn sau 60 phút.', $message->getTextBody());
        $this->assertStringContainsString('/reset-password/', $message->getTextBody());
    }

    public function test_password_can_be_reset_once_with_valid_token(): void
    {
        $user = User::factory()->create();
        $token = $this->requestResetToken($user);

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))->assertOk();

        $credentials = [
            'token' => $token,
            'email' => strtoupper($user->email),
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ];

        $this->post(route('password.update'), $credentials)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login', absolute: false));

        $this->assertGuest();
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
        $this->assertNotSame($user->remember_token, $user->fresh()->remember_token);

        $this->post(route('password.update'), [
            ...$credentials,
            'password' => 'second-password',
            'password_confirmation' => 'second-password',
        ])->assertSessionHasErrors(['email' => __('passwords.token')]);
        $this->assertGuest();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => __('auth.failed')]);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'new-password'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_an_expired_reset_token_does_not_change_the_password(): void
    {
        $user = User::factory()->create();
        $token = $this->requestResetToken($user);

        $this->travel(config('auth.passwords.users.expire') + 1)->minutes();

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertSessionHasErrors(['email' => __('passwords.token')]);

        $this->assertGuest();
        $this->assertSame($user->password, $user->fresh()->password);
    }

    public function test_a_locked_user_cannot_reset_the_password(): void
    {
        $user = User::factory()->create();
        $token = $this->requestResetToken($user);
        $user->is_active = false;
        $user->save();

        Notification::fake();
        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHasErrors(['email' => __('passwords.user')]);
        Notification::assertNothingSent();

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertSessionHasErrors(['email' => __('passwords.user')]);

        $this->assertGuest();
        $this->assertSame($user->password, $user->fresh()->password);
    }

    public function test_a_failed_reset_rolls_back_the_password_and_keeps_the_token(): void
    {
        $user = User::factory()->create();
        $token = $this->requestResetToken($user);

        // Fortify dispatches this event after saving the password and remember token.
        app('events')->listen(PasswordReset::class, function (): never {
            throw new RuntimeException('Simulated reset failure');
        });

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertServerError();

        $this->assertSame($user->password, $user->fresh()->password);
        $this->assertSame($user->remember_token, $user->fresh()->remember_token);
        $this->assertTrue(Password::broker()->tokenExists($user, $token));
    }

    private function requestResetToken(User $user): string
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status', __('passwords.sent'));

        return Notification::sent($user, ResetPassword::class)->sole()->token;
    }
}
