<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_payment_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->uuid('reference')->unique();
            $table->unsignedBigInteger('amount_vnd');
            $table->string('result', 12)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            $table->index(['payment_id', 'result', 'applied_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_payment_sessions');
    }
};
