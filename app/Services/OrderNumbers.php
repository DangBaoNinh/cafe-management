<?php

namespace App\Services;

use App\Models\OrderNumberSequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderNumbers
{
    public function next(): string
    {
        // The checkout transaction retains this lock until the whole order is saved.
        return DB::transaction(function (): string {
            $sequence = OrderNumberSequence::query()->lockForUpdate()->findOrFail(1);
            if ($sequence->last_number >= 9999999) {
                throw ValidationException::withMessages(['checkout_key' => 'Đã hết dải mã hóa đơn 7 chữ số. Vui lòng liên hệ quản trị viên.']);
            }
            $sequence->last_number++;
            $sequence->save();

            return 'HD'.str_pad((string) $sequence->last_number, 7, '0', STR_PAD_LEFT);
        });
    }
}
