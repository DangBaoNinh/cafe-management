@props(['amount', 'memo', 'bank' => null])
@php
    $recipient = $bank ?? app(\App\Services\BankTransferQr::class)->details();
    $qr = $recipient ? app(\App\Services\BankTransferQr::class)->forSnapshot($recipient, (int) $amount, $memo) : null;
@endphp
@if($qr && $amount > 0)
    <section {{ $attributes->class(['space-y-2 text-center']) }}>
        <img src="{{ $qr }}" class="mx-auto h-auto w-full max-w-xs rounded-lg bg-white" alt="Mã QR tài khoản nhận chuyển khoản" loading="lazy">
        <p class="text-sm">Số tiền: <strong><x-customer.vnd :amount="(int) $amount" /></strong></p>
        <p class="text-sm">Nội dung: <strong class="wrap-anywhere">{{ $memo }}</strong></p>
        <p class="terrasse-muted text-sm">Nhập đúng số tiền và nội dung trên trong ứng dụng ngân hàng. Chuyển khoản sẽ được nhân viên đối chiếu trước khi xác nhận.</p>
    </section>
@endif
