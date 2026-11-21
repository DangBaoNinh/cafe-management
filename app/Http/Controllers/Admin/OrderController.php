<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CancelOrderRequest;
use App\Http\Requests\Admin\FilterOrdersRequest;
use App\Models\Order;
use App\Models\User;
use App\Services\CancelAdminOrder;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Date;

class OrderController extends Controller
{
    private const LOCAL_TIMEZONE = 'Asia/Ho_Chi_Minh';

    public function index(FilterOrdersRequest $request): View
    {
        $filters = array_filter($request->validated(), fn ($value) => $value !== null);
        $orders = Order::query()
            ->select(['id', 'order_number', 'recipient_name', 'fulfillment_type', 'status', 'total_vnd', 'created_at'])
            ->with('payment:id,order_id,method,status')
            ->when(isset($filters['number']), fn (Builder $orders) => $orders->whereLike('order_number', $filters['number'].'%'))
            ->when(isset($filters['status']), fn (Builder $orders) => $orders->where('status', $filters['status']))
            ->when(isset($filters['method']), fn (Builder $orders) => $orders->whereHas(
                'payment',
                fn (Builder $payments) => $payments->where('method', $filters['method']),
            ))
            ->when(isset($filters['from']), fn (Builder $orders) => $orders->where(
                'created_at', '>=', Date::parse($filters['from'], self::LOCAL_TIMEZONE)->utc(),
            ))
            ->when(isset($filters['to']), fn (Builder $orders) => $orders->where(
                'created_at', '<', Date::parse($filters['to'], self::LOCAL_TIMEZONE)->addDay()->utc(),
            ))
            ->latest()->latest('id')->paginate(15)->appends($filters);

        return view('admin.orders.index', ['orders' => $orders, 'filters' => $filters]);
    }

    public function show(string $number): View
    {
        $order = Order::query()->where('order_number', $number)->with([
            'items' => fn (HasMany $items) => $items->orderBy('id'),
            'items.toppings' => fn (HasMany $toppings) => $toppings->orderBy('id'),
            'statusHistories' => fn (HasMany $histories) => $histories->with('actor:id,name')->oldest()->orderBy('id'),
            'payment.statusHistories' => fn (HasMany $histories) => $histories->with('actor:id,name')->oldest()->orderBy('id'),
        ])->firstOrFail();

        return view('admin.orders.show', ['order' => $order]);
    }

    public function cancel(CancelOrderRequest $request, string $number, CancelAdminOrder $cancellation): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();
        $cancellation->cancel($admin, $number, OrderStatus::from($request->validated('expected_status')), $request->validated('reason'));

        return to_route('admin.orders.show', $number, 303)->with('status', 'Đã hủy đơn hàng.');
    }
}
