<x-layouts::admin title="Quản lý danh mục">
    <section class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold">Quản lý danh mục</h1>
            <flux:button variant="primary" :href="route('admin.categories.create')">Thêm danh mục</flux:button>
        </div>

        <x-auth-session-status :status="session('status')" />

        <div class="relative overflow-x-auto rounded-lg border border-coffee/15 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-coffee/15">
                    <tr>
                        <th scope="col" class="p-4">Danh mục</th>
                        <th scope="col" class="p-4">Thứ tự</th>
                        <th scope="col" class="p-4">Trạng thái</th>
                        <th scope="col" class="p-4"><span class="sr-only">Thao tác</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-coffee/15">
                    @forelse ($categories as $category)
                        <tr>
                            <td class="p-4"><div class="font-medium">{{ $category->name }}</div>{{ $category->slug }}</td>
                            <td class="p-4">{{ $category->sort_order }}</td>
                            <td class="p-4">{{ $category->is_active ? 'Đang hoạt động' : 'Đã ẩn' }}</td>
                            <td class="p-4 text-right">
                                <flux:button size="sm" :href="route('admin.categories.edit', $category)">
                                    Chỉnh sửa<span class="sr-only"> {{ $category->name }}</span>
                                </flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="p-4">Chưa có danh mục.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $categories->links() }}
    </section>
</x-layouts::admin>
