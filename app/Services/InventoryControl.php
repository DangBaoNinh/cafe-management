<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\ProductSize;
use App\Models\Topping;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryControl
{
    public function enabled(): bool
    {
        return (bool) DB::table('inventory_settings')->where('id', 1)->value('enabled');
    }

    // Let checkouts and preparation run in parallel while serializing the initial cutover.
    public function lockForOperation(): bool
    {
        return (bool) DB::table('inventory_settings')->where('id', 1)->sharedLock()->value('enabled');
    }

    public function enable(): void
    {
        DB::transaction(function (): void {
            if (DB::table('inventory_settings')->where('id', 1)->lockForUpdate()->value('enabled')) {
                return;
            }

            $issues = [];
            $uninitialized = Ingredient::query()->where('stock_version', 0)->orderBy('name')->limit(10)->pluck('name');
            if ($uninitialized->isNotEmpty()) {
                $issues[] = 'Chưa ghi tồn đầu kỳ cho: '.$uninitialized->implode(', ').'.';
            }
            if (! Ingredient::query()->exists()) {
                $issues[] = 'Hãy khai báo nguyên liệu và ghi tồn đầu kỳ.';
            }

            $sizes = ProductSize::query()->active()->whereHas('product', fn (Builder $product) => $product->visibleInCatalog())
                ->doesntHave('recipeIngredients')->with('product')->orderBy('id')->limit(10)->get();
            $toppings = Topping::query()->active()->whereHas('products', fn (Builder $product) => $product->visibleInCatalog())
                ->doesntHave('recipeIngredients')->orderBy('id')->limit(10)->pluck('name');
            if ($sizes->isNotEmpty() || $toppings->isNotEmpty()) {
                $options = $sizes->map(fn (ProductSize $size): string => $size->product->name.' · '.$size->label)
                    ->concat($toppings->map(fn (string $name): string => 'Topping '.$name))->implode(', ');
                $issues[] = 'Cần bổ sung công thức cho: '.$options.'.';
            }

            $openOrders = Order::query()->whereIn('status', [OrderStatus::Pending, OrderStatus::Confirmed])
                ->where(fn (Builder $orders) => $orders->whereNull('inventory_snapshot')->orWhereJsonLength('inventory_snapshot->missing', '>', 0));
            $openCount = $openOrders->count();
            if ($openCount > 0) {
                $numbers = $openOrders->orderBy('id')->limit(10)->pluck('order_number')->implode(', ');
                $issues[] = "Còn {$openCount} đơn chưa pha chế thiếu ảnh chụp công thức. Tra các mã sau trong Quản lý đơn hàng và xử lý trước khi bật: {$numbers}.";
            }
            if ($issues !== []) {
                throw ValidationException::withMessages(['inventory_control' => $issues])->redirectTo(route('admin.inventory.index'));
            }

            DB::table('inventory_settings')->where('id', 1)->update(['enabled' => true]);
        }, 5);
    }
}
