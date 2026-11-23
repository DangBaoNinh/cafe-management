<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('reservation_seat_id')->constrained()->restrictOnDelete();
            $table->foreignId('active_seat_id')->nullable()->unique()->constrained('reservation_seats')->restrictOnDelete();
            $table->foreignId('reservation_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->text('access_code')->nullable();
            $table->char('access_digest', 64)->unique();
            $table->string('status', 12);
            $table->dateTime('checkout_requested_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('locked_at')->nullable();
            $table->unsignedBigInteger('subtotal_vnd')->nullable();
            $table->unsignedBigInteger('fees_vnd')->nullable();
            $table->unsignedBigInteger('total_vnd')->nullable();
            $table->foreignId('billed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('billed_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('paid_at')->nullable()->index();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('table_rounds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('table_session_id')->constrained()->restrictOnDelete();
            $table->uuid('submission_key');
            $table->char('request_hash', 64);
            $table->foreignId('entered_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status', 12);
            $table->unsignedBigInteger('total_vnd');
            $table->json('inventory_snapshot');
            $table->timestamps();
            $table->unique(['table_session_id', 'submission_key']);
            $table->index(['table_session_id', 'status']);
        });

        Schema::create('table_round_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('table_round_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_size_id')->constrained()->restrictOnDelete();
            $table->string('product_name');
            $table->string('size_label');
            $table->unsignedSmallInteger('quantity');
            $table->string('sugar_level', 12)->nullable();
            $table->string('ice_level', 12)->nullable();
            $table->json('toppings');
            $table->unsignedBigInteger('unit_price_vnd');
            $table->unsignedBigInteger('line_total_vnd');
        });

        Schema::create('table_round_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('table_round_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('from_status', 12);
            $table->string('to_status', 12);
            $table->string('reason', 500)->nullable();
            $table->timestamp('created_at');
        });

        Schema::table('pickup_fees', fn (Blueprint $table) => $table->foreignId('table_session_id')->nullable()->constrained()->restrictOnDelete());
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->foreignId('table_round_id')->nullable()->constrained()->restrictOnDelete();
            $table->unique(['ingredient_id', 'table_round_id']);
        });
    }

    public function down(): void
    {
        if (DB::table('table_sessions')->exists()) {
            throw new RuntimeException('Cannot roll back table ordering while sessions exist.');
        }

        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->dropUnique(['ingredient_id', 'table_round_id']);
            $table->dropConstrainedForeignId('table_round_id');
        });
        Schema::table('pickup_fees', fn (Blueprint $table) => $table->dropConstrainedForeignId('table_session_id'));
        Schema::dropIfExists('table_round_events');
        Schema::dropIfExists('table_round_items');
        Schema::dropIfExists('table_rounds');
        Schema::dropIfExists('table_sessions');
    }
};
