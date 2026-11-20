<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_settings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->boolean('delivery_enabled')->default(false);
            $table->unsignedBigInteger('flat_fee_vnd')->default(0);
            $table->json('eligible_districts');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamps();
        });

        DB::table('delivery_settings')->insert([
            'id' => 1,
            'eligible_districts' => '[]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_settings');
    }
};
