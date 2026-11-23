<?php

namespace App\Http\Requests\Admin;

use App\Models\DeliverySetting;
use App\Services\VietnamAddressCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveDeliverySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(VietnamAddressCatalog $catalog): array
    {
        $codes = $catalog->districtCodes();

        return [
            'delivery_enabled' => ['required', 'boolean'],
            'flat_fee_vnd' => ['bail', 'required', 'integer:strict', 'min:0', 'max:'.DeliverySetting::MAX_FEE_VND],
            'eligible_districts' => [
                'bail', Rule::requiredIf(in_array($this->input('delivery_enabled'), [true, 1, '1'], true)),
                'array', 'list', 'max:'.count($codes),
            ],
            'eligible_districts.*' => ['bail', 'required', 'string', 'distinct:strict', Rule::in($codes)],
            'version' => ['required', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $fee = $this->input('flat_fee_vnd');
        if (is_string($fee) && preg_match('/\A-?[0-9]{1,10}\z/', $fee) === 1) {
            $this->merge(['flat_fee_vnd' => (int) $fee]);
        }
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'eligible_districts.required' => 'Chọn ít nhất một quận/huyện khi bật giao hàng.',
            'eligible_districts.*.in' => 'Có mã quận/huyện không hợp lệ.',
            'eligible_districts.*.distinct' => 'Mỗi quận/huyện chỉ được chọn một lần.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'delivery_enabled' => 'trạng thái giao hàng',
            'flat_fee_vnd' => 'phí giao hàng (VND)',
            'eligible_districts' => 'danh sách quận/huyện',
            'eligible_districts.*' => 'mã quận/huyện',
            'version' => 'phiên bản cấu hình',
        ];
    }
}
