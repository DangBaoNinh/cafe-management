<?php

namespace App\Services;

use App\Enums\FulfillmentType;
use App\Enums\PaymentMethod;

final readonly class CheckoutDetails
{
    public function __construct(
        public int $cartVersion,
        public FulfillmentType $fulfillment,
        public PaymentMethod $method,
        public string $recipientName,
        public string $phone,
        public ?int $addressId,
        public ?string $couponCode,
        public ?string $pickupTime = null,
    ) {}

    /** @return array<string, int|string|null> */
    public function fields(): array
    {
        return [
            'cart_version' => $this->cartVersion,
            'fulfillment_type' => $this->fulfillment->value,
            'method' => $this->method->value,
            'recipient_name' => $this->recipientName,
            'phone' => $this->phone,
            'address_id' => $this->addressId,
            'coupon_code' => $this->couponCode,
        ] + ($this->pickupTime === null ? [] : ['pickup_time' => $this->pickupTime]);
    }
}
