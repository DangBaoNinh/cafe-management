<?php

namespace App\Services;

use App\Models\CustomerRefundRequest;
use App\Models\Payment;
use App\Models\ReservationDeposit;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use stdClass;

class RefundHistory
{
    /** @return LengthAwarePaginator<int, stdClass> */
    public function listing(): LengthAwarePaginator
    {
        $requests = DB::table('customer_refund_requests')->selectRaw("id, 'request' AS source, COALESCE(completed_at, created_at) AS event_at");
        $payments = DB::table('payments')->whereIn('status', ['refund_pending', 'refunded'])
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('customer_refund_requests')
                ->whereColumn('customer_refund_requests.order_id', 'payments.order_id'))
            ->selectRaw("id, 'payment' AS source, COALESCE(refunded_at, updated_at) AS event_at");
        $deposits = DB::table('reservation_deposits')->where('status', 'settled')
            ->selectRaw("id, 'deposit' AS source, COALESCE(settled_at, updated_at) AS event_at");
        $fees = DB::table('pickup_fees')->whereIn('status', ['refund_pending', 'refunded'])
            ->selectRaw("id, 'fee' AS source, COALESCE(refunded_at, updated_at) AS event_at");

        $history = DB::query()->fromSub($requests->unionAll($payments)->unionAll($deposits)->unionAll($fees), 'refund_history')
            ->orderByDesc('event_at')->orderBy('source')->orderByDesc('id')->paginate(20)->withQueryString();
        $ids = $history->getCollection()->groupBy('source')->map(fn ($rows) => $rows->pluck('id')->all());
        $requestRows = CustomerRefundRequest::with(['customer', 'order', 'deposit.reservation.seat', 'completedBy'])
            ->whereIn('id', $ids->get('request', []))->get()->keyBy('id');
        $paymentRows = Payment::with(['order', 'statusHistories' => fn ($query) => $query->where('to_status', 'refunded')->latest('id'), 'statusHistories.actor'])
            ->whereIn('id', $ids->get('payment', []))->get()->keyBy('id');
        $depositRows = ReservationDeposit::with(['reservation.seat', 'invoice', 'settledBy'])
            ->whereIn('id', $ids->get('deposit', []))->get()->keyBy('id');
        $feeRows = DB::table('pickup_fees')->leftJoin('reservations', 'reservations.id', '=', 'pickup_fees.reservation_id')
            ->leftJoin('scheduled_pickups', 'scheduled_pickups.id', '=', 'pickup_fees.scheduled_pickup_id')
            ->leftJoin('orders', 'orders.id', '=', 'scheduled_pickups.order_id')
            ->leftJoin('users', 'users.id', '=', 'pickup_fees.refund_actor_id')
            ->whereIn('pickup_fees.id', $ids->get('fee', []))
            ->select('pickup_fees.*', 'reservations.code AS reservation_code', 'reservations.contact_name',
                'scheduled_pickups.code AS pickup_code', 'orders.order_number', 'orders.recipient_name', 'users.name AS actor_name')->get()->keyBy('id');

        $history->setCollection($history->getCollection()->map(function (stdClass $event) use ($requestRows, $paymentRows, $depositRows, $feeRows): stdClass {
            $details = match ($event->source) {
                'request' => $this->request($requestRows->get($event->id)),
                'payment' => $this->payment($paymentRows->get($event->id)),
                'deposit' => $this->deposit($depositRows->get($event->id)),
                'fee' => $this->fee($feeRows->get($event->id)),
                default => throw new \LogicException('Unknown refund history source.'),
            };

            return (object) [...$details, 'source' => $event->source, 'id' => $event->id, 'event_at' => $event->event_at];
        }));

