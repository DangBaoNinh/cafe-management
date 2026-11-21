<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Validation\ValidationException;

class BookingRules
{
    public const TIMEZONE = 'Asia/Ho_Chi_Minh';

    public const CHANGE_FEE = 10_000;

    public static function slot(string $input): CarbonImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $input, new DateTimeZone(self::TIMEZONE));
        $slot = $date === false ? null : CarbonImmutable::instance($date);
        if ($slot === null || $slot->format('Y-m-d H:i') !== $input
            || $slot->format('H:i') < '07:00' || $slot->format('H:i') > '22:30'
            || $slot->minute % 5 !== 0 || $slot->lt(now()->addHour())) {
            throw ValidationException::withMessages(['pickup_time' => 'Chọn giờ từ 07:00 đến 22:30 theo bước 5 phút và đặt trước ít nhất 60 phút.']);
        }

        return $slot->utc();
    }

    /** @return list<string> */
    public static function times(): array
    {
        return array_map(
            fn (int $minutes): string => sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60),
            range(7 * 60, 22 * 60 + 30, 5),
        );
    }

    public static function replacementSlot(string $input, CarbonImmutable $previous): CarbonImmutable
    {
        $slot = self::slot($input);
        if ($slot->eq($previous)) {
            throw ValidationException::withMessages(['pickup_time' => 'Chọn giờ mới khác giờ đang hẹn.']);
        }
        if (abs($slot->getTimestamp() - $previous->getTimestamp()) > 3600) {
            throw ValidationException::withMessages(['pickup_time' => 'Giờ mới chênh quá 60 phút. Vui lòng đặt lịch mới.']);
        }

        return $slot;
    }

    public static function lateMinutes(CarbonImmutable $slot, CarbonImmutable $arrival): int
    {
        return max(0, intdiv($arrival->getTimestamp() - $slot->getTimestamp(), 60));
    }

    public static function authorizeBooking(User $customer): void
    {
        if ($customer->booking_blocked_until?->isFuture()) {
            throw ValidationException::withMessages([
                'pickup_time' => 'Tạm ngưng đặt lịch đến '.$customer->booking_blocked_until->setTimezone(self::TIMEZONE)->format('H:i d/m/Y').'. Bạn vẫn có thể đặt nhận ngay.',
            ]);
        }
    }

    /** Record once per booking under the same transaction and customer row lock. */
    public static function recordAttendance(User $customer, bool $arrived, CarbonImmutable $at): void
    {
        // A delayed expiry still cancels the booking, but cannot change a streak reset by a later arrival.
        if ($customer->booking_last_arrived_at?->gte($at)) {
            return;
        }

        if ($arrived) {
            $customer->booking_last_arrived_at = $at;
            $customer->booking_no_shows = 0;
            $customer->save();

            return;
        }

        $previous = $customer->booking_blocked_until?->lte($at) ? 0 : $customer->booking_no_shows;
        $customer->booking_no_shows = min(2, $previous + 1);
        if ($previous === 1) {
            $customer->booking_blocked_until = $at->addDays(7);
        } elseif ($previous === 0 && $customer->booking_blocked_until?->lte($at)) {
            $customer->booking_blocked_until = null;
        }
        $customer->save();
    }
}
