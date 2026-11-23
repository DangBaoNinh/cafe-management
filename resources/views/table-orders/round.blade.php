@use('App\Enums\DrinkLevel')

<article id="luot-{{ $round->id }}" class="space-y-3 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
            <h3 class="font-semibold">Lượt {{ $round->id }}</h3>
            <p class="text-sm"><x-customer.order-time :date="$round->created_at" /></p>
        </div>
        <p class="font-medium">{{ $round->tableSession->billing_mode === 'prepaid' ? ($round->invoice?->order_status->label() ?? 'Chờ thanh toán') : $round->status->label() }}</p>
    </div>
    <ul class="space-y-2">
        @foreach ($round->items as $item)
            <li class="flex flex-wrap justify-between gap-2 text-sm">
                <div class="min-w-0 wrap-anywhere">
                    <p>{{ $item->quantity }} × {{ $item->product_name }} · {{ $item->size_label }}</p>
                    @if($item->notes)<p class="whitespace-pre-wrap wrap-anywhere text-sm">Lưu ý: {{ $item->notes }}</p>@endif
                    @if ($item->sugar_level !== null || $item->ice_level !== null || $item->toppings !== [])
                        <p>
                            @if ($item->sugar_level !== null) Đường {{ DrinkLevel::from($item->sugar_level)->label() }}. @endif
                            @if ($item->ice_level !== null) Đá {{ DrinkLevel::from($item->ice_level)->label() }}. @endif
                            @if ($item->toppings !== []) {{ implode(', ', array_column($item->toppings, 'name')) }} @endif
                        </p>
                    @endif
                </div>
                <span class="whitespace-nowrap">{{ number_format($item->line_total_vnd, 0, ',', '.') }} ₫</span>
            </li>
        @endforeach
    </ul>
    @if ($operational)
        @if($round->tableSession->billing_mode === 'prepaid' && $round->status === \App\Enums\TableRoundStatus::Pending)
            <p class="text-sm">Chờ thanh toán trước khi pha chế.</p>
            <flux:button :href="route('operations.table-sessions.pos', [$round->tableSession, 'round' => $round->id])">Nhận order / thu tiền</flux:button>
        @else
            @include('operations.table-sessions.round-actions')
        @endif
        @if($round->invoice)<flux:link :href="route('operations.invoices.prepaid.show', $round->invoice)">{{ $round->invoice->number }} · {{ $round->invoice->status === 'paid' ? 'Đã thanh toán' : 'Tạm tính' }}</flux:link>@endif
    @endif
</article>
