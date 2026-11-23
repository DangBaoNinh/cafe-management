<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveCategoryRequest;
use App\Models\Category;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.categories.index', [
            'categories' => Category::query()->orderBy('sort_order')->orderBy('id')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.categories.form', ['category' => new Category]);
    }

    public function store(SaveCategoryRequest $request): RedirectResponse
    {
        return $this->save($request, new Category);
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.form', ['category' => $category]);
    }

    public function update(SaveCategoryRequest $request, Category $category): RedirectResponse
    {
        return $this->save($request, $category);
    }

    private function save(SaveCategoryRequest $request, Category $category): RedirectResponse
    {
        try {
            $category->fill($request->validated())->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'slug' => __('validation.unique', ['attribute' => 'slug']),
            ]);
        }

        return to_route('admin.categories.index')->with('status', 'Đã lưu danh mục.');
    }
}
