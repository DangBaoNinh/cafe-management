<x-layouts::admin title="Đối chiếu thanh toán">
    <section class="space-y-6">
        <header class="space-y-2 border-b border-terrasse-ink/15 pb-5">
            <h1 class="font-semibold">Đối chiếu thanh toán</h1>
            <p class="terrasse-muted text-sm">{{ number_format($payments->total(), 0, ',', '.') }} khoản thanh toán</p>
        </header>

        @if ($payments->isEmpty())
            <p class="rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-5">
                {{ $payments->total() > 0 ? 'Trang này không có khoản thanh toán.' : 'Chưa có khoản thanh toán cần đối chiếu hoặc chờ hoàn tiền.' }}
            </p>
        @else
            <ul class="grid gap-4 md:grid-cols-2">
                @foreach ($payments as $payment)
                    <li class="min-w-0">
                        <x-terrasse.order-card :order="$payment->order" :href="route('operations.payments.show', $payment->order->order_number)" :amount="$payment->amount_vnd">
                            <dl class="text-sm">
                                <dt class="terrasse-muted text-xs">{{ $payment->method->label() }}</dt>
                                <dd class="mt-1"><x-terrasse.status :status="$payment->status" /></dd>
                            </dl>
                        </x-terrasse.order-card>
                    </li>
                @endforeach
            </ul>
        @endif

        {{ $payments->links() }}
    </section>
</x-layouts::admin>
