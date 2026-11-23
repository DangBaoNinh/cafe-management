<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $slug = Rule::unique(Category::class);
        $category = $this->route('category');

        if ($category instanceof Category) {
            $slug->ignore($category);
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['bail', 'required', 'string', 'max:255', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $slug],
            'sort_order' => ['bail', 'required', 'integer', 'min:0', 'max:2147483647'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'slug.regex' => 'Slug chỉ gồm chữ thường không dấu, chữ số và dấu gạch nối giữa các từ.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'tên danh mục', 'slug' => 'slug', 'sort_order' => 'thứ tự', 'is_active' => 'trạng thái'];
    }
}
