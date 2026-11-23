<?php

namespace App\Http\Controllers;

use App\Enums\CollectionMethod;
use App\Http\Requests\CompleteScheduledPickupRequest;
use App\Models\PickupFee;
use App\Models\ScheduledPickup;
use App\Models\User;
use App\Services\BookingRules;
use App\Services\Checkout;
use App\Services\PickupFees;
use App\Services\ScheduledPickups;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ScheduledPickupController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'time' => ['nullable', Rule::in(BookingRules::times())],
        ]);
        $date = $filters['date'] ?? now(BookingRules::TIMEZONE)->toDateString();
        $time = $filters['time'] ?? null;
        $start = CarbonImmutable::parse($date.' '.($time ?? '00:00'), BookingRules::TIMEZONE);
        $end = $time === null ? $start->addDay() : $start->addMinutes(5);
        $pickups = ScheduledPickup::query()->with('order')
            ->where('scheduled_at', '>=', $start->utc())->where('scheduled_at', '<', $end->utc())
            ->orderBy('scheduled_at')->orderBy('id')->paginate(20)->withQueryString();

        return view('operations.pickups.index', compact('pickups', 'date', 'time'));
    }

    public function lookup(Request $request): RedirectResponse
    {
        $code = strtoupper($request->validate(['code' => ['required', 'string', 'size:16', 'regex:/\A[0-9a-f]+\z/i']])['code']);
        if (! ScheduledPickup::query()->where('code', $code)->exists()) {
            throw ValidationException::withMessages(['code' => 'Không tìm thấy phiếu nhận với mã này.']);
        }

        return to_route('operations.pickups.show', $code, 303);
    }

    public function show(string $code, PickupFees $fees, Checkout $checkout): View
    {
        $pickup = ScheduledPickup::query()->with(['order.payment', 'fees'])->where('code', $code)->firstOrFail();
        $order = $pickup->order;

        return view('operations.pickups.show', [
            'pickup' => $pickup, 'order' => $order,
            'invoice' => $fees->invoice($order, $pickup),
            'bankAvailable' => $checkout->bankDetails() !== null,
        ]);
    }

    public function reschedule(Request $request, string $number, ScheduledPickups $pickups): RedirectResponse
    {
        $data = $request->validate([
            'pickup_date' => ['required', 'date_format:Y-m-d'],
            'pickup_clock' => ['required', 'date_format:H:i'],
            'revision' => ['required', 'integer', 'min:0'],
            'channel' => ['required', Rule::in(array_keys(PickupFee::CHANNELS))],
        ]);
        $pickups->reschedule($this->user($request), $number, $data['pickup_date'].' '.$data['pickup_clock'], (int) $data['revision'], $data['channel']);

        return to_route('orders.show', $number, 303)->with('status', 'Đã đổi giờ nhận và ghi nhận phí đổi lịch.');
    }

    public function arrive(Request $request, string $code, ScheduledPickups $pickups): RedirectResponse
    {
        $channel = $request->validate(['channel' => ['required', Rule::in(array_keys(PickupFee::CHANNELS))]])['channel'];
        $request->validate(['return_to' => ['nullable', Rule::in(['order'])]]);
        $pickups->arrive($this->user($request), $code, $channel);

        if ($request->input('return_to') === 'order') {
            $pickup = ScheduledPickup::query()->with('order')->where('code', $code)->firstOrFail();

            return to_route('operations.orders.show', $pickup->order->order_number, 303)->with('status', 'Đã ghi nhận khách đến và chốt phụ phí.');
        }

        return $this->result($code, 'Đã ghi nhận khách đến và chốt phụ phí.');
    }

    public function handoff(CompleteScheduledPickupRequest $request, string $code, ScheduledPickups $pickups): RedirectResponse
    {
        $amount = $request->validated('collected_vnd');
        $pickups->handoff($this->user($request), $code, $amount === null ? null : (int) $amount,
            $request->filled('collection_method') ? CollectionMethod::from($request->validated('collection_method')) : null);
        $pickup = ScheduledPickup::query()->with('order')->where('code', $code)->firstOrFail();

        return to_route('operations.invoices.orders.show', $pickup->order->order_number, 303)->with('status', 'Đã hoàn tất bàn giao và thanh toán. Phiếu nhận đã được sử dụng.');
    }

    public function resolve(Request $request, string $code, int $fee, PickupFees $fees): RedirectResponse
    {
        $data = $request->validate([
            'result' => ['required', Rule::in(['paid', 'failed', 'expired', 'cancelled'])],
            'amount_vnd' => ['required_if:result,paid', 'nullable', 'integer', 'min:1'],
            'memo' => ['required_if:result,paid', 'nullable', 'string', 'max:24'],
            'reference' => ['required_if:result,paid', 'nullable', 'string', 'max:100'],
        ]);
        $fees->resolve($this->user($request), $code, $fee, $data['result'], isset($data['amount_vnd']) ? (int) $data['amount_vnd'] : null,
            $data['memo'] ?? null, $data['reference'] ?? null);

        return $this->result($code, 'Đã ghi kết quả đối chiếu khoản phí.');
    }

    public function moveToCounter(Request $request, string $code, int $fee, PickupFees $fees): RedirectResponse
    {
        $fees->moveToCounter($this->user($request), $code, $fee);

        return $this->result($code, 'Đã chuyển khoản phí sang hóa đơn tại quầy.');
    }

    public function refund(Request $request, string $code, int $fee, PickupFees $fees): RedirectResponse
    {
        $reference = $request->validate(['refund_reference' => ['required', 'string', 'max:100']])['refund_reference'];
        $fees->refund($this->user($request), $code, $fee, $reference);

        return $this->result($code, 'Đã ghi nhận hoàn tiền phụ phí.');
    }

    private function result(string $code, string $message): RedirectResponse
    {
        return to_route('operations.pickups.show', $code, 303)->with('status', $message);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
