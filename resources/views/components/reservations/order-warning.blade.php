@props(['seat'])
@php($booking = $seat->activeReservation)
@if($booking && $booking->status === \App\Enums\ReservationStatus::Confirmed && $booking->scheduled_at->setTimezone('Asia/Ho_Chi_Minh')->toDateString() === now('Asia/Ho_Chi_Minh')->toDateString())
    <aside role="status" class="rounded-lg border border-amber-600/40 bg-amber-100 p-4 text-sm text-amber-950">
        <p class="font-bold">Bàn có lịch đặt hôm nay lúc {{ $booking->scheduled_at->setTimezone('Asia/Ho_Chi_Minh')->format('H:i') }}</p>
        <p>{{ $booking->contact_name }} · {{ $booking->party_size }} khách · Mã {{ $booking->code }}</p>
        <p>Nhắc khách vãng lai rời bàn trước giờ đặt. Order tại đây tính bình thường, chưa sử dụng cọc.</p>
        <a class="font-semibold underline" href="{{ route('operations.reservations.show', $booking->code) }}">Mở lịch đặt để xác nhận nhận bàn</a>
    </aside>
@endif
