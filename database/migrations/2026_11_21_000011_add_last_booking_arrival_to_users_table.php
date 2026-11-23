<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dateTime('booking_last_arrived_at')->nullable();
        });

        $arrivals = DB::table('orders')
            ->join('scheduled_pickups', 'scheduled_pickups.order_id', '=', 'orders.id')
            ->whereColumn('orders.user_id', 'users.id')
            ->whereNotNull('scheduled_pickups.arrived_at');

        DB::table('users')
            ->whereNull('booking_last_arrived_at')
            ->whereExists((clone $arrivals)->selectRaw('1'))
            ->update(['booking_last_arrived_at' => $arrivals->selectRaw('MAX(scheduled_pickups.arrived_at)')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('booking_last_arrived_at');
        });
    }
};
