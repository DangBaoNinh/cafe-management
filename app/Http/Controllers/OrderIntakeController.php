<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Http\Requests\OrderIntakeRequest;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderIntake;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;

class OrderIntakeController extends Controller
{
    public function index(): View
    {
        $orders = Order::query()
            ->where('status', OrderStatus::Pending)
            ->with('payment:id,order_id,method,status')
            ->oldest()->orderBy('id')
            ->paginate(15);

        return view('operations.orders.index', ['orders' => $orders]);
    }

    public function show(string $number): View
    {
        $order = Order::query()
            ->where('order_number', $number)
            ->with([
                'items' => fn (HasMany $items) => $items->orderBy('id'),
                'items.toppings' => fn (HasMany $toppings) => $toppings->orderBy('id'),
                'payment:id,order_id,method,status',
            ])->firstOrFail();

        return view('operations.orders.show', ['order' => $order]);
    }

    public function decide(OrderIntakeRequest $request, string $number, OrderIntake $intake): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $decision = OrderStatus::from($request->validated('decision'));
        $intake->decide($actor, $number, $decision, $request->validated('reason'));

        $message = $decision === OrderStatus::Confirmed ? 'Đã xác nhận đơn hàng.' : 'Đã từ chối đơn hàng.';

        return to_route('operations.orders.show', $number, 303)->with('status', $message);
    }
}
