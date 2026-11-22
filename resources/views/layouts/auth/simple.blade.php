<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="terrasse french-theme french-auth antialiased">
        <main class="terrasse-auth flex min-h-svh flex-col items-center justify-center p-6 md:p-10">
            <div class="french-auth-card flex w-full max-w-md flex-col gap-6">
                <x-terrasse.brand :href="route('home')" class="self-center" />
                <div class="flex flex-col gap-6">
                    {{ $slot }}
                </div>
            </div>
        </main>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
