<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Http\Requests\UpdateOrderPreparationRequest;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderPreparation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class OrderPreparationController extends Controller
{
    public function index(): View
    {
        $orders = Order::query()
            ->whereIn('status', [OrderStatus::Confirmed->value, OrderStatus::Preparing->value])
            ->with('payment:id,order_id,method,status')
            ->oldest()->orderBy('id')->paginate(15);

        return view('operations.orders.index', [
            'orders' => $orders,
            'title' => 'Pha chế',
            'emptyMessage' => 'Chưa có đơn cần pha chế.',
            'showOrderStatus' => true,
        ]);
    }

    public function update(UpdateOrderPreparationRequest $request, string $number, OrderPreparation $preparation): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $target = OrderStatus::from($request->validated('target_status'));
        $preparation->advance($actor, $number, OrderStatus::from($request->validated('expected_status')), $target);
        $message = $target === OrderStatus::Preparing ? 'Đã bắt đầu pha chế.' : 'Đã đánh dấu đơn sẵn sàng.';

        return to_route('operations.orders.show', $number, 303)->with('status', $message);
    }
}
