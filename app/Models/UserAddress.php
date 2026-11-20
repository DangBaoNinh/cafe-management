<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $recipient_name
 * @property string $phone
 * @property string $province_code
 * @property string $district_code
 * @property string|null $ward_code
 * @property string $address_line
 * @property bool $is_default
 */
#[Fillable(['recipient_name', 'phone', 'province_code', 'district_code', 'ward_code', 'address_line', 'is_default'])]
class UserAddress extends Model
{
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }
}
