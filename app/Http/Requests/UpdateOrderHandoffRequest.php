<?php

namespace App\Http\Requests;

use App\Enums\FulfillmentType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderHandoffRequest extends FormRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $collectCod = $this->input('target_status') === OrderStatus::Completed->value
            && $this->input('method') === PaymentMethod::Cod->value;

        return [
            'expected_status' => ['required', Rule::in([OrderStatus::Ready->value, OrderStatus::OutForDelivery->value])],
            'target_status' => ['required', Rule::in([OrderStatus::OutForDelivery->value, OrderStatus::Completed->value])],
            'fulfillment_type' => ['required', Rule::enum(FulfillmentType::class)],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'confirmed' => ['accepted'],
            'cod_amount_vnd' => [Rule::requiredIf($collectCod), Rule::prohibitedIf(! $collectCod), 'nullable', 'integer', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'expected_status' => 'trạng thái hiện tại',
            'target_status' => 'mốc bàn giao',
            'fulfillment_type' => 'cách nhận',
            'method' => 'phương thức thanh toán',
            'cod_amount_vnd' => 'số tiền COD đã thu (VND)',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'confirmed.accepted' => 'Xác nhận đã bàn giao đơn trước khi tiếp tục.',
            'cod_amount_vnd.prohibited' => 'Chỉ nhập số tiền COD khi hoàn tất đơn thanh toán khi nhận.',
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('operations.orders.show', $this->route('number'));
    }
}
