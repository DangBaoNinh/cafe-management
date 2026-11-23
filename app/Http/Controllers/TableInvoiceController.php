<?php

namespace App\Http\Controllers;

use App\Models\TableInvoice;
use App\Models\User;
use App\Services\BankTransferQr;
use App\Services\TableInvoices;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TableInvoiceController extends Controller
{
    public function pay(Request $request, TableInvoice $tableInvoice, TableInvoices $invoices): RedirectResponse
    {
        $input = $request->validate(['revision' => ['required', 'integer', 'min:1'], 'deposit_refund_confirmed' => ['nullable', 'boolean']]);
        /** @var User $actor */
        $actor = $request->user();
        $invoices->completeSaved($actor, $tableInvoice, (int) $input['revision'], $request->boolean('deposit_refund_confirmed'));

        return to_route('operations.invoices.prepaid.show', $tableInvoice, 303)->with('status', 'Đã hoàn tất thanh toán và xuất hóa đơn.');
    }

    public function show(Request $request, TableInvoice $tableInvoice, TablePosController $pos, BankTransferQr $qr): Response|RedirectResponse
    {
        if ($request->user()?->can('access-admin')) {
            return to_route('admin.orders.show', $tableInvoice->number);
        }

        return response()->view('operations.invoices.prepaid', $pos->receipt($tableInvoice, $qr))->header('Cache-Control', 'private, no-store');
    }
}
