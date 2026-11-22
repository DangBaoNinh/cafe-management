<?php

namespace App\Http\Requests;

use App\Enums\DrinkLevel;
use App\Models\CartItem;
use App\Services\TableRounds;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** @phpstan-import-type Selection from TableRounds */
class TableRoundRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'submission_key' => ['required', 'uuid'],
            'lines' => ['required', 'array', 'list', 'min:1', 'max:12'],
            'lines.*' => ['required', 'array:product_id,size_id,quantity,sugar_level,ice_level,topping_ids'],
            'lines.*.product_id' => ['required', 'integer', 'min:1'],
            'lines.*.size_id' => ['required', 'integer', 'min:1'],
            'lines.*.quantity' => ['required', 'integer', 'between:1,'.CartItem::MAX_QUANTITY],
            'lines.*.sugar_level' => ['nullable', Rule::enum(DrinkLevel::class)],
            'lines.*.ice_level' => ['nullable', Rule::enum(DrinkLevel::class)],
            'lines.*.topping_ids' => ['sometimes', 'array', 'list', 'max:100'],
            'lines.*.topping_ids.*' => ['required', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'submission_key' => 'mã gửi lượt', 'lines' => 'danh sách món', 'lines.*' => 'dòng món',
            'lines.*.product_id' => 'món', 'lines.*.size_id' => 'size', 'lines.*.quantity' => 'số ly',
            'lines.*.sugar_level' => 'mức đường', 'lines.*.ice_level' => 'mức đá',
            'lines.*.topping_ids' => 'topping', 'lines.*.topping_ids.*' => 'topping',
        ];
    }

    /** @return list<Selection> */
    public function selections(): array
    {
        $selections = [];
        foreach ($this->validated('lines') as $line) {
            $toppings = array_map(intval(...), $line['topping_ids'] ?? []);
            sort($toppings, SORT_NUMERIC);

            $selections[] = [
                'product_id' => (int) $line['product_id'], 'size_id' => (int) $line['size_id'], 'quantity' => (int) $line['quantity'],
                'sugar_level' => isset($line['sugar_level']) ? (string) $line['sugar_level'] : null,
                'ice_level' => isset($line['ice_level']) ? (string) $line['ice_level'] : null,
                'topping_ids' => $toppings,
            ];
        }

        return $selections;
    }

    public function submissionKey(): string
    {
        return strtolower($this->validated('submission_key'));
    }
}
