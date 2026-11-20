<?php

use App\Concerns\ProfileValidationRules;
use Flux\Flux;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Cài đặt hồ sơ')] class extends Component {
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

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

            </div>

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full" data-test="update-profile-button">
                        {{ __('Save') }}
                    </flux:button>
                </div>

            </div>
        </form>
    </x-pages::settings.layout>
</section>
