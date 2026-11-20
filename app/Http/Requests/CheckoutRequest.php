<?php

namespace App\Http\Requests;

use App\Enums\FulfillmentType;
use App\Enums\PaymentMethod;
use App\Models\Coupon;
use App\Services\CheckoutDetails;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    protected $redirectRoute = 'checkout.create';

    public function authorize(): bool
    {
        return $this->user()?->can('manage-cart') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $coupon = $this->input('coupon_code');
        $key = $this->input('checkout_key');

        if (is_string($coupon)) {
            $coupon = Coupon::normalizeCode($coupon);
            $this->merge(['coupon_code' => $coupon === '' ? null : $coupon]);
        }
        if (is_string($key)) {
            $this->merge(['checkout_key' => strtolower($key)]);
        }

        foreach (['cart_version', 'address_id', 'accepted_total_vnd'] as $field) {
            $value = $this->input($field);
            if (is_string($value) && ($number = filter_var($value, FILTER_VALIDATE_INT)) !== false) {
                $this->merge([$field => $number]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'cart_version' => ['required', 'integer:strict', 'min:0'],
            'fulfillment_type' => ['required', Rule::enum(FulfillmentType::class)],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'recipient_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'regex:/\A(?:0|\+84)[1-9][0-9]{8,9}\z/'],
            'address_id' => [
                Rule::excludeIf($this->input('fulfillment_type') !== FulfillmentType::Delivery->value),
                'required', 'integer:strict', 'min:1',
            ],
            'coupon_code' => ['nullable', 'string', 'max:40', 'regex:/\A[A-Z0-9]+(?:[-_][A-Z0-9]+)*\z/'],
        ];

        if ($this->routeIs('checkout.store')) {
            $rules += [
                'checkout_key' => ['required', 'uuid:4'],
                'accepted_total_vnd' => ['required', 'integer:strict', 'min:0'],
                'quote_hash' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
            ];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'cart_version' => 'phiên bản giỏ',
            'fulfillment_type' => 'cách nhận',
            'method' => 'phương thức thanh toán',
            'recipient_name' => 'tên người nhận',
            'phone' => 'số điện thoại',
            'address_id' => 'địa chỉ giao hàng',
            'coupon_code' => 'mã giảm giá',
            'checkout_key' => 'mã xác nhận đặt hàng',
            'accepted_total_vnd' => 'tổng tiền đã xác nhận',
            'quote_hash' => 'thông tin xác nhận',
        ];
    }

    public function details(): CheckoutDetails
    {
        $data = $this->validated();

        return new CheckoutDetails(
            $data['cart_version'],
            FulfillmentType::from($data['fulfillment_type']),
            PaymentMethod::from($data['method']),
            $data['recipient_name'],
            $data['phone'],
            $data['address_id'] ?? null,
            $data['coupon_code'] ?? null,
        );
    }
}
