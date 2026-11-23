<?php

namespace App\Http\Controllers;

use App\Enums\ReservationStatus;
use App\Models\PickupFee;
use App\Models\ReservationHours;
use App\Models\ReservationSeat;
use App\Models\User;
use App\Services\BookingRules;
use App\Services\Checkout;
use App\Services\ReservationCalendar;
use App\Services\TableReservations;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ReservationController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'scope' => ['nullable', Rule::in(['all', 'open', 'history'])],
            'status' => ['nullable', Rule::enum(ReservationStatus::class)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
        ]);
        $scope = $filters['scope'] ?? 'all';
        $finished = [ReservationStatus::Completed, ReservationStatus::Cancelled, ReservationStatus::NoShow];
        $query = $this->customer($request)->reservations();
        if ($scope === 'history') {
            $query->whereIn('status', $finished);
        } elseif ($scope === 'open') {
            $query->whereNotIn('status', $finished);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['from'])) {
            $query->where('scheduled_at', '>=', CarbonImmutable::parse($filters['from'], BookingRules::TIMEZONE)->startOfDay()->utc());
        }
        if (! empty($filters['to'])) {
            $query->where('scheduled_at', '<', CarbonImmutable::parse($filters['to'], BookingRules::TIMEZONE)->startOfDay()->addDay()->utc());
        }

        return view('customer.reservations.index', [
            'reservations' => $query->with(['seat', 'deposit'])->latest('id')->paginate(12)->withQueryString(),
            'filters' => $filters, 'scope' => $scope,
        ]);
    }

    public function create(Request $request, Checkout $checkout): View
    {
        $customer = $request->user();
        $request->validate(['booking_date' => ['nullable', 'date_format:Y-m-d']]);
        $hours = ReservationHours::query()->sole();
        $filters = ['pickup_date' => $request->query('booking_date', now(BookingRules::TIMEZONE)->toDateString()), 'pickup_clock' => null,
            'kind' => 'table', 'party_size' => 2, 'floor' => null];
        $seats = ReservationSeat::query()->with('activeReservation:id,reservation_seat_id,active_seat_id,status')
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

        $day = CarbonImmutable::parse($filters['pickup_date'], BookingRules::TIMEZONE);
        foreach ($seats as $seat) {
            $booking = ReservationCalendar::bookings($seat->id, $day)->first();
            $seat->setRelation('activeReservation', $booking);
            if (! $day->isToday()) {
                $seat->walk_in_at = null;
            }
        }

        return view('customer.spaces', [
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
            'booking_key' => strtolower($data['booking_key']), 'deposit_required' => true,
        ]);

        return to_route('reservations.show', $booking->code, 303)->with('status', $booking->status === ReservationStatus::PendingDeposit ? 'Đã tạo yêu cầu đặt chỗ. Vui lòng chuyển khoản cọc theo số tiền trên phiếu để xác nhận bàn.' : 'Đã đặt chỗ tại quán.');
    }

    public function show(Request $request, string $code): View
    {
        $reservation = $this->customer($request)->reservations()->with(['seat', 'fees', 'deposit.invoice',
            'events' => fn ($query) => $query->orderBy('occurred_at')->orderBy('id')])->where('code', $code)->firstOrFail();

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

        return to_route('reservations.show', $code, 303)->with('status', 'Đã xử lý yêu cầu. Khoản đã thu được tự hoàn demo về ngân hàng liên kết khi hủy hợp lệ.');
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
