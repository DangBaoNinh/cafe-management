@php($title = $topping->exists ? 'Chỉnh sửa topping' : 'Thêm topping')

<x-layouts::admin :title="$title">
    <section class="max-w-xl space-y-5">
        <flux:link :href="route('admin.toppings.index')">Về danh sách topping</flux:link>
        <h1 class="text-2xl font-semibold">{{ $title }}</h1>

        <form method="POST" class="space-y-4"
            action="{{ $topping->exists ? route('admin.toppings.update', $topping) : route('admin.toppings.store') }}">
            @csrf
            @if ($topping->exists)
                @method('PATCH')
            @endif

            <x-admin.option-fields :option="$topping" name-field="name" name-label="Tên topping" />
            <flux:button type="submit" variant="primary">Lưu topping</flux:button>
        </form>
    </section>
</x-layouts::admin>
