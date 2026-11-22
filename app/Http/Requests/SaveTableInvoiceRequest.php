<?php

namespace App\Http\Requests;

use App\Enums\CollectionMethod;
use Illuminate\Validation\Rule;

class SaveTableInvoiceRequest extends TableRoundRequest
{
    public function rules(): array
    {
        return [...parent::rules(),
            'method' => ['required', Rule::enum(CollectionMethod::class)],
            'revision' => ['required', 'integer', 'min:0'],
            'round_id' => ['nullable', 'integer', 'min:1'],
            'intent' => ['required', Rule::in(['draft', 'pay'])],
            'accepted_total_vnd' => ['required', 'integer', 'min:0'],
            'changes_confirmed' => ['nullable', 'boolean'],
            'deposit_refund_confirmed' => ['nullable', 'boolean'],
            'payment_confirmed' => ['accepted_if:intent,pay'],
        ];
    }

    public function attributes(): array
    {
        return [...parent::attributes(), 'method' => 'phương thức thanh toán', 'revision' => 'phiên bản hóa đơn',
            'accepted_total_vnd' => 'tổng tiền xác nhận', 'payment_confirmed' => 'xác nhận đã nhận đủ tiền'];
    }

    public function messages(): array
    {
        return [...parent::messages(), 'payment_confirmed.accepted_if' => 'Xác nhận đã nhận đủ tiền trước khi hoàn tất thanh toán.'];
    }
}
