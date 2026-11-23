<?php

namespace App\Http\Requests;

use App\Enums\CollectionMethod;
use App\Enums\PaymentMethod;
use App\Models\ScheduledPickup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteScheduledPickupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('operate-orders') ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $pickup = ScheduledPickup::query()->with('order.payment')->where('code', $this->route('code'))->firstOrFail();

        return [
            'collected_vnd' => ['nullable', 'integer', 'min:0'],
            'collection_method' => [Rule::requiredIf($pickup->order->payment?->method === PaymentMethod::Cod), 'nullable', Rule::enum(CollectionMethod::class)],
            'confirmed' => ['accepted'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['collected_vnd' => 'số tiền thực thu', 'collection_method' => 'hình thức thực thu'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['confirmed.accepted' => 'Xác nhận khách đã nhận đơn và đã thu đủ tiền trước khi hoàn tất.'];
    }
}
