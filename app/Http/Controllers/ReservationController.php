<?php

namespace App\Http\Controllers;

use App\Models\PickupFee;
use App\Models\ReservationHours;
use App\Models\ReservationSeat;
use App\Models\User;
use App\Services\BookingRules;
use App\Services\Checkout;
use App\Services\TableReservations;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ReservationController extends Controller
{
    public function index(Request $request): View
    {
        return view('customer.reservations.index', [
            'reservations' => $this->customer($request)->reservations()->with('seat')->latest('id')->paginate(12),
        ]);
    }

    public function create(Request $request, Checkout $checkout): View
    {
        $customer = $this->customer($request);
        $hours = ReservationHours::query()->sole();
        $filters = ['pickup_date' => now(BookingRules::TIMEZONE)->toDateString(), 'pickup_clock' => null,
            'kind' => 'table', 'party_size' => 2, 'floor' => null];
        $seats = ReservationSeat::query()->with('activeReservation:id,active_seat_id,status')
            ->orderBy('floor')->orderBy('position')->get();
        $seatId = filter_var($request->query('seat_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $preferredSeat = $seatId === false ? null : $seats->find($seatId);
        $searched = $request->has('pickup_date');
        if ($searched) {
            $filters = $request->validate(array_diff_key($this->rules(), ['seat_id' => true, 'contact_name' => true, 'phone' => true, 'channel' => true, 'booking_key' => true]));
            $hours->validateSlot(BookingRules::slot($filters['pickup_date'].' '.$filters['pickup_clock']));
            if ($preferredSeat && ($preferredSeat->kind !== $filters['kind'] || $preferredSeat->capacity < $filters['party_size']
                || (! empty($filters['floor']) && $preferredSeat->floor !== (int) $filters['floor']))) {
                $preferredSeat = null;
            }
        } elseif ($preferredSeat) {
            $filters['kind'] = $preferredSeat->kind;
            $filters['floor'] = $preferredSeat->floor;
            $partySize = filter_var($request->query('party_size'), FILTER_VALIDATE_INT, ['options' => [
                'min_range' => 1, 'max_range' => min(10, $preferredSeat->capacity),
            ]]);
            $filters['party_size'] = $partySize === false ? min(2, $preferredSeat->capacity) : $partySize;
        }

        return view('customer.reservations.create', [
            'seats' => $seats->toBase()->groupBy('floor'), 'filters' => $filters, 'searched' => $searched,
            'preferredSeat' => $preferredSeat, 'customer' => $customer,
            'bankAvailable' => $checkout->bankDetails() !== null, 'bookingKey' => Str::uuid()->toString(), 'slots' => $hours->slots(),
        ]);
    }

    public function store(Request $request, TableReservations $bookings): RedirectResponse
    {
        $data = $request->validate($this->rules());
        $booking = $bookings->place($this->customer($request), [
            'seat_id' => (int) $data['seat_id'], 'kind' => $data['kind'], 'party_size' => (int) $data['party_size'],
            'pickup_time' => $data['pickup_date'].' '.$data['pickup_clock'],
            'contact_name' => $data['contact_name'], 'phone' => $data['phone'], 'channel' => $data['channel'],
            'booking_key' => strtolower($data['booking_key']),
        ]);

        return to_route('reservations.show', $booking->code, 303)->with('status', 'Đã giữ chỗ tại quán.');
    }

    public function show(Request $request, string $code): View
    {
        $reservation = $this->customer($request)->reservations()->with('seat', 'fees')->where('code', $code)->firstOrFail();

        return view('customer.reservations.show', ['reservation' => $reservation, 'slots' => ReservationHours::query()->sole()->slots()]);
    }

    public function reschedule(Request $request, string $code, TableReservations $bookings): RedirectResponse
    {
        $data = $request->validate([
            'pickup_date' => ['required', 'date_format:Y-m-d'], 'pickup_clock' => ['required', 'date_format:H:i'],
            'revision' => ['required', 'integer', 'min:0'],
        ]);
        $bookings->reschedule($this->customer($request), $code, $data['pickup_date'].' '.$data['pickup_clock'], (int) $data['revision']);

        return to_route('reservations.show', $code, 303)->with('status', 'Đã đổi giờ và ghi nhận phụ phí.');
    }

    public function cancel(Request $request, string $code, TableReservations $bookings): RedirectResponse
    {
        $request->validate(['confirm' => ['accepted']], ['confirm.accepted' => 'Bạn cần xác nhận hủy lịch.']);
        $bookings->cancel($this->customer($request), $code);

        return to_route('reservations.show', $code, 303)->with('status', 'Đã hủy lịch và trả chỗ.');
    }

    /** @return array<string, list<mixed>> */
    private function rules(): array
    {
        return [
            'pickup_date' => ['required', 'date_format:Y-m-d'], 'pickup_clock' => ['required', 'date_format:H:i'],
            'floor' => ['nullable', 'integer', Rule::in([1, 2])],
            'kind' => ['required', Rule::in(array_keys(ReservationSeat::KINDS))],
            'party_size' => ['required', 'integer', 'between:1,10'],
            'seat_id' => ['required', 'integer', 'min:1'],
            'contact_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'regex:/\A(?:0|\+84)[1-9][0-9]{8,9}\z/'],
            'channel' => ['required', Rule::in(array_keys(PickupFee::CHANNELS))],
            'booking_key' => ['required', 'uuid:4'],
        ];
    }

    private function customer(Request $request): User
    {
        /** @var User $customer */
        $customer = $request->user();

        return $customer;
    }
}
