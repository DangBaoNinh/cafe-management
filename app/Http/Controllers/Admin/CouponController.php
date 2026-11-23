<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveCouponRequest;
use App\Models\Coupon;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class CouponController extends Controller
{
    public function index(): View
    {
        return view('admin.coupons.index', [
            'coupons' => Coupon::query()->latest('id')->paginate(20),
            'now' => now(),
        ]);
    }

    public function create(): View
    {
        return $this->edit(new Coupon);
    }

    public function store(SaveCouponRequest $request): RedirectResponse
    {
        return $this->save($request, new Coupon);
    }

    public function edit(Coupon $coupon): View
    {
        return view('admin.coupons.form', ['coupon' => $coupon]);
    }

    public function update(SaveCouponRequest $request, Coupon $coupon): RedirectResponse
    {
        return $this->save($request, $coupon);
    }

    private function save(SaveCouponRequest $request, Coupon $coupon): RedirectResponse
    {
        $data = $request->validated();
        foreach (['starts_at', 'ends_at'] as $field) {
            $data[$field] = CarbonImmutable::parse($data[$field], Coupon::LOCAL_TIMEZONE)->utc();
        }
        foreach (['max_discount_vnd', 'total_limit', 'per_user_limit'] as $field) {
            $data[$field] ??= null;
        }

        try {
            $coupon->fill($data)->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'code' => __('validation.unique', ['attribute' => 'mã giảm giá']),
            ]);
        }

        return to_route('admin.coupons.index')->with('status', 'Đã lưu mã giảm giá.');
    }
}
