<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TableRoundStatus;
use App\Http\Controllers\Controller;
use App\Models\TableInvoice;
use App\Services\AdminCounterConfirmation;
use App\Services\PaidCounterOrderStatus;
use App\Services\TableInvoices;
use App\Services\TableRounds;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CounterOrderController extends Controller
{
    public function preview(Request $request, TableInvoice $invoice, AdminCounterConfirmation $confirmation): View
    {
        $data = $request->validate([
            'target' => ['required', Rule::in(['completed', 'cancelled'])],
            'revision' => ['required', 'integer', 'min:1'],
            'reason' => [Rule::requiredIf($invoice->status === 'paid'), 'required_if:target,cancelled', 'nullable', 'string', 'max:500'],
        ]);
        if (! in_array($invoice->status, ['draft', 'paid'], true) || $invoice->revision !== (int) $data['revision']
            || ($invoice->status === 'paid' && $invoice->order_status->value === $data['target'])) {
            throw ValidationException::withMessages(['revision' => 'Phiếu đã đổi trạng thái hoặc nội dung. Vui lòng tải lại đơn hàng.'])->status(409);
        }
        $token = $confirmation->issue($request->user(), $invoice, $data['target'], (int) $data['revision'], $data['reason'] ?? null);

        return view('admin.orders.confirm-status', ['invoice' => $invoice, 'target' => $data['target'], 'reason' => $data['reason'] ?? null, 'token' => $token, 'returnToInvoices' => $request->boolean('return_to_invoices')]);
    }

    public function update(Request $request, TableInvoice $invoice, TableInvoices $invoices, TableRounds $rounds, AdminCounterConfirmation $confirmation): RedirectResponse
    {
        try {
            return $this->applyUpdate($request, $invoice, $invoices, $rounds, $confirmation);
        } catch (ValidationException $exception) {
            // A failed confirmation must return to a GET page in the admin workspace.
            throw $exception->redirectTo(route($request->boolean('return_to_invoices') ? 'admin.invoices.show' : 'admin.orders.show', $invoice->number));
        }
    }

    private function applyUpdate(Request $request, TableInvoice $invoice, TableInvoices $invoices, TableRounds $rounds, AdminCounterConfirmation $confirmation): RedirectResponse
    {
        $input = $request->validate(['confirmation_token' => ['required', 'string', 'max:16384']]);
        $data = $confirmation->read($request->user(), $invoice, $input['confirmation_token']);
        // The same settlement/cancellation services are used by staff and administrators.
        if ($data['payment_status'] === 'paid') {
            app(PaidCounterOrderStatus::class)->change($request->user(), $invoice, $data['target'], $data['revision'], $data['reason']);
        } elseif ($data['target'] === 'completed') {
            $invoices->completeSaved($request->user(), $invoice, (int) $data['revision']);
        } else {
            $rounds->advance($request->user(), $invoice->tableSession, (string) $invoice->table_round_id,
                TableRoundStatus::Pending, TableRoundStatus::Cancelled, $data['reason'], (int) $data['revision']);
        }

        return to_route($request->boolean('return_to_invoices') ? 'admin.invoices.show' : 'admin.orders.show', $invoice->number, 303)->with('status', 'Đã cập nhật trạng thái đơn hàng.');
    }
}
