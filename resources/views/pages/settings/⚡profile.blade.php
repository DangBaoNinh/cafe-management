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

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $this->name = trim($this->name);
        $this->email = trim($this->email);

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
    <x-pages::settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
        <form wire:submit="updateProfileInformation" class="max-w-lg space-y-5">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autocomplete="name" />
            <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

            <flux:button variant="primary" type="submit" data-test="update-profile-button">
                Lưu hồ sơ
            </flux:button>
        </form>
    </x-pages::settings.layout>
</div>
