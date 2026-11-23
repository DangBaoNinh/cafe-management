<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_toppings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('topping_id')->constrained()->restrictOnDelete();
            $table->unique(['product_id', 'topping_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_toppings');
    }
};
