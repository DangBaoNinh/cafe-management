<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordRefundRequest extends FormRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'expected_status' => ['required', Rule::in([PaymentStatus::RefundPending->value])],
            'refund_reference' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'method' => 'phương thức thanh toán',
            'expected_status' => 'trạng thái thanh toán',
            'refund_reference' => 'tham chiếu hoàn',
            'note' => 'ghi chú',
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('operations.payments.show', $this->route('number'));
    }
}
