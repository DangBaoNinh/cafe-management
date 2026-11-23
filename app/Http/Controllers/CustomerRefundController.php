<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\CustomerRefundRequest;
use App\Models\Order;
use App\Models\ReservationDeposit;
use App\Services\RefundHistory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerRefundController extends Controller
{
    public function index(RefundHistory $history): View
    {
        return view('operations.refunds', ['refunds' => $history->listing()]);
    }

    public function complete(Request $request, CustomerRefundRequest $refund): RedirectResponse
    {
        $request->validate(['confirm' => ['accepted'], 'refund_reference' => ['required', 'string', 'max:128']]);
        DB::transaction(function () use ($request, $refund): void {
            // Match order cancellation's order -> payment lock order.
            $order = $refund->order_id ? Order::query()->lockForUpdate()->findOrFail($refund->order_id) : null;
            $payment = $order?->payment()->lockForUpdate()->firstOrFail();
            $deposit = $refund->reservation_deposit_id ? ReservationDeposit::query()->lockForUpdate()->findOrFail($refund->reservation_deposit_id) : null;
            $row = CustomerRefundRequest::query()->lockForUpdate()->findOrFail($refund->id);
            if ($row->status !== 'pending') {
                return;
            }
            if ($payment !== null) {
                abort_unless(in_array($payment->status, [PaymentStatus::RefundPending, PaymentStatus::Refunded], true), 409);
                if ($payment->status === PaymentStatus::RefundPending) {
                    $payment->forceFill(['status' => PaymentStatus::Refunded, 'refunded_at' => now(), 'refund_reference' => $request->string('refund_reference')->trim()->toString()])->save();
                    $payment->statusHistories()->create(['actor_id' => $request->user()->id, 'from_status' => 'refund_pending', 'to_status' => 'refunded', 'reason' => 'Đã hoàn tiền thủ công theo yêu cầu #'.$row->id]);
                }
            }
            if ($deposit !== null) {
                abort_unless($deposit->status === 'refund_pending' && $deposit->amount_vnd === $row->amount_vnd, 409);
                $deposit->update(['status' => 'refunded']);
            }
            $row->update(['status' => 'completed', 'completed_at' => now(), 'completed_by' => $request->user()->id, 'refund_reference' => $request->string('refund_reference')->trim()->toString()]);
        }, 5);

        return to_route('operations.refunds.index')->with('status', 'Đã ghi nhận hoàn tiền thủ công.');
    }
}
