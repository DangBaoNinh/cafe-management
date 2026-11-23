<?php

namespace App\Http\Controllers;

use App\Enums\ReservationStatus;
use App\Models\ReservationSeat;
use App\Models\User;
use App\Services\TableManagement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TableManagementController extends Controller
{
    public function order(Request $request, ReservationSeat $reservationSeat, TableManagement $tables): RedirectResponse
    {
        $drafts = $reservationSeat->tableInvoices()->where('table_invoices.status', 'draft');
        $current = $reservationSeat->activeTableSession;
        if ($current !== null) {
            $drafts->where('table_sessions.id', $current->id);
        } elseif ($reservationSeat->activeReservation?->status === ReservationStatus::CheckedIn) {
            $drafts->where('table_sessions.reservation_id', $reservationSeat->activeReservation->id);
        }
        $invoice = $drafts->latest('table_invoices.updated_at')->latest('table_invoices.id')->first();
        if ($invoice !== null) {
            return to_route('operations.invoices.prepaid.show', $invoice, 303);
        }
        /** @var User $actor */
        $actor = $request->user();
        $orderContext = $tables->openForOrder($actor, $reservationSeat);

        return to_route($orderContext->billing_mode === 'prepaid' ? 'operations.table-sessions.pos' : 'operations.table-sessions.show', $orderContext, 303);
    }

    public function status(Request $request, ReservationSeat $reservationSeat, TableManagement $tables): RedirectResponse
    {
        $input = $request->validate(['expected' => ['required', Rule::in(['free', 'occupied', 'held', 'inactive'])],
            'status' => ['required', Rule::in(['free', 'occupied', 'inactive'])]]);
        /** @var User $actor */
        $actor = $request->user();
        $tables->changeStatus($actor, $reservationSeat, $input['expected'], $input['status']);

        return to_route('operations.table-sessions.index', status: 303)->with('status', 'Đã cập nhật trạng thái bàn.');
    }
}
