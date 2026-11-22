@use('App\Enums\InventoryMovementKind')
@use('App\Support\InventoryQuantity')
@use('Illuminate\Support\Str')

<x-layouts::admin :title="$ingredient->name">
    <div class="space-y-6">
        <header class="space-y-2">
            <flux:link :href="route('admin.inventory.index')">Về kho nguyên liệu</flux:link>
            <h1 class="text-2xl font-semibold wrap-anywhere">{{ $ingredient->name }}</h1>
            <p>Tồn hiện tại <strong>{{ InventoryQuantity::format($ingredient->stock) }} {{ $ingredient->unit->label() }}</strong></p>
            @if ($ingredient->stock < $ingredient->low_threshold)
                <p class="text-sm text-terrasse-danger">Dưới ngưỡng tồn thấp</p>
            @endif
        </header>

        <x-auth-session-status :status="session('status')" />

        <div class="grid items-start gap-4 lg:grid-cols-2">
            <form method="POST" action="{{ route('admin.inventory.record', $ingredient) }}" class="space-y-4 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4">
                @csrf
                <input type="hidden" name="stock_version" value="{{ $ingredient->stock_version }}">
                <input type="hidden" name="entry_key" value="{{ Str::uuid() }}">
                <h2 class="text-lg font-semibold">Ghi biến động</h2>
                <flux:select name="kind" label="Loại biến động" required>
                    @foreach ([InventoryMovementKind::Receipt, InventoryMovementKind::Count, InventoryMovementKind::Adjustment] as $kind)
                        <flux:select.option :value="$kind->value" :selected="old('kind', $ingredient->stock_version === 0 ? 'count' : 'receipt') === $kind->value">{{ $kind->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input name="quantity" :label="'Số lượng ('.$ingredient->unit->label().')'" :value="old('quantity')" inputmode="decimal" required />
                <p class="text-sm">Nhập số tồn thực tế khi kiểm kê. Khi điều chỉnh, dùng số âm cho hao hụt và số dương cho nguyên liệu trả lại.</p>
                <flux:input name="reason" label="Lý do" :value="old('reason')" maxlength="255" required />
                <flux:button type="submit" variant="primary">Ghi biến động</flux:button>
            </form>

            <form method="POST" action="{{ route('admin.inventory.update', $ingredient) }}" class="space-y-4 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4">
                @csrf
                @method('PATCH')
                <h2 class="text-lg font-semibold">Thông tin nguyên liệu</h2>
                <flux:input name="name" label="Tên nguyên liệu" :value="old('name', $ingredient->name)" maxlength="120" required />
                <flux:input name="low_threshold" :label="'Ngưỡng tồn thấp ('.$ingredient->unit->label().')'" :value="old('low_threshold', InventoryQuantity::format($ingredient->low_threshold))" inputmode="decimal" required />
                <flux:button type="submit">Lưu thông tin</flux:button>
            </form>
        </div>

        <section class="space-y-3">
            <h2 class="text-lg font-semibold">Lịch sử biến động</h2>
            <div class="overflow-x-auto rounded-lg border border-terrasse-ink/15 bg-terrasse-paper">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-terrasse-ink/15">
                        <tr>
                            <th scope="col" class="p-3">Thời điểm</th>
                            <th scope="col" class="p-3">Biến động</th>
                            <th scope="col" class="p-3">Thay đổi</th>
                            <th scope="col" class="p-3">Tồn sau</th>
                            <th scope="col" class="p-3">Người ghi</th>
                            <th scope="col" class="p-3">Lý do hoặc đơn hàng</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-terrasse-ink/15">
                        @forelse ($movements as $movement)
                            <tr>
                                <td class="p-3 whitespace-nowrap"><x-customer.order-time :date="$movement->created_at" /></td>
                                <td class="p-3">{{ $movement->kind->label() }}</td>
                                <td class="p-3 whitespace-nowrap">{{ $movement->quantity_change > 0 ? '+' : '' }}{{ InventoryQuantity::format($movement->quantity_change) }}</td>
                                <td class="p-3 whitespace-nowrap">{{ InventoryQuantity::format($movement->balance_after) }}</td>
                                <td class="p-3">{{ $movement->actor->name }}</td>
                                <td class="p-3">
                                    @if ($movement->order)
                                        <flux:link :href="route('admin.orders.show', $movement->order->order_number)">{{ $movement->order->order_number }}</flux:link>
                                    @else
                                        {{ $movement->reason }}
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td class="p-3" colspan="6">Chưa có biến động.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $movements->links() }}
        </section>
    </div>
</x-layouts::admin>
