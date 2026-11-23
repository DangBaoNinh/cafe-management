@php
    $title = $category->exists ? 'Chỉnh sửa danh mục' : 'Thêm danh mục';
    $name = old('name', $category->name);
    $slug = old('slug', $category->slug);
    $sortOrder = old('sort_order', $category->sort_order);
    $status = old('is_active', (string) (int) $category->is_active);
@endphp

<x-layouts::admin :title="$title">
    <section class="max-w-xl space-y-5">
        <flux:link :href="route('admin.categories.index')">Về danh sách danh mục</flux:link>
        <h1 class="text-2xl font-semibold">{{ $title }}</h1>

        <form method="POST" action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}" class="space-y-4">
            @csrf
            @if ($category->exists)
                @method('PATCH')
            @endif

            <flux:input name="name" label="Tên danh mục" :value="is_string($name) ? $name : ''" required maxlength="255" />
            <flux:input name="slug" label="Slug" :value="is_string($slug) ? $slug : ''" required maxlength="255"
                description="Dùng chữ thường không dấu, chữ số và dấu gạch nối." placeholder="ca-phe" />
            <flux:input name="sort_order" label="Thứ tự" type="number" :value="is_scalar($sortOrder) ? $sortOrder : ''"
                min="0" max="2147483647" step="1" required />

            <flux:select name="is_active" label="Trạng thái" required>
                <flux:select.option value="1" :selected="$status === '1'">Đang hoạt động</flux:select.option>
                <flux:select.option value="0" :selected="$status === '0'">Ẩn cùng các món trong danh mục</flux:select.option>
            </flux:select>

            <flux:button type="submit" variant="primary">Lưu danh mục</flux:button>
        </form>
    </section>
</x-layouts::admin>
