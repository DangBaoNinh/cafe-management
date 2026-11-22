@props(['state'])
<span class="order-state order-state--{{ $state }}">{{ match($state) { 'paid' => 'Đã thanh toán', 'cancelled' => 'Đã hủy', default => 'Chờ thanh toán' } }}</span>
