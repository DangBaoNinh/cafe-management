<?php

namespace App\Http\Requests;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderIntakeRequest extends FormRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in([OrderStatus::Confirmed->value, OrderStatus::Rejected->value])],
            'expected_status' => ['required', Rule::in([OrderStatus::Pending->value])],
            'reason' => ['required_if:decision,rejected', 'prohibited_if:decision,confirmed', 'nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'decision.required' => 'Chọn xác nhận hoặc từ chối đơn.',
            'decision.in' => 'Quyết định xử lý đơn không hợp lệ.',
            'expected_status.required' => 'Thiếu trạng thái đơn. Vui lòng tải lại trang.',
            'expected_status.in' => 'Trạng thái đơn không hợp lệ. Vui lòng tải lại trang.',
            'reason.required_if' => 'Nhập lý do từ chối đơn.',
            'reason.prohibited_if' => 'Lý do chỉ áp dụng khi từ chối đơn.',
            'reason.string' => 'Lý do từ chối phải là văn bản.',
            'reason.max' => 'Lý do từ chối tối đa 500 ký tự.',
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('operations.orders.show', $this->route('number'));
    }
}
