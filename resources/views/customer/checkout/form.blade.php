@php
    $fields = [
        'recipient_name' => old('recipient_name', $addresses->first()?->recipient_name ?? $customer->name),
        'phone' => old('phone', $addresses->first()?->phone ?? $customer->phone),
        'coupon_code' => old('coupon_code'),
    ];
    $fulfillment = old('fulfillment_type', 'pickup');
    $method = old('method', 'bank_transfer');
    $addressId = old('address_id');
    $pickupTime = old('pickup_time');
    $pickupMode = old('pickup_mode', is_string($pickupTime) && $pickupTime !== '' ? 'scheduled' : 'now');
    $inlineErrorFields = ['recipient_name', 'phone', 'fulfillment_type', 'method', 'coupon_code'];
    if ($addresses->isNotEmpty()) {
        $inlineErrorFields[] = 'address_id';
    }
    $summaryErrors = collect($errors->getMessages())->except($inlineErrorFields)->flatten();
@endphp

<x-layouts::customer title="Đặt hàng">
    <section class="mx-auto max-w-2xl space-y-5">
        <flux:link :href="route('cart.index')">Về giỏ hàng</flux:link>
        <h1 class="text-3xl font-semibold tracking-tight">Đặt hàng</h1>

        @if ($summaryErrors->isNotEmpty())
            <ul role="alert" class="space-y-1 rounded-lg border border-terrasse-danger/30 bg-terrasse-paper p-4 text-sm text-terrasse-danger">
                @foreach ($summaryErrors as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('checkout.review') }}" class="space-y-5 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4 sm:p-6"
            data-checkout-pickup>
            @csrf
            <input type="hidden" name="cart_version" value="{{ $cartVersion }}">

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input name="recipient_name" label="Tên người nhận" autocomplete="name" maxlength="120"
                    :value="is_string($fields['recipient_name']) ? $fields['recipient_name'] : ''" required />
                <flux:input name="phone" label="Số điện thoại" type="tel" autocomplete="tel" maxlength="13"
                    :value="is_string($fields['phone']) ? $fields['phone'] : ''" required />
            </div>

            <flux:select name="fulfillment_type" label="Cách nhận" required>
                @foreach (\App\Enums\FulfillmentType::cases() as $type)
                    <flux:select.option :value="$type->value" :selected="$fulfillment === $type->value">{{ $type->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <p class="text-sm">Nh?n t?i qu?n kh?ng gi?i h?n th?i gian l?y h?ng.</p>

            @if ($addresses->isEmpty())
                <p class="text-sm">Để chọn giao tận nơi, hãy <flux:link :href="route('addresses.index')">thêm địa chỉ giao hàng</flux:link>.</p>
            @else
                <flux:select name="address_id" label="Địa chỉ đã lưu" description="Chỉ cần chọn khi giao tận nơi.">
                    <flux:select.option value="">Chọn địa chỉ</flux:select.option>
                    @foreach ($addresses as $address)
                        <flux:select.option :value="$address->id" :selected="is_scalar($addressId) && (string) $addressId === (string) $address->id">
                            {{ $address->address_line }}, {{ $catalog->districts($address->province_code)[$address->district_code] ?? $address->district_code }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:select name="method" label="Phương thức thanh toán" required>
                <flux:select.option value="cod" :selected="$method === 'cod'">{{ \App\Enums\PaymentMethod::Cod->label() }}</flux:select.option>
                @if ($bankAvailable)
                    <flux:select.option value="bank_transfer" :selected="$method === 'bank_transfer'">{{ \App\Enums\PaymentMethod::BankTransfer->label() }}</flux:select.option>
                @endif
                @if (\App\Services\DemoPaymentGateway::enabled())
                    <flux:select.option value="demo_gateway" :selected="$method === 'demo_gateway'">Ngân hàng liên kết (demo)</flux:select.option>
                @endif
            </flux:select>

            <p>Nhận tại quán chỉ dùng QR chuyển khoản hoặc tài khoản liên kết. <a class="underline" href="{{ route('profile.edit') }}">Liên kết ngân hàng trong Hồ sơ</a>.</p>
            <section class="coupon-checkout-field" aria-labelledby="coupon-checkout-heading">
                <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="coupon-summary__eyebrow">Ưu đãi</p><h2 id="coupon-checkout-heading" class="text-lg font-semibold">Bạn có mã giảm giá?</h2></div><span class="coupon-checkout-field__seal" aria-hidden="true">%</span></div>
                <flux:input name="coupon_code" label="Nhập mã ưu đãi" maxlength="40" description="Mỗi đơn dùng một mã. Hệ thống sẽ kiểm tra điều kiện và tổng giảm ở bước tiếp theo." :value="is_string($fields['coupon_code']) ? $fields['coupon_code'] : ''" />
            </section>
            <flux:button type="submit" variant="primary">Xem tổng tiền</flux:button>
        </form>
    </section>
</x-layouts::customer>
