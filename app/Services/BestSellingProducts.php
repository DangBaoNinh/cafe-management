<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class BestSellingProducts
{
    /**
     * @param  Builder<Product>  $catalog
     * @return Collection<int, Product>
     */
    public function topFive(Builder $catalog): Collection
    {
        $today = now(RevenueReport::TIMEZONE)->startOfDay();
        $orders = Order::paidCompletionsBetween($today->subDays(29)->utc(), $today->addDay()->utc())->select('orders.id');

        $sales = OrderItem::query()
            ->join('product_sizes', 'product_sizes.id', '=', 'order_items.product_size_id')
            ->whereIn('order_items.order_id', $orders)
            ->select('product_sizes.product_id')
            ->selectRaw('SUM(order_items.quantity) AS cups_sold')
            ->groupBy('product_sizes.product_id');

        return $catalog
            ->joinSub($sales, 'product_sales', 'product_sales.product_id', '=', 'products.id')
            ->orderByDesc('product_sales.cups_sold')->orderBy('products.id')
            ->limit(5)->get();
    }
}
