<?php

namespace App\Models;

use App\Services\BookingRules;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ReservationHours extends Model
{
    protected $table = 'reservation_hours';

    public $timestamps = false;

    /** @return list<string> */
    public function slots(): array
    {
        return array_values(array_filter(BookingRules::times(), fn (string $slot): bool => $this->contains($slot)));
    }

    public function validateSlot(CarbonImmutable $at): void
    {
        if (! $this->contains($at->setTimezone(BookingRules::TIMEZONE)->format('H:i'))) {
            throw ValidationException::withMessages(['pickup_time' => 'Chọn giờ còn nhận đặt chỗ từ '.$this->first_slot.' đến '.$this->last_slot.'.']);
        }
    }

    private function contains(string $slot): bool
    {
        return $slot >= $this->first_slot && $slot <= $this->last_slot;
    }
}
