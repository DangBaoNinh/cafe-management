<?php

namespace App\Http\Controllers;

use App\Enums\CollectionMethod;
use App\Enums\TableSessionStatus;
use App\Http\Requests\SaveTableInvoiceRequest;
use App\Models\Product;
use App\Models\TableInvoice;
use App\Models\TableRound;
use App\Models\TableSession;
use App\Models\User;
use App\Services\BankTransferQr;
use App\Services\ReservationDeposits;
use App\Services\TableInvoices;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class TablePosController extends Controller
{
    public function show(Request $request, TableSession $tableSession, TableInvoices $invoices, BankTransferQr $qr): Response
    {
        abort_unless($tableSession->billing_mode === 'prepaid', 409, 'Phiên cũ tiếp tục xử lý theo hóa đơn hiện có.');
        $request->validate(['round' => ['nullable', 'integer', 'min:1']]);
        $round = $request->filled('round') ? $tableSession->rounds()->with('invoice')->findOrFail($request->integer('round')) : null;
        if ($round?->invoice !== null && $round->invoice->status !== 'draft') {
            return response()->view('operations.invoices.prepaid', $this->receipt($round->invoice, $qr))->header('Cache-Control', 'private, no-store');
        }
        abort_unless($tableSession->status === TableSessionStatus::Open || $round?->invoice !== null, 409, 'Phiên đã đóng.');

        return $this->screen($request, $tableSession, $invoices, $qr, $round);
    }

    public function store(SaveTableInvoiceRequest $request, TableSession $tableSession, TableInvoices $invoices): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $invoice = $invoices->save($tableSession, $request->selections(), $request->submissionKey(),
            $request->filled('round_id') ? $request->integer('round_id') : null, $request->integer('revision'),
            CollectionMethod::from($request->validated('method')), $request->integer('accepted_total_vnd'),
            $request->boolean('changes_confirmed'), $request->validated('intent') === 'pay', $actor, $request->boolean('deposit_refund_confirmed'));

        if ($invoice->status !== 'paid') {
            return to_route('operations.invoices.prepaid.show', $invoice, 303)->with('status', 'Đã lưu tạm tính. Kiểm tra phiếu và bấm hoàn tất thanh toán.');
        }

        return to_route('operations.invoices.prepaid.show', $invoice, 303)->with('status', 'Đã hoàn tất thanh toán và xuất hóa đơn.');
    }

    public function screen(Request $request, TableSession $tableSession, TableInvoices $invoices, BankTransferQr $qr, ?TableRound $round = null, bool $customer = false): Response
    {
        $tableSession->load('seat');
        $products = Product::query()->visibleInCatalog()->with(['category', 'activeSizes', 'activeToppings'])->orderBy('name')->get();
        $invoice = $round?->invoice;
        $fees = $invoices->unassignedFees($tableSession);
        $config = [
            'products' => $products->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'category_id' => $p->category_id,
                'sizes' => $p->activeSizes->map(fn ($s) => ['id' => $s->id, 'label' => $s->label, 'price' => $s->price_vnd])->values(),
                'toppings' => $p->activeToppings->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'price' => $t->price_vnd])->values(),
                'sugar' => $p->allows_sugar, 'ice' => $p->allows_ice])->values(),
            'lines' => $round ? $invoices->selectionsFor($round) : [],
            'baseline' => $invoice->selections ?? ($round ? $invoices->selectionsFor($round) : []),
            'original' => $invoice->initial_items ?? ($round?->items->toArray() ?? []), 'saved' => $round !== null,
            'method' => $invoice?->method->value ?? 'cash', 'fees' => $fees['total'] + (int) ($invoice?->fees()->where('status', 'pending')->sum('amount_vnd') ?? 0),
            'originalTotal' => $invoice->total_vnd ?? ($round ? $round->total_vnd + $fees['total'] : null), 'customer' => $customer,
            'deposit' => app(ReservationDeposits::class)->available($tableSession, $invoice),
        ];
        $submissionKey = $round->submission_key ?? (string) Str::uuid();
        $bankAvailable = $qr->details() !== null || $invoice?->bank_details !== null;
        $action = $customer ? route('table-orders.invoice.store', $tableSession->seat->code) : route('operations.table-sessions.pos.store', $tableSession);

        return response()->view($customer ? 'table-orders.pos' : 'operations.table-sessions.pos', compact('tableSession', 'products', 'invoice', 'round', 'config', 'submissionKey', 'bankAvailable', 'action', 'customer', 'fees'))
            ->header('Cache-Control', 'private, no-store');
    }

    /** @return array<string, mixed> */
    public function receipt(TableInvoice $invoice, BankTransferQr $qr, bool $customer = false): array
    {
        $invoice->load(['tableSession.seat', 'round.items', 'fees']);

        return ['invoice' => $invoice, 'customer' => $customer,
            'qrImage' => $invoice->amount_due_vnd > 0 && $invoice->method === CollectionMethod::BankTransfer && $invoice->bank_details !== null
                ? $qr->image($invoice->bank_details, $invoice->amount_due_vnd, $invoice->number) : null];
    }
}
