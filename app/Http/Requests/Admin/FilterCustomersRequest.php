<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterCustomersRequest extends FormRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['active', 'locked'])],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['search' => 'từ khóa', 'status' => 'trạng thái'];
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.customers.index');
    }
}
