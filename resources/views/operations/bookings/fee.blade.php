<article class="space-y-3 border-t border-terrasse-ink/15 pt-3">
    <div>
        <p class="font-semibold">{{ $fee->label() }} · {{ number_format($fee->amount_vnd, 0, ',', '.') }} ₫</p>
        <p class="text-sm">{{ \App\Models\PickupFee::CHANNELS[$fee->channel] }} · {{ $fee->statusLabel() }}</p>
        @if ($fee->transfer_code !== null)
            <p class="text-sm">Nội dung chuyển khoản · <span class="font-mono">{{ $fee->transfer_code }}</span></p>
        @endif
        @if ($fee->bank_reference !== null)
            <p class="text-sm wrap-anywhere">Mã giao dịch · {{ $fee->bank_reference }}</p>
        @endif
    </div>
    @php($awaitingTransfer = $canSettle && $fee->status === 'pending')
    @if($awaitingTransfer && $fee->channel === 'app' && $fee->bank_details)
        <x-payment-qr :amount="$fee->amount_vnd" :memo="$fee->transfer_code" :bank="$fee->bank_details" />
    @endif
    @if ($fee->channel === 'app' && ($awaitingTransfer || ($fee->status === 'voided' && $fee->transfer_result === null)))
        <details class="space-y-3">
            <summary class="cursor-pointer text-sm font-semibold">Đối chiếu qua sao kê</summary>
            <form method="POST" action="{{ route($feeRoutes.'.resolve', [$bookingCode, $fee->id]) }}" class="space-y-3">
                @csrf
                <input type="hidden" name="result" value="paid">
                <div class="grid gap-3 sm:grid-cols-3">
                    <flux:input name="amount_vnd" label="Số tiền đã thu (₫)" type="number" min="1" step="1" required />
                    <flux:input name="memo" label="Nội dung chuyển khoản" maxlength="24" required />
                    <flux:input name="reference" label="Mã giao dịch" maxlength="100" required />
                </div>
                <flux:button type="submit">{{ $awaitingTransfer ? 'Xác nhận đã thu' : 'Ghi nhận tiền đến để hoàn' }}</flux:button>
            </form>
            @if ($awaitingTransfer)
                <form method="POST" action="{{ route($feeRoutes.'.resolve', [$bookingCode, $fee->id]) }}" class="space-y-3">
                    @csrf
                    <flux:select name="result" label="Kết quả khi chưa thu tiền" required>
                        <flux:select.option value="">Chọn kết quả đã xác minh</flux:select.option>
                        <flux:select.option value="failed">Thất bại</flux:select.option>
                        <flux:select.option value="expired">Hết hạn</flux:select.option>
                        <flux:select.option value="cancelled">Đã hủy</flux:select.option>
                    </flux:select>
                    <flux:button type="submit">Ghi kết quả</flux:button>
                </form>
            @endif
        </details>
    @elseif ($canSettle && $fee->channel === 'app' && $fee->status === 'failed')
        <form method="POST" action="{{ route($feeRoutes.'.counter', [$bookingCode, $fee->id]) }}">
            @csrf
            <flux:button type="submit">Chuyển sang hóa đơn tại quầy</flux:button>
        </form>
    @elseif ($fee->status === 'refund_pending' && auth()->user()?->can('access-admin'))
        <form method="POST" action="{{ route($feeRoutes.'.refund', [$bookingCode, $fee->id]) }}" class="space-y-3">
            @csrf
            <flux:input name="refund_reference" label="Mã giao dịch hoàn tiền đã thực hiện" maxlength="100" required />
            <flux:button type="submit">Ghi nhận đã hoàn</flux:button>
        </form>
    @endif
</article>
