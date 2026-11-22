<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_deposits', fn (Blueprint $table) => $table->text('forfeit_reason')->nullable());
        Schema::table('customer_refund_requests', fn (Blueprint $table) => $table->boolean('is_demo')->default(false));
    }

    public function down(): void
    {
        Schema::table('customer_refund_requests', fn (Blueprint $table) => $table->dropColumn('is_demo'));
        Schema::table('reservation_deposits', fn (Blueprint $table) => $table->dropColumn('forfeit_reason'));
    }
};
