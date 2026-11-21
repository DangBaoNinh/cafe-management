<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::settings')] #[Title('Cài đặt giao diện')] class extends Component {
    //
}; ?>

<div class="min-w-0">
    <x-pages::settings.layout :heading="__('Appearance')" :subheading="__('Update the appearance settings for your account')">
        <fieldset x-data class="grid gap-3 sm:grid-cols-3">
            <legend class="sr-only">Chế độ hiển thị</legend>
            @foreach ([
                ['light', 'sun', __('Light')],
                ['dark', 'moon', __('Dark')],
                ['system', 'computer-desktop', __('System')],
            ] as [$mode, $icon, $label])
                <label class="terrasse-theme-option">
                    <span aria-hidden="true" class="terrasse-theme-preview" data-theme="{{ $mode }}">
                        <span></span>
                    </span>
                    <span class="flex items-center gap-2 px-3 py-3 text-sm font-medium">
                        <flux:icon :icon="$icon" class="size-4 shrink-0" aria-hidden="true" />
                        <span class="me-auto">{{ $label }}</span>
                        <input type="radio" name="appearance" value="{{ $mode }}" x-model="$flux.appearance">
                    </span>
                </label>
            @endforeach
        </fieldset>
    </x-pages::settings.layout>
</div>
