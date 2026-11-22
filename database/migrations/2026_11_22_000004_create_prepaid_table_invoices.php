<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('table_sessions', fn (Blueprint $table) => $table->string('billing_mode', 12)->default('legacy'));
        Schema::create('table_invoices', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();
            $table->string('number', 16)->unique();
            $table->foreignId('table_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('table_round_id')->unique()->constrained()->restrictOnDelete();
            $table->uuid('submission_key');
            $table->char('request_hash', 64);
            $table->string('status', 12)->default('draft');
            $table->string('method', 16);
            $table->unsignedInteger('revision')->default(1);
            $table->json('selections');
            $table->json('initial_items');
            $table->json('bank_details')->nullable();
            $table->unsignedBigInteger('subtotal_vnd');
            $table->unsignedBigInteger('fees_vnd')->default(0);
            $table->unsignedBigInteger('total_vnd');
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('payment_reported_at')->nullable();
            $table->dateTime('paid_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['table_session_id', 'submission_key']);
        });
        Schema::create('table_invoice_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('table_invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->json('before');
            $table->json('after');
            $table->timestamp('created_at');
        });
        Schema::table('pickup_fees', fn (Blueprint $table) => $table->foreignId('table_invoice_id')->nullable()->constrained()->restrictOnDelete());
    }

    public function down(): void
    {
        if (DB::table('table_invoices')->exists()) {
            throw new RuntimeException('Cannot roll back prepaid billing while invoices exist.');
        }
        Schema::table('pickup_fees', fn (Blueprint $table) => $table->dropConstrainedForeignId('table_invoice_id'));
        Schema::dropIfExists('table_invoice_revisions');
        Schema::dropIfExists('table_invoices');
        Schema::table('table_sessions', fn (Blueprint $table) => $table->dropColumn('billing_mode'));
    }
};
