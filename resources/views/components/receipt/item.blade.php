@props(['item', 'orderItem' => false])
@php
    $toppings = $orderItem ? $item->toppings->pluck('topping_name')->all() : array_column($item->toppings, 'name');
    $unitPrice = $orderItem ? $item->base_unit_price_vnd + $item->toppings->sum('unit_price_vnd') : $item->unit_price_vnd;
@endphp
<tr>
    <td>
        <strong class="receipt-item-name">{{ $item->product_name }}</strong>
        <span class="receipt-item-details">{{ $item->size_label }}</span>
        @foreach(['Đường' => $item->sugar_level, 'Đá' => $item->ice_level] as $label => $level)
            @if($level !== null)<span class="receipt-item-details">{{ $label }}: {{ \App\Enums\DrinkLevel::from($level)->label() }}</span>@endif
        @endforeach
        @if($toppings)<span class="receipt-item-details">{{ implode(', ', $toppings) }}</span>@endif
        @if($item->notes ?? null)<span class="receipt-item-notes">Lưu ý: {{ $item->notes }}</span>@endif
    </td>
    <td class="receipt-qty">{{ $item->quantity }}</td>
    <td class="receipt-money">{{ number_format($unitPrice, 0, ',', '.') }}</td>
    <td class="receipt-money">{{ number_format($item->line_total_vnd, 0, ',', '.') }}</td>
</tr>
