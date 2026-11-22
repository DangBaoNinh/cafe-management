<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use Illuminate\Contracts\View\View;

class OfferController extends Controller
{
    public function __invoke(): View
    {
        $now = now();

        $coupons = Coupon::query()
            ->where('is_active', true)
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>', $now)
            ->orderBy('ends_at')
            ->orderBy('id')
            ->paginate(12);

        return view('customer.offers.index', compact('coupons'));
    }
}
