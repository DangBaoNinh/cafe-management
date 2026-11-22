<?php

namespace App\Http\Requests\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterOrdersRequest extends FormRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'number' => ['nullable', 'string', 'max:36', 'regex:/\A[A-Za-z0-9-]+\z/'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('from'), 'after_or_equal:from')],
            'status' => ['nullable', Rule::in([...array_column(OrderStatus::cases(), 'value'), 'pending_payment'])],
            'method' => ['nullable', Rule::in([...array_column(PaymentMethod::cases(), 'value'), 'cash', 'counter'])],
            'source' => ['nullable', Rule::in(['online', 'counter', 'legacy', 'deposit'])],
            'display_status' => ['nullable', Rule::in(['paid', 'cancelled', 'pending'])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'number.regex' => 'Mã đơn chỉ gồm chữ cái, chữ số và dấu gạch ngang.',
            'from.date_format' => 'Ngày bắt đầu không hợp lệ.',
            'to.date_format' => 'Ngày kết thúc không hợp lệ.',
            'to.after_or_equal' => 'Ngày kết thúc phải từ ngày bắt đầu trở đi.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'number' => 'mã đơn', 'from' => 'ngày bắt đầu', 'to' => 'ngày kết thúc',
            'status' => 'trạng thái đơn', 'method' => 'phương thức thanh toán',
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route($this->routeIs('admin.invoices.*') ? 'admin.invoices.index' : 'admin.orders.index');
    }
}
