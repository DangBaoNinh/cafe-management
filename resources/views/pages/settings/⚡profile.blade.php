<?php

use App\Concerns\ProfileValidationRules;
use Flux\Flux;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::settings')] #[Title('Cài đặt hồ sơ')] class extends Component {
    use ProfileValidationRules;

    public string $name = '';
    public string $email = '';
    public string $phone = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
        $this->phone = Auth::user()->phone ?? '';
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $this->name = trim($this->name);
        $this->email = trim($this->email);
        $this->phone = trim($this->phone);

        if (config('fortify.lowercase_usernames')) {
            $this->email = Str::lower($this->email);
        }

        $sessionUser = Auth::user();
        abort_unless($sessionUser?->is_active, 403);
        $validated = $this->validate($this->profileRules($sessionUser->id));

        try {
            $sessionUser->getConnection()->transaction(function () use ($sessionUser, $validated): void {
                $user = $sessionUser->newQuery()->lockForUpdate()->findOrFail($sessionUser->id);
                abort_unless($user->is_active, 403);

                if ($user->email !== $validated['email']) {
                    Password::broker()->deleteToken($user);
                    $user->email_verified_at = null;
                }

                $user->fill($validated)->save();
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'email' => __('validation.unique', ['attribute' => __('validation.attributes.email')]),
            ]);
        }

        $sessionUser->refresh();

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }

}; ?>

<div class="min-w-0">
    <x-pages::settings.layout :heading="__('Profile')" :subheading="__('Cập nhật thông tin dùng khi đặt hàng và liên hệ')">
        <form wire:submit="updateProfileInformation" class="max-w-lg space-y-5">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autocomplete="name" />
            <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />
            <flux:input wire:model="phone" label="Số điện thoại" type="tel" autocomplete="tel" maxlength="13" />

            <flux:button variant="primary" type="submit" data-test="update-profile-button">
                Lưu hồ sơ
            </flux:button>
        </form>
        @can('access-customer-orders')
        <form method="POST" action="{{ route('profile.bank.update') }}" class="mt-8 max-w-lg space-y-4 rounded-xl border p-5">
            @csrf
            <h2 class="text-xl font-bold">Liên kết tài khoản ngân hàng</h2>
            <p>Liên kết demo dùng thanh toán online và nhận hoàn tiền. Không nhập mật khẩu ngân hàng hoặc OTP.</p>
            @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
            <flux:input name="bank_name" label="Ngân hàng" :value="old('bank_name', auth()->user()->linked_bank['bank_name'] ?? '')" required maxlength="100" />
            <flux:input name="account_number" label="Số tài khoản" :value="old('account_number', auth()->user()->linked_bank['account_number'] ?? '')" required inputmode="numeric" maxlength="30" />
            <flux:input name="account_holder" label="Tên chủ tài khoản" :value="old('account_holder', auth()->user()->linked_bank['account_holder'] ?? '')" required maxlength="120" />
            <label><input type="checkbox" name="confirm" value="1" required> Tôi xác nhận đây là tài khoản của mình.</label>
            <flux:button type="submit" variant="primary">Liên kết / cập nhật tài khoản demo</flux:button>
        </form>
        @endcan
    </x-pages::settings.layout>
</div>