        return $history;
    }

    /** @return array<string, mixed> */
    private function request(CustomerRefundRequest $refund): array
    {
        $deposit = $refund->deposit;
        $order = $refund->order;

        return [
            'label' => $deposit !== null ? 'Hoàn cọc đặt bàn' : 'Hoàn tiền đơn hàng',
            'number' => $deposit->number ?? $order->order_number ?? 'Yêu cầu #'.$refund->id,
            'customer' => $deposit?->reservation->contact_name ?? $order->recipient_name ?? $refund->customer->name,
            'context' => $deposit !== null ? 'Bàn '.$deposit->reservation->seat->code.' · Phiếu '.$deposit->reservation->code : null,
            'document_url' => $deposit !== null ? route('operations.deposits.show', $deposit) : ($order !== null ? route('admin.invoices.show', $order->order_number) : null),
            'amount_vnd' => $refund->amount_vnd, 'credited_vnd' => 0, 'status' => $refund->status,
            'bank' => $refund->bank_snapshot, 'method' => 'Chuyển khoản', 'reference' => $refund->refund_reference,
            'actor' => $refund->is_demo ? 'Hệ thống (demo)' : $refund->completedBy?->name,
            'note' => $refund->is_demo ? 'Hoàn tự động demo về ngân hàng liên kết.' : 'Hoàn tiền vào tài khoản đã ghi nhận khi yêu cầu được tạo.',
            'complete_url' => $refund->status === 'pending' ? route('operations.refunds.complete', $refund) : null,
            'action_url' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function payment(Payment $payment): array
    {
        $lastRefund = $payment->statusHistories->first();

        return [
            'label' => 'Hoàn tiền đơn hàng', 'number' => $payment->order->order_number,
            'customer' => $payment->order->recipient_name, 'context' => null,
            'document_url' => route('admin.invoices.show', $payment->order->order_number),
            'amount_vnd' => $payment->amount_vnd, 'credited_vnd' => 0,
            'status' => $payment->status->value === 'refunded' ? 'completed' : 'pending',
            'bank' => null, 'method' => 'Hoàn thủ công', 'reference' => $payment->refund_reference,
            'actor' => $lastRefund?->actor?->name,
            'note' => $lastRefund->reason ?? 'Giao dịch hoàn tiền được ghi nhận tại chi tiết thanh toán.',
            'complete_url' => null, 'action_url' => route('operations.payments.show', $payment->order->order_number),
        ];
    }

    /** @return array<string, mixed> */
    private function deposit(ReservationDeposit $deposit): array
    {
        $hasCashRefund = $deposit->cash_refund_vnd > 0;

        return [
            'label' => $hasCashRefund ? 'Hoàn tiền cọc dư tại quầy' : 'Khấu trừ tiền cọc vào hóa đơn',
            'number' => $deposit->number, 'customer' => $deposit->reservation->contact_name,
            'context' => 'Bàn '.$deposit->reservation->seat->code.' · Phiếu '.$deposit->reservation->code,
            'document_url' => route('operations.deposits.show', $deposit),
            'amount_vnd' => $deposit->cash_refund_vnd, 'credited_vnd' => $deposit->credited_vnd,
            'status' => $hasCashRefund ? 'completed' : 'credited',
            'bank' => null, 'method' => $hasCashRefund ? 'Tiền mặt tại quầy' : 'Khấu trừ hóa đơn',
            'reference' => $deposit->invoice?->number, 'actor' => $deposit->settledBy?->name,
            'note' => $hasCashRefund ? 'Đã trừ tiền cọc vào hóa đơn và hoàn phần dư bằng tiền mặt.' : 'Cọc đã dùng thanh toán hóa đơn; không phát sinh chuyển trả tiền cho khách.',
            'complete_url' => null,
            'action_url' => $deposit->invoice !== null ? route('admin.invoices.show', $deposit->invoice->number) : null,
        ];
    }

    /** @return array<string, mixed> */
    private function fee(stdClass $fee): array
    {
        $reservation = $fee->reservation_code !== null;

        return [
            'label' => 'Hoàn phụ phí', 'number' => $fee->order_number ?? $fee->reservation_code,
            'customer' => $fee->contact_name ?? $fee->recipient_name ?? 'Chưa lưu tên khách',
            'context' => $fee->kind === 'reschedule' ? 'Phụ phí đổi lịch' : 'Phụ phí đến trễ',
            'document_url' => $reservation ? route('operations.reservations.show', $fee->reservation_code) : route('operations.pickups.show', $fee->pickup_code),
            'amount_vnd' => (int) $fee->amount_vnd, 'credited_vnd' => 0,
            'status' => $fee->status === 'refunded' ? 'completed' : 'pending',
            'bank' => null, 'method' => 'Hoàn thủ công', 'reference' => $fee->refund_reference,
            'actor' => $fee->actor_name, 'note' => 'Khoản phụ phí được hoàn riêng với tiền đơn hàng hoặc tiền cọc.',
            'complete_url' => null,
            'action_url' => $reservation ? route('operations.reservations.show', $fee->reservation_code) : route('operations.pickups.show', $fee->pickup_code),
        ];
    }
}
