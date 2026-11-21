<?php

namespace App\Http\Controllers;

use App\Enums\FulfillmentType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Http\Requests\UpdateOrderHandoffRequest;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderHandoff;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class OrderHandoffController extends Controller
{
    public function index(): View
    {
        $orders = Order::query()
            ->whereIn('status', [OrderStatus::Ready->value, OrderStatus::OutForDelivery->value])
            ->with('payment:id,order_id,status')
            ->oldest()->orderBy('id')->paginate(15);

        return view('operations.orders.index', [
            'orders' => $orders,
            'title' => 'Bàn giao',
            'emptyMessage' => 'Chưa có đơn cần bàn giao.',
            'showOrderStatus' => true,
        ]);
    }

    public function update(UpdateOrderHandoffRequest $request, string $number, OrderHandoff $handoff): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $target = OrderStatus::from($request->validated('target_status'));
        $handoff->advance(
            $actor,
            $number,
            OrderStatus::from($request->validated('expected_status')),
            $target,
            FulfillmentType::from($request->validated('fulfillment_type')),
            PaymentMethod::from($request->validated('method')),
            $request->validated('cod_amount_vnd') === null ? null : $request->integer('cod_amount_vnd'),
        );
        $message = $target === OrderStatus::OutForDelivery ? 'Đã bàn giao cho người giao hàng.' : 'Đã hoàn tất đơn hàng.';

        return to_route('operations.orders.show', $number, 303)->with('status', $message);
    }
}
