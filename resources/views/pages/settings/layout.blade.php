@props(['heading', 'subheading'])

<section aria-labelledby="settings-panel-heading" class="terrasse-panel terrasse-settings-panel min-w-0 rounded-lg">
    <header class="terrasse-settings-panel-header">
        <h2 id="settings-panel-heading" class="terrasse-display text-3xl font-semibold sm:text-4xl">{{ $heading }}</h2>
        <p class="terrasse-muted mt-2 text-sm leading-relaxed">{{ $subheading }}</p>
    </header>

    <div class="mt-6">
        {{ $slot }}
    </div>
</section>
