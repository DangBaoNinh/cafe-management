<?php

namespace Tests\Integration\MySql;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Livewire\Livewire;

class CustomerProfileTest extends MySqlTestCase
{
    public function test_customer_can_update_the_profile_and_password_on_mysql(): void
    {
        DB::setDefaultConnection($this->primary->getName());
        $this->primary->beginTransaction();

        try {
            $id = Str::uuid();
            $user = User::factory()->create(['email' => "old-{$id}@example.test"]);
            $other = User::factory()->create(['email' => "Taken-{$id}@example.test"]);
            $originalEmail = $user->email;
            $rememberToken = $user->remember_token;
            $token = Password::broker()->createToken($user);
            $this->actingAs($user);

            Livewire::test('pages::settings.profile')
                ->set('email', ' '.strtoupper($other->email).' ')
                ->call('updateProfileInformation')
                ->assertHasErrors(['email' => 'unique']);
            $this->assertSame($originalEmail, $user->fresh()->email);
            $this->assertTrue(Password::broker()->tokenExists($user, $token));

            Livewire::test('pages::settings.profile')
                ->set('email', " New-{$id}@Example.Test ")
                ->call('updateProfileInformation')
                ->assertHasNoErrors();

            $this->assertSame("new-{$id}@example.test", $user->fresh()->email);
            $this->assertDatabaseMissing('password_reset_tokens', ['email' => $originalEmail]);

            $this->withSession(['auth.password_confirmed_at' => time()]);
            Livewire::test('pages::settings.security')
                ->set('current_password', 'password')
                ->set('password', 'new-password')
                ->set('password_confirmation', 'new-password')
                ->call('updatePassword')
                ->assertHasNoErrors();

            $updated = $user->fresh();
            $this->assertTrue(Hash::check('new-password', $updated->password));
            $this->assertNotSame($rememberToken, $updated->remember_token);
        } finally {
            $this->primary->rollBack();
        }
    }
}
