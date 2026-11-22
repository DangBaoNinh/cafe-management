<x-layouts::customer title="Cổng thanh toán mô phỏng">
    <section class="mx-auto max-w-xl space-y-5">
        <flux:link :href="route('orders.show', $order->order_number)">Về đơn hàng</flux:link>
        <header class="space-y-2">
            <h1 class="text-2xl font-semibold sm:text-3xl">Chuyển khoản mô phỏng</h1>
            <p class="text-sm">Phiên dùng để trình diễn. Không phát sinh giao dịch thật; cửa hàng vẫn đối chiếu kết quả trước khi xử lý đơn.</p>
        </header>

        <dl class="grid gap-4 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-5 sm:grid-cols-2">
            <div class="wrap-anywhere">
                <dt class="text-sm">Mã đơn</dt>
                <dd class="font-mono font-semibold tabular-nums tracking-normal">{{ $order->order_number }}</dd>
            </div>
            <div class="wrap-anywhere">
                <dt class="text-sm">Số tiền mô phỏng</dt>
                <dd class="font-semibold"><x-customer.vnd :amount="$session->amount_vnd" /></dd>
            </div>
            <div class="wrap-anywhere sm:col-span-2">
                <dt class="text-sm">Mã thanh toán mô phỏng</dt>
                <dd class="mt-1 font-mono text-sm font-semibold tabular-nums tracking-normal">{{ $session->reference }}</dd>
            </div>
        </dl>

        @if ($canPay)
            <form method="POST" action="{{ route('orders.demo-payment.simulate', [$order->order_number, $session->reference]) }}" class="space-y-4">
                @csrf
                <flux:error name="scenario" />
                <div class="flex flex-wrap gap-3">
                    <flux:button type="submit" name="scenario" value="success" variant="primary" class="max-w-full">Tôi đã hoàn tất chuyển khoản</flux:button>
                    <flux:button type="submit" name="scenario" value="failure" class="max-w-full">Thanh toán thất bại</flux:button>
                    <flux:button type="submit" name="scenario" value="no-callback" class="max-w-full">Thành công, mất callback</flux:button>
                </div>
            </form>
        @elseif ($session->result === \App\Models\DemoPaymentSession::SUCCEEDED && $session->applied_at === null)
            <p role="status" class="rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4">Cổng đã ghi giao dịch. Đơn đang chờ quản trị viên đối chiếu.</p>
        @else
            <p role="status" class="rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4">Phiên này đã kết thúc. Xem trạng thái mới nhất trên trang đơn hàng.</p>
        @endif
    </section>
</x-layouts::customer>
