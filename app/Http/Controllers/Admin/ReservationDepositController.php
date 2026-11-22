<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReservationDeposit;
use App\Services\TableReservations;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReservationDepositController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['status' => ['nullable', Rule::in(['pending', 'paid', 'settled', 'refunded', 'refund_pending', 'forfeited', 'voided'])]]);
        $deposits = ReservationDeposit::query()->with(['reservation.seat', 'invoice'])
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))->latest('id')->paginate(20)->withQueryString();

        return view('admin.deposits.index', compact('deposits', 'filters'));
    }

    public function forfeit(Request $request, ReservationDeposit $deposit, TableReservations $bookings): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500'], 'confirm' => ['accepted']]);
        DB::transaction(function () use ($request, $deposit, $bookings, $data): void {
            // TableReservations locks customer -> seat -> reservation -> deposit consistently.
            $bookings->cancel($request->user(), $deposit->reservation->code, $data['reason']);
            $row = ReservationDeposit::query()->lockForUpdate()->findOrFail($deposit->id);
            abort_unless($row->table_invoice_id === null && $row->status === 'forfeited', 409);
        }, 5);

        return to_route('admin.deposits.index')->with('status', 'Đã giữ cọc và lưu lý do.');
    }
}
