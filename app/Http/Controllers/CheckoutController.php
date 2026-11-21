<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Models\User;
use App\Services\Checkout;
use App\Services\VietnamAddressCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    public function create(Request $request, Checkout $checkout, VietnamAddressCatalog $catalog): View|RedirectResponse
    {
        $customer = $this->customer($request);
        $cart = $customer->cart()->first();
        if ($cart === null || ! $cart->items()->exists()) {
            return to_route('cart.index')->with('status', 'Thêm món vào giỏ trước khi đặt hàng.');
        }

        return view('customer.checkout.form', [
            'customer' => $customer,
            'cartVersion' => $cart->version,
            'addresses' => $customer->addresses()->orderByDesc('is_default')->latest('id')->get(),
            'catalog' => $catalog,
            'bankAvailable' => $checkout->bankDetails() !== null,
        ]);
    }

    public function review(CheckoutRequest $request, Checkout $checkout): View
    {
        $details = $request->details();
        $quote = $checkout->preview($this->customer($request), $details);
        $request->session()->flashInput($details->fields());

        return view('customer.checkout.review', [
            'details' => $details,
            'quote' => $quote,
            'key' => Str::uuid()->toString(),
        ]);
    }

    public function store(CheckoutRequest $request, Checkout $checkout): RedirectResponse
    {
        $data = $request->validated();
        $order = $checkout->place(
            $this->customer($request),
            $request->details(),
            $data['checkout_key'],
            $data['accepted_total_vnd'],
            $data['quote_hash'],
        );

        return to_route('checkout.confirmation', $order->order_number, 303);
    }

    public function confirmation(Request $request, string $number): View
    {
        $order = $this->customer($request)->orders()->where('order_number', $number)->firstOrFail();

        return view('customer.checkout.confirmation', [
            'order' => $order,
            'payment' => $order->payment()->firstOrFail(),
            'pickup' => $order->scheduledPickup()->first(),
        ]);
    }

    private function customer(Request $request): User
    {
        /** @var User $customer */
        $customer = $request->user();

        return $customer;
    }
}
