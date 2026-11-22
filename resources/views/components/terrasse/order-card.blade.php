@props(['order', 'href', 'amount'])

<article class="terrasse-panel terrasse-work-card flex h-full min-w-0 flex-col gap-3 rounded-md p-4 sm:p-5">
    <header class="flex flex-wrap items-start justify-between gap-2">
        <div class="min-w-0">
            <h2 class="terrasse-display text-3xl font-semibold wrap-anywhere">
                <a href="{{ $href }}" class="terrasse-store-link">{{ $order->order_number }}</a>
            </h2>
            <p class="terrasse-muted mt-1 text-xs"><x-customer.order-time :date="$order->created_at" /></p>
        </div>
        <x-terrasse.status :status="$order->status" />
    </header>

    {{ $slot }}

    <footer class="mt-auto flex flex-wrap items-center justify-between gap-2 border-t border-terrasse-ink/15 pt-3">
        <strong class="text-sm tabular-nums"><x-customer.vnd :$amount /></strong>
        @isset($actions){{ $actions }}@endisset
        <flux:button :href="$href" size="sm" icon:trailing="arrow-up-right" :aria-label="'Chi tiết đơn '.$order->order_number">Chi tiết</flux:button>
    </footer>
</article>
