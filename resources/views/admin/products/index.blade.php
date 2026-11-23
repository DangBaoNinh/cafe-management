<x-layouts::admin title="Quản lý sản phẩm">
    <section class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold">Quản lý sản phẩm</h1>
            <flux:button variant="primary" :href="route('admin.products.create')">Thêm sản phẩm</flux:button>
        </div>

        <x-auth-session-status :status="session('status')" />

        <div class="relative overflow-x-auto rounded-lg border border-terrasse-ink/15 bg-terrasse-paper">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-terrasse-ink/15">
                    <tr>
                        <th scope="col" class="p-4">Sản phẩm</th>
                        <th scope="col" class="p-4">Danh mục</th>
                        <th scope="col" class="p-4">Trạng thái</th>
                        <th scope="col" class="p-4"><span class="sr-only">Thao tác</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-terrasse-ink/15">
                    @forelse ($products as $product)
                        <tr>
                            <td class="p-4"><div class="font-medium">{{ $product->name }}</div>{{ $product->slug }}</td>
                            <td class="p-4">
                                {{ $product->category->name }}
                                @unless ($product->category->is_active)
                                    <span class="block text-xs">(đã ẩn)</span>
                                @endunless
                            </td>
                            <td class="p-4">{{ $product->is_active ? 'Đang hoạt động' : 'Ngừng bán' }}</td>
                            <td class="p-4">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <flux:button size="sm" :href="route('admin.products.options', $product)">
                                        Size và topping<span class="sr-only"> của {{ $product->name }}</span>
                                    </flux:button>
                                    <flux:button size="sm" :href="route('admin.products.edit', $product)">
                                        Chỉnh sửa<span class="sr-only"> {{ $product->name }}</span>
                                    </flux:button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="p-4">Chưa có sản phẩm.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $products->links() }}
    </section>
</x-layouts::admin>
