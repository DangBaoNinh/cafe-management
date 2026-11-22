<x-layouts::admin title="Mã giảm giá">
    <section class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold">Mã giảm giá</h1>
            <flux:button variant="primary" :href="route('admin.coupons.create')">Thêm mã giảm giá</flux:button>
        </div>

        <x-auth-session-status :status="session('status')" />

        @if ($coupons->isEmpty())
            <div class="coupon-empty-state">
                <p class="coupon-summary__eyebrow">Ưu đãi</p>
                <h2 class="terrasse-display text-3xl">Chưa có mã giảm giá</h2>
                <p class="terrasse-muted max-w-md text-sm">Tạo phiếu ưu đãi đầu tiên để áp dụng tại bước đặt hàng.</p>
            </div>
        @else
            <div class="grid gap-4 lg:grid-cols-2">
                @foreach ($coupons as $coupon)
                    <x-coupon.summary :$coupon :$now>
                        <div class="flex justify-end border-t border-terrasse-ink/10 pt-4">
                            <flux:button size="sm" :href="route('admin.coupons.edit', $coupon)">Chỉnh sửa<span class="sr-only"> {{ $coupon->code }}</span></flux:button>
                        </div>
                    </x-coupon.summary>
                @endforeach
            </div>
        @endif

        {{ $coupons->links() }}
    </section>
</x-layouts::admin>
