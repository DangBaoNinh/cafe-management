<?php

namespace App\Http\Controllers;

use App\Enums\DrinkLevel;
use App\Models\Product;
use App\Models\User;
use App\Services\ShoppingCart;
use App\Services\StorefrontChat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StorefrontChatController extends Controller
{
    public function __invoke(Request $request, StorefrontChat $chat): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:500'],
            'history' => ['sometimes', 'array', 'list', 'max:8'],
            'history.*' => ['required', 'array:role,text'],
            'history.*.role' => ['required', 'in:user,model'],
            'history.*.text' => ['required', 'string', 'max:1200'],
        ]);

        return response()->json($chat->reply($data['message'], $data['history'] ?? []));
    }

    public function addToCart(Request $request, ShoppingCart $cart): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'min:1'],
            'size_id' => ['required', 'integer', 'min:1'],
        ]);
        $product = Product::visibleInCatalog()->whereKey($data['product_id'])->firstOrFail();
        $cart->add($user, $product->id, [
            'size_id' => $data['size_id'],
            'sugar_level' => $product->allows_sugar ? DrinkLevel::Standard->value : null,
            'ice_level' => $product->allows_ice ? DrinkLevel::Standard->value : null,
            'topping_ids' => [],
        ], 1);

        return response()->json(['message' => 'Đã thêm 1 món vào giỏ hàng.']);
    }
}
