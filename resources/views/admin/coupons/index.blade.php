<x-layouts::admin title="Mã giảm giá">
    <section class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold">Mã giảm giá</h1>
            <flux:button variant="primary" :href="route('admin.coupons.create')">Thêm mã giảm giá</flux:button>
        </div>

        <x-auth-session-status :status="session('status')" />

        <div class="overflow-x-auto rounded-lg border border-coffee/15 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-coffee/15">
                    <tr>
                        <th scope="col" class="p-4">Mã</th>
                        <th scope="col" class="p-4">Mức giảm</th>
                        <th scope="col" class="p-4">Hiệu lực (giờ Việt Nam)</th>
                        <th scope="col" class="p-4">Trạng thái</th>
                        <th scope="col" class="p-4"><span class="sr-only">Thao tác</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-coffee/15">
                    @forelse ($coupons as $coupon)
                        <tr>
                            <th scope="row" class="p-4 text-left font-semibold wrap-anywhere">{{ $coupon->code }}</th>
                            <td class="p-4 whitespace-nowrap">
                                @if ($coupon->discount_type === \App\Enums\CouponDiscountType::Percentage)
                                    {{ $coupon->discount_value }}%
                                @else
                                    {{ preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', (string) $coupon->discount_value) }} ₫
                                @endif
                            </td>
                            <td class="p-4">
                                <div>{{ $coupon->starts_at->setTimezone(\App\Models\Coupon::LOCAL_TIMEZONE)->format('d/m/Y H:i') }}</div>
                                <div>{{ $coupon->ends_at->setTimezone(\App\Models\Coupon::LOCAL_TIMEZONE)->format('d/m/Y H:i') }}</div>
                            </td>
                            <td class="p-4">
                                {{ match (true) {
                                    ! $coupon->is_active => 'Đã tắt',
                                    $coupon->isAvailableAt($now) => 'Đang hiệu lực',
                                    $now < $coupon->starts_at => 'Chưa bắt đầu',
                                    default => 'Đã hết hạn',
                                } }}
                            </td>
                            <td class="p-4 text-right">
                                <flux:button size="sm" :href="route('admin.coupons.edit', $coupon)">
                                    Chỉnh sửa<span class="sr-only"> {{ $coupon->code }}</span>
                                </flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-4">Chưa có mã giảm giá.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $coupons->links() }}
    </section>
</x-layouts::admin>
