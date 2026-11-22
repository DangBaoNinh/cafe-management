@use('App\Enums\IngredientUnit')
@use('App\Support\InventoryQuantity')

<x-layouts::admin title="Kho nguyên liệu">
    <div class="space-y-6">
        <h1 class="text-2xl font-semibold">Kho nguyên liệu</h1>
        <x-auth-session-status :status="session('status')" />

        <section class="space-y-3 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4">
            <h2 class="text-lg font-semibold">Kiểm soát tồn khi pha chế</h2>
            @if ($enabled)
                <p>Đang bật. Kho được trừ khi nhân viên bắt đầu pha chế.</p>
            @else
                <p>Đang tắt. Hoàn thiện công thức món đang bán, xử lý đơn cũ thiếu định lượng và chốt tồn đầu kỳ trước khi bật.</p>
                @if ($errors->has('inventory_control'))
                    <ul class="list-disc space-y-1 ps-5 text-terrasse-danger" role="alert">
                        @foreach ($errors->get('inventory_control') as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                @endif
                <form method="POST" action="{{ route('admin.inventory.enable') }}">
                    @csrf
                    <flux:button type="submit" variant="primary">Bật kiểm soát tồn</flux:button>
                </form>
            @endif
        </section>

        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <section class="min-w-0 space-y-3">
                <h2 class="text-lg font-semibold">Tồn hiện tại</h2>
                <ul class="divide-y divide-terrasse-ink/15 rounded-lg border border-terrasse-ink/15 bg-terrasse-paper">
                    @forelse ($ingredients as $ingredient)
                        <li class="flex flex-wrap items-center justify-between gap-3 p-4">
                            <div class="min-w-0 wrap-anywhere">
                                <flux:link :href="route('admin.inventory.show', $ingredient)">{{ $ingredient->name }}</flux:link>
                                <p class="text-sm">Ngưỡng thấp {{ InventoryQuantity::format($ingredient->low_threshold) }} {{ $ingredient->unit->label() }}</p>
                            </div>
                            <div>
                                <p class="font-semibold tabular-nums">{{ InventoryQuantity::format($ingredient->stock) }} {{ $ingredient->unit->label() }}</p>
                                @if ($ingredient->stock_version === 0)
                                    <p class="text-sm text-terrasse-danger">Chưa ghi tồn đầu kỳ</p>
                                @elseif ($ingredient->stock < $ingredient->low_threshold)
                                    <p class="text-sm text-terrasse-danger">Dưới ngưỡng</p>
                                @endif
                            </div>
                        </li>
                    @empty
                        <li class="p-4 text-sm">Chưa có nguyên liệu.</li>
                    @endforelse
                </ul>
                {{ $ingredients->links() }}
            </section>

            <form method="POST" action="{{ route('admin.inventory.store') }}" class="space-y-4 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4">
                @csrf
                <h2 class="text-lg font-semibold">Thêm nguyên liệu</h2>
                <flux:input name="name" label="Tên nguyên liệu" :value="old('name')" maxlength="120" required />
                <flux:select name="unit" label="Đơn vị cơ sở" placeholder="Chọn đơn vị" required>
                    @foreach (IngredientUnit::cases() as $unit)
                        <flux:select.option :value="$unit->value" :selected="old('unit') === $unit->value">{{ $unit->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input name="low_threshold" label="Ngưỡng tồn thấp" :value="old('low_threshold', '0')" inputmode="decimal" required />
                <flux:button type="submit" variant="primary">Thêm nguyên liệu</flux:button>
            </form>
        </div>
    </div>
</x-layouts::admin>
