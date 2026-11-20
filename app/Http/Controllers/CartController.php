<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ShoppingCart;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index(Request $request, ShoppingCart $cart): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('customer.cart', $cart->view($user));
    }

    public function update(Request $request, ShoppingCart $cart, string $item): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $version = $this->version($request);
        $cart->update($user, $item, $version, $request->input('quantity'));

        return to_route('cart.index')->with('status', 'Đã cập nhật số lượng.');
    }

    public function destroy(Request $request, ShoppingCart $cart, string $item): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $cart->remove($user, $item, $this->version($request));

        return to_route('cart.index')->with('status', 'Đã xóa món khỏi giỏ.');
    }

    private function version(Request $request): int
    {
        $validated = $request->validate(['version' => ['required', 'integer', 'min:0']], [
            'version.*' => 'Phiên bản giỏ hàng không hợp lệ. Vui lòng tải lại giỏ.',
        ]);

        return (int) $validated['version'];
    }
}
