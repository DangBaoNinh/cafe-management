<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use App\Models\ProductSize;
use App\Models\Topping;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProductOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $code = $this->input('code');

        if (is_string($code)) {
            $this->merge(['code' => strtolower($code)]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $product = $this->route('product');
        $isSize = $product instanceof Product;

        $code = $isSize
            ? Rule::unique(ProductSize::class)->where('product_id', $product->getKey())
            : Rule::unique(Topping::class);
        $option = $this->route($isSize ? 'size' : 'topping');

        if ($option instanceof ProductSize || $option instanceof Topping) {
            $code->ignore($option);
        }

        return [
            'code' => ['bail', 'required', 'string', 'max:40', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $code],
            ($isSize ? 'label' : 'name') => ['required', 'string', 'max:255'],
            'price_vnd' => ['bail', 'required', 'integer', 'min:0', 'max:4294967295'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['code.regex' => 'Mã chỉ gồm chữ cái không dấu, chữ số và dấu gạch nối giữa các từ.'];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['code' => 'mã', 'label' => 'tên size', 'name' => 'tên topping', 'price_vnd' => 'giá (VND)', 'is_active' => 'trạng thái'];
    }
}
