<?php

namespace App\Http\Controllers;

use App\Enums\CollectionMethod;
use App\Enums\FulfillmentType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Http\Requests\UpdateOrderHandoffRequest;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderHandoff;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderHandoffController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['view' => ['nullable', Rule::in(['active', 'completed'])]]);
        $completed = ($filters['view'] ?? 'active') === 'completed';
        $orders = Order::query()
            ->whereIn('status', $completed ? [OrderStatus::Completed->value] : [OrderStatus::Ready->value, OrderStatus::OutForDelivery->value])
            ->with(['payment:id,order_id,method,status', 'items:id,order_id,product_name,quantity'])
            ->when($completed, fn ($query) => $query->orderByDesc('completed_at')->orderByDesc('id'), fn ($query) => $query->oldest()->orderBy('id'))
            ->paginate(15)->withQueryString();

        return view('operations.orders.index', [
            'orders' => $orders,
            'title' => 'Bàn giao',
            'emptyMessage' => $completed ? 'Chưa có đơn đã hoàn tất bàn giao.' : 'Chưa có đơn cần bàn giao.',
            'handoffView' => $completed ? 'completed' : 'active',
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
            $request->filled('collection_method') ? CollectionMethod::from($request->validated('collection_method')) : null,
        );
        $message = $target === OrderStatus::OutForDelivery ? 'Đã bàn giao cho người giao hàng.' : 'Đã hoàn tất đơn hàng.';

        return to_route($target === OrderStatus::Completed ? 'operations.invoices.orders.show' : 'operations.orders.show', $number, 303)->with('status', $message);
    }
}
