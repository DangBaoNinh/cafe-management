<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_seats', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 16)->unique();
            $table->unsignedTinyInteger('floor');
            $table->string('kind', 12);
            $table->unsignedTinyInteger('capacity');
            $table->unsignedTinyInteger('position');
            $table->boolean('is_active')->default(true);
            $table->dateTime('walk_in_at')->nullable();
            $table->dateTime('walk_out_at')->nullable();
            $table->foreignId('walk_in_actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('walk_out_actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->unique(['floor', 'position']);
        });

        Schema::create('reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('reservation_seat_id')->constrained()->restrictOnDelete();
            $table->foreignId('active_seat_id')->nullable()->unique()->constrained('reservation_seats')->restrictOnDelete();
            $table->string('code', 16)->unique();
            $table->uuid('booking_key');
            $table->char('request_hash', 64);
            $table->string('contact_name', 120);
            $table->string('phone', 13);
            $table->unsignedTinyInteger('party_size');
            $table->string('fee_channel', 12);
            $table->unsignedInteger('revision')->default(0);
            $table->string('status', 16);
            $table->dateTime('scheduled_at');
            $table->dateTime('arrival_deadline');
            $table->dateTime('arrived_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('no_show_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'booking_key']);
            $table->index(['status', 'arrival_deadline']);
            $table->index('scheduled_at');
        });

        Schema::create('reservation_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('from_status', 16)->nullable();
            $table->string('to_status', 16);
            $table->string('reason', 500)->nullable();
            $table->dateTime('occurred_at');
        });

        Schema::table('pickup_fees', function (Blueprint $table): void {
            $table->unsignedBigInteger('scheduled_pickup_id')->nullable()->change();
            $table->foreignId('reservation_id')->nullable()->constrained()->restrictOnDelete();
            $table->unique(['reservation_id', 'kind', 'revision']);
        });

        $seats = [];
        foreach ([1, 2] as $floor) {
            for ($position = 1; $position <= 11; $position++) {
                $room = $position === 11;
                $seats[] = [
                    'floor' => $floor, 'position' => $position,
                    'code' => $room ? "T{$floor}-PH" : sprintf('T%d-B%02d', $floor, $position),
                    'kind' => $room ? 'room' : 'table', 'capacity' => $room ? 10 : ($position <= 5 ? 4 : 2),
                ];
            }
        }
        DB::table('reservation_seats')->insert($seats);
    }

    public function down(): void
    {
        if (DB::table('reservations')->exists()) {
            throw new RuntimeException('Cannot roll back the reservation tables while they contain bookings.');
        }

        Schema::table('pickup_fees', function (Blueprint $table): void {
            $table->dropUnique(['reservation_id', 'kind', 'revision']);
            $table->dropConstrainedForeignId('reservation_id');
            $table->unsignedBigInteger('scheduled_pickup_id')->nullable(false)->change();
        });
        Schema::dropIfExists('reservation_events');
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('reservation_seats');
    }
};
