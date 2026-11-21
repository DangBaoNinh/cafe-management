<?php

namespace App\Http\Requests\Admin;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CancelOrderRequest extends FormRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'expected_status' => ['required', Rule::enum(OrderStatus::class)],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['expected_status' => 'trạng thái hiện tại', 'reason' => 'lý do hủy đơn'];
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.orders.show', $this->route('number'));
    }
}
