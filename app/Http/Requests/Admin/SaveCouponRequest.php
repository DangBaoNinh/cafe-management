<?php

namespace App\Http\Requests\Admin;

use App\Enums\CouponDiscountType;
use App\Models\Coupon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $code = $this->input('code');
        if (is_string($code)) {
            $this->merge(['code' => Coupon::normalizeCode($code)]);
        }

        foreach (['discount_value', 'min_subtotal_vnd', 'max_discount_vnd', 'total_limit', 'per_user_limit'] as $field) {
            $value = $this->input($field);
            if (is_string($value) && ($integer = filter_var($value, FILTER_VALIDATE_INT)) !== false) {
                $this->merge([$field => $integer]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $coupon = $this->route('coupon');
        $unique = Rule::unique(Coupon::class);
        if ($coupon instanceof Coupon) {
            $unique->ignore($coupon);
        }

        $percentage = $this->input('discount_type') === CouponDiscountType::Percentage->value;

        return [
            'code' => ['bail', 'required', 'string', 'max:40', 'regex:/\A[A-Z0-9]+(?:[-_][A-Z0-9]+)*\z/', $unique],
            'discount_type' => ['required', Rule::enum(CouponDiscountType::class)],
            'discount_value' => ['bail', 'required', 'integer:strict', $percentage ? 'between:1,100' : 'min:1'],
            'min_subtotal_vnd' => ['bail', 'required', 'integer:strict', 'min:0'],
            'max_discount_vnd' => ['bail', Rule::prohibitedIf(! $percentage), 'nullable', 'integer:strict', 'min:1'],
            'starts_at' => ['bail', 'required', 'date_format:'.Coupon::FORM_DATE_FORMAT, 'after_or_equal:1000-01-02T00:00'],
            'ends_at' => ['bail', 'required', 'date_format:'.Coupon::FORM_DATE_FORMAT, 'after:starts_at'],
            'total_limit' => ['bail', 'nullable', 'integer:strict', 'min:1'],
            'per_user_limit' => ['bail', 'nullable', 'integer:strict', 'min:1'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $coupon = $this->route('coupon');
            $now = CarbonImmutable::now(Coupon::LOCAL_TIMEZONE)->startOfMinute();

            foreach (['ends_at'] as $field) {
                if ($validator->errors()->has($field) || ! is_string($this->input($field))) {
                    continue;
                }

                $submitted = CarbonImmutable::createFromFormat(
                    Coupon::FORM_DATE_FORMAT,
                    $this->string($field)->toString(),
                    Coupon::LOCAL_TIMEZONE,
                );
                if ($submitted->lt($now)) {
                    $validator->errors()->add($field, 'Không được chọn thời gian trong quá khứ.');
                }
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.regex' => 'Dùng chữ cái không dấu, chữ số và dấu gạch ngang hoặc gạch dưới giữa các nhóm ký tự.',
            'discount_value.between' => 'Mức giảm phần trăm phải từ 1 đến 100.',
            'max_discount_vnd.prohibited' => 'Chỉ đặt mức giảm tối đa cho loại phần trăm.',
            'starts_at.after_or_equal' => 'Thời điểm bắt đầu nằm ngoài khoảng lưu trữ được hỗ trợ.',
            'ends_at.after' => 'Thời điểm kết thúc phải sau thời điểm bắt đầu.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'code' => 'mã giảm giá',
            'discount_type' => 'loại giảm',
            'discount_value' => 'mức giảm',
            'min_subtotal_vnd' => 'tạm tính tối thiểu',
            'max_discount_vnd' => 'mức giảm tối đa',
            'starts_at' => 'thời điểm bắt đầu',
            'ends_at' => 'thời điểm kết thúc',
            'total_limit' => 'giới hạn tổng lượt',
            'per_user_limit' => 'giới hạn lượt mỗi khách',
            'is_active' => 'trạng thái',
        ];
    }
}
