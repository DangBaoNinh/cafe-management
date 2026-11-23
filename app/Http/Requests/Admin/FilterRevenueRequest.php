<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Validator;

class FilterRevenueRequest extends FormRequest
{
    public const MAX_DAYS = 366;

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'from' => ['required_with:to', 'nullable', 'date_format:Y-m-d'],
            'to' => ['required_with:from', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->filled(['from', 'to'])) {
                return;
            }

            if (Date::parse($this->input('from'))->diffInDays($this->input('to')) >= self::MAX_DAYS) {
                $validator->errors()->add('to', 'Chỉ có thể xem tối đa '.self::MAX_DAYS.' ngày mỗi lần.');
            }
        }];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['from' => 'ngày bắt đầu', 'to' => 'ngày kết thúc'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'from.date_format' => 'Ngày bắt đầu không hợp lệ.',
            'to.date_format' => 'Ngày kết thúc không hợp lệ.',
            'to.after_or_equal' => 'Ngày kết thúc phải từ ngày bắt đầu trở đi.',
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.dashboard');
    }
}
