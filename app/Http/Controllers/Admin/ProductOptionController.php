<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Topping;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProductOptionController extends Controller
{
    public function show(Product $product): View
    {
        return view('admin.products.options', [
            'product' => $product,
            'sizes' => $product->sizes()->orderBy('price_vnd')->orderBy('code')->get(),
            'toppings' => $product->toppings()->orderBy('name')->orderBy('toppings.id')->get(),
            'availableToppings' => Topping::active()
                ->whereDoesntHave('products', fn ($query) => $query->whereKey($product->getKey()))
                ->orderBy('name')->orderBy('id')->get(),
        ]);
    }

    public function attach(Request $request, Product $product): RedirectResponse
    {
        $request->validate(['topping_id' => ['required', 'integer', 'min:1']], attributes: ['topping_id' => 'topping']);

        try {
            $product->getConnection()->transaction(function () use ($request, $product): void {
                $topping = Topping::active()->whereKey($request->integer('topping_id'))->lockForUpdate()->first();

                if ($topping === null) {
                    throw ValidationException::withMessages(['topping_id' => 'Hãy chọn topping đang hoạt động.']);
                }

                $product->toppings()->attach($topping);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['topping_id' => 'Topping này đã được gắn cho sản phẩm.']);
        }

        return to_route('admin.products.options', $product)->with('status', 'Đã gắn topping.');
    }

    public function detach(Product $product, Topping $topping): RedirectResponse
    {
        abort_if($product->toppings()->detach($topping) === 0, 404);

        return to_route('admin.products.options', $product)->with('status', 'Đã bỏ gắn topping.');
    }
}
