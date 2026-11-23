<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedTinyInteger('booking_no_shows')->default(0);
            $table->dateTime('booking_blocked_until')->nullable();
        });

        Schema::create('scheduled_pickups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->string('code', 24)->unique();
            $table->unsignedInteger('revision')->default(0);
            $table->dateTime('scheduled_at');
            $table->dateTime('arrival_deadline');
            $table->dateTime('arrived_at')->nullable();
            $table->foreignId('arrival_actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('used_at')->nullable();
            $table->dateTime('no_show_at')->nullable();
            $table->timestamps();
            $table->index('scheduled_at');
            $table->index(['arrived_at', 'no_show_at', 'arrival_deadline']);
        });

        Schema::create('pickup_fees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scheduled_pickup_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->string('kind', 12);
            $table->unsignedBigInteger('amount_vnd');
            $table->string('channel', 12);
            $table->string('status', 20)->default('pending');
            $table->dateTime('old_slot')->nullable();
            $table->dateTime('new_slot')->nullable();
            $table->unsignedTinyInteger('late_minutes')->nullable();
            $table->json('bank_details')->nullable();
            $table->string('transfer_code', 24)->nullable()->unique();
            $table->string('transfer_result', 12)->nullable();
            $table->dateTime('transfer_resolved_at')->nullable();
            $table->foreignId('transfer_actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('bank_reference', 100)->nullable()->unique();
            $table->dateTime('moved_to_counter_at')->nullable();
            $table->foreignId('counter_actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('paid_at')->nullable();
            $table->string('refund_reference', 100)->nullable()->unique();
            $table->dateTime('refunded_at')->nullable();
            $table->foreignId('refund_actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['scheduled_pickup_id', 'kind', 'revision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pickup_fees');
        Schema::dropIfExists('scheduled_pickups');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['booking_no_shows', 'booking_blocked_until']));
    }
};
