<x-layouts::customer :title="$title ?? null" settings>
    <div class="space-y-6 sm:space-y-8">
        <header class="terrasse-account-header terrasse-settings-header">
            <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 text-sm font-medium">
                <flux:icon.arrow-left class="size-4" aria-hidden="true" />
                Tài khoản của bạn
            </a>
            <h1 class="mt-3 font-semibold">Cài đặt tài khoản</h1>
        </header>

        <div class="grid items-start gap-5 md:grid-cols-[14rem_minmax(0,1fr)] lg:gap-7">
            <nav aria-label="Cài đặt tài khoản" class="terrasse-panel terrasse-settings-nav grid grid-cols-2 gap-1 rounded-lg p-2 md:grid-cols-1">
                @foreach ([
                    ['profile.edit', 'user-circle', 'Hồ sơ', true],
                    ['addresses.index', 'map-pin', 'Địa chỉ giao hàng', auth()->user()->can('manage-delivery-addresses')],
                    ['security.edit', 'lock-closed', 'Bảo mật', true],
                    ['appearance.edit', 'swatch', 'Giao diện', true],
                ] as [$route, $icon, $label, $allowed])
                    @if ($allowed)
                        <a href="{{ route($route) }}" wire:navigate
                            @if (request()->routeIs($route)) aria-current="page" @endif
                            class="flex min-w-0 items-center gap-2.5 rounded-md px-2 py-3 text-sm md:px-3">
                            <flux:icon :icon="$icon" class="size-5 shrink-0" aria-hidden="true" />
                            <span>{{ $label }}</span>
                        </a>
                    @endif
                @endforeach
            </nav>

            {{ $slot }}
        </div>
    </div>
</x-layouts::customer>
