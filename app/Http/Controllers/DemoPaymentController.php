<?php

namespace App\Http\Controllers;

use App\Models\DemoPaymentSession;
use App\Models\User;
use App\Services\DemoPaymentGateway;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class DemoPaymentController extends Controller
{
    public function start(Request $request, string $number, DemoPaymentGateway $gateway): RedirectResponse
    {
        /** @var User $customer */
        $customer = $request->user();
        $session = $gateway->start($customer, $number);

        return to_route('orders.demo-payment.show', [$number, $session->reference], 303);
    }

    public function show(Request $request, string $number, string $reference): View
    {
        /** @var User $customer */
        $customer = $request->user();
        $order = $customer->orders()->where('order_number', $number)->firstOrFail();
        $payment = $order->payment()->firstOrFail();
        $session = $payment->demoSessions()->where('reference', $reference)->firstOrFail();

        return view('customer.orders.demo-payment', [
            'order' => $order,
            'session' => $session,
            'canPay' => DemoPaymentGateway::canPay($order, $payment) && $session->result === null,
        ]);
    }

    public function simulate(Request $request, string $number, string $reference, DemoPaymentGateway $gateway): RedirectResponse
    {
        $action = $request->validate(['scenario' => ['required', Rule::in(['success', 'failure', 'no-callback'])]])['scenario'];
        /** @var User $customer */
        $customer = $request->user();
        $gateway->simulate(
            $customer,
            $number,
            $reference,
            $action === 'failure' ? DemoPaymentSession::FAILED : DemoPaymentSession::SUCCEEDED,
            $action !== 'no-callback',
        );

        $message = match ($action) {
            'failure' => 'Giao dịch mô phỏng thất bại. Bạn có thể thử lại.',
            'no-callback' => 'Cổng mô phỏng đã ghi giao dịch. Đơn đang chờ quản trị viên đối chiếu.',
            default => 'Đã xử lý thanh toán mô phỏng.',
        };

        return to_route('orders.show', $number, 303)->with('status', $message);
    }

    public function callback(Request $request, DemoPaymentGateway $gateway): Response
    {
        $gateway->receive($request->getContent(), $request->header('X-Demo-Signature'));

        return response()->noContent();
    }

    public function reconcile(Request $request, string $number, DemoPaymentGateway $gateway): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();
        $applied = $gateway->reconcile($admin, $number);

        return to_route('operations.payments.show', $number, 303)->with('status', $applied
            ? 'Đã đối chiếu giao dịch mô phỏng.'
            : 'Cổng mô phỏng chưa ghi nhận giao dịch thành công.');
    }
}
