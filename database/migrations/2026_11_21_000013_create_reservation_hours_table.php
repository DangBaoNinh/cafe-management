<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_hours', function (Blueprint $table): void {
            $table->id();
            $table->char('first_slot', 5);
            $table->char('last_slot', 5);
        });
        DB::table('reservation_hours')->insert(['id' => 1, 'first_slot' => '07:00', 'last_slot' => '22:30']);
        Schema::table('reservations', function (Blueprint $table): void {
            $table->json('fee_bank_details')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', fn (Blueprint $table) => $table->dropColumn('fee_bank_details'));
        Schema::dropIfExists('reservation_hours');
    }
};
