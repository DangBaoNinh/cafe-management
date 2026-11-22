<?php

namespace App\Models;

use App\Enums\UserRole;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property positive-int $id
 * @property string $name
 * @property string $email
 * @property array{bank_name:string,account_number:string,account_holder:string}|null $linked_bank
 * @property string|null $phone
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property UserRole $role
 * @property bool $is_active
 * @property CarbonImmutable|null $booking_blocked_until
 * @property CarbonImmutable|null $booking_last_arrived_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'phone', 'password'])]
#[Hidden(['password', 'remember_token', 'booking_no_shows', 'booking_blocked_until', 'booking_last_arrived_at', 'linked_bank'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $attributes = [
        'role' => UserRole::Customer->value,
        'is_active' => true,
        'booking_no_shows' => 0,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'linked_bank' => 'encrypted:array',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'booking_no_shows' => 'integer',
            'booking_blocked_until' => 'immutable_datetime',
            'booking_last_arrived_at' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<UserAddress, $this> */
    public function addresses(): HasMany
    {
        return $this->hasMany(UserAddress::class);
    }

    /** @return HasOne<Cart, $this> */
    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** @return HasMany<Reservation, $this> */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /** @return HasMany<SupportConversation, $this> */
    public function supportConversations(): HasMany
    {
        return $this->hasMany(SupportConversation::class);
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
