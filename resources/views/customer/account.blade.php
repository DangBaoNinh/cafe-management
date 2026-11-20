<x-layouts::app title="Tài khoản">
    <section class="space-y-4">
        <h1 class="text-2xl font-semibold">Tài khoản của bạn</h1>
        <p>Xin chào, {{ auth()->user()->name }}.</p>
        <div class="flex flex-wrap gap-2">
            <flux:button :href="route('profile.edit')" wire:navigate>Cập nhật hồ sơ</flux:button>
            @can('manage-delivery-addresses')
                <flux:button :href="route('addresses.index')" wire:navigate>Địa chỉ giao hàng</flux:button>
            @endcan
            @can('access-customer-orders')
                <flux:button :href="route('orders.index')">Đơn của tôi</flux:button>
            @endcan
        </div>
    </section>
</x-layouts::app>
