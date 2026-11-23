<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReconcileTransferRequest extends FormRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'method' => ['required', Rule::in([PaymentMethod::BankTransfer->value])],
            'expected_status' => ['required', Rule::in([PaymentStatus::Pending->value])],
            'amount_vnd' => ['required', 'integer', 'min:0'],
            'collection_reference' => ['required_unless:amount_vnd,0', 'prohibited_if:amount_vnd,0', 'nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'method' => 'phương thức thanh toán',
            'expected_status' => 'trạng thái thanh toán',
            'amount_vnd' => 'số tiền đã thu (VND)',
            'collection_reference' => 'tham chiếu thu',
            'note' => 'ghi chú',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'collection_reference.required_unless' => 'Nhập mã giao dịch trên sao kê.',
            'collection_reference.prohibited_if' => 'Đơn 0 ₫ không có mã giao dịch để đối chiếu.',
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('operations.payments.show', $this->route('number'));
    }
}
