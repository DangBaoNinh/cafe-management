<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OperationsInvoices
{
    /** @return LengthAwarePaginator<int, \stdClass> */
    public function listing(bool $history, ?CarbonImmutable $from, ?CarbonImmutable $to, string $source): LengthAwarePaginator
    {
        $orderDate = $history ? 'payments.paid_at' : 'orders.created_at';
        $orders = DB::table('payments')->join('orders', 'orders.id', '=', 'payments.order_id')
            ->selectRaw("'order' AS source, orders.id, orders.order_number AS reference, orders.recipient_name AS label,
                orders.total_vnd AS amount_vnd, payments.status AS payment_status, payments.method, payments.collection_method, $orderDate AS event_at, orders.order_number AS display_number")
            ->when($history, fn ($query) => $query->whereNotNull('payments.paid_at'),
                fn ($query) => $query->where('payments.status', 'pending')->whereNotIn('orders.status', ['cancelled', 'rejected']));
        $tableDate = $history ? 'table_sessions.paid_at' : 'table_sessions.billed_at';
        $tables = DB::table('table_sessions')->join('reservation_seats', 'reservation_seats.id', '=', 'table_sessions.reservation_seat_id')
            ->whereNotNull('table_sessions.billed_at')
            ->selectRaw("'table' AS source, table_sessions.id, table_sessions.reference, reservation_seats.code AS label,
                table_sessions.total_vnd AS amount_vnd, CASE WHEN table_sessions.paid_at IS NULL THEN 'pending' ELSE 'paid' END AS payment_status,
                'counter' AS method, NULL AS collection_method, $tableDate AS event_at, NULL AS display_number")
            ->when($history, fn ($query) => $query->whereNotNull('table_sessions.paid_at'),
                fn ($query) => $query->whereNull('table_sessions.paid_at')->where('table_sessions.status', 'closing'));

        $date = $history ? 'table_invoices.paid_at' : 'table_invoices.created_at';
        $prepaid = DB::table('table_invoices')->join('table_sessions', 'table_sessions.id', '=', 'table_invoices.table_session_id')
            ->join('reservation_seats', 'reservation_seats.id', '=', 'table_sessions.reservation_seat_id')
            ->selectRaw("'prepaid' AS source, table_invoices.id, table_invoices.reference, reservation_seats.code AS label,
                table_invoices.total_vnd AS amount_vnd, CASE WHEN table_invoices.status = 'paid' THEN 'paid' ELSE 'pending' END AS payment_status,
                'counter' AS method, table_invoices.method AS collection_method, $date AS event_at, table_invoices.number AS display_number")
            ->when($history, fn ($q) => $q->whereNotNull('table_invoices.paid_at'), fn ($q) => $q->where('table_invoices.status', 'draft'));
        $query = DB::query()->fromSub($orders->unionAll($tables)->unionAll($prepaid), 'invoices');
        if ($source === 'table') {
            $query->whereIn('source', ['table', 'prepaid']);
        } elseif ($source !== 'all') {
            $query->where('source', $source);
        }
        if ($from !== null && $to !== null) {
            $query->where('event_at', '>=', $from->utc())->where('event_at', '<', $to->addDay()->utc());
        }

        return $query->orderByDesc('event_at')->orderBy('source')->orderByDesc('id')->paginate(15)->withQueryString();
    }
}
