<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $slug = Rule::unique(Product::class);
        $product = $this->route('product');

        if ($product instanceof Product) {
            $slug->ignore($product);
        }

        return [
            'category_id' => ['bail', 'required', 'integer', Rule::exists(Category::class, 'id')],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['bail', 'required', 'string', 'max:255', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $slug],
            'description' => ['nullable', 'string', 'max:4000'],
            'image' => ['bail', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'is_active' => ['required', 'boolean'],
            'allows_sugar' => ['required', 'boolean'],
            'allows_ice' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'slug.regex' => 'Slug chỉ gồm chữ thường không dấu, chữ số và dấu gạch nối giữa các từ.',
            'image.image' => 'Tệp tải lên phải là ảnh hợp lệ.',
            'image.mimes' => 'Ảnh phải có định dạng JPEG, PNG hoặc WebP.',
            'image.max' => 'Ảnh không được vượt quá 2 MiB.',
            'image.uploaded' => 'Không thể tải ảnh lên. Hãy chọn ảnh có dung lượng tối đa 2 MiB.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'category_id' => 'danh mục',
            'name' => 'tên sản phẩm',
            'slug' => 'slug',
            'description' => 'mô tả',
            'image' => 'ảnh',
            'is_active' => 'trạng thái',
            'allows_sugar' => 'tùy chọn đường',
            'allows_ice' => 'tùy chọn đá',
        ];
    }
}
