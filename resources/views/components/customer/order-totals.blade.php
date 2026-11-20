@props(['subtotal', 'discount', 'deliveryFee', 'total'])

<dl class="space-y-2 rounded-xl border border-coffee/15 bg-white p-4 tabular-nums">
    @foreach (['Tạm tính' => $subtotal, 'Giảm giá' => $discount, 'Phí giao' => $deliveryFee, 'Tổng tiền' => $total] as $label => $amount)
        <div @class(['flex flex-wrap justify-between gap-x-4', 'border-t border-coffee/15 pt-2 text-lg font-semibold' => $loop->last])>
            <dt>{{ $label }}</dt>
            <dd><x-customer.vnd :amount="$amount" /></dd>
        </div>
    @endforeach
</dl>
