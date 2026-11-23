<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Services\SaveProduct;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function __construct(private SaveProduct $products) {}

    public function index(): View
    {
        return view('admin.products.index', [
            'products' => Product::query()->with('category')->orderBy('name')->orderBy('id')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return $this->form(new Product);
    }

    public function store(SaveProductRequest $request): RedirectResponse
    {
        return $this->save($request, new Product);
    }

    public function edit(Product $product): View
    {
        return $this->form($product);
    }

    public function update(SaveProductRequest $request, Product $product): RedirectResponse
    {
        return $this->save($request, $product);
    }

    private function form(Product $product): View
    {
        return view('admin.products.form', [
            'product' => $product,
            'categories' => Category::query()->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    private function save(SaveProductRequest $request, Product $product): RedirectResponse
    {
        $image = $request->file('image');

        try {
            $this->products->save($product, $request->safe()->except('image'), $image instanceof UploadedFile ? $image : null);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'slug' => __('validation.unique', ['attribute' => 'slug']),
            ]);
        }

        return to_route('admin.products.index')->with('status', 'Đã lưu sản phẩm.');
    }
}
