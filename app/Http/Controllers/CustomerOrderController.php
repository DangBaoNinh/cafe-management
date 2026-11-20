<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;

class CustomerOrderController extends Controller
{
    private const FILTERS = [
        'all' => 'Tất cả',
        'processing' => 'Đang xử lý',
        'finished' => 'Đã kết thúc',
    ];

    public function index(Request $request): View
    {
        $filter = $request->query('status', 'all');
        abort_unless(is_string($filter) && isset(self::FILTERS[$filter]), 422, 'Bộ lọc trạng thái đơn không hợp lệ.');

        $orders = $this->customer($request)->orders()
            ->select(['id', 'order_number', 'fulfillment_type', 'status', 'total_vnd', 'created_at'])
            ->with('payment:id,order_id,status')
            ->latest()->latest('id');

        match ($filter) {
            'processing' => $orders->whereNotIn('status', OrderStatus::finishedValues()),
            'finished' => $orders->whereIn('status', OrderStatus::finishedValues()),
            default => null,
        };

        return view('customer.orders.index', [
            'orders' => $orders->paginate(10)->appends($filter === 'all' ? [] : ['status' => $filter]),
            'filter' => $filter,
            'filters' => self::FILTERS,
        ]);
    }

    public function show(Request $request, string $number): View
    {
        $order = $this->customer($request)->orders()
            ->select([
                'id', 'order_number', 'coupon_code', 'recipient_name', 'phone', 'address_snapshot',
                'fulfillment_type', 'status', 'subtotal_vnd', 'discount_vnd', 'delivery_fee_vnd', 'total_vnd', 'created_at',
            ])
            ->with([
                'items' => fn (HasMany $items) => $items->orderBy('id'),
                'items.toppings' => fn (HasMany $toppings) => $toppings->orderBy('id'),
                'payment:id,order_id,method,status',
                'statusHistories' => fn (HasMany $histories) => $histories
                    ->select(['id', 'order_id', 'to_status', 'created_at'])
                    ->orderBy('created_at')->orderBy('id'),
            ])
            ->where('order_number', $number)
            ->firstOrFail();

        return view('customer.orders.show', ['order' => $order]);
    }

    private function customer(Request $request): User
    {
        /** @var User $customer */
        $customer = $request->user();

        return $customer;
    }
}
