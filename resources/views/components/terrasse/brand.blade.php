@props(['href', 'caption' => null])

<a href="{{ $href }}" {{ $attributes->class(['terrasse-brand']) }}>
    <span class="terrasse-brand-mark" aria-hidden="true">C</span>
    <span class="min-w-0 wrap-anywhere">
        {{ config('app.name') }}
        @if ($caption)
            <span class="terrasse-sidebar-caption mt-1 block">{{ $caption }}</span>
        @endif
    </span>
</a>
