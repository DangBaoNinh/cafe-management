<?php

namespace App\Http\Controllers;

use App\Enums\DemoShipmentEvent;
use App\Models\User;
use App\Services\DemoDeliveryProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class DemoDeliveryController extends Controller
{
    public function create(Request $request, string $number, DemoDeliveryProvider $delivery): RedirectResponse
    {
        $request->validate(['simulate_unavailable' => ['sometimes', 'boolean']]);
        /** @var User $actor */
        $actor = $request->user();
        $shipment = $delivery->createShipment($actor, $number, $request->boolean('simulate_unavailable'));

        return to_route('operations.orders.show', $number, 303)->with('status', 'Đã có vận đơn mô phỏng '.$shipment->tracking_number.'.');
    }

    public function simulate(Request $request, string $number, DemoDeliveryProvider $delivery): RedirectResponse
    {
        $input = $request->validate([
            'event' => ['required', Rule::enum(DemoShipmentEvent::class)],
            'cod_amount_vnd' => ['nullable', 'integer', 'min:0'],
        ], attributes: ['cod_amount_vnd' => 'số tiền COD đã thu (VND)', 'event' => 'mốc vận chuyển']);
        /** @var User $actor */
        $actor = $request->user();
        $delivery->simulate($actor, $number, DemoShipmentEvent::from($input['event']), isset($input['cod_amount_vnd']) ? $request->integer('cod_amount_vnd') : null);

        return to_route('operations.orders.show', $number, 303)->with('status', 'Đã cập nhật vận đơn mô phỏng.');
    }

    public function callback(Request $request, DemoDeliveryProvider $delivery): Response
    {
        $delivery->receive($request->getContent(), $request->header('X-Demo-Signature'));

        return response()->noContent();
    }
}
