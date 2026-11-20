<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function index(Request $request): View
    {
        $categories = Category::active()->orderBy('sort_order')->orderBy('id')->get();
        $categorySlug = $request->query('category');
        $selectedCategory = $categorySlug === null ? null : $categories->firstWhere('slug', '===', $categorySlug);

        abort_if($categorySlug !== null && $selectedCategory === null, 404);

        $filters = $selectedCategory ? ['category' => $selectedCategory->slug] : [];
        $products = Product::visibleInCatalog()
            ->with('category')
            ->withMin('activeSizes as starting_price_vnd', 'price_vnd')
            ->when($selectedCategory, fn ($query, Category $category) => $query->whereBelongsTo($category))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(12)
            ->appends($filters);

        if ($products->currentPage() > 1) {
            $filters['page'] = $products->currentPage();
        }

        return view('customer.menu.index', compact('categories', 'selectedCategory', 'products', 'filters'));
    }

    public function show(Request $request, string $slug): View
    {
        $product = Product::visibleInCatalog()
            ->with(['category', 'activeSizes' => fn (HasMany $sizes) => $sizes->orderBy('price_vnd')->orderBy('id')])
            ->where('slug', $slug)
            ->firstOrFail();

        $sizes = $product->activeSizes;
        abort_if($sizes->isEmpty(), 404);

        $category = $request->query('category');
        $page = filter_var($request->query('page'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 2]]);
        $filters = [];

        if ($category === $product->category->slug) {
            $filters['category'] = $category;
        }

        if ($page !== false && ($category === null || $filters !== [])) {
            $filters['page'] = $page;
        }

        return view('customer.menu.show', compact('product', 'sizes', 'filters'));
    }
}
