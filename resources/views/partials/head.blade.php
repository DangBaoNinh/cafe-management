<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="{{ csrf_token() }}" />
<meta name="theme-color" content="#28463b" />
<meta name="french-theme-base" content="{{ asset('french-theme/') }}/" />
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500;1,600&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

<title>
    {{ filled($title ?? null) ? $title.' - Toàn Café & Jardin' : 'Toàn Café & Jardin' }}
</title>

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
