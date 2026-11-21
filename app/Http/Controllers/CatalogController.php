<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CatalogController extends Controller
{
    private const SORT_OPTIONS = [
        'name' => 'Tên món (A–Z)',
        'price_asc' => 'Giá thấp đến cao',
        'price_desc' => 'Giá cao đến thấp',
    ];

    public function home(): View
    {
        $products = $this->pricedCatalog()
            ->orderBy('name')
            ->orderBy('id')
            ->limit(3)
            ->get();

        return view('customer.home', compact('products'));
    }

    public function index(Request $request): View|Response
    {
        $categories = Category::active()->orderBy('sort_order')->orderBy('id')->get();
        $categorySlug = $request->query('category');
        $selectedCategory = $categorySlug === null ? null : $categories->firstWhere('slug', '===', $categorySlug);

        abort_if($categorySlug !== null && $selectedCategory === null, 404);

        $menu = [...compact('categories', 'selectedCategory'), 'sortOptions' => self::SORT_OPTIONS];

        try {
            $filters = $this->filterValidator($request)->validate();
        } catch (ValidationException $exception) {
            if ($request->expectsJson()) {
                throw $exception;
            }

            return response()->view('customer.menu.index', [
                ...$menu,
                'filters' => $selectedCategory ? ['category' => $selectedCategory->slug] : [],
                'filterInput' => $request->query(),
                'filterErrors' => $exception->validator->errors(),
                'products' => null,
            ], 422);
        }

        $filters = array_filter($filters, fn (mixed $value): bool => $value !== null);
        $page = (int) ($filters['page'] ?? 1);
        unset($filters['page']);

        $query = $this->pricedCatalog()
            ->when($selectedCategory, fn ($query, Category $category) => $query->whereBelongsTo($category));

        if (isset($filters['q'])) {
            $keyword = strtr($filters['q'], ['!' => '!!', '%' => '!%', '_' => '!_']);
            $query->whereRaw("products.name LIKE ? ESCAPE '!'", ['%'.$keyword.'%']);
        }

        // With at least one active size, these bounds are equivalent to comparing the minimum.
        if (isset($filters['min_price'])) {
            $query->whereDoesntHave('activeSizes', fn ($sizes) => $sizes->where('price_vnd', '<', (int) $filters['min_price']));
        }

        if (isset($filters['max_price'])) {
            $query->whereHas('activeSizes', fn ($sizes) => $sizes->where('price_vnd', '<=', (int) $filters['max_price']));
        }

        match ($filters['sort'] ?? 'name') {
            'price_asc' => $query->orderBy('starting_price_vnd'),
            'price_desc' => $query->orderByDesc('starting_price_vnd'),
            default => null,
        };

        $products = $query
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(12, page: $page)
            ->appends($filters);

        return view('customer.menu.index', [
            ...$menu,
            'products' => $products,
            'filters' => $filters,
            'detailFilters' => $page > 1 ? [...$filters, 'page' => $page] : $filters,
            'filterInput' => $filters,
            'filterErrors' => null,
        ]);
    }

    public function show(Request $request, string $slug): View
    {
        $product = Product::visibleInCatalog()
            ->with(['category', 'activeSizes' => fn (HasMany $sizes) => $sizes->orderBy('price_vnd')->orderBy('id')])
            ->where('slug', $slug)
            ->firstOrFail();

        $sizes = $product->activeSizes;
        abort_if($sizes->isEmpty(), 404);

        $validator = $this->filterValidator($request);
        $filters = $validator->fails() ? [] : array_filter($validator->validated(), fn (mixed $value): bool => $value !== null);

        if (isset($filters['category']) && $filters['category'] !== $product->category->slug) {
            unset($filters['category'], $filters['page']);
        }

        return view('customer.menu.show', compact('product', 'sizes', 'filters'));
    }

    /** @return Builder<Product> */
    private function pricedCatalog(): Builder
    {
        return Product::visibleInCatalog()
            ->with('category')
            ->withMin('activeSizes as starting_price_vnd', 'price_vnd');
    }

    private function filterValidator(Request $request): Validator
    {
        $price = ['bail', 'nullable', 'integer', 'min:0', 'max:4294967295'];

        return validator($request->query(), [
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:255'],
            'min_price' => $price,
            'max_price' => [...$price, Rule::when(filled($request->query('min_price')), ['gte:min_price'])],
            'sort' => ['nullable', Rule::in(array_keys(self::SORT_OPTIONS))],
            'page' => ['bail', 'nullable', 'integer', 'min:1', 'max:2147483647'],
        ], [
            'max_price.gte' => 'Giá đến phải lớn hơn hoặc bằng giá từ.',
        ], [
            'q' => 'từ khóa',
            'category' => 'danh mục',
            'min_price' => 'giá từ',
            'max_price' => 'giá đến',
            'sort' => 'sắp xếp',
            'page' => 'số trang',
        ]);
    }
}
