@use('App\Support\InventoryQuantity')

<x-layouts::admin :title="'Công thức '.$title">
    <div class="max-w-3xl space-y-6">
        <header class="space-y-2">
            <flux:link :href="$back">Quay lại</flux:link>
            <h1 class="text-2xl font-semibold wrap-anywhere">Công thức {{ $title }}</h1>
            <p>Định lượng cho {{ $kind === 'size' ? 'một ly' : 'một phần topping' }}.</p>
        </header>
        <x-auth-session-status :status="session('status')" />

        <ul class="divide-y divide-terrasse-ink/15 rounded-lg border border-terrasse-ink/15 bg-terrasse-paper">
            @forelse ($components as $component)
                <li class="flex flex-wrap items-center justify-between gap-3 p-4">
                    <div class="min-w-0 wrap-anywhere">
                        <p class="font-medium">{{ $component->name }}</p>
                        <p class="text-sm">{{ InventoryQuantity::format((int) $component->pivot->quantity) }} {{ $component->unit->label() }}</p>
                    </div>
                    <form method="POST" action="{{ route('admin.inventory.recipes.destroy', [$kind, $option, $component]) }}">
                        @csrf
                        @method('DELETE')
                        <flux:button type="submit" size="sm">Bỏ<span class="sr-only"> {{ $component->name }}</span></flux:button>
                    </form>
                </li>
            @empty
                <li class="p-4 text-sm">Chưa có định lượng.</li>
            @endforelse
        </ul>

        @if ($ingredients->isEmpty())
            <flux:link :href="route('admin.inventory.index')">Khai báo nguyên liệu</flux:link>
        @else
            <form method="POST" action="{{ route('admin.inventory.recipes.store', [$kind, $option]) }}" class="space-y-4 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4">
                @csrf
                <h2 class="text-lg font-semibold">Thêm hoặc sửa định lượng</h2>
                <flux:select name="ingredient_id" label="Nguyên liệu" placeholder="Chọn nguyên liệu" required>
                    @foreach ($ingredients as $ingredient)
                        <flux:select.option :value="$ingredient->id" :selected="(string) old('ingredient_id') === (string) $ingredient->id">
                            {{ $ingredient->name }} ({{ $ingredient->unit->label() }})
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input name="quantity" label="Định lượng theo đơn vị cơ sở" :value="old('quantity')" inputmode="decimal" required />
                <flux:button type="submit" variant="primary">Lưu định lượng</flux:button>
            </form>
        @endif
    </div>
</x-layouts::admin>
