<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShoppingCart
{
    public function __construct(private DrinkPricing $pricing) {}

    /**
     * @return array{version: int, lines: list<array{item: CartItem, unitPrice: ?int, total: ?int}>, subtotal: int}
     */
    public function view(User $user): array
    {
        return DB::transaction(function () use ($user): array {
            // Read the version and its lines under the same lock used by writers.
            $cart = $user->cart()->sharedLock()->first();
            if ($cart === null) {
                return ['version' => 0, 'lines' => [], 'subtotal' => 0];
            }

            $lines = [];
            $subtotal = 0;
            $items = $cart->items()->with([
                'toppings', 'size.product.category', 'size.product.activeSizes', 'size.product.activeToppings',
            ])->orderBy('id')->get();

            foreach ($items as $item) {
                $unitPrice = null;

                try {
                    $unitPrice = $this->quote($item->size->product, $item->selection())['unit_price_vnd'];
                } catch (ValidationException) {
                    // Keep unavailable lines visible so the customer can remove them.
                }

                $total = $unitPrice === null ? null : $unitPrice * $item->quantity;
                $subtotal += $total ?? 0;
                $lines[] = ['item' => $item, 'unitPrice' => $unitPrice, 'total' => $total];
            }

            return ['version' => $cart->version, 'lines' => $lines, 'subtotal' => $subtotal];
        }, 3);
    }

    /** @param array<string, mixed> $selection */
    public function add(User $user, int $productId, array $selection, mixed $quantity): void
    {
        $quantity = $this->quantity($quantity);

        DB::transaction(function () use ($user, $productId, $selection, $quantity): void {
            // Serialize the first add even when the customer has no cart row yet.
            User::query()->lockForUpdate()->findOrFail($user->id);
            $cart = $user->cart()->lockForUpdate()->first();

            $product = Product::query()->find($productId);
            $quote = $this->quote($product, $selection);
            $hash = hash('sha256', json_encode([
                $quote['size_id'], $quote['sugar_level'], $quote['ice_level'], $quote['topping_ids'],
            ], JSON_THROW_ON_ERROR));

            $cart ??= $user->cart()->create();
            $item = $cart->items()->where('configuration_hash', $hash)->first();

            if ($item === null) {
                $item = $cart->items()->create([
                    'product_size_id' => $quote['size_id'],
                    'configuration_hash' => $hash,
                    'quantity' => $quantity,
                    'sugar_level' => $quote['sugar_level'],
                    'ice_level' => $quote['ice_level'],
                ]);
                $item->toppings()->attach($quote['topping_ids']);
            } else {
                $item->update(['quantity' => $this->quantity($item->quantity + $quantity)]);
            }

            $cart->increment('version');
        }, 3);
    }

    public function update(User $user, string $itemId, int $version, mixed $quantity): void
    {
        $quantity = $this->quantity($quantity);

        $this->change($user, $itemId, $version, function (CartItem $item) use ($quantity): void {
            $this->quote($item->size->product, $item->selection());
            $item->update(['quantity' => $quantity]);
        });
    }

    public function remove(User $user, string $itemId, int $version): void
    {
        $this->change($user, $itemId, $version, fn (CartItem $item) => $item->delete());
    }

    /** @param callable(CartItem): mixed $change */
    private function change(User $user, string $itemId, int $version, callable $change): void
    {
        DB::transaction(function () use ($user, $itemId, $version, $change): void {
            $cart = $user->cart()->lockForUpdate()->firstOrFail();

            if ($cart->version !== $version) {
                throw ValidationException::withMessages([
                    'version' => 'Giỏ hàng đã thay đổi. Vui lòng kiểm tra giỏ và thử lại.',
                ])->redirectTo(route('cart.index'));
            }

            $item = $cart->items()->findOrFail($itemId);
            $change($item);
            $cart->increment('version');
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $selection
     * @return array{size_id: int, sugar_level: ?string, ice_level: ?string, topping_ids: list<int>, unit_price_vnd: int}
     */
    private function quote(?Product $product, array $selection): array
    {
        if ($product === null || ! $product->is_active || ! $product->category->is_active) {
            throw ValidationException::withMessages(['product' => 'Món này hiện không còn bán.']);
        }

        return $this->pricing->quote($product->loadMissing(['activeSizes', 'activeToppings']), $selection);
    }

    private function quantity(mixed $quantity): int
    {
        $quantity = is_int($quantity) || is_string($quantity) ? $quantity : null;
        $validated = validator(['quantity' => $quantity], [
            'quantity' => ['required', 'integer', 'between:1,'.CartItem::MAX_QUANTITY],
        ], [], ['quantity' => 'số lượng'])->validate();

        return (int) $validated['quantity'];
    }
}
