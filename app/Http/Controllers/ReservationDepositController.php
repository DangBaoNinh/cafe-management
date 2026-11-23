<?php

namespace App\Http\Controllers;

use App\Models\ReservationDeposit;
use App\Services\ReservationDeposits;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReservationDepositController extends Controller
{
    public function pay(Request $request, string $code, ReservationDeposits $deposits): RedirectResponse
    {
        $reservation = $request->user()->reservations()->where('code', $code)->firstOrFail();
        $request->validate(['confirmed' => ['accepted'], 'amount_vnd' => ['required', 'integer', 'in:'.$reservation->deposit->amount_vnd]]);
        $deposits->demoPay($request->user(), $reservation);

        return to_route('reservations.show', $code, 303)->with('status', 'Đã xác nhận cọc demo. Bàn đã được đặt.');
    }

    public function show(ReservationDeposit $deposit): View
    {
        $deposit->load(['reservation.seat', 'invoice']);

        return view('operations.invoices.deposit', compact('deposit'));
    }
}
