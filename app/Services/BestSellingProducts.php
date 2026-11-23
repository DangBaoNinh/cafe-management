<?php

namespace App\Services;

use App\Enums\TableRoundStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class BestSellingProducts
{
    /**
     * @param  Builder<Product>  $catalog
     * @return Collection<int, Product>
     */
    public function topFive(Builder $catalog): Collection
    {
        $today = now(RevenueReport::TIMEZONE)->startOfDay();
        $from = $today->subDays(29)->utc();
        $until = $today->addDay()->utc();
        $orders = Order::paidCompletionsBetween($from, $until)->select('orders.id');

        $orderCups = OrderItem::query()
            ->join('product_sizes', 'product_sizes.id', '=', 'order_items.product_size_id')
            ->whereIn('order_items.order_id', $orders)
            ->select('product_sizes.product_id', 'order_items.quantity')->toBase();

        $tableCups = DB::table('table_round_items')
            ->join('product_sizes', 'product_sizes.id', '=', 'table_round_items.product_size_id')
            ->join('table_rounds', 'table_rounds.id', '=', 'table_round_items.table_round_id')
            ->join('table_sessions', 'table_sessions.id', '=', 'table_rounds.table_session_id')
            ->leftJoin('table_invoices', 'table_invoices.table_round_id', '=', 'table_rounds.id')
            ->where(function ($query) use ($from, $until): void {
                $query->where(fn ($legacy) => $legacy->where('table_sessions.billing_mode', 'legacy')->where('table_rounds.status', TableRoundStatus::Served->value)->where('table_sessions.paid_at', '>=', $from)->where('table_sessions.paid_at', '<', $until))
                    ->orWhere(fn ($prepaid) => $prepaid->where('table_invoices.status', 'paid')->where('table_invoices.order_status', 'completed')->where('table_invoices.paid_at', '>=', $from)->where('table_invoices.paid_at', '<', $until));
            })
            ->select('product_sizes.product_id', 'table_round_items.quantity');

        $sales = DB::query()->fromSub($orderCups->unionAll($tableCups), 'paid_cups')
            ->select('product_id')->selectRaw('SUM(quantity) AS cups_sold')->groupBy('product_id');

        return $catalog
            ->joinSub($sales, 'product_sales', 'product_sales.product_id', '=', 'products.id')
            ->orderByDesc('product_sales.cups_sold')->orderBy('products.id')
            ->limit(5)->get();
    }
}
