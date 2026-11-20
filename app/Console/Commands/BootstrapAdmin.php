<?php

namespace App\Console\Commands;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BootstrapAdmin extends Command
{
    use PasswordValidationRules, ProfileValidationRules;

    protected $signature = 'admin:bootstrap {name : Tên quản trị viên} {email : Email mới}';

    protected $description = 'Tạo tài khoản quản trị viên đầu tiên từ terminal';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Hãy chạy lệnh trong terminal tương tác để nhập mật khẩu.');

            return self::FAILURE;
        }

        if (User::where('role', UserRole::Admin)->exists()) {
            $this->error('Hệ thống đã có tài khoản quản trị viên.');

            return self::FAILURE;
        }

        $profile = Validator::make([
            'name' => trim((string) $this->argument('name')),
            'email' => Str::lower(trim((string) $this->argument('email'))),
        ], $this->profileRules());

        if ($profile->fails()) {
            $this->error($profile->errors()->first());

            return self::FAILURE;
        }

        $password = Validator::make([
            'password' => $this->secret('Mật khẩu quản trị viên', false),
            'password_confirmation' => $this->secret('Nhập lại mật khẩu', false),
        ], [
            'password' => [...$this->passwordRules(), 'min:12'],
        ]);

        if ($password->fails()) {
            $this->error($password->errors()->first());

            return self::FAILURE;
        }

        $admin = new User([...$profile->validated(), ...$password->validated()]);
        $admin->role = UserRole::Admin;

        return $this->createFirstAdmin($admin);
    }

    private function createFirstAdmin(User $admin): int
    {
        $store = Cache::store('database')->getStore();

        if (! $store instanceof LockProvider) {
            $this->error('Kho cache cơ sở dữ liệu không hỗ trợ khóa khởi tạo.');

            return self::FAILURE;
        }

        $lock = $store->lock('admin:bootstrap', 300);

        if (! $lock->get()) {
            $this->error('Đang có lệnh khởi tạo quản trị viên khác chạy.');

            return self::FAILURE;
        }

        try {
            if (User::where('role', UserRole::Admin)->exists()) {
                $this->error('Hệ thống đã có tài khoản quản trị viên.');

                return self::FAILURE;
            }

            $admin->saveOrFail();
        } catch (UniqueConstraintViolationException) {
            $this->error('Email này đã được sử dụng.');

            return self::FAILURE;
        } finally {
            $lock->release();
        }

        $this->info('Đã tạo tài khoản quản trị viên đầu tiên.');

        return self::SUCCESS;
    }
}
