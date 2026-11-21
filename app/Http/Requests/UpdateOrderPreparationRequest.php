<?php

namespace App\Http\Requests;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderPreparationRequest extends FormRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'expected_status' => ['required', Rule::in([OrderStatus::Confirmed->value, OrderStatus::Preparing->value])],
            'target_status' => ['required', Rule::in([OrderStatus::Preparing->value, OrderStatus::Ready->value])],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['expected_status' => 'trạng thái hiện tại', 'target_status' => 'mốc pha chế'];
    }

    protected function getRedirectUrl(): string
    {
        return route('operations.orders.show', $this->route('number'));
    }
}
