<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_deposits', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();
            $table->string('number', 20)->nullable()->unique();
            $table->foreignId('reservation_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('table_invoice_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedInteger('amount_vnd')->default(100000);
            $table->json('bank_details');
            $table->boolean('demo')->default(false);
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('settled_at')->nullable();
            $table->unsignedInteger('credited_vnd')->default(0);
            $table->unsignedInteger('cash_refund_vnd')->default(0);
            $table->foreignId('settled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::table('table_invoices', function (Blueprint $table): void {
            $table->unsignedInteger('deposit_credit_vnd')->default(0);
            $table->unsignedInteger('deposit_refund_vnd')->default(0);
        });
    }

    public function down(): void
    {
        if (DB::table('reservation_deposits')->exists()) {
            throw new RuntimeException('Không xóa schema khi đã có chứng từ đặt cọc.');
        }
        Schema::table('table_invoices', fn (Blueprint $table) => $table->dropColumn(['deposit_credit_vnd', 'deposit_refund_vnd']));
        Schema::dropIfExists('reservation_deposits');
    }
};
