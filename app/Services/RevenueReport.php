<?php

namespace App\Services;

use App\Enums\OrderStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** @phpstan-type RevenueTotals array{orders: int, subtotal: int, discount: int, delivery: int, total: int} */
class RevenueReport
{
    public const TIMEZONE = 'Asia/Ho_Chi_Minh';

    private const ZERO_TOTALS = ['orders' => 0, 'subtotal' => 0, 'discount' => 0, 'delivery' => 0, 'total' => 0];

    private function receipts(): Builder
    {
        $online = DB::table('orders')->join('payments', 'payments.order_id', '=', 'orders.id')
            ->where('orders.status', 'completed')->where('payments.status', 'paid')
            ->selectRaw("orders.completed_at AS event_at, orders.subtotal_vnd AS subtotal, orders.discount_vnd AS discount,
                orders.delivery_fee_vnd AS delivery, 0 AS fees, orders.total_vnd AS total,
                COALESCE(payments.collection_method, CASE WHEN payments.method = 'cod' THEN 'unclassified' ELSE payments.method END) AS channel, 1 AS invoice_count, 1 AS channel_count");
        $counter = DB::table('table_invoices')->where('status', 'paid')->where('order_status', 'completed')
            ->selectRaw("paid_at AS event_at, subtotal_vnd AS subtotal, 0 AS discount, 0 AS delivery, fees_vnd AS fees,
                total_vnd - deposit_credit_vnd AS total, method AS channel, 1 AS invoice_count,
                CASE WHEN total_vnd > deposit_credit_vnd OR deposit_credit_vnd = 0 OR method = 'bank_transfer' THEN 1 ELSE 0 END AS channel_count");
        $credits = DB::table('table_invoices')->where('status', 'paid')->where('order_status', 'completed')->where('deposit_credit_vnd', '>', 0)
            ->selectRaw("paid_at AS event_at, 0 AS subtotal, 0 AS discount, 0 AS delivery, 0 AS fees, deposit_credit_vnd AS total,
                'bank_transfer' AS channel, 0 AS invoice_count, CASE WHEN method = 'bank_transfer' THEN 0 ELSE 1 END AS channel_count");
        $legacy = DB::table('table_sessions')->where('billing_mode', 'legacy')->whereNotNull('paid_at')
            ->selectRaw("paid_at AS event_at, subtotal_vnd AS subtotal, 0 AS discount, 0 AS delivery, fees_vnd AS fees, total_vnd AS total, 'unclassified' AS channel, 1 AS invoice_count, 1 AS channel_count");
        $forfeited = DB::table('reservation_deposits')->where('status', 'forfeited')
            ->selectRaw("settled_at AS event_at, amount_vnd AS subtotal, 0 AS discount, 0 AS delivery, 0 AS fees, amount_vnd AS total,
                'bank_transfer' AS channel, 1 AS invoice_count, 1 AS channel_count");

        return DB::query()->fromSub($online->unionAll($counter)->unionAll($credits)->unionAll($legacy)->unionAll($forfeited), 'receipts');
    }

    /** @return array<string, mixed> */
    public function summarize(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $start = $from->utc();
        $end = $to->addDay()->utc();
        $daySql = DB::getDriverName() === 'sqlite' ? 'DATE(event_at, ?)' : "DATE(CONVERT_TZ(event_at, '+00:00', ?))";
        $rows = $this->receipts()->where('event_at', '>=', $start)->where('event_at', '<', $end)
            ->selectRaw("$daySql AS local_day", [$from->format('P')])
            ->selectRaw('channel, SUM(invoice_count) AS orders, SUM(channel_count) AS channel_orders, SUM(subtotal) AS subtotal, SUM(discount) AS discount, SUM(delivery) AS delivery, SUM(fees) AS fees, SUM(total) AS total')
            ->groupBy('local_day', 'channel')->get();
        $totals = self::ZERO_TOTALS;
        $daily = $dailyFees = $dailyChannels = [];
        $channels = [];
        foreach (['cash', 'bank_transfer', 'demo_gateway', 'unclassified'] as $key) {
            $channels[$key] = ['orders' => 0, 'total' => 0];
        }
        for ($day = $from; $day <= $to; $day = $day->addDay()) {
            $date = $day->toDateString();
            $daily[$date] = self::ZERO_TOTALS;
            $dailyFees[$date] = 0;
            $dailyChannels[$date] = ['cash' => 0, 'bank_transfer' => 0, 'other' => 0];
        }
        foreach ($rows as $row) {
            $date = $row->local_day;
            foreach (array_keys(self::ZERO_TOTALS) as $metric) {
                $daily[$date][$metric] = ($daily[$date][$metric] ?? 0) + (int) $row->$metric;
                $totals[$metric] += (int) $row->$metric;
            }
            $dailyFees[$date] += (int) $row->fees;
            $channel = array_key_exists($row->channel, $channels) ? $row->channel : 'unclassified';
            $channels[$channel]['orders'] += (int) $row->channel_orders;
            $channels[$channel]['total'] += (int) $row->total;
            $dailyChannel = in_array($channel, ['cash', 'bank_transfer'], true) ? $channel : 'other';
            $dailyChannels[$date][$dailyChannel] = ($dailyChannels[$date][$dailyChannel] ?? 0) + (int) $row->total;
        }
        $feesTotal = array_sum($dailyFees);
        $counts = app(AdminOrders::class)->query()->where('source', 'online')->where('created_at', '>=', $start)->where('created_at', '<', $end)
            ->selectRaw('status, COUNT(*) AS orders')->groupBy('status')->pluck('orders', 'status');
        $statuses = [];
        foreach (OrderStatus::cases() as $status) {
            $statuses[$status->value] = (int) $counts->get($status->value, 0);
        }
        $counterStatuses = app(AdminOrders::class)->query()->where('source', '!=', 'online')->where('created_at', '>=', $start)->where('created_at', '<', $end)
            ->selectRaw('status, COUNT(*) AS orders')->groupBy('status')->pluck('orders', 'status');
        $displayStatuses = app(AdminOrders::class)->query()->where('created_at', '>=', $start)->where('created_at', '<', $end)
            ->selectRaw('display_status, COUNT(*) AS orders')->groupBy('display_status')->pluck('orders', 'display_status');

        return compact('totals', 'daily', 'statuses', 'counterStatuses', 'channels', 'dailyChannels', 'dailyFees', 'feesTotal', 'displayStatuses');
    }
}
