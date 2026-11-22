<x-layouts::admin title="Yêu cầu hoàn tiền">
    <h1 class="text-3xl font-bold">Yêu cầu hoàn tiền</h1>
    <p>Theo dõi khoản hoàn tự động demo và khoản chờ xử lý thủ công. Chỉ xác nhận khoản thủ công sau khi đã chuyển trả đủ tiền.</p>
    @foreach($refunds as $refund)
        <section class="my-4 space-y-3 rounded-xl border p-5">
            <h2 class="font-bold">Yêu cầu #{{ $refund->id }} · {{ $refund->order_id ? 'Đơn #'.$refund->order_id : 'Cọc #'.$refund->reservation_deposit_id }}</h2>
            <p>@if($refund->is_demo)<strong>Hoàn tự động demo</strong> · @endif<x-customer.vnd :amount="$refund->amount_vnd" /> · {{ $refund->status === 'pending' ? 'Chờ hoàn tiền' : 'Đã hoàn tiền' }}</p>
            <p>{{ $refund->bank_snapshot['bank_name'] }} · {{ $refund->bank_snapshot['account_number'] }} · {{ $refund->bank_snapshot['account_holder'] }}</p>
            @if($refund->completed_at)<p>Mã hoàn: {{ $refund->refund_reference }} · {{ $refund->completed_at->setTimezone('Asia/Ho_Chi_Minh')->format('H:i d/m/Y') }}</p>@endif
            @if($refund->status === 'pending')
                <form method="POST" action="{{ route('operations.refunds.complete', $refund) }}" class="space-y-3">@csrf
                    <flux:input name="refund_reference" label="Mã giao dịch hoàn tiền" required maxlength="128" />
                    <label><input type="checkbox" name="confirm" value="1" required> Tôi xác nhận đã chuyển trả đủ tiền đúng tài khoản.</label>
                    <flux:button type="submit" variant="primary">Xác nhận đã hoàn tiền</flux:button>
                </form>
            @endif
        </section>
    @endforeach
    {{ $refunds->links() }}
</x-layouts::admin>
