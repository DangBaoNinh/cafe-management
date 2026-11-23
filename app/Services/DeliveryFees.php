<?php

namespace App\Services;

use App\Models\DeliverySetting;
use App\Models\UserAddress;

class DeliveryFees
{
    public const PICKUP_FEE_VND = 0;

    public function forDelivery(UserAddress $address): ?int
    {
        $settings = DeliverySetting::query()->findOrFail(DeliverySetting::SINGLETON_ID);

        if (! $settings->delivery_enabled || ! in_array($address->district_code, $settings->eligible_districts, true)) {
            return null;
        }

        return $settings->flat_fee_vnd;
    }
}
