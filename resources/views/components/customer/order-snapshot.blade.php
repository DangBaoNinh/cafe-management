@props(['order'])

@php($address = $order->address_snapshot)

<div class="space-y-2 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4 wrap-anywhere">
    <h2 class="text-lg font-semibold">Thông tin nhận hàng</h2>
    <p>{{ $order->recipient_name }} · {{ $order->phone }}</p>
    @if ($address !== null)
        <p>{{ implode(', ', array_filter([
            $address['address_line'] ?? null,
            $address['ward_name'] ?? null,
            $address['district_name'] ?? null,
            $address['province_name'] ?? null,
        ])) }}</p>
    @endif
</div>

<div class="space-y-3">
    <h2 class="text-lg font-semibold">Món đã đặt</h2>
    <ul class="divide-y divide-terrasse-ink/15 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper">
        @foreach ($order->items as $item)
            <li class="space-y-2 p-4 wrap-anywhere">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h3 class="min-w-0 font-semibold">{{ $item->quantity }} × {{ $item->product_name }}</h3>
                    <strong class="tabular-nums"><x-customer.vnd :amount="$item->line_total_vnd" /></strong>
                </div>
                <p class="text-sm">{{ $item->size_label }} · <x-customer.vnd :amount="$item->base_unit_price_vnd" />/ly</p>
                @foreach (['Đường' => $item->sugar_level, 'Đá' => $item->ice_level] as $label => $level)
                    @if ($level !== null)
                        <p class="text-sm">{{ $label }} · {{ \App\Enums\DrinkLevel::from($level)->label() }}</p>
                    @endif
                @endforeach
                @if ($item->toppings->isNotEmpty())
                    <ul aria-label="Topping" class="space-y-1 text-sm">
                        @foreach ($item->toppings as $topping)
                            <li>{{ $topping->topping_name }} · +<x-customer.vnd :amount="$topping->unit_price_vnd" />/ly</li>
                        @endforeach
                    </ul>
                @endif
            </li>
        @endforeach
    </ul>

    @if ($order->coupon_code !== null)
        <p class="text-sm wrap-anywhere">Mã giảm giá · {{ $order->coupon_code }}</p>
    @endif
    <x-customer.order-totals :subtotal="$order->subtotal_vnd" :discount="$order->discount_vnd"
        :delivery-fee="$order->delivery_fee_vnd" :total="$order->total_vnd" />
</div>
