<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_number_sequences', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedInteger('last_number')->default(0);
        });

        $last = 0;
        foreach (DB::table('orders')->where('order_number', 'like', 'HD_______')->pluck('order_number') as $number) {
            if (preg_match('/\AHD([0-9]{7})\z/', $number, $matches)) {
                $last = max($last, (int) $matches[1]);
            }
        }
        DB::table('order_number_sequences')->insert(['id' => 1, 'last_number' => $last]);
    }

    public function down(): void
    {
        Schema::dropIfExists('order_number_sequences');
    }
};
