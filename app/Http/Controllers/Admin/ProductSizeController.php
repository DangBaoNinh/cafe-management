<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveProductOptionRequest;
use App\Models\Product;
use App\Models\ProductSize;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class ProductSizeController extends Controller
{
    public function create(Product $product): View
    {
        return $this->form($product, new ProductSize);
    }

    public function store(SaveProductOptionRequest $request, Product $product): RedirectResponse
    {
        return $this->save($request, $product, $product->sizes()->make());
    }

    public function edit(Product $product, ProductSize $size): View
    {
        return $this->form($product, $size);
    }

    public function update(SaveProductOptionRequest $request, Product $product, ProductSize $size): RedirectResponse
    {
        return $this->save($request, $product, $size);
    }

    private function form(Product $product, ProductSize $size): View
    {
        return view('admin.products.size-form', ['product' => $product, 'size' => $size]);
    }

    private function save(SaveProductOptionRequest $request, Product $product, ProductSize $size): RedirectResponse
    {
        try {
            $size->fill($request->validated())->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'code' => __('validation.unique', ['attribute' => 'mã size trong sản phẩm này']),
            ]);
        }

        return to_route('admin.products.options', $product)->with('status', 'Đã lưu size.');
    }
}
