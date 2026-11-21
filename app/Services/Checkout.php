<?php

namespace App\Services;

use App\Enums\FulfillmentType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Topping;
use App\Models\User;
use App\Models\UserAddress;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-type BankDetails array{bank_name: string, account_number: string, account_holder: string}
 * @phpstan-type QuotedItem array{line: array<string, int|string|null>, toppings: list<array{topping_id: int, topping_name: string, unit_price_vnd: int}>}
 * @phpstan-type OrderDetails array{coupon_id: int|null, coupon_code: string|null, recipient_name: string, phone: string, address_snapshot: array<string, string|null>|null, fulfillment_type: string, subtotal_vnd: int, discount_vnd: int, delivery_fee_vnd: int, total_vnd: int}
 * @phpstan-type Quote array{order: OrderDetails, items: list<QuotedItem>, transferDetails: BankDetails|null, hash: string}
 */
class Checkout
{
    public function __construct(
        private ShoppingCart $shoppingCart,
        private DeliveryFees $fees,
        private VietnamAddressCatalog $catalog,
    ) {}

    /** @return Quote */
    public function preview(User $user, CheckoutDetails $details): array
    {
        $this->pickupSlot($user, $details);

        return DB::transaction(fn (): array => $this->price(
            $user,
            $user->cart()->first(),
            $details,
            $details->couponCode === null ? null : Coupon::query()->where('code', $details->couponCode)->first(),
        ));
    }

    public function place(User $user, CheckoutDetails $details, string $key, int $acceptedTotal, string $quoteHash): Order
    {
        $requestHash = $this->fingerprint([$details->fields(), $acceptedTotal, $quoteHash]);

        return DB::transaction(function () use ($user, $details, $key, $acceptedTotal, $quoteHash, $requestHash): Order {
            // The user row serializes retries and follows the cart's first-add lock order.
            $customer = User::query()->lockForUpdate()->findOrFail($user->id);
            Gate::forUser($customer)->authorize('manage-cart');
            $cart = $customer->cart()->lockForUpdate()->first();
            $coupon = $details->couponCode === null
                ? null
                : Coupon::query()->where('code', $details->couponCode)->lockForUpdate()->first();

            // Start MySQL's consistent reads after acquiring the coupon lock, so usage includes earlier checkouts.
            $existing = $customer->orders()->where('checkout_key', $key)->first();
            if ($existing !== null) {
                if (! hash_equals($existing->request_hash, $requestHash)) {
                    throw $this->invalid('checkout_key', 'Mã xác nhận này đã dùng cho nội dung đặt hàng khác.', 409);
                }

                return $existing;
            }

            $slot = $this->pickupSlot($customer, $details);
            $quote = $this->price($customer, $cart, $details, $coupon);
            if ($quote['order']['total_vnd'] !== $acceptedTotal || ! hash_equals($quote['hash'], $quoteHash)) {
                throw $this->invalid('accepted_total_vnd', 'Thông tin hoặc giá đã thay đổi. Vui lòng xem lại tổng tiền trước khi đặt hàng.', 409);
            }

            $order = $this->saveOrder($customer, $details, $quote, $key, $requestHash);
            if ($slot !== null) {
                $order->scheduledPickup()->create([
                    'code' => strtoupper(bin2hex(random_bytes(8))),
                    'scheduled_at' => $slot,
                    'arrival_deadline' => $slot->addMinutes(30),
                ]);
            }
            $cart->items()->delete();
            $cart->increment('version');

            return $order;
        }, 5);
    }

    private function pickupSlot(User $user, CheckoutDetails $details): ?CarbonImmutable
    {
        if ($details->pickupTime === null) {
            return null;
        }
        if ($details->fulfillment !== FulfillmentType::Pickup) {
            throw $this->invalid('pickup_time', 'Hẹn giờ chỉ áp dụng khi nhận tại quán.');
        }

        try {
            BookingRules::authorizeBooking($user);

            return BookingRules::slot($details->pickupTime);
        } catch (ValidationException $exception) {
            throw $exception->redirectTo(route('checkout.create'));
        }
    }

    /** @return BankDetails|null */
    public function bankDetails(): ?array
    {
        $bank = config('checkout.bank_transfer.bank_name');
        $number = config('checkout.bank_transfer.account_number');
        $holder = config('checkout.bank_transfer.account_holder');

        if (! is_string($bank) || ! is_string($number) || ! is_string($holder)
            || trim($bank) === '' || trim($number) === '' || trim($holder) === '') {
            return null;
        }

        return ['bank_name' => trim($bank), 'account_number' => trim($number), 'account_holder' => trim($holder)];
    }

