<x-layouts::customer title="Cổng thanh toán mô phỏng">
    <section class="mx-auto max-w-xl space-y-5">
        <flux:link :href="route('orders.show', $order->order_number)">Về đơn hàng</flux:link>
        <header class="space-y-2">
            <h1 class="text-2xl font-semibold sm:text-3xl">Cổng thanh toán mô phỏng</h1>
            <p class="text-sm">Phiên dùng để trình diễn. Không phát sinh giao dịch thật.</p>
        </header>

        <dl class="grid gap-4 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-5 sm:grid-cols-2">
            <div class="wrap-anywhere">
                <dt class="text-sm">Mã đơn</dt>
                <dd class="font-semibold">{{ $order->order_number }}</dd>
            </div>
            <div class="wrap-anywhere">
                <dt class="text-sm">Số tiền mô phỏng</dt>
                <dd class="font-semibold"><x-customer.vnd :amount="$session->amount_vnd" /></dd>
            </div>
        </dl>

        @if ($canPay)
            <form method="POST" action="{{ route('orders.demo-payment.simulate', [$order->order_number, $session->reference]) }}" class="space-y-4">
                @csrf
                <flux:error name="scenario" />
                <div class="flex flex-wrap gap-3">
                    <flux:button type="submit" name="scenario" value="success" variant="primary" class="max-w-full">Thanh toán thành công</flux:button>
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
