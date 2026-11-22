<?php

namespace App\Http\Controllers;

use App\Enums\TableRoundStatus;
use App\Http\Requests\FilterInvoicesRequest;
use App\Models\Order;
use App\Models\TableSession;
use App\Services\OperationsInvoices;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;

class OperationsInvoiceController extends Controller
{
    public function index(FilterInvoicesRequest $request, OperationsInvoices $invoices): Response
    {
        $history = $request->routeIs('operations.invoices.history');
        $data = $request->validated();
        $today = CarbonImmutable::now('Asia/Ho_Chi_Minh')->startOfDay();
        $from = isset($data['from']) ? CarbonImmutable::parse($data['from'], 'Asia/Ho_Chi_Minh')->startOfDay() : ($history ? $today->subDays(6) : null);
        $to = isset($data['to']) ? CarbonImmutable::parse($data['to'], 'Asia/Ho_Chi_Minh')->startOfDay() : ($history ? $today : null);
        $source = $data['source'] ?? 'all';
        $bills = $invoices->listing($history, $from, $to, $source);

        return response()->view('operations.invoices.index', compact('history', 'from', 'to', 'source', 'bills'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function order(string $number): Response
    {
        $order = Order::query()->with(['items.toppings', 'payment', 'scheduledPickup.fees'])->where('order_number', $number)->firstOrFail();
        abort_if($order->payment === null, 404);

        return response()->view('operations.invoices.order', compact('order'))->header('Cache-Control', 'private, no-store');
    }

    public function table(TableSession $tableSession): Response
    {
        abort_if($tableSession->billed_at === null, 404);
        $tableSession->load(['seat', 'fees']);
        $rounds = $tableSession->rounds()->where('status', TableRoundStatus::Served)->with('items')->orderBy('id')->get();

        return response()->view('operations.invoices.table', compact('tableSession', 'rounds'))->header('Cache-Control', 'private, no-store');
    }
}
