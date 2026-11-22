@if($reservation->deposit)
    @php($deposit = $reservation->deposit)
    <section class="terrasse-panel space-y-3 rounded p-4" aria-label="Tiền cọc đặt bàn">
        <h2 class="text-xl font-semibold">Đặt cọc bắt buộc · <x-customer.vnd :amount="$deposit->amount_vnd" /></h2>
        <p>Chứng từ <strong>{{ $deposit->number }}</strong> · <strong>{{ $deposit->statusLabel() }}</strong></p>
        @if($deposit->status === 'pending')
            <p>Chỉ nhận chuyển khoản. Bàn được xác nhận đã đặt sau khi thanh toán cọc thành công. Nếu bàn vừa được người khác đặt, hệ thống sẽ yêu cầu chọn lại.</p>
            <p>{{ $deposit->bank_details['bank_name'] }} · {{ $deposit->bank_details['account_number'] }} · {{ $deposit->bank_details['account_holder'] }}</p>
            <x-payment-qr :amount="$deposit->amount_vnd" :memo="$deposit->number" :bank="$deposit->bank_details" />
            <p>Nội dung chuyển khoản: <strong>{{ $deposit->number }}</strong></p>
            <p class="text-sm">Chế độ demo: nút bên dưới mô phỏng xác nhận chuyển khoản, không kiểm tra giao dịch ngân hàng thật.</p>
            @can('access-customer-orders')
                @if(config('checkout.reservation_deposit_demo'))
                    <form method="POST" action="{{ route('reservations.deposit.pay', $reservation->code) }}">@csrf
                        <input type="hidden" name="confirmed" value="1"><input type="hidden" name="amount_vnd" value="{{ $deposit->amount_vnd }}">
                        <flux:button type="submit" variant="primary">Tôi đã chuyển khoản — xác nhận demo</flux:button>
                    </form>
                @else<p>Chức năng xác nhận demo đang tắt.</p>@endif
            @endcan
        @elseif($deposit->status === 'paid')
            <p><x-customer.vnd :amount="$deposit->amount_vnd" /> sẽ được tự trừ vào hóa đơn tại bàn một lần. Nếu còn dư cọc, nhân viên hoàn tiền mặt và ghi nhận khi hoàn tất thanh toán.</p>
        @elseif($deposit->status === 'settled')
            <p>Đã trừ vào hóa đơn {{ $deposit->invoice?->number }}: <strong><x-customer.vnd :amount="$deposit->credited_vnd" /></strong>.</p>
            @if($deposit->cash_refund_vnd > 0)<p>Đã hoàn tiền mặt phần cọc dư: <strong><x-customer.vnd :amount="$deposit->cash_refund_vnd" /></strong>.</p>@endif
        @elseif($deposit->status === 'refunded')<p>Đã hoàn tiền demo về tài khoản ngân hàng liên kết. Đây là giao dịch mô phỏng.</p>
        @elseif($deposit->status === 'forfeited')<p>Quán giữ cọc: {{ $deposit->forfeit_reason ?? 'Xử lý theo quy định của lượt đặt.' }}</p>@endif
    </section>
@endif
