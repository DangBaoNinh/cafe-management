<?php

namespace App\Http\Controllers;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\ReservationHours;
use App\Models\ReservationSeat;
use App\Models\User;
use App\Services\BookingFees;
use App\Services\BookingRules;
use App\Services\ReservationFees;
use App\Services\TableReservations;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReservationOperationsController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'floor' => ['nullable', 'integer', Rule::in([1, 2])],
            'kind' => ['nullable', Rule::in(array_keys(ReservationSeat::KINDS))],
            'status' => ['nullable', Rule::enum(ReservationStatus::class)],
        ]);
        $date = $filters['date'] ?? now(BookingRules::TIMEZONE)->toDateString();
        $from = CarbonImmutable::parse($date, BookingRules::TIMEZONE);
        $reservations = Reservation::query()->with('seat')
            ->where('scheduled_at', '>=', $from->utc())->where('scheduled_at', '<', $from->addDay()->utc())
            ->when(isset($filters['floor']), fn ($query) => $query->whereRelation('seat', 'floor', (int) $filters['floor']))
            ->when(isset($filters['kind']), fn ($query) => $query->whereRelation('seat', 'kind', $filters['kind']))
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->orderBy('scheduled_at')->orderBy('id')->paginate(20)->withQueryString();

        return view('operations.reservations.index', compact('date', 'filters', 'reservations'));
    }

    public function seats(): View
    {
        return view('operations.reservations.seats', [
            'seats' => ReservationSeat::query()->with('activeReservation:id,code,active_seat_id,status')
                ->orderBy('floor')->orderBy('position')->get()->toBase()->groupBy('floor'),
            'hours' => ReservationHours::query()->sole(),
        ]);
    }

    public function lookup(Request $request): RedirectResponse
    {
        $code = strtoupper($request->validate(['code' => ['required', 'string', 'regex:/\A[0-9a-f]{16}\z/i']])['code']);
        if (! Reservation::query()->where('code', $code)->exists()) {
            throw ValidationException::withMessages(['code' => 'Không tìm thấy mã đặt chỗ.']);
        }

        return to_route('operations.reservations.show', $code, 303);
    }

    public function show(string $code, BookingFees $fees): View
    {
        $reservation = Reservation::query()->with(['seat', 'fees', 'events'])->where('code', $code)->firstOrFail();

        return view('operations.reservations.show', [
            'reservation' => $reservation, 'counterTotal' => $fees->counterTotal($reservation),
        ]);
    }

    public function checkIn(Request $request, string $code, TableReservations $bookings): RedirectResponse
    {
        $bookings->checkIn($this->actor($request), $code);

        return $this->result($code, 'Đã ghi nhận khách đến và chốt phụ phí.');
    }

    public function release(Request $request, string $code, TableReservations $bookings): RedirectResponse
    {
        $bookings->release($this->actor($request), $code);

        return $this->result($code, 'Đã xác nhận khách rời đi và trả chỗ.');
    }

    public function cancel(Request $request, string $code, TableReservations $bookings): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:500']])['reason'];
        $bookings->cancel($this->actor($request), $code, $reason);

        return $this->result($code, 'Đã hủy lịch và trả chỗ.');
    }

    public function walkIn(Request $request, int $seat, TableReservations $bookings): RedirectResponse
    {
        $arriving = $request->validate(['arriving' => ['required', 'boolean']])['arriving'];
        $bookings->walkIn($this->actor($request), $seat, (bool) $arriving);

        return to_route('operations.reservations.seats', status: 303)->with('status', 'Đã cập nhật tình trạng khách trực tiếp.');
    }

    public function seatActive(Request $request, int $seat, TableReservations $bookings): RedirectResponse
    {
        $active = $request->validate(['active' => ['required', 'boolean']])['active'];
        $bookings->setSeatActive($this->actor($request), $seat, (bool) $active);

        return to_route('operations.reservations.seats', status: 303)->with('status', 'Đã cập nhật trạng thái phục vụ.');
    }

    public function collect(Request $request, string $code, ReservationFees $fees): RedirectResponse
    {
        $amount = $request->validate(['amount_vnd' => ['required', 'integer', 'min:1']])['amount_vnd'];
        $fees->collect($this->actor($request), $code, (int) $amount);

        return $this->result($code, 'Đã ghi nhận số tiền thu tại quầy.');
    }

    public function hours(Request $request, TableReservations $bookings): RedirectResponse
    {
        $input = $request->validate(['first_slot' => ['required', 'date_format:H:i'], 'last_slot' => ['required', 'date_format:H:i']]);
        $bookings->updateHours($this->actor($request), $input['first_slot'], $input['last_slot']);

        return to_route('operations.reservations.seats', status: 303)->with('status', 'Đã cập nhật giờ nhận đặt chỗ.');
    }

    public function resolve(Request $request, string $code, int $fee, ReservationFees $fees): RedirectResponse
    {
        $data = $request->validate([
            'result' => ['required', Rule::in(['paid', 'failed', 'expired', 'cancelled'])],
            'amount_vnd' => ['required_if:result,paid', 'nullable', 'integer', 'min:1'],
            'memo' => ['required_if:result,paid', 'nullable', 'string', 'max:24'],
            'reference' => ['required_if:result,paid', 'nullable', 'string', 'max:100'],
        ]);
        $fees->resolve($this->actor($request), $code, $fee, $data['result'], isset($data['amount_vnd']) ? (int) $data['amount_vnd'] : null,
            $data['memo'] ?? null, $data['reference'] ?? null);

        return $this->result($code, 'Đã ghi kết quả đối chiếu khoản phí.');
    }

    public function moveToCounter(Request $request, string $code, int $fee, ReservationFees $fees): RedirectResponse
    {
        $fees->moveToCounter($this->actor($request), $code, $fee);

        return $this->result($code, 'Đã chuyển khoản phí sang hóa đơn tại quầy.');
    }

    public function refund(Request $request, string $code, int $fee, ReservationFees $fees): RedirectResponse
    {
        $reference = $request->validate(['refund_reference' => ['required', 'string', 'max:100']])['refund_reference'];
        $fees->refund($this->actor($request), $code, $fee, $reference);

        return $this->result($code, 'Đã ghi nhận hoàn tiền phụ phí.');
    }

    private function result(string $code, string $message): RedirectResponse
    {
        return to_route('operations.reservations.show', $code, 303)->with('status', $message);
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
