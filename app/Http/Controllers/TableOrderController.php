<?php

namespace App\Http\Controllers;

use App\Enums\TableSessionStatus;
use App\Http\Requests\TableRoundRequest;
use App\Models\Product;
use App\Models\ReservationSeat;
use App\Models\TableSession;
use App\Services\TableRounds;
use App\Services\TableSessions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class TableOrderController extends Controller
{
    public function show(Request $request, ReservationSeat $reservationSeat): Response
    {
        $session = $this->authorizedSession($request, $reservationSeat);
        $rounds = $session?->rounds()->with('items')->orderBy('id')->get();
        $products = $session?->status === TableSessionStatus::Open
            ? Product::query()->visibleInCatalog()->with(['activeSizes', 'activeToppings'])->orderBy('name')->get()
            : collect();

        return response()->view('table-orders.show', [
            'seat' => $reservationSeat, 'tableSession' => $session, 'rounds' => $rounds, 'products' => $products,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function join(Request $request, ReservationSeat $reservationSeat, TableSessions $sessions): RedirectResponse
    {
        $code = $request->validate(['access_code' => ['required', 'string', 'regex:/\A[0-9]{8}\z/']], [
            'access_code.required' => 'Nhập mã phiên do nhân viên cung cấp.',
            'access_code.regex' => 'Mã phiên gồm 8 chữ số.',
        ])['access_code'];
        $session = $sessions->join($reservationSeat, $code);
        $request->session()->regenerate(true);
        $request->session()->put('table_order_session', $session->reference);

        return to_route('table-orders.show', $reservationSeat->code, 303);
    }

    public function store(TableRoundRequest $request, ReservationSeat $reservationSeat, TableRounds $rounds): RedirectResponse
    {
        $session = $this->forSubmission($request, $reservationSeat);
        $rounds->submit($session, $request->selections(), $request->submissionKey());

        return to_route('table-orders.show', $reservationSeat->code, 303)->with('status', 'Đã gửi lượt gọi.');
    }

    public function checkout(Request $request, ReservationSeat $reservationSeat, TableSessions $sessions): RedirectResponse
    {
        $sessions->requestCheckout($this->forSubmission($request, $reservationSeat));

        return to_route('table-orders.show', $reservationSeat->code, 303)->with('status', 'Đã gửi yêu cầu tính tiền.');
    }

    private function authorizedSession(Request $request, ReservationSeat $seat): ?TableSession
    {
        $grant = $request->session()->get('table_order_session');
        if (! is_string($grant)) {
            return null;
        }

        return $seat->activeTableSession()->where('reference', $grant)->first();
    }

    private function forSubmission(Request $request, ReservationSeat $seat): TableSession
    {
        $session = $this->authorizedSession($request, $seat);
        abort_if($session === null, 403, __('Forbidden'));
        if ($request->input('session_reference') !== $session->reference) {
            throw ValidationException::withMessages(['table_session' => 'Phiên đã thay đổi. Vui lòng tải lại trang.'])
                ->status(409)->redirectTo(route('table-orders.show', $seat->code));
        }

        return $session;
    }
}
