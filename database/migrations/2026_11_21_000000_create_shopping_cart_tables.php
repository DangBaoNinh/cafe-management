<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamps();
        });

        Schema::create('cart_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_size_id')->constrained()->restrictOnDelete();
            $table->char('configuration_hash', 64);
            $table->unsignedSmallInteger('quantity');
            $table->string('sugar_level', 8)->nullable();
            $table->string('ice_level', 8)->nullable();
            $table->unique(['cart_id', 'configuration_hash']);
            $table->timestamps();
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE cart_items ADD CONSTRAINT cart_items_positive_quantity CHECK (quantity > 0)');
        }

        Schema::create('cart_item_toppings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cart_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topping_id')->constrained()->restrictOnDelete();
            $table->unique(['cart_item_id', 'topping_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_item_toppings');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
