<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AdminOrders
{
    public function query(bool $withDeposits = false): Builder
    {
        $online = DB::table('orders')->leftJoin('payments', 'payments.order_id', '=', 'orders.id')
            ->selectRaw("'online' AS source, orders.id, orders.order_number AS number, orders.recipient_name AS label,
                orders.fulfillment_type AS fulfillment, orders.status, orders.total_vnd, orders.created_at,
                payments.status AS payment_status, payments.method, payments.collection_method,
                CASE WHEN orders.status IN ('cancelled','rejected') THEN 'cancelled' WHEN payments.status = 'paid' THEN 'paid' ELSE 'pending' END AS display_status, orders.order_number AS document_reference");
        $counter = DB::table('table_invoices')->join('table_sessions', 'table_sessions.id', '=', 'table_invoices.table_session_id')
            ->join('reservation_seats', 'reservation_seats.id', '=', 'table_sessions.reservation_seat_id')
            ->selectRaw("'counter' AS source, table_invoices.id, table_invoices.number, reservation_seats.code AS label,
                'table' AS fulfillment, table_invoices.order_status AS status, table_invoices.total_vnd, table_invoices.created_at,
                table_invoices.status AS payment_status, table_invoices.method, table_invoices.method AS collection_method,
                CASE WHEN table_invoices.order_status = 'cancelled' OR table_invoices.status = 'voided' THEN 'cancelled' WHEN table_invoices.status = 'paid' THEN 'paid' ELSE 'pending' END AS display_status, table_invoices.reference AS document_reference");
        $legacy = DB::table('table_sessions')->join('reservation_seats', 'reservation_seats.id', '=', 'table_sessions.reservation_seat_id')
            ->where('table_sessions.billing_mode', 'legacy')->whereNotNull('table_sessions.billed_at')
            ->selectRaw("'legacy' AS source, table_sessions.id, table_sessions.reference AS number, reservation_seats.code AS label,
                'table' AS fulfillment, CASE WHEN table_sessions.paid_at IS NULL THEN 'pending_payment' ELSE 'completed' END AS status,
                table_sessions.total_vnd, table_sessions.billed_at AS created_at,
                CASE WHEN table_sessions.paid_at IS NULL THEN 'draft' ELSE 'paid' END AS payment_status,
                'counter' AS method, NULL AS collection_method,
                CASE WHEN table_sessions.paid_at IS NULL THEN 'pending' ELSE 'paid' END AS display_status, table_sessions.reference AS document_reference");

        $union = $online->unionAll($counter)->unionAll($legacy);
        if ($withDeposits) {
            $deposits = DB::table('reservation_deposits')->join('reservations', 'reservations.id', '=', 'reservation_deposits.reservation_id')->join('reservation_seats', 'reservation_seats.id', '=', 'reservations.reservation_seat_id')
                ->selectRaw("'deposit' AS source, reservation_deposits.id, reservation_deposits.number, reservation_seats.code AS label,
                    'table' AS fulfillment, reservation_deposits.status, reservation_deposits.amount_vnd AS total_vnd, reservation_deposits.created_at,
                    CASE WHEN reservation_deposits.status IN ('refund_pending', 'refunded') THEN reservation_deposits.status WHEN reservation_deposits.paid_at IS NULL THEN 'pending' ELSE 'paid' END AS payment_status,
                    'bank_transfer' AS method, 'bank_transfer' AS collection_method,
                    CASE WHEN reservation_deposits.status = 'voided' THEN 'cancelled' WHEN reservation_deposits.paid_at IS NULL THEN 'pending' ELSE 'paid' END AS display_status, reservation_deposits.reference AS document_reference");
            $union->unionAll($deposits);
        }

        return DB::query()->fromSub($union, 'admin_orders');
    }

    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, \stdClass>
     */
    public function listing(array $filters, bool $withDeposits = false): LengthAwarePaginator
    {
        $query = $this->query($withDeposits);
        foreach (['number', 'status', 'display_status', 'source'] as $field) {
            if (isset($filters[$field])) {
                $field === 'number' ? $query->whereLike($field, $filters[$field].'%') : $query->where($field, $filters[$field]);
            }
        }
        if (isset($filters['method'])) {
            $method = $filters['method'];
            $query->where(function (Builder $q) use ($method): void {
                if (in_array($method, ['cash', 'bank_transfer'], true)) {
                    $q->where('collection_method', $method)->orWhere(function (Builder $q) use ($method): void {
                        $q->whereNull('collection_method')->where('method', $method);
                    });
                } else {
                    $q->where('method', $method);
                }
            });
        }
        foreach (['from' => '>=', 'to' => '<'] as $field => $operator) {
            if (isset($filters[$field])) {
                $date = CarbonImmutable::parse($filters[$field], RevenueReport::TIMEZONE);
                $query->where('created_at', $operator, ($field === 'to' ? $date->addDay() : $date)->utc());
            }
        }

        return $query->orderByDesc('created_at')->orderBy('source')->orderByDesc('id')->paginate(15)->appends($filters);
    }
}
