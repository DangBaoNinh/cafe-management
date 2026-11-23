<?php

use App\Services\BookingExpirations;
use App\Services\ScheduledPickups;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('pickups:expire', function (ScheduledPickups $pickups): void {
    $this->info('Số đơn hẹn đã hủy do không đến: '.$pickups->expireDue());
})->purpose('Hủy đơn hẹn quá hạn nhận tại quán');

Artisan::command('bookings:expire', function (BookingExpirations $bookings): void {
    $counts = $bookings->run();
    $this->info('Số đơn hẹn đã hủy do không đến: '.$counts['pickups']);
    $this->info('Số lượt đặt chỗ đã hủy do không đến: '.$counts['reservations']);
})->purpose('Xử lý các lịch quá hạn theo thời điểm không đến');

Schedule::command('bookings:expire')->everyMinute()->withoutOverlapping();
