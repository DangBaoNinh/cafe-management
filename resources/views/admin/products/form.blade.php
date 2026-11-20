@php
    $title = $product->exists ? 'Chỉnh sửa sản phẩm' : 'Thêm sản phẩm';
    $name = old('name', $product->name);
    $slug = old('slug', $product->slug);
    $description = old('description', $product->description);
    $categoryId = old('category_id', (string) $product->category_id);
    $status = old('is_active', (string) (int) $product->is_active);
    $allowsSugar = old('allows_sugar', (string) (int) $product->allows_sugar);
    $allowsIce = old('allows_ice', (string) (int) $product->allows_ice);
@endphp

<x-layouts::admin :title="$title">
    <section class="max-w-xl space-y-5">
        <flux:link :href="route('admin.products.index')">Về danh sách sản phẩm</flux:link>
        <h1 class="text-2xl font-semibold">{{ $title }}</h1>

        @if ($categories->isEmpty())
            <p>Hãy tạo danh mục trước khi thêm sản phẩm.</p>
            <flux:button :href="route('admin.categories.create')">Thêm danh mục</flux:button>
        @else
            <form method="POST" enctype="multipart/form-data"
                action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}"
                class="space-y-4">
                @csrf
                @if ($product->exists)
                    @method('PATCH')
                @endif

                <flux:select name="category_id" label="Danh mục" placeholder="Chọn danh mục" required>
                    @foreach ($categories as $category)
                        <flux:select.option :value="$category->id" :selected="$categoryId === (string) $category->id">
                            {{ $category->name }}{{ $category->is_active ? '' : ' (đã ẩn)' }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input name="name" label="Tên sản phẩm" :value="is_string($name) ? $name : ''" required maxlength="255" />
                <flux:input name="slug" label="Slug" :value="is_string($slug) ? $slug : ''" required maxlength="255"
                    description="Dùng chữ thường không dấu, chữ số và dấu gạch nối." placeholder="ca-phe-sua" />
                <flux:textarea name="description" label="Mô tả" maxlength="4000">{{ is_string($description) ? $description : '' }}</flux:textarea>

                @if ($product->image_path)
                    <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($product->image_path) }}"
                        alt="Ảnh hiện tại của {{ $product->name }}" class="size-24 rounded-lg object-cover">
                @endif
                <flux:input name="image" type="file" label="{{ $product->image_path ? 'Thay ảnh' : 'Ảnh sản phẩm' }}"
                    accept="image/jpeg,image/png,image/webp" description="JPEG, PNG hoặc WebP, tối đa 2 MiB." />

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:select name="allows_sugar" label="Tùy chọn đường" required>
                        <flux:select.option value="0" :selected="$allowsSugar === '0'">Cố định</flux:select.option>
                        <flux:select.option value="1" :selected="$allowsSugar === '1'">Cho phép chọn</flux:select.option>
                    </flux:select>
                    <flux:select name="allows_ice" label="Tùy chọn đá" required>
                        <flux:select.option value="0" :selected="$allowsIce === '0'">Cố định</flux:select.option>
                        <flux:select.option value="1" :selected="$allowsIce === '1'">Cho phép chọn</flux:select.option>
                    </flux:select>
                </div>
                <flux:select name="is_active" label="Trạng thái" required>
                    <flux:select.option value="1" :selected="$status === '1'">Đang hoạt động</flux:select.option>
                    <flux:select.option value="0" :selected="$status === '0'">Ngừng bán</flux:select.option>
                </flux:select>

                <flux:button type="submit" variant="primary">Lưu sản phẩm</flux:button>
            </form>
        @endif
    </section>
</x-layouts::admin>
