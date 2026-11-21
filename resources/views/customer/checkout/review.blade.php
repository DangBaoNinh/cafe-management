@php($order = $quote['order'])

<x-layouts::customer title="Xác nhận đặt hàng">
    <section class="mx-auto max-w-2xl space-y-5">
        <flux:link :href="route('checkout.create')">Sửa thông tin đặt hàng</flux:link>
        <h1 class="text-3xl font-semibold tracking-tight">Xác nhận đặt hàng</h1>

        <div class="space-y-2 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4">
            <p class="font-semibold">{{ $details->fulfillment->label() }} · {{ $details->method->label() }}</p>
            <p class="wrap-anywhere">{{ $order['recipient_name'] }} · {{ $order['phone'] }}</p>
            @if ($address = $order['address_snapshot'])
                <p class="wrap-anywhere">{{ implode(', ', array_filter([
                    $address['address_line'], $address['ward_name'], $address['district_name'], $address['province_name'],
                ])) }}</p>
            @endif
            @if ($order['coupon_code'] !== null)
                <p class="wrap-anywhere">Mã giảm giá · {{ $order['coupon_code'] }}</p>
            @endif
        </div>

        <ul class="divide-y divide-terrasse-ink/15 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper">
            @foreach ($quote['items'] as ['line' => $line, 'toppings' => $toppings])
                <li class="space-y-1 p-4 wrap-anywhere">
                    <p class="font-semibold">{{ $line['quantity'] }} × {{ $line['product_name'] }}</p>
                    <p class="text-sm">{{ $line['size_label'] }}
                        @if ($toppings !== [])
                            · {{ implode(', ', array_column($toppings, 'topping_name')) }}
                        @endif
                    </p>
                </li>
            @endforeach
        </ul>

        <x-customer.order-totals :subtotal="$order['subtotal_vnd']" :discount="$order['discount_vnd']"
            :delivery-fee="$order['delivery_fee_vnd']" :total="$order['total_vnd']" />

        <form method="POST" action="{{ route('checkout.store') }}">
            @csrf
            @foreach ($details->fields() as $name => $value)
                @if ($value !== null)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endif
            @endforeach
            <input type="hidden" name="checkout_key" value="{{ $key }}">
            <input type="hidden" name="accepted_total_vnd" value="{{ $order['total_vnd'] }}">
            <input type="hidden" name="quote_hash" value="{{ $quote['hash'] }}">
            <flux:button type="submit" variant="primary">Xác nhận đặt hàng</flux:button>
        </form>
    </section>
</x-layouts::customer>
