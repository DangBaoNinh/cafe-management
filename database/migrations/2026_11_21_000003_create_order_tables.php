<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('order_number', 32)->unique();
            $table->uuid('checkout_key');
            $table->char('request_hash', 64);
            $table->foreignId('coupon_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('coupon_code', 40)->nullable();
            $table->string('recipient_name', 120);
            $table->string('phone', 13);
            $table->json('address_snapshot')->nullable();
            $table->string('fulfillment_type', 16);
            $table->string('status', 24)->default('pending');
            $table->unsignedBigInteger('subtotal_vnd');
            $table->unsignedBigInteger('discount_vnd');
            $table->unsignedBigInteger('delivery_fee_vnd');
            $table->unsignedBigInteger('total_vnd');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'checkout_key']);
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['coupon_id', 'status', 'user_id']);
            $table->index(['status', 'completed_at']);
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_size_id')->constrained()->restrictOnDelete();
            $table->string('product_name');
            $table->string('size_label');
            $table->string('sugar_level', 8)->nullable();
            $table->string('ice_level', 8)->nullable();
            $table->unsignedSmallInteger('quantity');
            $table->unsignedBigInteger('base_unit_price_vnd');
            $table->unsignedBigInteger('line_total_vnd');
            $table->timestamps();
        });

        Schema::create('order_item_toppings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('topping_id')->constrained()->restrictOnDelete();
            $table->string('topping_name');
            $table->unsignedBigInteger('unit_price_vnd');
            $table->unique(['order_item_id', 'topping_id']);
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->string('method', 16);
            $table->string('status', 24)->default('pending');
            $table->unsignedBigInteger('amount_vnd');
            $table->json('transfer_details')->nullable();
            $table->string('collection_reference')->nullable();
            $table->string('refund_reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
            $table->unique(['method', 'collection_reference']);
        });

        $this->createHistory('order_status_histories', 'order_id');
        $this->createHistory('payment_status_histories', 'payment_id');
    }

    private function createHistory(string $name, string $parent): void
    {
        Schema::create($name, function (Blueprint $table) use ($parent): void {
            $table->id();
            $table->foreignId($parent)->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->text('reason')->nullable();
            $table->timestamp('created_at');
            $table->index([$parent, 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_status_histories');
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_item_toppings');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
