<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('table_invoices', fn (Blueprint $table) => $table->string('order_status', 24)->default('pending_payment')->index());
        DB::table('table_invoices')->where('status', 'paid')->update(['order_status' => 'completed']);
        DB::table('table_invoices')->where('status', 'voided')->update(['order_status' => 'cancelled']);
    }

    public function down(): void
    {
        Schema::table('table_invoices', function (Blueprint $table): void {
            $table->dropIndex(['order_status']);
            $table->dropColumn('order_status');
        });
    }
};
