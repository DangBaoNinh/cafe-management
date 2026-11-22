<?php

namespace App\Http\Controllers;

use App\Enums\TableRoundStatus;
use App\Enums\TableSessionStatus;
use App\Http\Requests\TableRoundRequest;
use App\Models\Product;
use App\Models\ReservationSeat;
use App\Models\TableSession;
use App\Models\User;
use App\Services\TableRounds;
use App\Services\TableSessions;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class TableSessionOperationsController extends Controller
{
    public function index(): View
    {
        $seats = ReservationSeat::query()->with([
            'activeReservation',
            'activeTableSession' => fn ($sessions) => $sessions->withCount([
                'rounds as pending_rounds_count' => fn ($rounds) => $rounds->where('status', TableRoundStatus::Pending),
            ]),
        ])
            ->orderBy('floor')->orderBy('position')->get()->toBase()->groupBy('floor');
        $closedSessions = TableSession::query()->where('status', TableSessionStatus::Closed)
            ->with('seat')->orderByDesc('closed_at')->orderByDesc('id')->paginate(12);

        return view('operations.table-sessions.index', compact('seats', 'closedSessions'));
    }

    public function qr(ReservationSeat $reservationSeat): Response
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(320, 4), new SvgImageBackEnd));
        $image = $writer->writeString(route('table-orders.show', $reservationSeat->code));

        return response($image, 200, ['Content-Type' => 'image/svg+xml; charset=UTF-8', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function open(Request $request, ReservationSeat $reservationSeat, TableSessions $sessions): RedirectResponse
    {
        return $this->result($sessions->open($this->actor($request), $reservationSeat), 'Đã mở phiên gọi món.');
    }

    public function show(TableSession $tableSession): Response
    {
        $tableSession->load(['seat', 'reservation', 'fees']);
        $rounds = $tableSession->rounds()->with(['items', 'events.actor'])->orderBy('id')->get();
        $products = $tableSession->status === TableSessionStatus::Open
            ? Product::query()->visibleInCatalog()->with(['activeSizes', 'activeToppings'])->orderBy('name')->get()
            : collect();

        return response()->view('operations.table-sessions.show', compact('tableSession', 'rounds', 'products'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function store(TableRoundRequest $request, TableSession $tableSession, TableRounds $rounds): RedirectResponse
    {
        $rounds->submit($tableSession, $request->selections(), $request->submissionKey(), $this->actor($request));

        return $this->result($tableSession, 'Đã ghi lượt gọi thay khách.');
    }

    public function advance(Request $request, TableSession $tableSession, string $round, TableRounds $rounds): RedirectResponse
    {
        $input = $request->validate([
            'expected' => ['required', Rule::enum(TableRoundStatus::class)],
            'target' => ['required', Rule::enum(TableRoundStatus::class)],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $next = TableRoundStatus::from($input['target']);
        $rounds->advance($this->actor($request), $tableSession, $round, TableRoundStatus::from($input['expected']), $next, $input['reason'] ?? null);

        return $this->result($tableSession, 'Đã cập nhật lượt gọi.');
    }

    public function update(Request $request, TableSession $tableSession, string $action, TableSessions $sessions): RedirectResponse
    {
        $actor = $this->actor($request);
        switch ($action) {
            case 'lock':
                $sessions->lockOrdering($actor, $tableSession);
                $message = 'Đã khóa gọi thêm.';
                break;
            case 'bill':
                $sessions->bill($actor, $tableSession);
                $message = 'Đã chốt hóa đơn.';
                break;
            case 'pay':
                $amount = $request->validate(['amount_vnd' => ['required', 'integer', 'min:0']], [], ['amount_vnd' => 'số tiền đã thu'])['amount_vnd'];
                $sessions->pay($actor, $tableSession, (int) $amount);
                $message = 'Đã ghi nhận thanh toán tại quầy.';
                break;
            case 'close':
                $sessions->close($actor, $tableSession);
                $message = 'Đã xác nhận khách rời đi và trả chỗ.';
                break;
            default:
                abort(404);
        }

        return $this->result($tableSession, $message);
    }

    private function result(TableSession $session, string $message): RedirectResponse
    {
        return to_route('operations.table-sessions.show', $session, 303)->with('status', $message);
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
