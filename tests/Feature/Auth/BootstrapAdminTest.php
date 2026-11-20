<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BootstrapAdminTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Local-test-123!';

    public function test_console_creates_first_admin_and_refuses_a_second(): void
    {
        $this->artisan('admin:bootstrap', ['name' => 'First Admin', 'email' => 'ADMIN@example.test'])
            ->expectsQuestion('Mật khẩu quản trị viên', self::PASSWORD)
            ->expectsQuestion('Nhập lại mật khẩu', self::PASSWORD)
            ->assertSuccessful();

        $admin = User::where('email', 'admin@example.test')->sole();
        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertTrue($admin->is_active);
        $this->assertTrue(Hash::check(self::PASSWORD, $admin->password));

        $admin->is_active = false;
        $admin->save();

        $this->artisan('admin:bootstrap', ['name' => 'Second Admin', 'email' => 'other@example.test'])
            ->assertFailed();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_console_refuses_to_promote_an_existing_customer(): void
    {
        $user = User::factory()->create();

        $this->artisan('admin:bootstrap', ['name' => 'First Admin', 'email' => $user->email])
            ->assertFailed();
        $this->assertSame(UserRole::Customer, $user->fresh()->role);
        $this->assertSame($user->password, $user->fresh()->password);
    }

    public function test_console_refuses_unconfirmed_password_and_non_interactive_input(): void
    {
        $arguments = ['name' => 'First Admin', 'email' => 'admin@example.test'];

        $this->artisan('admin:bootstrap', $arguments)
            ->expectsQuestion('Mật khẩu quản trị viên', self::PASSWORD)
            ->expectsQuestion('Nhập lại mật khẩu', 'Different-123!')
            ->assertFailed();
        $this->artisan('admin:bootstrap', [...$arguments, '--no-interaction' => true])
            ->assertFailed();
        $this->assertDatabaseEmpty('users');
    }

    public function test_console_refuses_to_create_an_admin_while_another_command_holds_the_lock(): void
    {
        $lock = Cache::store('database')->getStore()->lock('admin:bootstrap', 300);
        $this->assertTrue($lock->get());

        try {
            $this->artisan('admin:bootstrap', ['name' => 'First Admin', 'email' => 'admin@example.test'])
                ->expectsQuestion('Mật khẩu quản trị viên', self::PASSWORD)
                ->expectsQuestion('Nhập lại mật khẩu', self::PASSWORD)
                ->assertFailed();
            $this->assertDatabaseEmpty('users');
        } finally {
            $lock->release();
        }
    }
}
