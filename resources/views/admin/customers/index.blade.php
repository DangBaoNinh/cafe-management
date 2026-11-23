<x-layouts::admin title="Tra cứu khách hàng">
    <section class="customer-lookup space-y-6">
        <header class="customer-lookup-heading">
            <p class="customer-lookup-eyebrow">Quản trị / Khách hàng</p>
            <h1>Tra cứu khách hàng</h1>
            <p>Tìm kiếm thông tin, kiểm tra trạng thái tài khoản và xem hồ sơ khách hàng.</p>
        </header>

        <form method="GET" action="{{ route('admin.customers.index') }}" class="terrasse-panel customer-lookup-filters">
            <div class="customer-lookup-filter-field">
                <label class="block text-sm font-medium" for="customer-search">Tên hoặc email</label>
                <flux:input id="customer-search" name="search" :value="$filters['search'] ?? ''" placeholder="Nhập tên hoặc email khách hàng…" maxlength="255" />
                <flux:error name="search" />
            </div>
            <div class="customer-lookup-filter-field">
                <label class="block text-sm font-medium" for="customer-status">Trạng thái tài khoản</label>
                <flux:select id="customer-status" name="status">
                    <flux:select.option value="">Tất cả trạng thái</flux:select.option>
                    <flux:select.option value="active" :selected="($filters['status'] ?? '') === 'active'">Đang hoạt động</flux:select.option>
                    <flux:select.option value="locked" :selected="($filters['status'] ?? '') === 'locked'">Đã khóa</flux:select.option>
                </flux:select>
                <flux:error name="status" />
            </div>
            <div class="customer-lookup-filter-actions">
                <flux:button type="submit" variant="primary">Lọc khách hàng</flux:button>
                @if ($filters !== [])
                    <flux:link :href="route('admin.customers.index')">Xóa bộ lọc</flux:link>
                @endif
            </div>
        </form>

        <div class="terrasse-panel customer-lookup-list">
            <div class="customer-lookup-list-heading">
                <h2>Danh sách khách hàng</h2>
                <p><strong>{{ number_format($customers->total(), 0, ',', '.') }}</strong> khách hàng{{ $filters !== [] ? ' phù hợp' : '' }}</p>
            </div>
            <p class="customer-lookup-scroll-hint">Vuốt ngang để xem đầy đủ thông tin khách hàng.</p>
            <div class="customer-lookup-table-scroll" role="region" aria-label="Danh sách khách hàng" tabindex="0">
                <table class="customer-lookup-table">
                    <caption class="sr-only">Thông tin khách hàng, email, trạng thái tài khoản và liên kết xem hồ sơ.</caption>
                    <thead>
                        <tr>
                            @foreach (['Khách hàng', 'Email', 'Trạng thái tài khoản', 'Thao tác'] as $label)
                                <th scope="col">{{ $label }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customers as $customer)
                            <tr>
                                <th scope="row">
                                    <div class="customer-lookup-person">
                                        <span class="customer-lookup-avatar" aria-hidden="true">{{ $customer->initials() }}</span>
                                        <div class="min-w-0">
                                            <a class="customer-lookup-name" href="{{ route('admin.customers.show', $customer->id) }}">{{ $customer->name }}</a>
                                            <span class="customer-lookup-id">Mã khách hàng #{{ $customer->id }}</span>
                                        </div>
                                    </div>
                                </th>
                                <td class="customer-lookup-email">{{ $customer->email }}</td>
                                <td><span class="customer-lookup-status customer-lookup-status--{{ $customer->is_active ? 'active' : 'locked' }}">{{ $customer->is_active ? 'Đang hoạt động' : 'Đã khóa' }}</span></td>
                                <td><flux:button :href="route('admin.customers.show', $customer->id)" size="sm" :aria-label="'Xem hồ sơ '.$customer->name">Xem hồ sơ</flux:button></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="customer-lookup-empty">
                                    <strong>{{ $customers->total() > 0 ? 'Trang này không có khách hàng.' : 'Không tìm thấy khách hàng.' }}</strong>
                                    <p>Thử thay đổi từ khóa hoặc trạng thái để tìm lại khách hàng.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($customers->hasPages())
                <div class="customer-lookup-pagination">{{ $customers->links() }}</div>
            @endif
        </div>
    </section>
</x-layouts::admin>
