<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\DeliveryFees;
use App\Services\VietnamAddressCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DeliveryFeeController extends Controller
{
    public function __invoke(Request $request, DeliveryFees $fees, VietnamAddressCatalog $catalog): View
    {
        $validated = $request->validate(
            ['address' => ['sometimes', 'required', 'integer', 'min:1']],
            [],
            ['address' => 'địa chỉ giao hàng'],
        );

        /** @var User $user */
        $user = $request->user();
        $addresses = $user->addresses()->orderByDesc('is_default')->latest('id')->get();
        $address = isset($validated['address']) ? $addresses->findOrFail((int) $validated['address']) : null;

        return view('customer.delivery-fee', [
            'addresses' => $addresses,
            'address' => $address,
            'deliveryFee' => $address === null ? null : $fees->forDelivery($address),
            'pickupFee' => DeliveryFees::PICKUP_FEE_VND,
            'catalog' => $catalog,
        ]);
    }
}
