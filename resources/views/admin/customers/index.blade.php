<x-layouts::admin title="Tra cứu khách hàng">
    <section class="space-y-6">
        <h1 class="text-2xl font-semibold sm:text-3xl">Tra cứu khách hàng</h1>

        <form method="GET" action="{{ route('admin.customers.index') }}" class="space-y-4 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="min-w-0 space-y-2">
                    <label class="block text-sm font-medium" for="customer-search">Tên hoặc email</label>
                    <flux:input id="customer-search" name="search" :value="$filters['search'] ?? ''" maxlength="255" />
                    <flux:error name="search" />
                </div>
                <div class="min-w-0 space-y-2">
                    <label class="block text-sm font-medium" for="customer-status">Trạng thái tài khoản</label>
                    <flux:select id="customer-status" name="status">
                        <flux:select.option value="">Tất cả trạng thái</flux:select.option>
                        <flux:select.option value="active" :selected="($filters['status'] ?? '') === 'active'">Đang hoạt động</flux:select.option>
                        <flux:select.option value="locked" :selected="($filters['status'] ?? '') === 'locked'">Đã khóa</flux:select.option>
                    </flux:select>
                    <flux:error name="status" />
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-4">
                <flux:button type="submit" variant="primary">Lọc khách hàng</flux:button>
                @if ($filters !== [])
                    <flux:link :href="route('admin.customers.index')">Xóa bộ lọc</flux:link>
                @endif
            </div>
        </form>

        @if ($customers->isEmpty())
            <p class="rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-5">
                {{ $customers->total() > 0 ? 'Trang này không có khách hàng.' : 'Không tìm thấy khách hàng.' }}
            </p>
        @else
            <ul class="divide-y divide-terrasse-ink/15 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper">
                @foreach ($customers as $customer)
                    <li class="grid gap-2 p-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center sm:p-5">
                        <div class="wrap-anywhere">
                            <h2 class="font-semibold">
                                <flux:link :href="route('admin.customers.show', $customer->id)">{{ $customer->name }}</flux:link>
                            </h2>
                            <p class="text-sm">{{ $customer->email }}</p>
                        </div>
                        <p class="text-sm">{{ $customer->is_active ? 'Đang hoạt động' : 'Đã khóa' }}</p>
                    </li>
                @endforeach
            </ul>
        @endif

        {{ $customers->links() }}
    </section>
</x-layouts::admin>
