<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveProductOptionRequest;
use App\Models\Topping;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class ToppingController extends Controller
{
    public function index(): View
    {
        return view('admin.toppings.index', [
            'toppings' => Topping::query()->orderBy('name')->orderBy('id')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return $this->form(new Topping);
    }

    public function store(SaveProductOptionRequest $request): RedirectResponse
    {
        return $this->save($request, new Topping);
    }

    public function edit(Topping $topping): View
    {
        return $this->form($topping);
    }

    public function update(SaveProductOptionRequest $request, Topping $topping): RedirectResponse
    {
        return $this->save($request, $topping);
    }

    private function form(Topping $topping): View
    {
        return view('admin.toppings.form', ['topping' => $topping]);
    }

    private function save(SaveProductOptionRequest $request, Topping $topping): RedirectResponse
    {
        try {
            $topping->fill($request->validated())->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'code' => __('validation.unique', ['attribute' => 'mã topping']),
            ]);
        }

        return to_route('admin.toppings.index')->with('status', 'Đã lưu topping.');
    }
}
