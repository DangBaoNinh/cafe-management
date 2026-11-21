@props(['href', 'title', 'description'])

<li class="min-w-0">
    <a href="{{ $href }}" class="terrasse-account-link terrasse-panel flex h-full items-start gap-4 p-4 sm:p-5">
        <span class="terrasse-account-icon" aria-hidden="true">{{ $icon }}</span>
        <span class="min-w-0 flex-1">
            <span class="terrasse-display block text-2xl font-semibold leading-tight wrap-anywhere">{{ $title }}</span>
            <span class="terrasse-muted mt-1.5 block text-sm leading-relaxed wrap-anywhere">{{ $description }}</span>
        </span>
        <flux:icon.arrow-up-right class="mt-1 size-4 shrink-0 text-accent" aria-hidden="true" />
    </a>
</li>
