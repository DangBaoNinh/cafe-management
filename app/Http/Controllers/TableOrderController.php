<?php

namespace App\Http\Controllers;

use App\Enums\CollectionMethod;
use App\Enums\TableSessionStatus;
use App\Http\Requests\SaveTableInvoiceRequest;
use App\Http\Requests\TableRoundRequest;
use App\Models\Product;
use App\Models\ReservationSeat;
use App\Models\TableInvoice;
use App\Models\TableSession;
use App\Services\BankTransferQr;
use App\Services\TableInvoices;
use App\Services\TableRounds;
use App\Services\TableSessions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TableOrderController extends Controller
{
    public function show(Request $request, ReservationSeat $reservationSeat): Response
    {
        $session = $this->authorizedSession($request, $reservationSeat);
        $rounds = $session?->rounds()->with(['items', 'invoice', 'tableSession'])->orderBy('id')->get();
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

    public function access(Request $request, ReservationSeat $reservationSeat, TableSession $tableSession): RedirectResponse
    {
        abort_unless($tableSession->billing_mode === 'prepaid' && $tableSession->reservation_seat_id === $reservationSeat->id
            && $tableSession->active_seat_id === $reservationSeat->id && $tableSession->status === TableSessionStatus::Open, 403);
        $request->session()->regenerate(true);
        $request->session()->put('table_order_session', $tableSession->reference);

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

    public function pos(Request $request, ReservationSeat $reservationSeat, TablePosController $pos, TableInvoices $invoices, BankTransferQr $qr): Response
    {
        $session = $this->forViewing($request, $reservationSeat);
        abort_unless($session->billing_mode === 'prepaid', 409);

        return $pos->screen($request, $session, $invoices, $qr, customer: true);
    }

    public function invoiceStore(SaveTableInvoiceRequest $request, ReservationSeat $reservationSeat, TableInvoices $invoices): RedirectResponse
    {
        $session = $this->forSubmission($request, $reservationSeat);
        abort_unless($request->validated('intent') === 'draft', 403);
        $invoice = $invoices->save($session, $request->selections(), $request->submissionKey(), null, 0,
            CollectionMethod::from($request->validated('method')), $request->integer('accepted_total_vnd'), false, false, null);

        return to_route('table-orders.invoice.show', [$reservationSeat->code, $invoice], 303)->with('status', 'Đã nhận order. Vui lòng thanh toán để bắt đầu pha chế.');
    }

    public function invoiceShow(Request $request, ReservationSeat $reservationSeat, TableInvoice $tableInvoice, TablePosController $pos, BankTransferQr $qr): Response
    {
        $session = $this->forViewing($request, $reservationSeat);
        abort_unless($tableInvoice->table_session_id === $session->id, 404);

        return response()->view('table-orders.receipt', $pos->receipt($tableInvoice, $qr, true))->header('Cache-Control', 'private, no-store');
    }

    public function reportPayment(Request $request, ReservationSeat $reservationSeat, TableInvoice $tableInvoice): RedirectResponse
    {
        $session = $this->forViewing($request, $reservationSeat);
        abort_unless($tableInvoice->table_session_id === $session->id && $tableInvoice->method === CollectionMethod::BankTransfer && $tableInvoice->status === 'draft', 404);
        DB::transaction(function () use ($tableInvoice, $session): void {
            $current = TableSession::query()->lockForUpdate()->findOrFail($session->id);
            abort_unless($current->status === TableSessionStatus::Open && $current->active_seat_id !== null, 403);
            $invoice = TableInvoice::query()->lockForUpdate()->findOrFail($tableInvoice->id);
            if ($invoice->status !== 'paid' && $invoice->payment_reported_at === null) {
                $invoice->payment_reported_at = now();
                $invoice->save();
            }
        });

        return to_route('table-orders.invoice.show', [$reservationSeat->code, $tableInvoice], 303)->with('status', 'Đã báo chuyển khoản. Nhân viên sẽ đối chiếu tiền vào tài khoản.');
    }

    private function forViewing(Request $request, ReservationSeat $seat): TableSession
    {
        $session = $this->authorizedSession($request, $seat);
        abort_if($session === null, 403);

        return $session;
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
