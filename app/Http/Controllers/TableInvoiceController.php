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
        $input = $request->validate(['revision' => ['required', 'integer', 'min:1']]);
        /** @var User $actor */
        $actor = $request->user();
        $invoices->completeSaved($actor, $tableInvoice, (int) $input['revision']);

        return to_route('operations.invoices.prepaid.show', $tableInvoice, 303)->with('status', 'Đã hoàn tất thanh toán và xuất hóa đơn.');
    }

    public function show(TableInvoice $tableInvoice, TablePosController $pos, BankTransferQr $qr): Response
    {
        return response()->view('operations.invoices.prepaid', $pos->receipt($tableInvoice, $qr))->header('Cache-Control', 'private, no-store');
    }
}
