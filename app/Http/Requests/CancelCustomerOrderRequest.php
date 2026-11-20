<?php

namespace App\Http\Requests;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CancelCustomerOrderRequest extends FormRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'confirm' => ['accepted'],
            'expected_status' => ['required', Rule::in([OrderStatus::Pending->value])],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'confirm.accepted' => 'Bạn cần xác nhận hủy đơn.',
            'expected_status.required' => 'Thiếu trạng thái đơn. Vui lòng tải lại trang.',
            'expected_status.in' => 'Trạng thái đơn không hợp lệ. Vui lòng tải lại trang.',
            'reason.string' => 'Lý do hủy phải là văn bản.',
            'reason.max' => 'Lý do hủy tối đa 500 ký tự.',
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('orders.show', $this->route('number'));
    }
}
