<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>@yield('title') - {{ config('app.name') }}</title>
        @fluxAppearance

        @if (app(\Illuminate\Foundation\Vite::class)->isRunningHot() || is_file(public_path('build/manifest.json')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif

        <style>
            .terrasse-error {
                display: grid;
                min-height: 100vh;
                margin: 0;
                place-items: center;
                padding: 1rem;
                box-sizing: border-box;
                background: var(--color-cream, #f6efe3);
                color: var(--terrasse-ink, #341a1b);
                font-family: var(--font-sans, system-ui);
            }
            .dark .terrasse-error {
                background: var(--color-cream, #26191b);
                color: var(--terrasse-ink, #fff0de);
            }
            .terrasse-error main { text-align: center; }
            .terrasse-error a { color: inherit; text-decoration: underline; }
        </style>
    </head>
    <body class="terrasse terrasse-error">
        <main>
            <h1>@yield('code')</h1>
            <p class="mb-4">@yield('message')</p>
            <a href="{{ url('/') }}">Về trang chủ</a>
        </main>
    </body>
</html>
