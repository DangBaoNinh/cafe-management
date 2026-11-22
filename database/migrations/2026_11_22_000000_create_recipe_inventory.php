<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('enabled')->default(false);
        });
        DB::table('inventory_settings')->insert(['id' => 1]);

        // All stock and recipe quantities are integers in thousandths of the base unit.
        Schema::create('ingredients', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('unit', 8);
            $table->unsignedBigInteger('low_threshold');
            $table->unsignedBigInteger('stock')->default(0);
            $table->unsignedBigInteger('stock_version')->default(0);
            $table->timestamps();
        });

        foreach (['product_size', 'topping'] as $option) {
            Schema::create('ingredient_'.$option, function (Blueprint $table) use ($option): void {
                $table->foreignId($option.'_id')->constrained()->cascadeOnDelete();
                $table->foreignId('ingredient_id')->constrained()->restrictOnDelete();
                $table->unsignedBigInteger('quantity');
                $table->primary([$option.'_id', 'ingredient_id']);
            });
        }

        Schema::table('orders', fn (Blueprint $table) => $table->json('inventory_snapshot')->nullable());

        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ingredient_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('kind', 16);
            $table->bigInteger('quantity_change');
            $table->unsignedBigInteger('balance_after');
            $table->string('reason', 255)->nullable();
            $table->uuid('entry_key')->nullable();
            $table->timestamp('created_at');
            $table->unique(['ingredient_id', 'order_id']);
            $table->unique(['ingredient_id', 'entry_key']);
            $table->index(['ingredient_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('inventory_snapshot'));
        Schema::dropIfExists('ingredient_topping');
        Schema::dropIfExists('ingredient_product_size');
        Schema::dropIfExists('ingredients');
        Schema::dropIfExists('inventory_settings');
    }
};
