@php
    $payment = $order->payment;
    $address = $order->address_snapshot;
@endphp

<x-layouts::customer :title="'Đơn '.$order->order_number">
    <section class="mx-auto max-w-3xl space-y-6">
        <header class="space-y-2">
            <flux:link :href="route('orders.index')">Về Đơn của tôi</flux:link>
            <h1 class="text-3xl font-semibold tracking-tight wrap-anywhere sm:text-4xl">Đơn {{ $order->order_number }}</h1>
            <p class="text-sm">Đặt lúc <x-customer.order-time :date="$order->created_at" /></p>
        </header>

        <dl class="grid gap-4 rounded-xl border border-coffee/15 bg-white p-4 sm:grid-cols-3">
            <div>
                <dt class="text-sm">Trạng thái đơn</dt>
                <dd class="font-semibold">{{ $order->status->label() }}</dd>
            </div>
            <div>
                <dt class="text-sm">Cách nhận</dt>
                <dd class="font-semibold">{{ $order->fulfillment_type->label() }}</dd>
            </div>
            <div>
                <dt class="text-sm">Thanh toán</dt>
                <dd class="font-semibold">{{ $payment?->status->label() ?? 'Chưa có thông tin' }}</dd>
                @if ($payment !== null)
                    <dd class="text-sm">{{ $payment->method->label() }}</dd>
                @endif
            </div>
        </dl>

        @if ($payment?->method === \App\Enums\PaymentMethod::BankTransfer
            && $payment->status === \App\Enums\PaymentStatus::Pending && $order->total_vnd > 0)
            <flux:link :href="route('checkout.confirmation', $order->order_number)">Xem hướng dẫn chuyển khoản</flux:link>
        @endif

        <div class="space-y-2 rounded-xl border border-coffee/15 bg-white p-4 wrap-anywhere">
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
            <ul class="divide-y divide-coffee/15 rounded-xl border border-coffee/15 bg-white">
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

        @if ($order->statusHistories->isNotEmpty())
            <div class="space-y-3">
                <h2 class="text-lg font-semibold">Tiến trình đơn</h2>
                <ol class="divide-y divide-coffee/15 rounded-xl border border-coffee/15 bg-white">
                    @foreach ($order->statusHistories as $history)
                        <li class="flex flex-wrap items-baseline justify-between gap-2 p-4">
                            <span class="font-medium">{{ \App\Enums\OrderStatus::from($history->to_status)->label() }}</span>
                            <span class="text-sm"><x-customer.order-time :date="$history->created_at" /></span>
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif
    </section>
</x-layouts::customer>
