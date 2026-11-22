<x-layouts::admin title="Quản lý topping">
    <section class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold">Quản lý topping</h1>
            <flux:button variant="primary" :href="route('admin.toppings.create')">Thêm topping</flux:button>
        </div>

        <x-auth-session-status :status="session('status')" />

        <div class="relative overflow-x-auto rounded-lg border border-terrasse-ink/15 bg-terrasse-paper">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-terrasse-ink/15">
                    <tr>
                        <th scope="col" class="p-4">Topping</th>
                        <th scope="col" class="p-4">Giá (VND)</th>
                        <th scope="col" class="p-4">Trạng thái</th>
                        <th scope="col" class="p-4"><span class="sr-only">Thao tác</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-terrasse-ink/15">
                    @forelse ($toppings as $topping)
                        <tr>
                            <td class="p-4"><div class="font-medium">{{ $topping->name }}</div>{{ $topping->code }}</td>
                            <td class="p-4">{{ number_format($topping->price_vnd, 0, ',', '.') }}</td>
                            <td class="p-4">{{ $topping->is_active ? 'Đang bán' : 'Ngừng bán' }}</td>
                            <td class="p-4 text-right">
                                <flux:button size="sm" :href="route('admin.inventory.recipes.show', ['topping', $topping])">
                                    Công thức<span class="sr-only"> {{ $topping->name }}</span>
                                </flux:button>
                                <flux:button size="sm" :href="route('admin.toppings.edit', $topping)">
                                    Chỉnh sửa<span class="sr-only"> {{ $topping->name }}</span>
                                </flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="p-4">Chưa có topping.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $toppings->links() }}
    </section>
</x-layouts::admin>
