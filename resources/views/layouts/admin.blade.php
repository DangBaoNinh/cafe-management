<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-cream font-sans text-coffee antialiased">
        <header class="bg-coffee text-white">
            <nav aria-label="Điều hướng quản trị" class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-5 py-4 sm:px-8">
                <a href="{{ route('admin.dashboard') }}" class="font-semibold">{{ config('app.name') }} · Quản trị</a>
                <a href="{{ route('home') }}" class="text-sm hover:underline">Về trang chủ</a>
            </nav>
        </header>

        <main class="mx-auto max-w-6xl px-5 py-8 sm:px-8">
            {{ $slot }}
        </main>
    </body>
</html>
