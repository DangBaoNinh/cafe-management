<x-layouts::customer title="Tài khoản">
    @php($user = auth()->user())

    <div class="space-y-8 sm:space-y-10">
        <header class="terrasse-account-header">
            <h1 class="font-semibold">Tài khoản của bạn</h1>
            <div class="mt-6 flex items-center gap-4">
                <span class="terrasse-account-avatar" aria-hidden="true">{{ $user->initials() }}</span>
                <div class="min-w-0 space-y-1">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <p class="text-base font-semibold wrap-anywhere">{{ $user->name }}</p>
                        <span class="terrasse-account-role">{{ $user->role->label() }}</span>
                    </div>
                    <p class="text-sm wrap-anywhere">{{ $user->email }}</p>
                </div>
            </div>
        </header>

        <section aria-labelledby="account-links-heading">
            <h2 id="account-links-heading" class="terrasse-display mb-4 text-3xl font-semibold sm:text-4xl">Quản lý tài khoản</h2>
            <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <x-terrasse.account-link :href="route('profile.edit')" title="Cập nhật hồ sơ"
                    description="Đổi tên và email đăng nhập.">
                    <x-slot:icon><flux:icon.user-circle class="size-5" /></x-slot:icon>
                </x-terrasse.account-link>

                @can('manage-delivery-addresses')
                    <x-terrasse.account-link :href="route('addresses.index')" title="Địa chỉ giao hàng"
                        description="Lưu địa chỉ và chọn nơi nhận mặc định.">
                        <x-slot:icon><flux:icon.map-pin class="size-5" /></x-slot:icon>
                    </x-terrasse.account-link>
                @endcan

                @can('access-customer-orders')
                    <x-terrasse.account-link :href="route('reservations.index')" title="Lịch sử đặt bàn"
                        description="Theo dõi lịch hẹn và xem chi tiết các lần đặt bàn, phòng họp.">
                        <x-slot:icon><flux:icon.calendar-days class="size-5" /></x-slot:icon>
                    </x-terrasse.account-link>
                    <x-terrasse.account-link :href="route('orders.index')" title="Đơn của tôi"
                        description="Xem lịch sử và theo dõi trạng thái đơn.">
                        <x-slot:icon><flux:icon.shopping-bag class="size-5" /></x-slot:icon>
                    </x-terrasse.account-link>
                @endcan

                @can('access-admin')
                    <x-terrasse.account-link :href="route('admin.dashboard')" title="Tổng quan quản trị"
                        description="Xem báo cáo doanh thu và trạng thái đơn hàng.">
                        <x-slot:icon><flux:icon.chart-bar class="size-5" /></x-slot:icon>
                    </x-terrasse.account-link>
                @endcan
            </ul>
        </section>

        @can('operate-orders')
            <section aria-labelledby="operations-links-heading">
                <h2 id="operations-links-heading" class="terrasse-display mb-4 text-3xl font-semibold sm:text-4xl">Vận hành cửa hàng</h2>
                <ul class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <x-terrasse.account-link :href="route('operations.orders.index')" title="Đơn chờ xác nhận"
                        description="Kiểm tra đơn mới trước khi tiếp nhận.">
                        <x-slot:icon><flux:icon.clipboard-document-list class="size-5" /></x-slot:icon>
                    </x-terrasse.account-link>

                    <x-terrasse.account-link :href="route('operations.preparation.index')" title="Pha chế"
                        description="Mở hàng đợi pha chế và xem yêu cầu từng ly.">
                        <x-slot:icon><flux:icon.beaker class="size-5" /></x-slot:icon>
                    </x-terrasse.account-link>

                    <x-terrasse.account-link :href="route('operations.handoff.index')" title="Bàn giao"
                        description="Xem đơn sẵn sàng nhận và đơn đang giao.">
                        <x-slot:icon><flux:icon.truck class="size-5" /></x-slot:icon>
                    </x-terrasse.account-link>

                    <x-terrasse.account-link :href="route('operations.invoices.index')" title="Hóa đơn thanh toán"
                        description="Tra cứu và thanh toán hóa đơn đã lưu.">
                        <x-slot:icon><flux:icon.credit-card class="size-5" /></x-slot:icon>
                    </x-terrasse.account-link>
                </ul>
            </section>
        @endcan
    </div>
</x-layouts::customer>
