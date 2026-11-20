<?php

use App\Concerns\PasswordValidationRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Cài đặt bảo mật')] class extends Component {
    use PasswordValidationRules;

    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(): void
    {
        try {
            $user = Auth::user();
            // Livewire does not stop the action on the middleware's JSON 423 response.
            abort_unless($user?->is_active, 403);
            $confirmedAt = session('auth.password_confirmed_at', 0);
            abort_if(
                Date::now()->unix() - $confirmedAt > config('auth.password_timeout'),
                423,
                __('Password confirmation required.'),
            );

            $validated = $this->validate([
                'current_password' => $this->currentPasswordRules(),
                'password' => $this->passwordRules(),
            ]);

            // The password checked by validation must still be current when the write happens.
            $updated = $user->newQuery()
                ->whereKey($user->id)
                ->where('is_active', true)
                ->where('password', $user->getAuthPassword())
                ->update([
                    'password' => Hash::make($validated['password']),
                    'remember_token' => Str::random(60),
                ]);

            if ($updated !== 1) {
                throw ValidationException::withMessages([
                    'current_password' => __('validation.current_password'),
                ]);
            }

            $user->refresh();
        } finally {
            $this->reset('current_password', 'password', 'password_confirmation');
        }

        Flux::toast(variant: 'success', text: __('Password updated.'));
    }

}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Security settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Update password')" :subheading="__('Ensure your account is using a long, random password to stay secure')">
        <form method="POST" wire:submit="updatePassword" class="mt-6 space-y-6">
            <flux:input
                wire:model="current_password"
                :label="__('Current password')"
                type="password"
                required
                autocomplete="current-password"
                viewable
            />
            <flux:input
                wire:model="password"
                :label="__('New password')"
                type="password"
                required
                autocomplete="new-password"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />
            <flux:input
                wire:model="password_confirmation"
                :label="__('Confirm password')"
                type="password"
                required
                autocomplete="new-password"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit" data-test="update-password-button">
                    {{ __('Save') }}
                </flux:button>
            </div>
        </form>

    </x-pages::settings.layout>

</section>