    /** @return Quote */
    private function price(User $user, ?Cart $cart, CheckoutDetails $details, ?Coupon $coupon): array
    {
        if ($cart === null || $cart->version !== $details->cartVersion) {
            throw $this->invalid('cart_version', 'Giỏ hàng đã thay đổi. Vui lòng kiểm tra lại giỏ.', 409);
        }

        $basket = $this->shoppingCart->pricedLines($cart);
        if ($basket['lines'] === []) {
            throw $this->invalid('cart_version', 'Giỏ hàng của bạn đang trống.');
        }

        $items = [];
        foreach ($basket['lines'] as ['item' => $item, 'total' => $total]) {
            if ($total === null) {
                throw $this->invalid('cart_version', 'Có món hoặc tùy chọn không còn bán. Vui lòng kiểm tra lại giỏ.');
            }

            $items[] = [
                'line' => [
                    'product_size_id' => $item->product_size_id,
                    'product_name' => $item->size->product->name,
                    'size_label' => $item->size->label,
                    'quantity' => $item->quantity,
                    'sugar_level' => $item->sugar_level,
                    'ice_level' => $item->ice_level,
                    'base_unit_price_vnd' => $item->size->price_vnd,
                    'line_total_vnd' => $total,
                ],
                'toppings' => array_values($item->toppings->sortBy('id')->map(fn (Topping $topping): array => [
                    'topping_id' => $topping->id,
                    'topping_name' => $topping->name,
                    'unit_price_vnd' => $topping->price_vnd,
                ])->all()),
            ];
        }

        $subtotal = $basket['subtotal'];
        $discount = $this->discount($user, $details->couponCode, $coupon, $subtotal);
        [$address, $fee] = $this->delivery($user, $details);
        if ($details->method === PaymentMethod::DemoGateway && ! DemoPaymentGateway::enabled()) {
            throw $this->invalid('method', 'Cổng thanh toán mô phỏng không khả dụng.');
        }
        $transfer = $details->method === PaymentMethod::BankTransfer ? $this->bankDetails() : null;
        if ($details->method === PaymentMethod::BankTransfer && $transfer === null) {
            throw $this->invalid('method', 'Cửa hàng chưa cấu hình chuyển khoản. Vui lòng chọn thanh toán khi nhận.');
        }

        if ($subtotal - $discount > PHP_INT_MAX - $fee) {
            throw $this->invalid('accepted_total_vnd', 'Tổng tiền vượt giới hạn VND được hỗ trợ.');
        }

        $order = [
            'coupon_id' => $coupon?->id,
            'coupon_code' => $coupon?->code,
            'recipient_name' => $details->recipientName,
            'phone' => $details->phone,
            'address_snapshot' => $address,
            'fulfillment_type' => $details->fulfillment->value,
            'subtotal_vnd' => $subtotal,
            'discount_vnd' => $discount,
            'delivery_fee_vnd' => $fee,
            'total_vnd' => $subtotal - $discount + $fee,
        ];

        return [
            'order' => $order,
            'items' => $items,
            'transferDetails' => $transfer,
            'hash' => $this->fingerprint([$details->fields(), $order, $items, $transfer]),
        ];
    }

    private function discount(User $user, ?string $code, ?Coupon $coupon, int $subtotal): int
    {
        if ($code === null) {
            return 0;
        }

        if ($coupon === null) {
            throw $this->invalid('coupon_code', 'Mã giảm giá không hợp lệ.');
        }

        $orders = Order::query()->where('coupon_id', $coupon->id)
            ->whereNotIn('status', [OrderStatus::Cancelled->value, OrderStatus::Rejected->value]);
        $totalUses = $coupon->total_limit === null ? 0 : (clone $orders)->count();
        $customerUses = $coupon->per_user_limit === null ? 0 : $orders->where('user_id', $user->id)->count();

        return $coupon->discountFor($subtotal, $totalUses, $customerUses, now())
            ?? throw $this->invalid('coupon_code', 'Mã giảm giá chưa có hiệu lực, đã hết lượt hoặc không áp dụng cho đơn này.');
    }

    /** @return array{array<string, string|null>|null, int} */
    private function delivery(User $user, CheckoutDetails $details): array
    {
        if ($details->fulfillment === FulfillmentType::Pickup) {
            return [null, DeliveryFees::PICKUP_FEE_VND];
        }

        $address = $user->addresses()->find($details->addressId);
        if (! $address instanceof UserAddress) {
            throw $this->invalid('address_id', 'Địa chỉ giao hàng không còn khả dụng trong tài khoản.');
        }

        $fee = $this->fees->forDelivery($address);
        $province = $this->catalog->provinces()[$address->province_code] ?? null;
        $district = $this->catalog->districts($address->province_code)[$address->district_code] ?? null;
        $wards = $this->catalog->wards($address->province_code, $address->district_code);
        $ward = $address->ward_code === null ? null : ($wards[$address->ward_code] ?? null);
        if ($fee === null || $province === null || $district === null || ($wards !== [] && $ward === null)) {
            throw $this->invalid('address_id', 'Địa chỉ chưa được hỗ trợ giao hàng. Vui lòng chọn địa chỉ khác hoặc nhận tại quán.');
        }

        return [[
            'address_line' => $address->address_line,
            'province_code' => $address->province_code,
            'province_name' => $province,
            'district_code' => $address->district_code,
            'district_name' => $district,
            'ward_code' => $address->ward_code,
            'ward_name' => $ward,
        ], $fee];
    }

    /** @param Quote $quote */
    private function saveOrder(User $user, CheckoutDetails $details, array $quote, string $key, string $requestHash): Order
    {
        $order = $user->orders()->create([
            ...$quote['order'],
            'order_number' => 'CF-'.Str::ulid(),
            'checkout_key' => $key,
            'request_hash' => $requestHash,
            'status' => OrderStatus::Pending,
        ]);

        foreach ($quote['items'] as $quotedItem) {
            $item = $order->items()->create($quotedItem['line']);
            $item->toppings()->createMany($quotedItem['toppings']);
        }

        $payment = $order->payment()->create([
            'method' => $details->method,
            'status' => PaymentStatus::Pending,
            'amount_vnd' => $order->total_vnd,
            'transfer_details' => $quote['transferDetails'],
        ]);
        $order->statusHistories()->create(['actor_id' => $user->id, 'to_status' => OrderStatus::Pending->value]);
        $payment->statusHistories()->create(['actor_id' => $user->id, 'to_status' => PaymentStatus::Pending->value]);

        return $order;
    }

    /** @param array<mixed> $data */
    private function fingerprint(array $data): string
    {
        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }

    private function invalid(string $field, string $message, int $status = 422): ValidationException
    {
        return ValidationException::withMessages([$field => $message])->status($status)->redirectTo(route('checkout.create'));
    }
}
