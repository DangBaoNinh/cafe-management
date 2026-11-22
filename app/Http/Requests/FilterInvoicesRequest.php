<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class FilterInvoicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('operate-orders') ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'required_with:to', 'date_format:Y-m-d'],
            'to' => ['nullable', 'required_with:from', 'date_format:Y-m-d', 'after_or_equal:from'],
            'source' => ['nullable', Rule::in(['all', 'order', 'table'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->filled(['from', 'to'])) {
                return;
            }
            if (CarbonImmutable::parse($this->input('from'))->diffInDays(CarbonImmutable::parse($this->input('to'))) >= 366) {
                $validator->errors()->add('to', 'Chỉ xem tối đa 366 ngày mỗi lần.');
            }
        }];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['from' => 'ngày bắt đầu', 'to' => 'ngày kết thúc', 'source' => 'loại hóa đơn'];
    }

    protected function getRedirectUrl(): string
    {
        return route($this->routeIs('operations.invoices.history') ? 'operations.invoices.history' : 'operations.invoices.index');
    }
}
