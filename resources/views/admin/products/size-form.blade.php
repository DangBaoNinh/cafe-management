@php($title = $size->exists ? 'Chỉnh sửa size' : 'Thêm size')

<x-layouts::admin :title="$title">
    <section class="max-w-xl space-y-5">
        <flux:link :href="route('admin.products.options', $product)">Về cấu hình sản phẩm</flux:link>
        <h1 class="text-2xl font-semibold">{{ $title }}</h1>
        <p class="wrap-anywhere">{{ $product->name }}</p>

        <form method="POST" class="space-y-4"
            action="{{ $size->exists ? route('admin.products.sizes.update', [$product, $size]) : route('admin.products.sizes.store', $product) }}">
            @csrf
            @if ($size->exists)
                @method('PATCH')
            @endif

            <x-admin.option-fields :option="$size" name-field="label" name-label="Tên size" />
            <flux:button type="submit" variant="primary">Lưu size</flux:button>
        </form>
    </section>
</x-layouts::admin>
