<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-type RevenueTotals array{orders: int, subtotal: int, discount: int, delivery: int, total: int}
 */
class RevenueReport
{
    public const TIMEZONE = 'Asia/Ho_Chi_Minh';

    private const ZERO_TOTALS = ['orders' => 0, 'subtotal' => 0, 'discount' => 0, 'delivery' => 0, 'total' => 0];

    /** @return array{totals: RevenueTotals, daily: array<string, RevenueTotals>, statuses: array<string, int>} */
    public function summarize(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $start = $from->utc();
        $end = $to->addDay()->utc();
        $orders = Order::query();
        $driver = DB::connection($orders->getModel()->getConnectionName())->getDriverName();
        $daySql = $driver === 'sqlite'
            ? 'DATE(completed_at, ?)'
            : "DATE(CONVERT_TZ(completed_at, '+00:00', ?))";

        $rows = $orders->where('status', OrderStatus::Completed)
            ->where('completed_at', '>=', $start)->where('completed_at', '<', $end)
            ->whereHas('payment', fn (Builder $payment) => $payment->where('status', PaymentStatus::Paid))
            ->selectRaw("$daySql AS local_day", [$from->format('P')])
            ->selectRaw('COUNT(*) AS orders, SUM(subtotal_vnd) AS subtotal, SUM(discount_vnd) AS discount')
            ->selectRaw('SUM(delivery_fee_vnd) AS delivery, SUM(total_vnd) AS total')
            ->groupBy('local_day')->toBase()->get()->keyBy('local_day');

        $totals = self::ZERO_TOTALS;
        $daily = [];
        for ($day = $from; $day <= $to; $day = $day->addDay()) {
            $date = $day->toDateString();
            $row = $rows->get($date);
            $values = $row === null ? self::ZERO_TOTALS : [
                'orders' => (int) $row->orders,
                'subtotal' => (int) $row->subtotal,
                'discount' => (int) $row->discount,
                'delivery' => (int) $row->delivery,
                'total' => (int) $row->total,
            ];
            $daily[$date] = $values;
            foreach ($values as $metric => $value) {
                $totals[$metric] += $value;
            }
        }

        $statusValues = array_column(OrderStatus::cases(), 'value');
        $counts = Order::query()->whereIn('status', $statusValues)
            ->where('created_at', '>=', $start)->where('created_at', '<', $end)
            ->selectRaw('status, COUNT(*) AS orders')->groupBy('status')
            ->toBase()->pluck('orders', 'status');

        $statuses = [];
        foreach ($statusValues as $status) {
            $statuses[$status] = (int) $counts->get($status, 0);
        }

        return compact('totals', 'daily', 'statuses');
    }
}
