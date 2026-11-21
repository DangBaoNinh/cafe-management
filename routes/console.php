<?php

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

Schedule::command('pickups:expire')->everyMinute()->withoutOverlapping();
