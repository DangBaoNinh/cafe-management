<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CancelOrderRequest;
use App\Http\Requests\Admin\FilterOrdersRequest;
use App\Models\Order;
use App\Models\TableInvoice;
use App\Models\User;
use App\Services\AdminOrders;
use App\Services\BankTransferQr;
use App\Services\CancelAdminOrder;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;

class OrderController extends Controller
{
    public function index(FilterOrdersRequest $request, AdminOrders $listing): View
    {
        $filters = array_filter($request->validated(), fn ($value) => $value !== null);

        return view('admin.orders.index', ['orders' => $listing->listing($filters, false), 'filters' => $filters, 'pendingDeposits' => 0]);
    }

    public function show(string $number): View
    {
        $invoice = TableInvoice::query()->where('number', $number)->with(['tableSession.seat', 'round.items', 'round.events.actor', 'fees', 'revisions.actor'])->first();
        if ($invoice !== null) {
            $qrImage = $invoice->bank_details === null || $invoice->amount_due_vnd === 0 ? null : app(BankTransferQr::class)->forSnapshot($invoice->bank_details, $invoice->amount_due_vnd, $invoice->number);

            return view('admin.orders.counter', ['invoice' => $invoice, 'qrImage' => $qrImage, 'customer' => false]);
        }
        $order = Order::query()->where('order_number', $number)->with([
            'demoShipment:id,order_id',
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
