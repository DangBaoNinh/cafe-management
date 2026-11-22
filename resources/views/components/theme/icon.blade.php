@props(['name' => 'grid'])
@php
    $paths = [
        'close' => 'm6 6 12 12M6 18 18 6',
        'bag' => 'M5 7h14l1 14H4L5 7Zm3 0V5a4 4 0 0 1 8 0v2',
        'user' => 'M20 21v-2a7 7 0 0 0-14 0v2M12 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8Z',
        'menu' => 'M4 6h16M4 12h16M4 18h16',
        'grid' => 'M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z',
        'table' => 'M4 8h16v9H4zM7 4h10M7 21h10',
        'orders' => 'M6 3h12v18l-3-2-3 2-3-2-3 2V3Zm3 5h6M9 12h6',
        'people' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z',
        'coffee' => 'M4 7h13v8a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5V7Zm13 1h2a3 3 0 0 1 0 6h-2M7 3v1m4-1v1m4-1v1',
        'calendar' => 'M4 5h16v16H4zM8 3v4m8-4v4M4 10h16',
        'settings' => 'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8Zm0-5 2 3 4-1 1 4 3 3-3 2-1 4-4-1-2 4-2-4-4 1-1-4-3-2 3-3 1-4 4 1 2-3Z',
        'clock' => 'M12 7v5l3 2M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z',
        'home' => 'm3 10 9-8 9 8M5 8v13h14V8m-10 13v-8h6v8',
        'cube' => 'm12 2 9 5v10l-9 5-9-5V7l9-5Zm0 10 9-5M12 12 3 7m9 5v10',
    ];
@endphp
<svg {{ $attributes->class(['icon']) }} viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $paths[$name] ?? $paths['grid'] }}" /></svg>
