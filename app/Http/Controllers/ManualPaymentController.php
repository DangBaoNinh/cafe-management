<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Requests\ReconcileTransferRequest;
use App\Http\Requests\RecordRefundRequest;
use App\Models\Payment;
use App\Models\User;
use App\Services\ManualPayment;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;

class ManualPaymentController extends Controller
{
    public function index(): View
    {
        $payments = Payment::query()
            ->with('order:id,order_number,status,created_at')
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query
                    ->where('method', PaymentMethod::BankTransfer)
                    ->where('status', PaymentStatus::Pending)
                    ->whereHas('order', fn (Builder $orders) => $orders->whereIn('status', OrderStatus::transferReconciliationValues())))
                ->orWhere('status', PaymentStatus::RefundPending))
            ->oldest('updated_at')->orderBy('id')->paginate(15);

        return view('operations.payments.index', ['payments' => $payments]);
    }

    public function show(string $number): View
    {
        $payment = Payment::query()
            ->with('order:id,order_number,status,total_vnd')
            ->whereHas('order', fn (Builder $orders) => $orders->where('order_number', $number))
            ->firstOrFail();
        $canReconcile = $payment->method === PaymentMethod::BankTransfer
            && $payment->status === PaymentStatus::Pending
            && in_array($payment->order->status->value, OrderStatus::transferReconciliationValues(), true);

        return view('operations.payments.show', ['payment' => $payment, 'canReconcile' => $canReconcile]);
    }

    public function reconcile(ReconcileTransferRequest $request, string $number, ManualPayment $payments): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $payments->reconcileTransfer(
            $actor,
            $number,
            $request->integer('amount_vnd'),
            $request->validated('collection_reference'),
            $request->validated('note'),
        );

        return to_route('operations.payments.show', $number, 303)->with('status', 'Đã xác nhận thanh toán.');
    }

    public function refund(RecordRefundRequest $request, string $number, ManualPayment $payments): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $payments->recordRefund(
            $actor,
            $number,
            PaymentMethod::from($request->validated('method')),
            $request->validated('refund_reference'),
            $request->validated('note'),
        );

        return to_route('operations.payments.show', $number, 303)->with('status', 'Đã ghi nhận hoàn tiền.');
    }
}
